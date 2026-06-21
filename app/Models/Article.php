<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Article extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'articles';

    // ✅ Corrigé pour correspondre EXACTEMENT aux colonnes réelles de la table
    // + ajout unite_mesure_id / conditionnement_id
    protected $fillable = [
        'code',
        'designation',
        'description',
        'type',
        'unite_mesure',
        'unite_mesure_id',
        'categorie',
        'categorie_id',
        'conditionnement_id',
        'marque',
        'reference_fournisseur',
        'fournisseur_id',
        'prix_unitaire_moyen',
        'seuil_alerte',
        'actif',
    ];

    protected $casts = [
        'prix_unitaire_moyen' => 'decimal:2',
        'actif'               => 'boolean',
        'seuil_alerte'        => 'integer',
    ];

    // ====================================
    // RELATIONS
    // ====================================

    public function stock(): HasOne
    {
        return $this->hasOne(Stock::class);
    }

    public function fichesStock(): HasMany
    {
        return $this->hasMany(FicheStock::class);
    }

    public function lignesExpressionBesoins(): HasMany
    {
        return $this->hasMany(LigneExpressionBesoin::class);
    }

    public function lignesReception(): HasMany
    {
        return $this->hasMany(LigneReception::class);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function uniteMesure(): BelongsTo
    {
        return $this->belongsTo(UniteMesure::class);
    }

    public function conditionnement(): BelongsTo
    {
        return $this->belongsTo(Conditionnement::class);
    }

    public function categorieArticle(): BelongsTo
    {
        return $this->belongsTo(CategorieArticle::class, 'categorie_id');
    }

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class);
    }

    // ====================================
    // ACCESSEURS
    // ====================================

    public function getQuantiteDisponibleAttribute(): int
    {
        return $this->stock?->quantite_disponible ?? 0;
    }

    public function getEstDurableAttribute(): bool
    {
        return $this->type === 'durable';
    }

    public function getEstConsomptibleAttribute(): bool
    {
        return $this->type === 'consomptible';
    }

    // ✅ Détection pharmacie — insensible à la casse
    public function getEstPharmacieAttribute(): bool
    {
        return (bool) $this->categorieArticle?->est_pharmacie;
    }

    public function getStockCritiqueAttribute(): bool
    {
        return $this->quantite_disponible <= $this->seuil_alerte;
    }

    public function getValeurStockAttribute(): float
    {
        return round(
            ($this->stock?->quantite_disponible ?? 0) * (float)$this->prix_unitaire_moyen,
            2
        );
    }

    // ====================================
    // SCOPES
    // ====================================

    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }

    public function scopeDurables($query)
    {
        return $query->where('type', 'durable');
    }

    public function scopeConsomptibles($query)
    {
        return $query->where('type', 'consomptible');
    }

    public function scopePharmacie($query)
    {
        return $query->whereHas('categorieArticle', fn($q) => $q->where('est_pharmacie', true));
    }

    public function scopeEnAlerteStock($query)
    {
        return $query->whereHas('stock', function ($q) {
            $q->whereColumn('quantite_disponible', '<=', 'articles.seuil_alerte');
        });
    }

    public function scopeParCategorie($query, string $categorie)
    {
        return $query->where('categorie', $categorie);
    }

    // ====================================
    // MÉTHODES
    // ====================================

    public function peutSortir(int $quantite): bool
    {
        return $this->quantite_disponible >= $quantite;
    }

    public function mettreAJourPrixMoyen(int $quantiteEntree, float $prixEntree): void
    {
        $stockActuel   = $this->stock?->quantite_disponible ?? 0;
        $valeurActuelle = $stockActuel * (float)$this->prix_unitaire_moyen;
        $valeurEntree   = $quantiteEntree * $prixEntree;
        $totalQuantite  = $stockActuel + $quantiteEntree;

        if ($totalQuantite > 0) {
            $this->prix_unitaire_moyen = round(
                ($valeurActuelle + $valeurEntree) / $totalQuantite,
                2
            );
            $this->saveQuietly();
        }
    }

    // ====================================
    // BOOT
    // ====================================

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($article) {
            if (!$article->code) {
                $article->code = static::genererCode();
            }
        });

        static::created(function ($article) {
            Stock::create([
                'article_id'          => $article->id,
                'quantite_disponible' => 0,
                'quantite_reservee'   => 0,
                'quantite_commandee'  => 0,
                'exercice_id'         => \App\Models\Exercice::getActif()?->id,
            ]);
        });
    }

    public static function genererCode(): string
    {
        return \DB::transaction(function () {
            $dernier = static::withTrashed()
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();

            $sequence = $dernier
                ? intval(substr($dernier->code, -4)) + 1
                : 1;

            return 'ART-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
        });
    }
}
