<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'montant_disponible',
        'encaisse_annuelle',
        'objet',
        'statut',
        'date_creation',
        'date_cloture',
        'observations',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date_creation'      => 'date',
        'date_cloture'       => 'date',
        'montant_alloue'     => 'decimal:2',
        'montant_decaisse'   => 'decimal:2',
        'montant_depense'    => 'decimal:2',
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

            // ✅ montant_disponible = décaissé - dépensé
            // Ne pas recalculer montant_depense ici pour éviter
            // les boucles infinies avec DecaissementRegie
            $regie->montant_disponible = (float) $regie->montant_decaisse
                - (float) $regie->montant_depense;
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
     * ✅ CORRIGÉ — source : DecaissementRegie.montant_depense
     *
     * Architecture de la régie :
     *   RegieAvance
     *     └── DecaissementRegie (tranches : 01ERE ENCAISSE, 02EME ENCAISSE...)
     *           └── montant_depense = dépenses réelles de la tranche
     *
     * montant_depense (régie)  = Σ decaissements.montant_depense
     * montant_disponible (régie) = montant_decaisse - montant_depense
     *
     * Avant : sommait DepenseRegie (toujours vide → 0)
     * Après : somme DecaissementRegie.montant_depense (correct)
     */
    public function recalculerMontants(): void
    {
        $totalDepense = $this->decaissements()->sum('montant_depense');
        $totalIr      = $this->decaissements()->sum('montant_ir_collecte');

        $this->updateQuietly([
            'montant_depense'    => $totalDepense,
            'montant_disponible' => (float) $this->montant_decaisse - $totalDepense,
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

    // ── Scopes ───────────────────────────────────────────────
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
     * Taux calculé à la volée depuis les colonnes en base
     * (montant_depense mis à jour par recalculerMontants())
     */
    public function getTauxConsommationAttribute(): float
    {
        $base = (float) $this->montant_decaisse;
        if ($base <= 0) return 0.0;
        return round(((float) $this->montant_depense / $base) * 100, 2);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['statut', 'montant_alloue', 'montant_disponible', 'montant_consomme', 'date_cloture'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('workflow')
            ->setDescriptionForEvent(fn(string $event) => 'Régie Avance ' . ($this->numero ?? '') . ' — ' . $event)
            ->dontSubmitEmptyLogs();
    }
}