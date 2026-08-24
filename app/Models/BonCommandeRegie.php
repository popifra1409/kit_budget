<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BonCommandeRegie extends Model
{
    use SoftDeletes;

    protected $table = 'bons_commande_regies';

    protected $fillable = [
        'regie_avance_id',
        'depense_regie_id',
        'numero',
        'numero_facture_definitive',
        'date_facture_definitive',
        'date_emission',
        'objet',
        'fournisseur_id',
        'montant_ht',
        'montant_tva',
        'montant_ttc',
        'montant_ir',
        'net_a_payer',
        'exonere_tva',
        'exonere_ir',
        'statut',
        'observations',
        'created_by',
        'updated_by',
        'ligne_regie_avance_id',
        'provision_ligne_regie_id',
        'engage',
        'date_engagement',
        'montant_engage',
        'pourcentage_engage',
        'reste_a_engager',
    ];

    protected $casts = [
        'date_emission'             => 'date',
        'date_facture_definitive'   => 'date',
        'montant_ht'    => 'decimal:2',
        'montant_tva'   => 'decimal:2',
        'montant_ttc'   => 'decimal:2',
        'montant_ir'    => 'decimal:2',
        'net_a_payer'   => 'decimal:2',
        'engage'           => 'boolean',
        'date_engagement'  => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($bc) {
            if (!$bc->numero) {
                $bc->numero = static::genererNumero($bc->regieAvance);
            }
            $bc->created_by = auth()->id();
        });

        static::updating(function ($bc) {
            $bc->updated_by = auth()->id();
        });
    }

    // ── Relations ─────────────────────────────────────────────
    public function regieAvance(): BelongsTo
    {
        return $this->belongsTo(RegieAvance::class, 'regie_avance_id');
    }

    public function depenseRegie(): BelongsTo
    {
        return $this->belongsTo(DepenseRegie::class, 'depense_regie_id');
    }

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneBonCommandeRegie::class, 'bon_commande_regie_id');
    }

    // ── Numérotation ──────────────────────────────────────────
    public static function genererNumero(RegieAvance $regie): string
    {
        $annee  = substr($regie->exercice->annee ?? now()->year, -2);
        $prefix = match ($regie->type) {
            'rav'          => "BCR{$annee}",
            'menu_depense' => "BCM{$annee}",
            default        => "BCR{$annee}",
        };

        $result = \DB::selectOne("
            SELECT COALESCE(MAX(CAST(SPLIT_PART(numero, '-', 2) AS INTEGER)), 0) AS max_seq
            FROM bons_commande_regies
            WHERE numero LIKE :pattern
        ", ['pattern' => "{$prefix}-%"]);

        return sprintf('%s-%05d', $prefix, ($result->max_seq ?? 0) + 1);
    }

    // ── Recalcul totaux depuis lignes ─────────────────────────
    public function recalculerTotaux(): void
    {
        $this->updateQuietly([
            'montant_ht'  => $this->lignes()->sum('montant_ht'),
            'montant_tva' => $this->lignes()->sum('montant_tva'),
            'montant_ttc' => $this->lignes()->sum('montant_ttc'),
            'montant_ir'  => $this->lignes()->sum('montant_ir'),
            'net_a_payer' => $this->lignes()->sum('net_a_payer'),
        ]);
    }


    public function ligneRegieAvance(): BelongsTo
    {
        return $this->belongsTo(LigneRegieAvance::class, 'ligne_regie_avance_id');
    }

    public function provisionLigneRegie(): BelongsTo
    {
        return $this->belongsTo(ProvisionLigneRegie::class, 'provision_ligne_regie_id');
    }

    // ── Engagement (en cascade sur toutes les provisions de la ligne) ──
    public function engager(
        ?float  $montantPartiel = null,
        float   $pourcentage    = 100,
        ?string $commentaire    = null
    ): void {
        $montantAEngager = $montantPartiel ?? (float) $this->montant_ttc;
        $dejaEngage      = (float) ($this->montant_engage ?? 0);

        // ✅ Autorise l'engagement incrémental (solde progressif) au lieu
        //    de bloquer dès qu'une première tranche a été engagée.
        if ($dejaEngage + $montantAEngager > (float) $this->montant_ttc + 0.01) {
            throw new \Exception(
                "Le montant déjà engagé (" . number_format($dejaEngage, 0, ',', ' ') . " FCFA) "
                    . "+ ce montant (" . number_format($montantAEngager, 0, ',', ' ') . " FCFA) "
                    . "dépasserait le montant TTC du bon de commande ("
                    . number_format((float) $this->montant_ttc, 0, ',', ' ') . " FCFA)."
            );
        }

        if (!$this->ligne_regie_avance_id) {
            throw new \Exception("Aucune ligne de régie associée à ce BCR.");
        }

        // ── Cascade : toutes les provisions de cette ligne, tous décaissements
        //    "versés", triées par ordre chronologique (le plus ancien d'abord)
        $provisions = ProvisionLigneRegie::where('ligne_regie_avance_id', $this->ligne_regie_avance_id)
            ->whereHas('decaissement', fn($q) => $q->where('statut', 'verse'))
            ->with('decaissement')
            ->get()
            ->sortBy(fn($p) => [
                $p->decaissement?->date_decaissement?->format('Y-m-d') ?? '9999-99-99',
                $p->decaissement?->numero ?? '',
            ])
            ->values();

        // ✅ On ne compte que les disponibilités POSITIVES : un décaissement en
        //    négatif (dépassement historique) ne doit jamais réduire ce qui est
        //    réellement disponible sur un AUTRE décaissement de la même ligne.
        $totalDisponible = (float) $provisions->sum(fn($p) => max(0, (float) $p->montant_disponible));

        if ($montantAEngager > $totalDisponible) {
            throw new \Exception(
                "Provision insuffisante sur l'ensemble des décaissements de cette ligne. "
                    . "Disponible cumulé : " . number_format($totalDisponible, 0, ',', ' ') . " FCFA — "
                    . "Demandé : " . number_format($montantAEngager, 0, ',', ' ') . " FCFA"
            );
        }

        $decaissementsImpactes = collect();

        \DB::transaction(function () use ($montantAEngager, $pourcentage, $commentaire, $provisions, $dejaEngage, &$decaissementsImpactes) {
            $restant = $montantAEngager;
            $premiereProvisionUtilisee = null;

            foreach ($provisions as $provision) {
                if ($restant <= 0.001) {
                    break;
                }

                $pris = min($restant, (float) $provision->montant_disponible);

                if ($pris <= 0) {
                    continue;
                }

                // ✅ debiter() met à jour montant_consomme ET montant_disponible correctement
                $provision->debiter($pris);

                \App\Models\ProvisionConsommation::create([
                    'provision_ligne_regie_id' => $provision->id,
                    'bon_commande_regie_id'    => $this->id,
                    'montant'                  => $pris,
                ]);

                $decaissementsImpactes->push($provision->decaissement_regie_id);

                $premiereProvisionUtilisee ??= $provision->id;
                $restant -= $pris;
            }

            $nouveauMontantEngage = $dejaEngage + $montantAEngager;
            $montantTtc           = (float) $this->montant_ttc;

            $this->updateQuietly([
                'provision_ligne_regie_id' => $this->provision_ligne_regie_id ?? $premiereProvisionUtilisee,
                'engage'                   => true,
                'montant_engage'           => $nouveauMontantEngage,
                'pourcentage_engage'       => $montantTtc > 0
                    ? round(($nouveauMontantEngage / $montantTtc) * 100, 2)
                    : 100,
                'reste_a_engager'          => max(0, $montantTtc - $nouveauMontantEngage),
                'date_engagement'          => $this->date_engagement ?? now(),
                'observations'             => ($this->observations ?? '')
                    . ($commentaire
                        ? "\n[Engagement {$pourcentage}% — " . now()->format('d/m/Y') . "] " . $commentaire
                        : ''
                    ),
            ]);
        });

        // ✅ CORRECTIF STRUCTUREL — resynchronise systématiquement chaque
        //    décaissement impacté depuis provision_consommations (source de
        //    vérité), au lieu de faire confiance aux seuls incréments de
        //    debiter(). C'est ce qui empêche montant_consomme de dériver
        //    silencieusement après des engagements partiels répétés.
        foreach ($decaissementsImpactes->unique() as $decaissementId) {
            $decaissement = \App\Models\DecaissementRegie::find($decaissementId);
            if ($decaissement) {
                $decaissement->load('provisions');
                $decaissement->recalculerDepenses();
            }
        }

        \App\Models\ActivityLog::logAction($this, 'engager', [
            'ancien_statut'  => $dejaEngage > 0 ? 'partiellement_engage' : 'non_engage',
            'nouveau_statut' => 'engage',
            'montant'        => $montantAEngager,
            'pourcentage'    => $pourcentage,
        ]);
    }

    public function desengager(): void
    {
        if (!$this->engage) {
            throw new \Exception("Ce BCR n'est pas engagé.");
        }

        $decaissementsImpactes = collect();

        \DB::transaction(function () use (&$decaissementsImpactes) {
            // ✅ Crédite chaque provision exactement du montant qui lui avait
            //    été pris (peut concerner plusieurs décaissements à la fois)
            $consommations = \App\Models\ProvisionConsommation::where('bon_commande_regie_id', $this->id)
                ->with('provisionLigneRegie')
                ->get();

            foreach ($consommations as $consommation) {
                $consommation->provisionLigneRegie?->crediter((float) $consommation->montant);
                $decaissementsImpactes->push($consommation->provisionLigneRegie?->decaissement_regie_id);
                $consommation->delete();
            }

            $montantLibere = $this->montant_engage;

            $this->updateQuietly([
                'engage'             => false,
                'montant_engage'     => 0,
                'pourcentage_engage' => 0,
                'reste_a_engager'    => 0,
                'date_engagement'    => null,
            ]);

            \App\Models\ActivityLog::logAction($this, 'desengager', [
                'ancien_statut'  => 'engage',
                'nouveau_statut' => 'non_engage',
                'montant_libere' => $montantLibere,
            ]);
        });

        // ✅ Même correctif structurel qu'à l'engagement : resynchronise
        //    depuis la source de vérité plutôt que de faire confiance aux
        //    seuls incréments de crediter().
        foreach ($decaissementsImpactes->filter()->unique() as $decaissementId) {
            $decaissement = \App\Models\DecaissementRegie::find($decaissementId);
            if ($decaissement) {
                $decaissement->load('provisions');
                $decaissement->recalculerDepenses();
            }
        }
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['statut', 'engage', 'montant_ttc', 'montant_engage', 'date_engagement'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('workflow')
            ->setDescriptionForEvent(fn(string $event) => 'Doc ' . ($this->numero ?? '') . ' — ' . $event);
    }
}
