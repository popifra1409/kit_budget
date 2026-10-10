<?php

namespace App\Filament\Budget\Resources\RecetteReelleResource\Pages;

use App\Filament\Budget\Resources\RecetteReelleResource;
use Filament\Resources\Pages\Page;
use Filament\Actions\Action;
use Filament\Forms;
use App\Models\ActivityLog;
use App\Models\Exercice;
use App\Models\LignePrevisionRecette;
use App\Models\NomenclatureBudgetaire;
use App\Models\PrevisionRecette;
use App\Models\PrevisionRecetteMensuelle;
use Illuminate\Support\Facades\DB;

class SuiviRecettes extends Page
{
    protected static string $resource = RecetteReelleResource::class;
    protected static string $view     = 'filament.pages.suivi-recettes';

    public string $search      = '';
    public ?int   $exerciceId  = null;
    public ?int   $previsionId = null;
    public int    $lastRefresh = 0;  // ← force Livewire à re-render

    /** Non publics : Livewire ne les sérialise pas, le cache tombe donc à chaque requête. */
    private ?array $orphelins = null;

    public function getTitle(): string
    {
        return 'Tableau de Suivi des Recettes';
    }

    public function mount(): void
    {
        $exercice         = Exercice::getActif();
        $this->exerciceId = $exercice?->id;

        if ($this->exerciceId) {
            $prevision = PrevisionRecette::where('exercice_id', $this->exerciceId)
                ->whereNotIn('statut', ['elaboration'])
                ->first();
            $this->previsionId = $prevision?->id;
        }

        $this->lastRefresh = time();
    }

    // =========================================================
    // DONNÉES — 100% SQL brut, toujours frais
    // =========================================================
    public function getData(): array
    {
        if (!$this->previsionId) return [];

        // ✅ 2 requêtes SQL au lieu de N requêtes Eloquent
        // whereNull('deleted_at') : en SQL brut le scope SoftDeletes ne s'applique pas,
        // les lignes déjà retirées réapparaissaient dans le tableau.
        $lignesRaw = DB::table('lignes_previsions_recettes')
            ->where('prevision_recette_id', $this->previsionId)
            ->where('actif', true)
            ->whereNull('deleted_at')
            ->when($this->search, function ($q) {
                $q->where(function ($q) {
                    $q->where('code_nomenclature', 'ilike', "%{$this->search}%")
                        ->orWhere('libelle_nomenclature', 'ilike', "%{$this->search}%");
                });
            })
            ->orderBy('ordre')
            ->select(
                'id',
                'code_nomenclature',
                'libelle_nomenclature',
                DB::raw('CAST(montant_rectifie AS FLOAT) as prevu_annuel')
            )
            ->get();

        if ($lignesRaw->isEmpty()) return [];

        $ligneIds = $lignesRaw->pluck('id')->toArray();

        // ✅ Charger toutes les mensuelles en une seule requête
        $mensuelles = DB::table('previsions_recettes_mensuelles')
            ->whereIn('ligne_prevision_recette_id', $ligneIds)
            ->whereNull('deleted_at')
            ->select(
                'ligne_prevision_recette_id',
                'mois',
                DB::raw('CAST(montant_prevu AS FLOAT) as montant_prevu'),
                DB::raw('CAST(montant_recouvre AS FLOAT) as montant_recouvre')
            )
            ->get()
            ->groupBy('ligne_prevision_recette_id');

        // ✅ Recettes CONSTATÉES non encaissées (futur RAR) et nombre de recettes, par ligne
        $parLigne = DB::table('recettes_reelles as r')
            ->join('previsions_recettes_mensuelles as m', 'm.id', '=', 'r.prevision_recette_mensuelle_id')
            ->whereIn('m.ligne_prevision_recette_id', $ligneIds)
            ->whereNull('r.deleted_at')
            ->groupBy('m.ligne_prevision_recette_id')
            ->select(
                'm.ligne_prevision_recette_id as ligne_id',
                // ✅ RAR = Σ (recette attendue − recette encaissée), calculé
                DB::raw('SUM(' . \App\Models\RecetteReelle::sqlResteARecouvrer('r') . ') as constate'),
                DB::raw('COUNT(*) as nb_recettes')
            )
            ->get()
            ->keyBy('ligne_id');

        // ✅ Lignes modifiées ou créées par un collectif (non retirables ici :
        //    c'est la réinitialisation du collectif qui doit les traiter)
        $avecMouvements = DB::table('mouvements_collectifs')
            ->where(function ($q) use ($ligneIds) {
                $q->whereIn('ligne_recette_id', $ligneIds)->orWhereIn('nouvelle_ligne_recette_id', $ligneIds);
            })
            ->when(\Illuminate\Support\Facades\Schema::hasColumn('mouvements_collectifs', 'deleted_at'), fn($q) => $q->whereNull('deleted_at'))
            ->pluck('ligne_recette_id')
            ->merge(
                DB::table('mouvements_collectifs')
                    ->whereIn('nouvelle_ligne_recette_id', $ligneIds)
                    ->pluck('nouvelle_ligne_recette_id')
            )
            ->filter()->unique()->flip();

        $anneeExercice = DB::table('exercices')
            ->where('id', $this->exerciceId)
            ->value('annee');

        $result = [];

        foreach ($lignesRaw as $ligne) {
            $mensuellesLigne = collect($mensuelles->get($ligne->id, collect()))
                ->keyBy('mois');

            $row = [
                'id'             => $ligne->id,
                'code'           => $ligne->code_nomenclature,
                'libelle'        => $ligne->libelle_nomenclature,
                'prevu_annuel'   => (float) $ligne->prevu_annuel,
                'mois'           => [],
                'nb_mois'        => $mensuellesLigne->count(),
                'cumul_prevu'    => 0,
                'cumul_recouvre' => 0,
                'taux_global'    => 0,
                'ecart'          => 0,
                'constate'       => (float) ($parLigne->get($ligne->id)->constate ?? 0),
                'nb_recettes'    => (int) ($parLigne->get($ligne->id)->nb_recettes ?? 0),
                'a_mouvements'   => $avecMouvements->has($ligne->id),
            ];

            $cumulPrevu    = 0;
            $cumulRecouvre = 0;

            for ($m = 1; $m <= 12; $m++) {
                $mensuelle = $mensuellesLigne->get($m);
                $prevu     = $mensuelle ? (float) $mensuelle->montant_prevu    : 0;
                $recouvre  = $mensuelle ? (float) $mensuelle->montant_recouvre : 0;
                $taux      = $prevu > 0 ? round(($recouvre / $prevu) * 100, 1) : null;

                $cumulPrevu    += $prevu;
                $cumulRecouvre += $recouvre;

                $row['mois'][$m] = [
                    'prevu'          => $prevu,
                    'recouvre'       => $recouvre,
                    'taux'           => $taux,
                    'cumul_prevu'    => $cumulPrevu,
                    'cumul_recouvre' => $cumulRecouvre,
                    'taux_cumul'     => $cumulPrevu > 0
                        ? round(($cumulRecouvre / $cumulPrevu) * 100, 1) : null,
                    'est_futur'      => $m > now()->month
                        && now()->year === $anneeExercice,
                ];
            }

            $row['cumul_prevu']    = $cumulPrevu;
            $row['cumul_recouvre'] = $cumulRecouvre;
            $row['ecart']          = $cumulRecouvre - $cumulPrevu;
            $row['taux_global']    = $cumulPrevu > 0
                ? round(($cumulRecouvre / $cumulPrevu) * 100, 1) : 0;

            // 12 mois ET leur somme égale le montant rectifié de la ligne : sinon la
            // ligne a été créée après l'adoption et le tableau la montre à zéro.
            $row['mois_coherents'] = $row['nb_mois'] === 12
                && abs($cumulPrevu - $row['prevu_annuel']) < 1;

            $result[] = $row;
        }

        return $result;
    }

    /**
     * ✅ Passer les données fraîches à la vue à chaque render Livewire
     */
    protected function getViewData(): array
    {
        $data   = $this->getData();
        $totaux = $this->getTotauxParMois();

        $exercice = \DB::table('exercices')
            ->where('id', $this->exerciceId)
            ->select('id', 'annee')
            ->first();

        $totalPrevu    = collect($data)->sum('cumul_prevu');
        $totalRecouvre = collect($data)->sum('cumul_recouvre');
        $totalEcart    = $totalRecouvre - $totalPrevu;
        $tauxGlobal    = $totalPrevu > 0
            ? round(($totalRecouvre / $totalPrevu) * 100, 1) : 0;

        return [
            'data'          => $data,
            'totaux'        => $totaux,
            'exercice'      => $exercice,
            'exercices'     => $this->getExercices(),
            'previsions'    => $this->getPrevisions(),
            'moisActuel'    => now()->month,
            'anneeActuelle' => now()->year,
            'totalPrevu'    => $totalPrevu,
            'totalRecouvre' => $totalRecouvre,
            'totalEcart'    => $totalEcart,
            'tauxGlobal'    => $tauxGlobal,
            'totalConstate' => collect($data)->sum('constate'),   // ✅ futur RAR
            'peutRetirer'   => $this->peutRetirerDesLignes(),
            'peutModifier'  => $this->peutModifierLaPrevision(),
            'etat'          => $this->etatDesMois($data),

            // ✅ AJOUTER — tableau des labels de mois
            'moisLabels'    => [
                'Jan',
                'Fév',
                'Mar',
                'Avr',
                'Mai',
                'Jun',
                'Jul',
                'Aoû',
                'Sep',
                'Oct',
                'Nov',
                'Déc',
            ],
        ];
    }

    public function getTotauxParMois(): array
    {
        $data   = $this->getData();
        $totaux = [];

        for ($m = 1; $m <= 12; $m++) {
            $totalPrevu    = collect($data)->sum(fn($r) => $r['mois'][$m]['prevu']    ?? 0);
            $totalRecouvre = collect($data)->sum(fn($r) => $r['mois'][$m]['recouvre'] ?? 0);
            $totaux[$m]    = [
                'prevu'    => $totalPrevu,
                'recouvre' => $totalRecouvre,
                'taux'     => $totalPrevu > 0
                    ? round(($totalRecouvre / $totalPrevu) * 100, 1) : null,
            ];
        }

        return $totaux;
    }

    // =========================================================
    // FILTRES
    // =========================================================

    public function getExercices(): array
    {
        return Exercice::orderByDesc('annee')->pluck('annee', 'id')->toArray();
    }

    public function getPrevisions(): array
    {
        if (!$this->exerciceId) return [];
        return PrevisionRecette::where('exercice_id', $this->exerciceId)
            ->whereNotIn('statut', ['elaboration'])
            ->pluck('libelle', 'id')
            ->toArray();
    }

    public function changerExercice(int $exerciceId): void
    {
        $this->exerciceId  = $exerciceId;
        $prevision = PrevisionRecette::where('exercice_id', $exerciceId)
            ->whereNotIn('statut', ['elaboration'])
            ->first();
        $this->previsionId = $prevision?->id;
        $this->rafraichir();
    }

    public function changerPrevision(int $previsionId): void
    {
        $this->previsionId = $previsionId;
        $this->rafraichir();
    }

    private function rafraichir(): void
    {
        $this->orphelins   = null;
        $this->lastRefresh = time();
    }

    // =========================================================
    // ✅ DROITS
    // =========================================================

    public function peutRetirerDesLignes(): bool
    {
        $u = auth()->user();

        return $u && ($u->hasAnyRole(['super_admin', 'admin']) || $u->can('delete_prevision_recette'));
    }

    /**
     * Ajouter une ligne ou régénérer ses 12 mois = écrire dans la prévision.
     * La prévision reste consultable quand elle est adoptée : ces deux écritures sont
     * autorisées après adoption (lignes ajoutées par un collectif, mois manquants),
     * seule la clôture — l'exercice ou la prévision — est bloquante.
     */
    public function peutModifierLaPrevision(): bool
    {
        $u = auth()->user();

        return $u && ($u->hasAnyRole(['super_admin', 'admin'])
            || $u->can('create_prevision_recette')
            || $u->can('update_prevision_recette'));
    }

    private function refus(string $titre, string $message): void
    {
        \Filament\Notifications\Notification::make()
            ->warning()->title($titre)->body($message)->persistent()->send();
    }

    // =========================================================
    // ✅ ÉTAT DES 12 MOIS
    // =========================================================

    /** Mois encore vivants rattachés à une ligne retirée ou désactivée (lignes superflues du tableau). */
    public function moisOrphelins(): array
    {
        if ($this->orphelins !== null) return $this->orphelins;
        if (!$this->exerciceId) return $this->orphelins = [];

        return $this->orphelins = DB::select("
            SELECT m.id, l.code_nomenclature, l.libelle_nomenclature,
                   CAST(m.montant_prevu AS FLOAT) AS prevu,
                   (SELECT COUNT(*) FROM recettes_reelles r
                     WHERE r.prevision_recette_mensuelle_id = m.id AND r.deleted_at IS NULL) AS nb_recettes
            FROM previsions_recettes_mensuelles m
            JOIN lignes_previsions_recettes l ON l.id = m.ligne_prevision_recette_id
            WHERE m.deleted_at IS NULL
              AND m.exercice_id = ?
              AND (l.deleted_at IS NOT NULL OR l.actif = FALSE)
            ORDER BY l.code_nomenclature, m.mois", [$this->exerciceId]);
    }

    /** Lignes actives de la prévision dont les mois sont absents, ranimables ou mal calés. */
    public function lignesADaligner(): array
    {
        if (!$this->previsionId) return [];

        return DB::select("
            SELECT l.id
            FROM lignes_previsions_recettes l
            LEFT JOIN previsions_recettes_mensuelles m
                   ON m.ligne_prevision_recette_id = l.id AND m.deleted_at IS NULL
            WHERE l.prevision_recette_id = ? AND l.deleted_at IS NULL AND l.actif = TRUE
            GROUP BY l.id, l.montant_rectifie
            HAVING COUNT(m.id) <> 12
                OR ABS(SUM(CAST(m.montant_prevu AS FLOAT)) - CAST(l.montant_rectifie AS FLOAT)) > 1
                OR EXISTS (SELECT 1 FROM previsions_recettes_mensuelles t
                            WHERE t.ligne_prevision_recette_id = l.id AND t.deleted_at IS NOT NULL)", [$this->previsionId]);
    }

    /**
     * Diagnostic affiché au bandeau : recalculé à chaque interaction, jamais caché.
     * Le comptage des lignes vient de la même requête que l'action « Régénérer les 12 mois »,
     * le bandeau et la modale annoncent donc toujours le même chiffre.
     */
    public function etatDesMois(array $data): array
    {
        $orphelins = $this->moisOrphelins();

        return [
            'lignes_incompletes' => count($this->lignesADaligner()),
            'mois_orphelins'     => count($orphelins),
            'mois_orphelins_montant' => array_sum(array_column($orphelins, 'prevu')),
            'mois_orphelins_retirables' => count(array_filter($orphelins, fn($o) => (int) $o->nb_recettes === 0)),
        ];
    }

    // =========================================================
    // ✅ AJOUT D'UNE LIGNE DE PRÉVISION (même prévision adoptée)
    // =========================================================

    public function creerLigne(array $data): void
    {
        if (!$this->peutModifierLaPrevision()) {
            $this->refus('Action non autorisée', 'Il faut la permission « create_prevision_recette » ou « update_prevision_recette » pour ajouter une ligne de prévision.');
            return;
        }

        $prevision = PrevisionRecette::find($this->previsionId);
        if (!$prevision) {
            $this->refus('Aucune prévision', 'Sélectionnez une prévision de recettes avant d\'ajouter une ligne.');
            return;
        }

        if ($prevision->estCloture() || $prevision->estLectureSeule()) {
            $this->refus('Prévision clôturée',
                "La prévision {$prevision->code} (ou son exercice) est clôturée : plus aucune ligne ne peut y être ajoutée.");
            return;
        }

        $doublon = LignePrevisionRecette::where('prevision_recette_id', $prevision->id)
            ->where('nomenclature_id', $data['nomenclature_id'])
            ->whereNull('deleted_at')
            ->first();
        if ($doublon) {
            $this->refus('Ligne déjà présente',
                "{$doublon->code_nomenclature} — {$doublon->libelle_nomenclature} figure déjà dans cette prévision "
                . '(' . number_format((float) $doublon->montant_rectifie, 0, ',', ' ') . ' FCFA).'
                . ' Modifiez ce montant par un collectif budgétaire.');
            return;
        }

        $montant = (float) $data['montant'];
        $nomenclature = NomenclatureBudgetaire::find($data['nomenclature_id']);

        $ligne = DB::transaction(function () use ($prevision, $data, $montant) {
            $ligne = LignePrevisionRecette::create([
                'prevision_recette_id'  => $prevision->id,
                'nomenclature_id'       => $data['nomenclature_id'],
                'montant_prevu_initial' => $montant,
                'montant_rectifie'      => $montant,
                'ordre'                 => (int) LignePrevisionRecette::where('prevision_recette_id', $prevision->id)->max('ordre') + 1,
                'observations'          => $data['observations'] ?: null,
                'actif'                 => true,
            ]);

            // created() insère déjà les 12 mois ; on rejoue l'alignement pour les
            // éventuels mois restés en suppression sous le même code.
            $ligne->alignerMensuelles();

            $prevision->recalculerTotaux();
            Exercice::find($prevision->exercice_id)?->mettreAJourStatistiques();

            ActivityLog::logAction($ligne, 'ajout_ligne_prevision', [
                'prevision'    => $prevision->code,
                'code'         => $ligne->code_nomenclature,
                'libelle'      => $ligne->libelle_nomenclature,
                'montant'      => $montant,
                'par'          => auth()->user()?->name,
                'statut_prevision' => $prevision->statut,
            ]);

            return $ligne;
        });

        \Filament\Notifications\Notification::make()->success()->title('Ligne ajoutée au suivi')
            ->body(($nomenclature ? "{$ligne->code_nomenclature} — {$ligne->libelle_nomenclature}" : $ligne->code_nomenclature)
                . ' : ' . number_format($montant, 0, ',', ' ') . ' FCFA sur 12 mois ('
                . number_format(round($montant / 12, 2), 0, ',', ' ') . ' FCFA/mois).')
            ->persistent()->send();

        $this->rafraichir();
    }

    // =========================================================
    // ✅ RÉGÉNÉRATION DES MOIS — toute la prévision ou une seule ligne
    // =========================================================

    public function alignerMensuelles(): void
    {
        if (!$this->peutModifierLaPrevision()) {
            $this->refus('Action non autorisée', 'Il faut la permission « update_prevision_recette » pour régénérer les prévisions mensuelles.');
            return;
        }

        $ids = collect($this->lignesADaligner())->pluck('id');
        if ($ids->isEmpty()) {
            \Filament\Notifications\Notification::make()->info()->title('Rien à régénérer')
                ->body('Toutes les lignes de cette prévision ont leurs 12 mois calés sur leur montant rectifié.')
                ->send();
            $this->rafraichir();
            return;
        }

        $lignes = LignePrevisionRecette::whereIn('id', $ids)->orderBy('ordre')->get();
        foreach ($lignes as $ligne) {
            $ligne->alignerMensuelles();
        }

        PrevisionRecette::find($this->previsionId)?->recalculerTotaux();
        Exercice::find($this->exerciceId)?->mettreAJourStatistiques();

        ActivityLog::logAction(PrevisionRecette::find($this->previsionId), 'regeneration_mois_prevision', [
            'lignes'    => $lignes->pluck('code_nomenclature')->all(),
            'par'       => auth()->user()?->name,
        ]);

        \Filament\Notifications\Notification::make()->success()->title('Prévisions mensuelles régénérées')
            ->body($lignes->count() . ' ligne(s) remise(s) à 12 mois : '
                . $lignes->pluck('code_nomenclature')->take(8)->implode(', ')
                . ($lignes->count() > 8 ? '…' : '') . '. Les recouvrements sont conservés.')
            ->persistent()->send();

        $this->rafraichir();
    }

    /** Action par ligne : compléter / realigner les 12 mois de cette seule ligne. */
    public function alignerLigne(int $ligneId): void
    {
        if (!$this->peutModifierLaPrevision()) {
            $this->refus('Action non autorisée', 'Il faut la permission « update_prevision_recette » pour régénérer les prévisions mensuelles.');
            return;
        }

        $ligne = LignePrevisionRecette::where('prevision_recette_id', $this->previsionId)->find($ligneId);
        if (!$ligne) return;

        DB::transaction(function () use ($ligne) {
            $ligne->alignerMensuelles();
            PrevisionRecette::find($this->previsionId)?->recalculerTotaux();
        });
        Exercice::find($this->exerciceId)?->mettreAJourStatistiques();

        $mensuel = round((float) $ligne->montant_rectifie / 12, 2);
        \Filament\Notifications\Notification::make()->success()->title('Mois régénérés')
            ->body("{$ligne->code_nomenclature} : 12 mois de " . number_format($mensuel, 0, ',', ' ') . ' FCFA.')
            ->send();

        $this->rafraichir();
    }

    // =========================================================
    // ✅ PURGE DES MOIS ORPHELINS
    // =========================================================

    public function nettoyerMoisOrphelins(): void
    {
        if (!$this->peutModifierLaPrevision()) {
            $this->refus('Action non autorisée', 'Il faut la permission « update_prevision_recette » pour purger les mois orphelins.');
            return;
        }

        $orphelins = $this->moisOrphelins();
        if ($orphelins === []) {
            \Filament\Notifications\Notification::make()->info()->title('Rien à purger')
                ->body("Aucun mois ne reste attaché à une ligne retirée ou désactivée.")
                ->send();
            $this->rafraichir();
            return;
        }

        $idsRetirables = collect($orphelins)->where('nb_recettes', 0)->pluck('id')->all();
        $proteges = count($orphelins) - count($idsRetirables);

        DB::transaction(function () use ($idsRetirables) {
            PrevisionRecetteMensuelle::whereIn('id', $idsRetirables)->get()->each->delete();
        });

        $this->orphelins = null;
        Exercice::find($this->exerciceId)?->mettreAJourStatistiques();

        if ($idsRetirables === []) {
            $this->refus('Purge impossible',
                "Les " . count($orphelins) . " mois orphelins portent encore des recettes : supprimez ou réimputez ces recettes d'abord.");
            $this->rafraichir();
            return;
        }

        ActivityLog::logAction(Exercice::find($this->exerciceId), 'purge_mois_orphelins', [
            'mois_supprimes' => count($idsRetirables),
            'mois_proteges'  => $proteges,
            'par'            => auth()->user()?->name,
        ]);

        \Filament\Notifications\Notification::make()->success()->title('Mois orphelins purgés')
            ->body(count($idsRetirables) . ' mois retiré(s) du tableau de bord'
                . ($proteges > 0 ? " ; {$proteges} mois conservés car ils portent encore des recettes." : '.'))
            ->persistent()->send();

        $this->rafraichir();
    }

    // =========================================================
    // ✅ RETRAIT D'UNE LIGNE DE PRÉVISION
    // =========================================================

    /**
     * Retire une ligne de prévision (et ses 12 prévisions mensuelles), en suppression récupérable.
     * Refusé si des recettes sont enregistrées sur la ligne ou si un collectif l'a modifiée ou
     * créée : la retirer fausserait alors le recouvré ou le budget rectifié — le refus s'affiche,
     * le bouton ne disparaît pas.
     */
    public function retirerLigne(int $ligneId): void
    {
        if (!$this->peutRetirerDesLignes()) {
            $this->refus('Action non autorisée',
                'Il faut la permission « delete_prevision_recette » pour retirer une ligne de prévision.');
            return;
        }

        $ligne = LignePrevisionRecette::where('prevision_recette_id', $this->previsionId)->find($ligneId);
        if (!$ligne) {
            return;
        }

        $nbRecettes = DB::table('recettes_reelles as r')
            ->join('previsions_recettes_mensuelles as m', 'm.id', '=', 'r.prevision_recette_mensuelle_id')
            ->where('m.ligne_prevision_recette_id', $ligne->id)
            ->whereNull('r.deleted_at')
            ->count();

        if ($nbRecettes > 0) {
            $this->refus('Retrait impossible',
                "{$nbRecettes} recette(s) sont enregistrées sur la ligne {$ligne->code_nomenclature} : "
                . 'supprimez-les ou réimputez-les d\'abord.');
            return;
        }

        $mouvement = DB::table('mouvements_collectifs')
            ->where(fn($q) => $q->where('ligne_recette_id', $ligne->id)->orWhere('nouvelle_ligne_recette_id', $ligne->id))
            ->when(\Illuminate\Support\Facades\Schema::hasColumn('mouvements_collectifs', 'deleted_at'), fn($q) => $q->whereNull('deleted_at'))
            ->orderByDesc('id')
            ->first();

        if ($mouvement) {
            $numero = \App\Models\CollectifBudgetaire::find($mouvement->collectif_budgetaire_id)?->numero ?? 'un collectif';
            $creee = (int) $mouvement->nouvelle_ligne_recette_id === (int) $ligne->id;
            $this->refus('Retrait impossible',
                "La ligne {$ligne->code_nomenclature} " . ($creee ? 'a été créée par' : 'a été modifiée par')
                . " le collectif {$numero} : réinitialisez ce collectif depuis « Réinitialisation des collectifs budgétaires », "
                . 'il retirera la ligne et ses effets en une fois.');
            return;
        }

        $prevision = PrevisionRecette::find($this->previsionId);
        if ($prevision && ($prevision->estCloture() || $prevision->estLectureSeule())) {
            $this->refus('Retrait impossible',
                "La prévision {$prevision->code} est clôturée : aucune ligne ne peut en être retirée.");
            return;
        }

        $nbMois = PrevisionRecetteMensuelle::where('ligne_prevision_recette_id', $ligne->id)->count();

        DB::transaction(function () use ($ligne) {
            // L'observateur entraine les 12 mois avec la ligne.
            $ligne->delete();

            ActivityLog::logAction($ligne, 'retrait_ligne_prevision', [
                'code' => $ligne->code_nomenclature,
                'libelle' => $ligne->libelle_nomenclature,
                'montant_prevu' => (float) $ligne->montant_rectifie,
                'par' => auth()->user()?->name,
            ]);
        });

        PrevisionRecette::find($this->previsionId)?->recalculerTotaux();
        Exercice::find($this->exerciceId)?->mettreAJourStatistiques();

        \Filament\Notifications\Notification::make()->success()->title('Ligne retirée')
            ->body("{$ligne->code_nomenclature} — {$ligne->libelle_nomenclature} et ses {$nbMois} mois "
                . '(suppression récupérable par un administrateur).')
            ->send();

        $this->rafraichir();
    }

    public function actualiser(): void
    {
        $this->rafraichir();
    }

    // =========================================================
    // DESCRIPTIONS DES MODALES
    // =========================================================

    private function descriptionAlignement(): string
    {
        $n = count($this->lignesADaligner());

        return $n === 0
            ? 'Toutes les lignes de cette prévision ont déjà leurs 12 mois calés sur leur montant rectifié : rien à régénérer.'
            : "{$n} ligne(s) de cette prévision sont sans mois, incomplètes ou mal calées. "
                . 'Chacune est remise à 12 mois égaux (montant rectifié ÷ 12), les mois retirés à tort '
                . 'redeviennent visibles, et les montants déjà recouvrés sont conservés.';
    }

    private function descriptionPurge(): string
    {
        $orphelins = $this->moisOrphelins();
        if ($orphelins === []) return 'Aucun mois ne reste attaché à une ligne retirée ou désactivée.';

        $retirables = count(array_filter($orphelins, fn($o) => (int) $o->nb_recettes === 0));
        $montant = array_sum(array_column($orphelins, 'prevu'));

        return count($orphelins) . ' prévision(s) mensuelle(s) affichent encore des crédits alors que leur '
            . 'ligne de prévision a été retirée ou désactivée ('
            . number_format($montant, 0, ',', ' ') . ' FCFA de prévu au total). '
            . $retirables . ' mois seront mis en suppression récupérable'
            . (count($orphelins) - $retirables > 0
                ? ' ; ' . (count($orphelins) - $retirables) . ' mois portant encore des recettes seront conservés.'
                : '.');
    }

    /** Compte des lignes déjà saisies, pour l'aide du champ nomenclature. */
    public function nomenclaturesDisponibles(): array
    {
        return NomenclatureBudgetaire::query()
            ->where('type', 'recette')
            ->where('actif', true)
            ->when($this->exerciceId, fn($q) => $q->where('exercice_id', $this->exerciceId))
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn($n) => [$n->id => "{$n->code} — {$n->libelle}"])
            ->all();
    }

    // =========================================================
    // ACTIONS HEADER
    // =========================================================
    protected function getHeaderActions(): array
    {
        return [
            Action::make('actualiser')
                ->label('Actualiser')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->outlined()
                ->action(fn() => $this->actualiser()),

            Action::make('ajouter_ligne')
                ->label('Ajouter une prévision')
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->form([
                    Forms\Components\Select::make('nomenclature_id')
                        ->label('Compte de recette')
                        ->options(fn() => $this->nomenclaturesDisponibles())
                        ->searchable(['code', 'libelle'])
                        ->required(),

                    Forms\Components\TextInput::make('montant')
                        ->label('Montant annuel prévu')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->prefix('FCFA')
                        ->helperText('Réparti automatiquement en 12 mois égaux.'),

                    Forms\Components\Textarea::make('observations')
                        ->label('Observations')
                        ->rows(2),
                ])
                ->modalHeading('Ajouter une ligne de prévision de recette')
                ->modalDescription('La ligne et ses 12 prévisions mensuelles sont créées tout de suite : '
                    . 'elle apparaît dans ce tableau sans repasser par une prévision en élaboration.')
                ->modalSubmitActionLabel('Ajouter la ligne')
                ->visible(fn() => $this->previsionId !== null && $this->peutModifierLaPrevision())
                ->action(fn(array $data) => $this->creerLigne($data)),

            Action::make('aligner_mois')
                ->label("Régénérer les 12 mois")
                ->icon('heroicon-o-calendar-days')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Régénérer les prévisions mensuelles')
                ->modalDescription(fn() => $this->descriptionAlignement())
                ->modalSubmitActionLabel('Régénérer')
                ->visible(fn() => $this->previsionId !== null)
                ->action(fn() => $this->alignerMensuelles()),

            Action::make('purger_orphelins')
                ->label('Purger les mois orphelins')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Purger les mois des lignes retirées')
                ->modalDescription(fn() => $this->descriptionPurge())
                ->modalSubmitActionLabel('Purger')
                ->action(fn() => $this->nettoyerMoisOrphelins()),

            Action::make('nouvelle_recette')
                ->label('Saisir une recette')
                ->icon('heroicon-o-plus')
                ->color('success')
                ->outlined()
                ->url(fn() => RecetteReelleResource::getUrl('create')),
        ];
    }
}
