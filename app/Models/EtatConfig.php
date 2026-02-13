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
    ];

    protected $casts = [
        'champs_variables' => 'array',
        'calculs' => 'array',
        'entete_config' => 'array',
        'pied_page_config' => 'array',
        'signature_config' => 'array',
        'options_pdf' => 'array',
        'actif' => 'boolean',
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
     * Vérifier si l'état est supprimable
     */
    public function estSupprimable(): bool
    {
        // Ne pas supprimer les états système
        $etatsSysteme = ['certificat_engagement', 'autorisation_engagement', 'bon_commande', 'bon_commande_simple', 'bordereau_engagement'];
        return !in_array($this->code, $etatsSysteme);
    }
}
