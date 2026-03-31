<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EtatConfig extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'nom',
        'template',
        'description',
        'champs_variables',
        'calculs',
        'entete_config',
        'pied_page_config',
        'signature_config',
        'options_pdf',
        'format_papier',
        'orientation',
        'actif',
        'ordre',
        'categorie',
        'type_document',
        'est_defaut',
    ];

    protected $casts = [
        'champs_variables' => 'array',
        'calculs' => 'array',
        'entete_config' => 'array',
        'pied_page_config' => 'array',
        'signature_config' => 'array',
        'options_pdf' => 'array',
        'actif' => 'boolean',
        'est_defaut' => 'boolean',
    ];

    /**
     * Scope pour les états actifs
     */
    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }

    /**
     * Scope par catégorie
     */
    public function scopeCategorie($query, string $categorie)
    {
        return $query->where('categorie', $categorie);
    }

    /**
     * Vérifier si un champ existe
     */
    public function hasChamp(string $nom): bool
    {
        return isset($this->champs_variables[$nom]);
    }

    /**
     * Obtenir la configuration d'un champ
     */
    public function getChampConfig(string $nom): ?array
    {
        return $this->champs_variables[$nom] ?? null;
    }

    /**
     * Vérifier si un calcul existe
     */
    public function hasCalcul(string $nom): bool
    {
        return isset($this->calculs[$nom]);
    }

    /**
     * Obtenir les états par catégorie groupés
     */
    public static function groupesParCategorie(): array
    {
        return static::actif()
            ->orderBy('ordre')
            ->get()
            ->groupBy('categorie')
            ->toArray();
    }

    /**
     * Vérifier si l'état est modifiable
     */
    public function estModifiable(): bool
    {
        // Logique métier : certains états système ne peuvent pas être modifiés
        $etatsSysteme = ['certificat_engagement', 'autorisation_engagement'];
        return !in_array($this->code, $etatsSysteme);
    }

    /**
     * Récupérer toutes les variantes d'un type de document
     */
    public static function variantesPour(string $typeDocument): array
    {
        return static::where('type_document', $typeDocument)
            ->where('actif', true)
            ->orderBy('est_defaut', 'desc')
            ->orderBy('ordre')
            ->get()
            // ✅ mapWithKeys garantit string clé => string valeur
            ->mapWithKeys(fn($e) => [
                (string) $e->code => (string) ($e->nom . ($e->est_defaut ? ' ⭐' : ''))
            ])
            ->toArray();
    }

    /**
     * Récupérer la variante par défaut d'un type de document
     */
    public static function defautPour(string $typeDocument): ?self
    {
        return static::actif()
            ->where('type_document', $typeDocument)
            ->where('est_defaut', true)
            ->first()
            ?? static::actif()->where('type_document', $typeDocument)->first();
    }

    /**
     * Scope variantes d'un type
     */
    public function scopeDeType($query, string $typeDocument)
    {
        return $query->where('type_document', $typeDocument);
    }

    /**
     * Liste pour Select Filament — options d'un type
     */
    public static function optionsPour(string $typeDocument): array
    {
        return static::variantesPour($typeDocument)
            ->mapWithKeys(fn($e) => [
                $e->code => $e->nom . ($e->est_defaut ? ' ⭐' : '')
            ])
            ->toArray();
    }

    // ============================================================
// 2. MODIFIER app/Models/EtatConfig.php
//    Ajouter dans estSupprimable() les nouveaux codes système
// ============================================================
    public function estSupprimable(): bool
    {
        $etatsSysteme = [
            'certificat_engagement',
            'autorisation_engagement',
            'bon_commande',
            'bordereau_engagement',
            'decision_administrative',
            'ordonnance_paiement',
            'ordonnance_paiement_impot',
            'memoire_depense',
        ];
        return !in_array($this->code, $etatsSysteme);
    }
}
