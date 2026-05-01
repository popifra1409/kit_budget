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
        'statut',
        'date_creation',
        'date_cloture',
        'observations',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date_creation'     => 'date',
        'date_cloture'      => 'date',
        'montant_alloue'    => 'decimal:2',
        'montant_decaisse'  => 'decimal:2',
        'montant_depense'   => 'decimal:2',
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
            $regie->created_by = auth()->id();
            $regie->montant_disponible = $regie->montant_alloue;
        });

        static::updating(function ($regie) {
            $regie->updated_by = auth()->id();
            // Recalculer disponible à chaque mise à jour
            $regie->montant_disponible = $regie->montant_alloue - $regie->montant_depense;
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

            $sequence = ($result->max_seq ?? 0) + 1;
            return sprintf('%s-%05d', $prefix, $sequence);
        });
    }

    // =========================================================
    // MÉTHODES MÉTIER
    // =========================================================

    /**
     * Seuil achat direct depuis les paramètres
     */
    public static function seuilAchatDirect(): float
    {
        return (float) (
            ParametresStructure::where('actif', true)->value('seuil_achat_direct_regie')
            ?? 500000
        );
    }

    /**
     * Recalculer montant_depense et montant_disponible depuis les dépenses
     */
    public function recalculerMontants(): void
    {
        $totalDepense = $this->depenses()
            ->whereNotIn('statut', ['annule'])
            ->sum('montant_ttc');

        $this->updateQuietly([
            'montant_depense'    => $totalDepense,
            'montant_disponible' => $this->montant_alloue - $totalDepense,
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

    public function getTauxConsommationAttribute(): float
    {
        if ($this->montant_alloue <= 0) return 0;
        return round(($this->montant_depense / $this->montant_alloue) * 100, 2);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['numero', 'libelle', 'statut', 'montant_alloue', 'montant_disponible'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
