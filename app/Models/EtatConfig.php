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
     * Obtenir les champs variables avec valeurs par défaut
     */
    public function getChampsVariablesAttribute($value)
    {
        $champs = json_decode($value, true) ?? [];

        foreach ($champs as $nom => &$config) {
            $config['type'] = $config['type'] ?? 'text';
            $config['required'] = $config['required'] ?? false;
        }

        return $champs;
    }

    /**
     * Obtenir la configuration d'en-tête avec valeurs par défaut
     */
    public function getEnteteConfigAttribute($value)
    {
        return array_merge([
            'afficher_logo' => true,
            'logo' => 'images/logo-ministere.png',
            'institution' => 'MINISTERE DE LA SANTE PUBLIQUE',
            'etablissement' => 'HOPITAL GENERAL DE YAOUNDE',
            'adresse' => "B.P. 5408 – Yaoundé\nTél.: (237) 221.31.81 - 221.20.18",
        ], json_decode($value, true) ?? []);
    }

    /**
     * Obtenir les options PDF avec valeurs par défaut
     */
    public function getOptionsPdfAttribute($value)
    {
        return array_merge([
            'orientation' => 'portrait',
            'page-size' => 'A4',
        ], json_decode($value, true) ?? []);
    }

    /**
     * Obtenir la configuration des signatures
     */
    public function getSignatureConfigAttribute($value)
    {
        $default = [
            'afficher' => true,
            'signatures' => [
                [
                    'titre' => 'L\'ORDONNATEUR',
                    'position' => 'left',
                    'largeur' => 33,
                ],
                [
                    'titre' => 'LE CONTRÔLEUR FINANCIER',
                    'position' => 'center',
                    'largeur' => 33,
                ],
                [
                    'titre' => 'L\'AGENT COMPTABLE',
                    'position' => 'right',
                    'largeur' => 33,
                ],
            ],
        ];

        return array_merge($default, json_decode($value, true) ?? []);
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
}
