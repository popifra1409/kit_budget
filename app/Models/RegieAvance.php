<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class RegieAvance extends Model
{
    use SoftDeletes, LogsActivity;

    protected $table = 'regies_avances';

    protected $fillable = [
        'numero',
        'libelle',
        'type',
        'exercice_id',
        'budget_id',
        'responsable_id',
        'decision_administrative_id',
        'montant_alloue',
        'montant_decaisse',
        'montant_depense',
        'montant_engage',
        'montant_disponible',
        'encaisse_annuelle',
        'objet',
        'statut',
        'date_creation',
        'date_cloture',
        'resolution_numero',
        'resolution_date',
        'decision_ouverture_numero',
        'decision_ouverture_date',
        'decision_designation_numero',
        'decision_designation_date',
        'observations',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date_creation'      => 'date',
        'date_cloture'       => 'date',
        'resolution_date'    => 'date',
        'decision_ouverture_date'   => 'date',
        'decision_designation_date' => 'date',
        'montant_alloue'     => 'decimal:2',
        'montant_decaisse'   => 'decimal:2',
        'montant_depense'    => 'decimal:2',
        'montant_engage'     => 'decimal:2',
        'montant_disponible' => 'decimal:2',
    ];

    // =========================================================
    // BOOT
    // =========================================================
    protected static function booted(): void
    {
        static::creating(function ($regie) {
            if (!$regie->numero) {
                $regie->numero = static::genererNumero($regie->type, $regie->exercice_id);
            }
            $regie->created_by         = auth()->id();
            $regie->montant_disponible = $regie->montant_alloue;
        });

        static::saving(function (RegieAvance $regie) {
            if (
                $regie->decision_administrative_id
                && ($regie->montant_alloue === null || $regie->montant_alloue == 0)
            ) {
                $da = \App\Models\DecisionAdministrative::find(
                    $regie->decision_administrative_id
                );
                if ($da) {
                    $regie->montant_alloue = (float) ($da->montant_net ?? 0);
                }
            }
        });

        static::updating(function (RegieAvance $regie) {
            $regie->updated_by = auth()->id();

            // Disponible = décaissé - ENGAGÉ. La consommation de la régie se fait à
            // l'engagement du BCR, pas à son paiement. Ne pas recalculer
            // montant_depense/montant_engage ici pour éviter les boucles avec
            // DecaissementRegie.
            $engage = $regie->isDirty('montant_engage')
                ? (float) $regie->montant_engage
                : $regie->montantEngageReel();

            $regie->montant_disponible = (float) $regie->montant_decaisse - $engage;
        });
    }

    // =========================================================
    // RELATIONS
    // =========================================================
    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function decisionAdministrative(): BelongsTo
    {
        return $this->belongsTo(DecisionAdministrative::class, 'decision_administrative_id');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneRegieAvance::class, 'regie_avance_id');
    }

    public function decaissements(): HasMany
    {
        return $this->hasMany(DecaissementRegie::class, 'regie_avance_id');
    }

    public function depenses(): HasMany
    {
        return $this->hasMany(DepenseRegie::class, 'regie_avance_id');
    }

    public function bonsCommande(): HasMany
    {
        return $this->hasMany(BonCommandeRegie::class, 'regie_avance_id');
    }

    public function decisionsSource(): HasMany
    {
        return $this->hasMany(MenuDepenseDecision::class, 'regie_avance_id');
    }

    // =========================================================
    // NUMÉROTATION
    // =========================================================
    public static function genererNumero(string $type, ?int $exerciceId = null): string
    {
        $exercice = $exerciceId
            ? Exercice::find($exerciceId)
            : Exercice::getActif();

        if (!$exercice) {
            throw new \Exception("Aucun exercice disponible.");
        }

        $annee  = substr($exercice->annee, -2);
        $prefix = match ($type) {
            'rav'          => "RAV{$annee}",
            'menu_depense' => "MDE{$annee}",
            default        => "REG{$annee}",
        };

        return \DB::transaction(function () use ($prefix, $exercice) {
            $result = \DB::selectOne("
                SELECT COALESCE(MAX(CAST(SPLIT_PART(numero, '-', 2) AS INTEGER)), 0) AS max_seq
                FROM regies_avances
                WHERE exercice_id = :exercice_id
                  AND numero LIKE :pattern
            ", [
                'exercice_id' => $exercice->id,
                'pattern'     => "{$prefix}-%",
            ]);

            return sprintf('%s-%05d', $prefix, ($result->max_seq ?? 0) + 1);
        });
    }

    // =========================================================
    // MÉTHODES MÉTIER
    // =========================================================
    public static function seuilAchatDirect(): float
    {
        return (float) (
            ParametresStructure::where('actif', true)->value('seuil_achat_direct_regie')
            ?? 500000
        );
    }

    public static function seuilBonCommande(): float
    {
        return (float) (
            ParametresStructure::where('actif', true)->value('seuil_bon_commande_regie')
            ?? 5000000
        );
    }

    public static function determinerTypeDepense(float $montantTtc): string
    {
        $seuilAd  = static::seuilAchatDirect();
        $seuilBcr = static::seuilBonCommande();

        return match (true) {
            $montantTtc < $seuilAd  => 'achat_direct',
            $montantTtc < $seuilBcr => 'bon_commande',
            default                 => 'marche_public',
        };
    }

    /**
     * ✅ Agrégats de consommation en sous-sélections corrélées, à injecter dans
     *    ->addSelect() d'une liste : une seule requête pour toute la page au lieu
     *    de trois par tranche. Les prédicats sont identiques à
     *    montantEngageReel() / montantPayeNonEngage(), qui lisent ces attributs
     *    quand ils sont présents — liste et détail restent concordants.
     *
     * @return array<string, \Illuminate\Database\Query\Builder>
     */
    public static function selectsConsommations(): array
    {
        $tranches = fn() => DB::query()
            ->select('decaissements_regies.id')
            ->from('decaissements_regies')
            ->whereIn('decaissements_regies.statut', ['verse', 'apure'])
            ->whereColumn('decaissements_regies.regie_avance_id', 'regies_avances.id');

        return [
            // BCR engagés : consommation tracée sur les provisions des tranches versées/apurées
            'engage_traces_calcule' => DB::table('provision_consommations')
                ->join('provisions_lignes_regies', 'provisions_lignes_regies.id', '=', 'provision_consommations.provision_ligne_regie_id')
                ->join('decaissements_regies', 'decaissements_regies.id', '=', 'provisions_lignes_regies.decaissement_regie_id')
                ->whereIn('decaissements_regies.statut', ['verse', 'apure'])
                ->whereColumn('decaissements_regies.regie_avance_id', 'regies_avances.id')
                ->selectRaw('coalesce(sum(provision_consommations.montant), 0)'),

            // Achats directs validés/payés rattachés à une tranche de la régie
            'engage_direct_calcule' => DB::table('depenses_regies')
                ->leftJoin('provisions_lignes_regies', 'provisions_lignes_regies.id', '=', 'depenses_regies.provision_ligne_regie_id')
                ->whereIn('depenses_regies.statut', ['valide', 'paye'])
                ->where(function ($query) use ($tranches) {
                    $query->whereIn('depenses_regies.decaissement_regie_id', $tranches())
                        ->orWhereIn('provisions_lignes_regies.decaissement_regie_id', $tranches());
                })
                ->selectRaw('coalesce(sum(depenses_regies.montant_ttc), 0)'),

            // BCR payés au-delà de leur engagement tracé (déficit à régulariser)
            'paye_non_engage_calcule' => DB::table('bons_commande_regies')
                ->leftJoin(
                    DB::raw('(select bon_commande_regie_id, sum(montant) as total from provision_consommations group by bon_commande_regie_id) as traces'),
                    'traces.bon_commande_regie_id',
                    '=',
                    'bons_commande_regies.id'
                )
                ->whereNull('bons_commande_regies.deleted_at')
                ->where('bons_commande_regies.statut', 'paye')
                ->whereColumn('bons_commande_regies.regie_avance_id', 'regies_avances.id')
                ->selectRaw('coalesce(sum(greatest(0, bons_commande_regies.montant_ttc - coalesce(traces.total, 0))), 0)'),

            'ir_collecte_calcule' => DB::table('decaissements_regies')
                ->whereColumn('decaissements_regies.regie_avance_id', 'regies_avances.id')
                ->selectRaw('coalesce(sum(decaissements_regies.montant_ir_collecte), 0)'),
        ];
    }

    /**
     * Ce que la régie a déjà consommé : les BCR au statut engagé et les achats
     * directs validés. Livrer ou payer un BCR ne change rien ici — la somme avait
     * déjà été réservée sur l'encaisse au moment de l'engagement.
     */
    public function montantEngageReel(): float
    {
        if (isset($this->engage_traces_calcule, $this->engage_direct_calcule)) {
            return (float) $this->engage_traces_calcule
                + (float) $this->engage_direct_calcule
                + $this->montantPayeNonEngage();
        }

        return (float) $this->decaissements()
            ->whereIn('statut', ['verse', 'apure'])
            ->get()
            ->sum(fn($d) => $d->montantEngageReel())
            + $this->montantPayeNonEngage();
    }

    /**
     * Déficit historique : ce que des BCR payés ont réellement réglé au-delà de
     * leur engagement tracé. L'argent est sorti de la régie — ne pas le compter
     * rendrait le Disponible fictif. budget:audit-regie les liste sous
     * « … sans encaisse » tant qu'une nouvelle DA ne vient pas les financer.
     */
    public function montantPayeNonEngage(): float
    {
        if (isset($this->paye_non_engage_calcule)) {
            return (float) $this->paye_non_engage_calcule;
        }

        $bons = $this->bonsCommande()->where('statut', 'paye')->get(['id', 'montant_ttc']);

        if ($bons->isEmpty()) {
            return 0.0;
        }

        $traces = \App\Models\ProvisionConsommation::whereIn('bon_commande_regie_id', $bons->pluck('id'))
            ->selectRaw('bon_commande_regie_id, SUM(montant) as total')
            ->groupBy('bon_commande_regie_id')
            ->pluck('total', 'bon_commande_regie_id');

        return (float) $bons->sum(
            fn($b) => max(0, (float) $b->montant_ttc - (float) ($traces[$b->id] ?? 0))
        );
    }

    /**
     * ✅ CORRIGÉ — source : DecaissementRegie.montant_depense
     *
     * Architecture de la régie :
     *   RegieAvance
     *     └── DecaissementRegie (tranches : 01ERE ENCAISSE, 02EME ENCAISSE...)
     *           ├── montant_engage = consommation réelle (BCR engagés + achats directs)
     *           └── montant_depense = part déjà livrée/payée
     *
     * montant_disponible (régie) = montant_decaisse - montant_engage
     *
     * Avant : sommait DepenseRegie (toujours vide → 0), puis le payé seul, ce qui
     * laissait un BCR engagé plein d'argent « disponible ».
     */
    public function recalculerMontants(): void
    {
        $totalDepense = $this->decaissements()->sum('montant_depense');
        $totalIr      = $this->decaissements()->sum('montant_ir_collecte');
        $totalEngage  = $this->montantEngageReel();

        $this->updateQuietly([
            'montant_depense'    => $totalDepense,
            'montant_engage'     => $totalEngage,
            'montant_disponible' => (float) $this->montant_decaisse - $totalEngage,
        ]);
    }

    /**
     * Réapprovisionner depuis une nouvelle DA
     */
    public function reapprovisionner(DecisionAdministrative $da): void
    {
        if ($da->statut !== 'engagee') {
            throw new \Exception("La décision doit être engagée avant d'alimenter la régie.");
        }

        $this->update([
            'montant_alloue'     => $this->montant_alloue + $da->montant_net,
            'montant_disponible' => $this->montant_disponible + $da->montant_net,
        ]);

        activity()
            ->performedOn($this)
            ->causedBy(auth()->user())
            ->withProperties(['montant' => $da->montant_net, 'da' => $da->numero])
            ->log('Régie réapprovisionnée');
    }

    // =========================================================
    // PIÈCES DE L'APUREMENT
    // =========================================================

    /**
     * « PHARMACIE » → « Régie d'Avance de la PHARMACIE » ; un libellé qui porte
     * déjà la mention de la régie est repris tel quel, sinon l'état écrirait
     * « la Régie d'Avance de la REGIE D'AVANCE DES REACTIFS ».
     */
    public function getIntituleRegieAttribute(): string
    {
        $libelle = trim(preg_replace('/\s+/u', ' ', (string) $this->libelle));

        if ($libelle === '') {
            return 'Régie d\'Avance';
        }

        return preg_match('/r[ée]gie/iu', $libelle)
            ? $libelle
            : 'Régie d\'Avance de la ' . $libelle;
    }

    /**
     * « 01ERE ENCAISSE 2026 » + « 02EME ENCAISSE » → « 1ERE ENCAISSE 2026 ET LA
     * 2EME ENCAISSE » : de quoi écrire « APUREMENT DE LA … » sans tripatouiller
     * les libellés saisis par le régisseur.
     *
     * @param \Illuminate\Support\Collection<int, DecaissementRegie> $decaissements
     */
    public static function enteteTranches($decaissements): string
    {
        $libelles = collect($decaissements)
            ->map(fn($d) => trim(ltrim((string) $d->libelle_tranche, '0')))
            ->filter()
            ->values();

        return $libelles->isEmpty() ? '' : $libelles->implode(' ET LA ');
    }

    /**
     * Les pièces justificatives qui précèdent le tableau des dépenses, dans
     * l'ordre du dossier d'apurement.
     *
     * Une ligne dont il manque une référence est omise plutôt qu'imprimée
     * « Décision n°  du  » : le modal pré-rempli reste éditable, le régisseur
     * ajoute à la main ce que la fiche ne porte pas.
     *
     * @param \Illuminate\Support\Collection<int, DecaissementRegie> $decaissements
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function lignesApurementPour($decaissements): \Illuminate\Support\Collection
    {
        $decaissements = collect($decaissements)->filter();
        $sigle         = ParametresStructure::where('actif', true)->value('sigle');
        $sigle         = $sigle ?: '';
        $regie         = $this->intitule_regie;
        $annee         = $this->exercice?->annee ?: (string) now()->year;
        $tranches      = static::enteteTranches($decaissements);

        // « 1ERE ENCAISSE 2026 ET LA 2EME ENCAISSE » se lit « la 1ere encaisse
        // 2026 et la 2eme encaisse » dans une phrase courant.
        $desEncaisses = $tranches === ''
            ? "l'encaisse"
            : 'la ' . mb_strtolower($tranches);

        $lignes = collect();

        // 1 — Résolution de renouvellement
        if ($this->resolution_numero) {
            $lignes->push(sprintf(
                "Résolution n°%s%s autorisant le renouvellement de la %s %spour l'exercice budgétaire %s.",
                $this->resolution_numero,
                $this->resolution_date ? ' du ' . $this->resolution_date->format('d/m/Y') : '',
                $regie,
                $sigle ? "du {$sigle} " : '',
                $annee
            ));
        }

        // 2 — Décision portant ouverture
        if ($this->decision_ouverture_numero) {
            $lignes->push(sprintf(
                "Décision n°%s%s portant ouverture de la %s%s.",
                $this->decision_ouverture_numero,
                $this->decision_ouverture_date ? ' du ' . $this->decision_ouverture_date->format('d/m/Y') : '',
                $regie,
                $sigle ? " au {$sigle}" : ''
            ));
        }

        // 3 — Décision portant désignation des responsables
        if ($this->decision_designation_numero) {
            $lignes->push(sprintf(
                "Décision n°%s%s portant désignation des responsables chargés de la gestion de la %s%s pour le compte de l'exercice budgétaire %s.",
                $this->decision_designation_numero,
                $this->decision_designation_date ? ' du ' . $this->decision_designation_date->format('d/m/Y') : '',
                $regie,
                $sigle ? " au {$sigle}" : '',
                $annee
            ));
        }

        if ($decaissements->isEmpty()) {
            return $lignes->values();
        }

        // 4 — Demande d'autorisation de mandat
        if ($tranches !== '') {
            $lignes->push(sprintf(
                "Demande d'autorisation d'un mandat de décaissement de %s de la %s%s pour le compte de l'exercice budgétaire %s.",
                $desEncaisses,
                $regie,
                $sigle ? " au {$sigle}" : '',
                $annee
            ));
        }

        // 5 — Décision(s) autorisant le déblocage : la DA propre à la tranche, à
        // défaut celle qui dote la régie.
        $decisionsDeblocage = $decaissements
            ->map(fn($d) => $d->decisionAdministrative ?? $this->decisionAdministrative)
            ->filter()
            ->unique('id')
            ->values();

        foreach ($decisionsDeblocage as $da) {
            $lignes->push(sprintf(
                "Décision n°%s%s, autorisant le déblocage de la somme TTC de : %s FCFA représentant %s de la %s pour le compte de l'exercice budgétaire %s.",
                $da->numero,
                $da->date_decision ? ' du ' . $da->date_decision->format('d/m/Y') : '',
                number_format((float) $da->montant_brut, 0, ',', ' '),
                $desEncaisses,
                $regie,
                $annee
            ));
        }

        // 6 — Certificats d'engagement
        foreach ($decaissements->filter(fn($d) => $d->certificat_numero) as $d) {
            $da = $d->decisionAdministrative ?? $this->decisionAdministrative;

            $lignes->push(sprintf(
                "Certificat d'engagement n°%s%s%s.",
                $d->certificat_numero,
                $d->certificat_date ? ' du ' . $d->certificat_date->format('d/m/Y') : '',
                $da ? ", décision de déblocage n°{$da->numero}" . ($da->date_decision ? ' du ' . $da->date_decision->format('d/m/Y') : '') : ''
            ));
        }

        // 7 — Mandats de décaissement
        foreach ($decaissements->filter(fn($d) => $d->mandat_numero) as $d) {
            $lignes->push(sprintf(
                "Mandat de décaissement n°%s%s.",
                $d->mandat_numero,
                $d->mandat_date ? ' du ' . $d->mandat_date->format('d/m/Y') : ''
            ));
        }

        // 8 — Quitances de reversement : une seule mention quand les encaisses
        // apurés ont été reversés par la même quittance.
        $quitances = $decaissements
            ->filter(fn($d) => $d->quittance_numero)
            ->groupBy(fn($d) => $d->quittance_numero . '|' . ($d->quittance_date?->getTimestamp() ?? 0));

        foreach ($quitances as $groupe) {
            $q       = $groupe->first();
            $reste   = $groupe->count() > 1
                ? 'des encaisses apurées'
                : $desEncaisses;

            $lignes->push(sprintf(
                "Quittance de reversement du reste %s%s n°%s%s à l'Agence Comptable %s.",
                $groupe->count() > 1 ? '' : 'de ',
                $reste,
                $q->quittance_numero,
                $q->quittance_date ? ' du ' . $q->quittance_date->format('d/m/Y') : '',
                $sigle ? "du {$sigle}" : 'du Trésor'
            ));
        }

        // 9 — Tableau justificatif
        $lignes->push(sprintf(
            "Tableau justificatif des dépenses de %s de la %s pour l'exercice %s.",
            $desEncaisses,
            $regie,
            $annee
        ));

        return $lignes->values();
    }

    // =========================================================
    // SCOPES
    // =========================================================
    public function scopeActif($query)
    {
        return $query->where('statut', 'actif');
    }

    public function scopeRav($query)
    {
        return $query->where('type', 'rav');
    }

    public function scopeMenuDepense($query)
    {
        return $query->where('type', 'menu_depense');
    }

    public function scopePourResponsable($query, int $userId)
    {
        return $query->where('responsable_id', $userId);
    }

    /**
     * Toutes les régies pour la supervision, uniquement les siennes pour un
     * responsable de régie.
     */
    public function scopeVisiblePour($query, $user)
    {
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        return $user->voitToutesLesRegies()
            ? $query
            : $query->where('responsable_id', $user->id);
    }

    /**
     * Cette régie est-elle accessible à cet utilisateur ?
     */
    public function estGerePar($user): bool
    {
        return $user !== null
            && ($user->voitToutesLesRegies() || (int) $this->responsable_id === (int) $user->id);
    }

    // ── Accesseurs ───────────────────────────────────────────
    public function getLabelTypeAttribute(): string
    {
        return match ($this->type) {
            'rav'          => 'Régie d\'Avance',
            'menu_depense' => 'Menu Dépense',
            default        => $this->type,
        };
    }

    /**
     * Taux de consommation de l'encaisse : sur la somme DÉJÀ ENGAGÉE, la régie
     * étant consommée dès l'engagement du BCR et non à son paiement.
     */
    public function getTauxConsommationAttribute(): float
    {
        $base = (float) $this->montant_decaisse;
        if ($base <= 0) return 0.0;

        $engage = $this->isDirty('montant_engage')
            ? (float) $this->montant_engage
            : $this->montantEngageReel();

        return round(($engage / $base) * 100, 2);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['statut', 'montant_alloue', 'montant_engage', 'montant_disponible', 'date_cloture'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('workflow')
            ->setDescriptionForEvent(fn(string $event) => 'Régie Avance ' . ($this->numero ?? '') . ' — ' . $event)
            ->dontSubmitEmptyLogs();
    }
}
