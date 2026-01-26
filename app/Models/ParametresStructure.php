<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class ParametresStructure extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'parametres_structure';

    protected $fillable = [
        'nom_structure',
        'sigle',
        'logo',
        'adresse',
        'ville',
        'pays',
        'telephone',
        'fax',
        'email',
        'site_web',
        'boite_postale',
        'ministere_tutelle',
        'numero_contribuable',
        'rccm',
        'devise_gauche',
        'devise_droite',
        'pays_gauche',
        'pays_droite',
        'direction_generale',
        'direction_generale_en',
        'sous_direction',
        'sous_direction_en',
        'nom_ordonnateur',
        'fonction_ordonnateur',
        'nom_comptable',
        'fonction_comptable',
        'taux_tva_defaut',
        'monnaie',
        'actif',
        'exercice_courant',
        'ir_tranche1_max',
        'ir_tranche1_taux',
        'ir_tranche2_min',
        'ir_tranche2_max',
        'ir_tranche2_taux',
        'ir_tranche3_min',
        'ir_tranche3_taux',
    ];

    protected $casts = [
        'taux_tva_defaut' => 'decimal:2',
        'actif' => 'boolean',
        'exercice_courant' => 'integer',
    ];

    /**
     * Récupérer les paramètres actifs (singleton)
     */
    public static function getParametres(): ?self
    {
        return static::where('actif', true)->first();
    }

    /**
     * URL complète du logo
     */
    public function getLogoUrlAttribute(): ?string
    {
        if ($this->logo && Storage::disk('public')->exists($this->logo)) {
            return Storage::disk('public')->url($this->logo);
        }
        return null;
    }

    /**
     * Nom complet (avec sigle)
     */
    public function getNomCompletAttribute(): string
    {
        return $this->sigle ? "{$this->nom_structure} ({$this->sigle})" : $this->nom_structure;
    }

    /**
     * Coordonnées complètes
     */
    public function getCoordonneesAttribute(): string
    {
        $coords = [];
        if ($this->adresse) $coords[] = $this->adresse;
        if ($this->ville) $coords[] = $this->ville;
        if ($this->pays) $coords[] = $this->pays;
        if ($this->boite_postale) $coords[] = "BP: {$this->boite_postale}";
        if ($this->telephone) $coords[] = "Tél: {$this->telephone}";
        if ($this->email) $coords[] = "Email: {$this->email}";
        return implode(' | ', $coords);
    }

    /**
     * Exercice courant ou année en cours
     */
    public function getExerciceAttribute(): int
    {
        return $this->exercice_courant ?? now()->year;
    }

    protected static function boot()
    {
        parent::boot();

        // Un seul paramétrage actif à la fois
        static::creating(function ($parametres) {
            if ($parametres->actif) {
                static::where('actif', true)->update(['actif' => false]);
            }
        });

        static::updating(function ($parametres) {
            if ($parametres->actif && $parametres->isDirty('actif')) {
                static::where('id', '!=', $parametres->id)
                    ->where('actif', true)
                    ->update(['actif' => false]);
            }
        });
    }
}
