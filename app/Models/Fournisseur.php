<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'regime_fiscal_id',
    ];

    protected $casts = [
        'actif'      => 'boolean',
        'blackliste' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function ($fournisseur) {
            if (empty($fournisseur->code)) {
                $fournisseur->code = static::genererCode();
            }
        });
    }

    // ════════════════════════════════════════════════════════
    // RELATIONS
    // ════════════════════════════════════════════════════════

    /**
     * Bons de commande de ce fournisseur
     */
    public function bonsCommande(): HasMany
    {
        return $this->hasMany(BonCommande::class, 'fournisseur_id');
    }

    /**
     * Régime fiscal
     */
    public function regimeFiscal(): BelongsTo
    {
        return $this->belongsTo(RegimeFiscal::class);
    }

    /**
     * ✅ Dossiers fournisseur — créés automatiquement à l'engagement BC/DA
     * Triés du plus récent au plus ancien
     */
    public function dossiers(): HasMany
    {
        return $this->hasMany(DossierFournisseur::class, 'fournisseur_id')
            ->orderBy('date_ouverture', 'desc');
    }

    // ════════════════════════════════════════════════════════
    // SCOPES
    // ════════════════════════════════════════════════════════

    public function scopeActifs($query)
    {
        return $query->where('actif', true)->where('blackliste', false);
    }

    public function scopeType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeBlacklistes($query)
    {
        return $query->where('blackliste', true);
    }

    // ════════════════════════════════════════════════════════
    // MÉTHODES MÉTIER
    // ════════════════════════════════════════════════════════

    /**
     * Calculer l'IR pour un montant donné
     */
    public function calculerIR(float $montantHT): float
    {
        if (!$this->regimeFiscal) return 0;
        return $this->regimeFiscal->calculerIR($montantHT);
    }

    /**
     * Montant total des commandes (hors brouillon/annulé)
     */
    public function getMontantTotalCommandes(): float
    {
        return $this->bonsCommande()
            ->whereNotIn('statut', ['brouillon', 'annule'])
            ->sum('montant_ttc');
    }

    /**
     * Nombre de commandes (hors brouillon/annulé)
     */
    public function getNombreCommandes(): int
    {
        return $this->bonsCommande()
            ->whereNotIn('statut', ['brouillon', 'annule'])
            ->count();
    }

    /**
     * Nom complet (sigle ou raison sociale)
     */
    public function getNomComplet(): string
    {
        return $this->sigle ?: $this->raison_sociale;
    }

    /**
     * Générer un code unique FOUR-AAAA-XXXX
     */
    public static function genererCode(): string
    {
        $annee   = now()->year;
        $prefixe = "FOUR-{$annee}-";

        $dernierNumero = static::where('code', 'like', "{$prefixe}%")
            ->get()
            ->map(function ($f) {
                if (preg_match('/FOUR-\d{4}-(\d+)$/', $f->code, $m)) {
                    return (int) $m[1];
                }
                return 0;
            })
            ->max();

        $sequence = ($dernierNumero ?? 0) + 1;
        return sprintf('FOUR-%s-%04d', $annee, $sequence);
    }

    /**
     * Blacklister le fournisseur
     */
    public function blacklister(string $motif): void
    {
        $this->blackliste      = true;
        $this->motif_blacklist = $motif;
        $this->actif           = false;
        $this->save();
    }

    /**
     * Réhabiliter le fournisseur
     */
    public function rehabiliter(): void
    {
        $this->blackliste      = false;
        $this->motif_blacklist = null;
        $this->actif           = true;
        $this->save();
    }
}
