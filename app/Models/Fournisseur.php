<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fournisseur extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'fournisseurs';

    protected $fillable = [
        'code',
        'raison_sociale',
        'sigle',
        'rccm',
        'nif',
        'forme_juridique',
        'adresse',
        'ville',
        'pays',
        'telephone',
        'email',
        'site_web',
        'contact_nom',
        'contact_fonction',
        'contact_telephone',
        'contact_email',
        'banque',
        'iban',
        'code_swift',
        'numero_compte',
        'type',
        'actif',
        'blackliste',
        'motif_blacklist',
        'observations',
    ];

    protected $casts = [
        'actif' => 'boolean',
        'blackliste' => 'boolean',
    ];

    /**
     * Relation : Bons de commande de ce fournisseur
     */
    public function bonsCommande(): HasMany
    {
        return $this->hasMany(BonCommande::class, 'fournisseur_id');
    }

    /**
     * Scope : Fournisseurs actifs
     */
    public function scopeActifs($query)
    {
        return $query->where('actif', true)
            ->where('blackliste', false);
    }

    /**
     * Scope : Par type
     */
    public function scopeType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope : Blacklistés
     */
    public function scopeBlacklistes($query)
    {
        return $query->where('blackliste', true);
    }

    /**
     * Obtenir le montant total des commandes
     */
    public function getMontantTotalCommandes(): float
    {
        return $this->bonsCommande()
            ->whereNotIn('statut', ['brouillon', 'annule'])
            ->sum('montant_ttc');
    }

    /**
     * Obtenir le nombre de commandes
     */
    public function getNombreCommandes(): int
    {
        return $this->bonsCommande()
            ->whereNotIn('statut', ['brouillon', 'annule'])
            ->count();
    }

    /**
     * Obtenir le nom complet (raison sociale ou sigle)
     */
    public function getNomComplet(): string
    {
        return $this->sigle ?: $this->raison_sociale;
    }

    /**
     * Blacklister le fournisseur
     */
    public function blacklister(string $motif): void
    {
        $this->blackliste = true;
        $this->motif_blacklist = $motif;
        $this->actif = false;
        $this->save();
    }

    /**
     * Réhabiliter le fournisseur
     */
    public function rehabiliter(): void
    {
        $this->blackliste = false;
        $this->motif_blacklist = null;
        $this->actif = true;
        $this->save();
    }
}
