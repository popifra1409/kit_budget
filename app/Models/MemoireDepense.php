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
        'decision_administrative_id',
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
    // MemoireDepense::genererNumero()
    public static function genererNumero(int $exercice): string
    {
        return \DB::transaction(function () use ($exercice) {
            // ✅ withTrashed() — inclure les supprimés pour éviter les doublons
            $dernier = static::withTrashed()
                ->where('exercice', $exercice)
                ->lockForUpdate()
                ->orderBy('id', 'desc')
                ->first();

            $numero = $dernier
                ? intval(substr($dernier->numero, -4)) + 1
                : 1;

            return 'MD-' . $exercice . '-' . str_pad($numero, 4, '0', STR_PAD_LEFT);
        });
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
        return $this->belongsTo(BonCommande::class, 'bon_commande_id');
    }

    public function decisionAdministrative(): BelongsTo
    {
        return $this->belongsTo(DecisionAdministrative::class, 'decision_administrative_id');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneMemoireDepense::class);
    }

    /**
     * Calculer les totaux depuis les lignes
     * 
     * Formules conformes au fichier Excel :
     * - Total MHT = Σ MHT de chaque ligne
     * - Total TVA = Σ TVA de chaque ligne
     * - Total TTC = Σ TTC de chaque ligne
     * - Total IR = Σ IR de chaque ligne
     * - Total Net (NAP) = Σ Net À Payer de chaque ligne
     * 
     * Note : Le montant_net est correctement calculé car chaque ligne
     * a net_a_payer = MHT - IR (formule conforme au fichier Excel)
     */
    public function calculerTotaux(): void
    {
        // Charger les lignes si pas déjà chargées
        if (!$this->relationLoaded('lignes')) {
            $this->load('lignes');
        }

        // Calculer les totaux
        $this->montant_ht = $this->lignes->sum('montant_ht');
        $this->montant_tva = $this->lignes->sum('montant_tva');
        $this->montant_ir = $this->lignes->sum('montant_ir');
        $this->montant_ttc = $this->lignes->sum('montant_ttc');
        $this->montant_net = $this->lignes->sum('net_a_payer');

        // Convertir le montant TTC en lettres
        $this->montant_lettres = NombreEnLettres::convertir($this->montant_ttc);
    }

    /**
     * Recalculer les totaux et sauvegarder silencieusement
     * 
     * Utilisé par les événements des lignes pour éviter les boucles infinies
     */
    public function recalculerTotaux(): void
    {
        $this->calculerTotaux();
        $this->saveQuietly();
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
     * Vérifier si le mémoire peut être transformé en DA
     */
    public function peutEtreTransformeEnDA(): bool
    {
        return $this->statut === 'valide'
            && !$this->decision_administrative_id;
    }

    /**
     * Accesseurs en lettres
     */
    public function getMontantNetEnLettresAttribute(): string
    {
        return NombreEnLettres::convertir($this->montant_net);
    }

    public function getMontantHtEnLettresAttribute(): string
    {
        return NombreEnLettres::convertir($this->montant_ht);
    }

    /**
     * Accesseurs formatés
     */
    public function getMontantHtFormateAttribute(): string
    {
        return number_format($this->montant_ht, 0, ',', ' ') . ' FCFA';
    }

    public function getMontantTvaFormateAttribute(): string
    {
        return number_format($this->montant_tva, 0, ',', ' ') . ' FCFA';
    }

    public function getMontantTtcFormateAttribute(): string
    {
        return number_format($this->montant_ttc, 0, ',', ' ') . ' FCFA';
    }

    public function getMontantIrFormateAttribute(): string
    {
        return number_format($this->montant_ir, 0, ',', ' ') . ' FCFA';
    }

    public function getMontantNetFormateAttribute(): string
    {
        return number_format($this->montant_net, 0, ',', ' ') . ' FCFA';
    }

    /**
     * Scopes
     */
    public function scopeValides($query)
    {
        return $query->where('statut', 'valide');
    }

    public function scopeNonTransformes($query)
    {
        return $query->whereNull('decision_administrative_id');
    }

    public function scopeParExercice($query, int $exercice)
    {
        return $query->where('exercice', $exercice);
    }

    public function scopeParStatut($query, string $statut)
    {
        return $query->where('statut', $statut);
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

            if (!$memoire->statut) {
                $memoire->statut = 'brouillon';
            }
        });

        static::saving(function ($memoire) {
            // Calculer les totaux si les lignes sont chargées
            if ($memoire->relationLoaded('lignes') && $memoire->lignes->count() > 0) {
                $memoire->calculerTotaux();
            }
        });
    }
}
