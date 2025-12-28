<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Services\NombreEnLettres;

class MemoireDepense extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'memoires_depense';

    protected $fillable = [
        'numero',
        'exercice',
        'date_memoire',
        'numero_decision',
        'date_decision',
        'numero_ce',
        'date_ce',
        'bordereau_engagement_id',
        'bon_commande_id',
        'objet',
        'observations',
        'montant_ht',
        'montant_tva',
        'montant_ir',
        'montant_ttc',
        'montant_net',
        'montant_lettres',
        'signataire_nom',
        'signataire_fonction',
        'date_signature',
        'lieu_signature',
        'statut',
        'fichier_pdf',
    ];

    protected $casts = [
        'exercice' => 'integer',
        'date_memoire' => 'date',
        'date_decision' => 'date',
        'date_ce' => 'date',
        'date_signature' => 'date',
        'montant_ht' => 'decimal:2',
        'montant_tva' => 'decimal:2',
        'montant_ir' => 'decimal:2',
        'montant_ttc' => 'decimal:2',
        'montant_net' => 'decimal:2',
    ];

    /**
     * Générer le numéro automatique
     */
    public static function genererNumero(int $exercice): string
    {
        $dernier = static::where('exercice', $exercice)
            ->orderBy('id', 'desc')
            ->first();

        $numero = $dernier ? intval(substr($dernier->numero, -4)) + 1 : 1;

        return 'MD-' . $exercice . '-' . str_pad($numero, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Relations
     */
    public function bordereauEngagement(): BelongsTo
    {
        return $this->belongsTo(BordereauEngagement::class);
    }

    public function bonCommande(): BelongsTo
    {
        return $this->belongsTo(BonCommande::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneMemoireDepense::class);
    }

    /**
     * Calculer les totaux depuis les lignes
     */
    public function calculerTotaux(): void
    {
        $this->montant_ht = $this->lignes->sum('montant_ht');
        $this->montant_tva = $this->lignes->sum('montant_tva');
        $this->montant_ir = $this->lignes->sum('montant_ir');
        $this->montant_ttc = $this->lignes->sum('montant_ttc');
        $this->montant_net = $this->lignes->sum('net_a_payer');

        // Convertir le montant TTC en lettres
        $this->montant_lettres = NombreEnLettres::convertir($this->montant_ttc);
    }

    /**
     * Valider le mémoire
     */
    public function valider(): void
    {
        $this->statut = 'valide';
        $this->date_signature = now();
        $this->save();
    }

    /**
     * Événements du modèle
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($memoire) {
            if (!$memoire->numero) {
                $memoire->numero = static::genererNumero($memoire->exercice ?? now()->year);
            }

            if (!$memoire->exercice) {
                $memoire->exercice = now()->year;
            }

            if (!$memoire->date_memoire) {
                $memoire->date_memoire = now();
            }

            if (!$memoire->lieu_signature) {
                $memoire->lieu_signature = 'Yaoundé';
            }
        });
    }
}
