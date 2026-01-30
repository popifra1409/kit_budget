<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Traits\HasExercice;

class OrdonnancePaiement extends Model
{
    use HasFactory, SoftDeletes, HasExercice;

    protected $table = 'ordonnances_paiement';

     protected $fillable = [
        'numero',
        'exercice_id',
        'type_ordonnance',
        'engagement_id',
        'beneficiaire_type',
        'beneficiaire_id',
        'objet',
        'montant_brut',
        'montant_impot',
        'montant_net',
        'montant_pec',
        'date_emission',
        'mois_emission',
        'numero_bon',
        'numero_emission',
        'numero_op',
        'periode',
        'statut',
        'date_paiement',
        'reference_paiement',
        'observations',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'date_emission' => 'date',
        'date_paiement' => 'date',
        'montant_brut' => 'decimal:2',
        'montant_impot' => 'decimal:2',
        'montant_net' => 'decimal:2',
        'montant_pec' => 'decimal:2',
        'metadata' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class);
    }

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function beneficiaire(): MorphTo
    {
        return $this->morphTo();
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopeBrouillon($query)
    {
        return $query->where('statut', 'brouillon');
    }

    public function scopeEmise($query)
    {
        return $query->where('statut', 'emise');
    }

    public function scopePayee($query)
    {
        return $query->where('statut', 'payee');
    }

    public function scopeStandard($query)
    {
        return $query->where('type_ordonnance', 'standard');
    }

    public function scopeImpot($query)
    {
        return $query->where('type_ordonnance', 'impot');
    }

    /*
    |--------------------------------------------------------------------------
    | MÉTHODES
    |--------------------------------------------------------------------------
    */

    /**
     * Générer un numéro d'OP basé sur le numéro d'engagement
     */
    public static function genererNumeroFromEngagement(Engagement $engagement, string $type = 'standard'): string
    {
        // Extraire les parties du numéro d'engagement (ex: BE-2026-00004)
        $parts = explode('-', $engagement->numero);

        if (count($parts) >= 3) {
            $annee = $parts[1];
            $numero = $parts[2];

            $prefix = $type === 'impot' ? 'OPT' : 'OP';

            return "{$prefix}-{$annee}-{$numero}";
        }

        // Fallback si le format est différent
        return static::genererNumero($type);
    }

    /**
     * Générer un numéro d'OP classique (fallback)
     */
    public static function genererNumero(string $type = 'standard'): string
    {
        $year = now()->year;
        $prefix = $type === 'impot' ? 'OPT' : 'OP';

        $lastOp = static::where('numero', 'like', "{$prefix}-{$year}-%")
            ->latest('id')
            ->first();

        $numero = $lastOp
            ? ((int) substr($lastOp->numero, -5)) + 1
            : 1;

        return sprintf('%s-%s-%05d', $prefix, $year, $numero);
    }

    /**
     * Émettre l'ordonnance
     */
    public function emettre(): void
    {
        $this->statut = 'emise';
        $this->save();
    }

    /**
     * Marquer comme payée
     */
    public function marquerPayee(string $referencePaiement = null): void
    {
        $this->statut = 'payee';
        $this->date_paiement = now();
        $this->reference_paiement = $referencePaiement;
        $this->save();
    }

    /**
     * Annuler l'ordonnance
     */
    public function annuler(): void
    {
        $this->statut = 'annulee';
        $this->save();
    }

    /**
     * Obtenir le label du statut
     */
    public function getStatutLabelAttribute(): string
    {
        return match ($this->statut) {
            'brouillon' => 'Brouillon',
            'emise' => 'Émise',
            'visee' => 'Visée',
            'payee' => 'Payée',
            'annulee' => 'Annulée',
            default => $this->statut,
        };
    }

    /**
     * Obtenir la couleur du statut
     */
    public function getStatutColorAttribute(): string
    {
        return match ($this->statut) {
            'brouillon' => 'gray',
            'emise' => 'info',
            'visee' => 'warning',
            'payee' => 'success',
            'annulee' => 'danger',
            default => 'secondary',
        };
    }
}
