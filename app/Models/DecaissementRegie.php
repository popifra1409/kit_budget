<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DecaissementRegie extends Model
{
    protected $table = 'decaissements_regies';

    protected $fillable = [
        'regie_avance_id',
        'decision_administrative_id',
        'numero',
        'trimestre',
        'libelle_tranche',
        'montant_demande',
        'montant_accorde',
        'date_demande',
        'date_decaissement',
        'date_apurement',
        'certificat_numero',
        'certificat_date',
        'mandat_numero',
        'mandat_date',
        'quittance_numero',
        'quittance_date',
        'certificat_fichier',
        'statut',
        'montant_depense',
        'montant_engage',
        'montant_ir_collecte',
        'montant_solde',
        'observations',
        'created_by',
    ];

    protected $casts = [
        'date_demande'       => 'date',
        'date_decaissement'  => 'date',
        'date_apurement'     => 'date',
        'certificat_date'    => 'date',
        'mandat_date'        => 'date',
        'quittance_date'     => 'date',
        'montant_demande'    => 'decimal:2',
        'montant_accorde'    => 'decimal:2',
        'montant_depense'    => 'decimal:2',
        'montant_engage'     => 'decimal:2',
        'montant_ir_collecte' => 'decimal:2',
        'montant_solde'      => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function ($decaissement) {
            if (!$decaissement->numero) {
                $decaissement->numero = static::genererNumero(
                    $decaissement->regieAvance
                );
            }
            $decaissement->created_by = auth()->id();
        });

        static::saved(fn($d) => $d->regieAvance?->recalculerMontants());
        static::deleted(fn($d) => $d->regieAvance?->recalculerMontants());
    }

    // ── Relations ─────────────────────────────────────────────
    public function regieAvance(): BelongsTo
    {
        return $this->belongsTo(RegieAvance::class, 'regie_avance_id');
    }

    public function depenses(): HasMany
    {
        return $this->hasMany(DepenseRegie::class, 'decaissement_regie_id');
    }

    public function provisions(): HasMany
    {
        return $this->hasMany(ProvisionLigneRegie::class, 'decaissement_regie_id');
    }

    /**
     * Les lignes de mini-budget propres à CETTE tranche (un encaisse = une ligne
     * par nomenclature). Les lignes sans tranche sont les lignes héritées.
     */
    public function lignes(): HasMany
    {
        return $this->hasMany(LigneRegieAvance::class, 'decaissement_regie_id');
    }

    public function decisionAdministrative(): BelongsTo
    {
        return $this->belongsTo(DecisionAdministrative::class, 'decision_administrative_id');
    }

    // ── Numérotation ──────────────────────────────────────────
    public static function genererNumero(RegieAvance $regie): string
    {
        $annee  = substr($regie->exercice->annee ?? now()->year, -2);
        $prefix = match ($regie->type) {
            'rav'          => "DCR{$annee}",
            'menu_depense' => "DCM{$annee}",
            default        => "DCR{$annee}",
        };

        $result = \DB::selectOne("
            SELECT COALESCE(MAX(CAST(SPLIT_PART(numero, '-', 2) AS INTEGER)), 0) AS max_seq
            FROM decaissements_regies
            WHERE numero LIKE :pattern
        ", ['pattern' => "{$prefix}-%"]);

        return sprintf('%s-%05d', $prefix, ($result->max_seq ?? 0) + 1);
    }

    // ── Méthodes ──────────────────────────────────────────────
    /**
     * Consommation RÉELLE de la tranche : la régie est consommée dès l'ENGAGEMENT
     * du BCR (traçabilité provision_consommations) et dès la validation d'un achat
     * direct. Le paiement ne fait que soldater ce qui a déjà été réservé.
     */
    public function montantEngageReel(): float
    {
        $provisionIds = \App\Models\ProvisionLigneRegie::where('decaissement_regie_id', $this->id)
            ->pluck('id');

        $engages = (float) \App\Models\ProvisionConsommation::whereIn(
            'provision_ligne_regie_id',
            $provisionIds
        )->sum('montant');

        $directes = (float) \App\Models\DepenseRegie::where(function ($q) use ($provisionIds) {
            $q->where('decaissement_regie_id', $this->id)
                ->orWhereIn('provision_ligne_regie_id', $provisionIds);
        })
            ->whereIn('statut', ['valide', 'paye'])
            ->sum('montant_ttc');

        return $engages + $directes;
    }

    public function recalculerDepenses(): void
    {
        // ✅ Mettre à jour montant_consomme sur chaque provision, en se basant
        //    sur la traçabilité RÉELLE (provision_consommations pour les BCR
        //    engagés en cascade + DepenseRegie pour les achats directs).
        //    Remplace l'ancienne resommation par statut qui double-comptait
        //    le TTC complet d'un BCR partiellement engagé.
        $this->load('provisions');

        foreach ($this->provisions as $prov) {
            $consommeParBcr = \App\Models\ProvisionConsommation::where('provision_ligne_regie_id', $prov->id)
                ->sum('montant');

            $consommeParDepenseDirecte = \App\Models\DepenseRegie::where('provision_ligne_regie_id', $prov->id)
                ->whereIn('statut', ['valide', 'paye'])
                ->sum('montant_ttc');

            $consommeProv = (float) $consommeParBcr + (float) $consommeParDepenseDirecte;

            $prov->updateQuietly([
                'montant_consomme'   => $consommeProv,
                'montant_disponible' => $prov->montant_provisionne - $consommeProv,
            ]);

            // ✅ La ligne de mini-budget se lit à partir de ses provisions :
            //    resynchronisée ici, plus de consommé affiché en retard après
            //    un engagement ou un paiement (engager() passe par updateQuietly,
            //    donc aucun observateur ne prévient la ligne).
            $prov->ligneRegie?->recalculerMontants();
        }

        // ✅ Mettre à jour le décaissement — dépenses réellement livrées/payées.
        //    Basé sur la consommation RÉELLE en cascade (provision_consommations),
        //    pas sur le TTC complet des BCR attribués à leur "provision principale"
        //    (qui double/mal-comptait en cas de répartition sur plusieurs décaissements).
        $statutsDepenses = ['livre', 'livre_partiellement', 'paye'];
        $provisionIds    = $this->provisions->pluck('id');

        $totalDepense = $this->depenses()
            ->whereIn('statut', ['valide', 'paye'])
            ->sum('montant_ttc');

        $totalIrDepenses = $this->depenses()
            ->whereIn('statut', ['valide', 'paye'])
            ->sum('montant_ir');

        // Consommations BCR réellement imputées aux provisions de CE décaissement,
        // limitées aux BCR au statut "réellement dépensé" — et pondérées à l'IR
        // proportionnellement à la part prise sur ce décaissement.
        $consommationsBcr = \App\Models\ProvisionConsommation::whereIn('provision_ligne_regie_id', $provisionIds)
            ->with('bonCommandeRegie')
            ->get()
            ->filter(fn($c) => $c->bonCommandeRegie && in_array($c->bonCommandeRegie->statut, $statutsDepenses));

        $totalBcr   = (float) $consommationsBcr->sum('montant');
        $totalIrBcr = (float) $consommationsBcr->sum(function ($c) {
            $bcr = $c->bonCommandeRegie;
            if ((float) $bcr->montant_ttc <= 0) {
                return 0;
            }
            // Part d'IR proportionnelle au montant réellement pris sur ce décaissement
            return (float) $c->montant * ((float) $bcr->montant_ir / (float) $bcr->montant_ttc);
        });
        $totalDepenseGlobal = $totalDepense + $totalBcr;
        $totalIr            = $totalIrDepenses + $totalIrBcr;

        $totalEngage = $this->montantEngageReel();

        $this->updateQuietly([
            'montant_depense'     => $totalDepenseGlobal,
            'montant_engage'      => $totalEngage,
            'montant_ir_collecte' => $totalIr,
            // Ce qui reste employable sur la tranche se juge à l'engagement :
            // un BCR engagé n'est plus dépensable, même non livré ni payé.
            'montant_solde'       => ($this->montant_accorde ?? 0) - $totalEngage,
        ]);

        // ✅ Mettre à jour la régie parente
        $regie = $this->regieAvance;
        if ($regie) {
            $encaissees = $regie->decaissements()
                ->whereIn('statut', ['verse', 'apure'])
                ->get();

            $totalEngageRegie = (float) $encaissees->sum('montant_engage');

            $regie->updateQuietly([
                'montant_depense'    => (float) $encaissees->sum('montant_depense'),
                'montant_engage'     => $totalEngageRegie,
                'montant_disponible' => (float) $regie->montant_decaisse - $totalEngageRegie,
            ]);
        }
    }

    // =========================================================
    // ✅ CRÉER LES PROVISIONS AUTOMATIQUEMENT — déplacé depuis
    // DecaissementsRelationManager pour être réutilisable (notamment
    // par RegieAvance::reapprovisionner(), qui crée une tranche sans
    // passer par l'action "Accorder & Verser" du RelationManager).
    //
    // Chaque tranche crée SES PROPRES lignes de mini-budget : le 2e encaisse
    // trimestriel ne se cumule plus sur la ligne du 1er déjà en consommation.
    // =========================================================
    public function creerProvisions(RegieAvance $regie, float $montantAccorde): void
    {
        $sources = $this->sourcesAllocation($regie);

        if ($sources->isEmpty()) {
            throw new \Exception(
                "Aucune nomenclature d'allocation pour cette régie : associez d'abord une DA source "
                    . "(onglet « Décision source ») ou une ligne de mini-budget, puis accordez la tranche."
            );
        }

        $repartition = [];

        // ── RAV (ou allocation unique) : la tranche entière sur la ligne ──
        if ($regie->type === 'rav' || $sources->count() === 1) {
            $repartition[] = ['source' => $sources->first(), 'montant' => $montantAccorde];
        } else {
            // ── Menu Dépense : répartition proportionnelle aux enveloppes ──
            $totalPoids = (float) $sources->sum('poids');
            $sources    = $sources->values();
            $restant    = $montantAccorde;

            foreach ($sources as $index => $source) {
                if ($index === $sources->count() - 1) {
                    $montant = round($restant, 2);
                } else {
                    $montant = $totalPoids > 0
                        ? round($montantAccorde * ((float) $source['poids'] / $totalPoids), 2)
                        : round($montantAccorde / $sources->count(), 2);
                    $restant -= $montant;
                }

                $repartition[] = ['source' => $source, 'montant' => $montant];
            }
        }

        foreach ($repartition as $part) {
            $ligne = $this->lignePourLaTranche($regie, $part['source'], $part['montant']);
            $this->creerOuMettreAJourProvision($ligne, $part['montant']);
        }
    }

    /**
     * Nomenclatures que la tranche doit approvisionner, avec le poids qui sert à
     * la répartition : les enveloppes des DA sources, à défaut les lignes de
     * mini-budget saisies à la main.
     */
    private function sourcesAllocation(RegieAvance $regie): \Illuminate\Support\Collection
    {
        $sources = [];

        foreach ($regie->decisionsSource()->with('nomenclature')->get() as $liaison) {
            if (!$liaison->nomenclature_id) {
                continue;
            }

            $sources[$liaison->nomenclature_id] ??= [
                'nomenclature_id'      => $liaison->nomenclature_id,
                'ligne_budgetaire_id'  => $liaison->ligne_budgetaire_id,
                'poids'                => 0.0,
            ];
            $sources[$liaison->nomenclature_id]['poids'] += (float) $liaison->montant_da;
        }

        if ($sources !== []) {
            return collect(array_values($sources));
        }

        return $regie->lignes()
            ->with('nomenclature')
            ->get()
            ->map(fn($ligne) => [
                'nomenclature_id'     => $ligne->nomenclature_id,
                'ligne_budgetaire_id' => $ligne->ligne_budgetaire_id,
                'poids'               => (float) $ligne->montant_alloue,
            ])
            ->values();
    }

    /**
     * La ligne de mini-budget de CETTE tranche pour cette nomenclature.
     * Son alloué ne descend jamais sous ce qui est déjà consommé.
     */
    public function lignePourLaTranche(
        RegieAvance $regie,
        array $source,
        float $montant
    ): LigneRegieAvance {
        $ligne = LigneRegieAvance::where([
            'decaissement_regie_id' => $this->id,
            'nomenclature_id'       => $source['nomenclature_id'],
        ])->first();

        if (!$ligne) {
            $ligneBudgetaireId = $source['ligne_budgetaire_id']
                ?: LigneBudgetaire::where('budget_id', $regie->budget_id)
                    ->where('nomenclature_id', $source['nomenclature_id'])
                    ->value('id');

            if (!$ligneBudgetaireId) {
                throw new \Exception(
                    'Aucune ligne budgétaire pour la nomenclature '
                        . ($source['nomenclature_id'])
                        . ' dans le budget de la régie : la tranche ne peut pas être provisionnée.'
                );
            }

            $ligne = LigneRegieAvance::create([
                'regie_avance_id'        => $regie->id,
                'decaissement_regie_id'  => $this->id,
                'nomenclature_id'        => $source['nomenclature_id'],
                'ligne_budgetaire_id'    => $ligneBudgetaireId,
                'montant_alloue'         => $montant,
                'montant_consomme'       => 0,
            ]);
        } elseif ($montant > (float) $ligne->montant_alloue) {
            $ligne->update(['montant_alloue' => $montant]);
        }

        $ligne->recalculerMontants();

        return $ligne;
    }

    public function creerOuMettreAJourProvision(LigneRegieAvance $ligne, float $montant): void
    {
        $existante = ProvisionLigneRegie::where([
            'decaissement_regie_id' => $this->id,
            'ligne_regie_avance_id' => $ligne->id,
        ])->first();

        if ($existante) {
            // Ré-accord d'une même tranche : on ne peut pas raboter en dessous
            // de ce qui est déjà consommé sur cette provision.
            $nouveau = max($montant, (float) $existante->montant_consomme);

            $existante->updateQuietly([
                'montant_provisionne' => $nouveau,
                'montant_disponible'  => $nouveau - $existante->montant_consomme,
            ]);
        } else {
            ProvisionLigneRegie::create([
                'decaissement_regie_id' => $this->id,
                'ligne_regie_avance_id' => $ligne->id,
                'montant_provisionne'   => $montant,
                'montant_consomme'      => 0,
                'montant_disponible'    => $montant,
            ]);
        }
    }
}
