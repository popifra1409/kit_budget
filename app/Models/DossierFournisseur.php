<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Traits\HasExercice;
use App\Models\Engagement;
use App\Models\BonCommande;

class DossierFournisseur extends Model
{
    use HasFactory, SoftDeletes, HasExercice;

    protected $table = 'dossiers_fournisseurs';

    protected $fillable = [
        'numero_dossier',
        'fournisseur_id',
        'exercice_id',
        'type_dossier',
        'document_principal_type',
        'document_principal_id',
        'reference_principale',
        'objet',
        'description',
        'statut',
        'montant_total',
        'montant_engage',
        'montant_facture',
        'montant_paye',
        'date_ouverture',
        'date_cloture',
        'date_limite_livraison',
        'responsable_id',
        'createur_id',
        'observations',
        'motif_cloture',
        'metadata',
    ];

    protected $casts = [
        'date_ouverture' => 'date',
        'date_cloture' => 'date',
        'date_limite_livraison' => 'date',
        'montant_total' => 'decimal:2',
        'montant_engage' => 'decimal:2',
        'montant_facture' => 'decimal:2',
        'montant_paye' => 'decimal:2',
        'metadata' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class);
    }

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'createur_id');
    }

    public function documentPrincipal(): MorphTo
    {
        return $this->morphTo();
    }

    public function pieces(): HasMany
    {
        return $this->hasMany(PieceDossier::class, 'dossier_id');
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopeOuvert($query)
    {
        return $query->where('statut', 'ouvert');
    }

    public function scopeEnCours($query)
    {
        return $query->where('statut', 'en_cours');
    }

    public function scopeCloture($query)
    {
        return $query->where('statut', 'cloture');
    }

    public function scopePourFournisseur($query, int $fournisseurId)
    {
        return $query->where('fournisseur_id', $fournisseurId);
    }

    public function scopeEnRetard($query)
    {
        return $query->whereNotNull('date_limite_livraison')
            ->where('date_limite_livraison', '<', now())
            ->whereNotIn('statut', ['cloture', 'annule']);
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSEURS
    |--------------------------------------------------------------------------
    */

    public function getMontantResteAttribute(): float
    {
        return $this->montant_total - $this->montant_paye;
    }

    public function getTauxRealisationAttribute(): float
    {
        if ($this->montant_total == 0) {
            return 0;
        }

        return ($this->montant_paye / $this->montant_total) * 100;
    }

    public function getEstEnRetardAttribute(): bool
    {
        if (!$this->date_limite_livraison || in_array($this->statut, ['cloture', 'annule'])) {
            return false;
        }

        return $this->date_limite_livraison->isPast();
    }

    public function getStatutLabelAttribute(): string
    {
        return match ($this->statut) {
            'ouvert' => 'Ouvert',
            'en_cours' => 'En cours',
            'attente_pieces' => 'Attente pièces',
            'attente_validation' => 'Attente validation',
            'attente_paiement' => 'Attente paiement',
            'cloture' => 'Clôturé',
            'annule' => 'Annulé',
            default => $this->statut,
        };
    }

    public function getStatutColorAttribute(): string
    {
        return match ($this->statut) {
            'ouvert' => 'info',
            'en_cours' => 'warning',
            'attente_pieces' => 'warning',
            'attente_validation' => 'warning',
            'attente_paiement' => 'danger',
            'cloture' => 'success',
            'annule' => 'gray',
            default => 'secondary',
        };
    }

    public function getTypeDossierLabelAttribute(): string
    {
        return match ($this->type_dossier) {
            'bon_commande' => 'Bon de Commande',
            'marche' => 'Marché',
            'decision_administrative' => 'Décision Administrative',
            'prestation' => 'Prestation',
            'autre' => 'Autre',
            default => $this->type_dossier,
        };
    }

    /**
     * Obtenir l'engagement principal du dossier
     */
    public function getEngagementPrincipal(): ?Engagement
    {
        // Si le document principal est un BC
        if ($this->document_principal_type === BonCommande::class) {
            $bc = BonCommande::find($this->document_principal_id);
            return $bc?->engagement;
        }

        // Si le document principal est un engagement
        if ($this->document_principal_type === Engagement::class) {
            return Engagement::find($this->document_principal_id);
        }

        return null;
    }

    /**
     * Vérifier si le dossier a des ordonnances de paiement
     */
    public function hasOrdonnancesPaiement(): bool
    {
        $engagement = $this->getEngagementPrincipal();

        if (!$engagement) {
            return false;
        }

        return \App\Models\OrdonnancePaiement::where('engagement_id', $engagement->id)->exists();
    }

    /**
     * Créer les ordonnances de paiement depuis le dossier
     */
    public function creerOrdonnancesPaiement(): array
    {
        $engagement = $this->getEngagementPrincipal();

        if (!$engagement) {
            throw new \Exception("Aucun engagement trouvé pour ce dossier");
        }

        if ($engagement->hasOrdonnancesPaiement()) {
            throw new \Exception("Les ordonnances de paiement existent déjà pour cet engagement");
        }

        return $engagement->creerOrdonnancesPaiement();
    }

    /*
    |--------------------------------------------------------------------------
    | MÉTHODES MÉTIER
    |--------------------------------------------------------------------------
    */

    /**
     * Générer un numéro de dossier unique
     */
    public static function genererNumeroDossier(string $typeDossier): string
    {
        $year = now()->year;
        $prefix = match ($typeDossier) {
            'bon_commande' => 'DF-BC',
            'marche' => 'DF-MR',
            'decision_administrative' => 'DF-DA',
            'prestation' => 'DF-PS',
            default => 'DF',
        };

        $lastDossier = static::where('numero_dossier', 'like', "{$prefix}-{$year}-%")
            ->latest('id')
            ->first();

        $numero = $lastDossier
            ? ((int) substr($lastDossier->numero_dossier, -4)) + 1
            : 1;

        return sprintf('%s-%s-%04d', $prefix, $year, $numero);
    }

    /**
     * Clôturer le dossier
     */
    public function cloturer(string $motif = null): void
    {
        $this->statut = 'cloture';
        $this->date_cloture = now();
        $this->motif_cloture = $motif;
        $this->save();
    }

    /**
     * Annuler le dossier
     */
    public function annuler(string $motif): void
    {
        $this->statut = 'annule';
        $this->motif_cloture = $motif;
        $this->save();
    }

    /**
     * Ajouter une pièce au dossier
     */
    public function ajouterPiece(array $data): PieceDossier
    {
        return $this->pieces()->create(array_merge($data, [
            'ajoute_par' => auth()->id(),
            'date_ajout' => now(),
        ]));
    }

    /**
     * Vérifier si le dossier peut être clôturé
     */
    public function peutEtreCloture(): bool
    {
        // Doit avoir au moins un engagement
        $hasEngagement = $this->pieces()
            ->where('type_piece', 'engagement')
            ->exists();

        // Doit avoir une facture validée
        $hasFactureValidee = $this->pieces()
            ->where('type_piece', 'facture_definitive')
            ->where('valide', true)
            ->exists();

        return $hasEngagement && $hasFactureValidee;
    }

    /**
     * Calculer le nombre de jours depuis ouverture
     */
    public function getJoursDepuisOuvertureAttribute(): int
    {
        return $this->date_ouverture->diffInDays(
            $this->date_cloture ?? now()
        );
    }

    /**
     * Obtenir le pourcentage de pièces validées
     */
    public function getTauxValidationPiecesAttribute(): float
    {
        $total = $this->pieces()->count();

        if ($total === 0) {
            return 0;
        }

        $validees = $this->pieces()->where('valide', true)->count();

        return ($validees / $total) * 100;
    }
}
