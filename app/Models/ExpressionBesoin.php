<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpressionBesoin extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'expressions_besoins';

    protected $fillable = [
        'numero',
        'exercice_id',
        'service_demandeur',
        'responsable_service_id',
        'comptable_matieres_id',
        'ordonnateur_id',
        'date_expression',
        'date_validation',
        'date_besoin',
        'objet',
        'observations',
        'motif_rejet',
        'statut',
        'bon_commande_id',
        'created_by',
    ];

    protected $casts = [
        'date_expression' => 'date',
        'date_validation' => 'date',
        'date_besoin'     => 'date',
    ];

    // ====================================
    // RELATIONS
    // ====================================

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function responsableService(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_service_id');
    }

    public function comptableMatieres(): BelongsTo
    {
        return $this->belongsTo(User::class, 'comptable_matieres_id');
    }

    public function ordonnateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordonnateur_id');
    }

    public function bonCommande(): BelongsTo
    {
        return $this->belongsTo(BonCommande::class, 'bon_commande_id');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneExpressionBesoin::class, 'expression_besoin_id');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ====================================
    // ACCESSEURS
    // ====================================

    public function getMontantTotalEstimeAttribute(): float
    {
        return (float) $this->lignes->sum('montant_estime');
    }

    public function getNombreLignesAttribute(): int
    {
        return $this->lignes->count();
    }

    public function getNombreArticlesACommanderAttribute(): int
    {
        return $this->lignes->where('quantite_a_commander', '>', 0)->count();
    }

    // ====================================
    // STATUTS
    // ====================================

    public function estModifiable(): bool
    {
        return $this->statut === 'brouillon';
    }

    public function peutEtreValide(): bool
    {
        return $this->statut === 'soumis' && $this->lignes->isNotEmpty();
    }

    public function peutGenererBC(): bool
    {
        return $this->statut === 'valide'
            && !$this->bon_commande_id
            && $this->lignes->where('quantite_a_commander', '>', 0)->isNotEmpty();
    }

    // ====================================
    // SCOPES
    // ====================================

    public function scopeParStatut($query, string $statut)
    {
        return $query->where('statut', $statut);
    }

    public function scopeParExercice($query, int $exerciceId)
    {
        return $query->where('exercice_id', $exerciceId);
    }

    // ====================================
    // BOOT
    // ====================================

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($eb) {
            if (!$eb->numero) {
                $eb->numero = static::genererNumero();
            }
            if (!$eb->created_by) {
                $eb->created_by = auth()->id();
            }
            if (!$eb->date_expression) {
                $eb->date_expression = now()->toDateString();
            }
        });
    }

    public static function genererNumero(): string
    {
        return \DB::transaction(function () {
            $annee   = now()->year;
            $prefixe = "EB-{$annee}-";
            $dernier = static::withTrashed()
                ->where('numero', 'like', "{$prefixe}%")
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();
            $seq = $dernier ? intval(substr($dernier->numero, -4)) + 1 : 1;
            return $prefixe . str_pad($seq, 4, '0', STR_PAD_LEFT);
        });
    }
}
