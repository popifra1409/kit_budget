<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Helpers\NombreEnLettres;
use App\Traits\GereTransmissions;

class MemoireDepense extends Model
{
    // ✅ Ajouter GereTransmissions dans le use
    use HasFactory, SoftDeletes, GereTransmissions;

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
        'mode_saisie ',
    ];

    protected $casts = [
        'exercice'       => 'integer',
        'date_memoire'   => 'date',
        'date_decision'  => 'date',
        'date_ce'        => 'date',
        'date_signature' => 'date',
        'montant_ht'     => 'decimal:2',
        'montant_tva'    => 'decimal:2',
        'montant_ir'     => 'decimal:2',
        'montant_ttc'    => 'decimal:2',
        'montant_net'    => 'decimal:2',
    ];

    // ====================================
    // GÉNÉRATION NUMÉRO
    // ====================================

    public static function genererNumero(int $exercice): string
    {
        return \DB::transaction(function () use ($exercice) {
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

    // ====================================
    // RELATIONS
    // ====================================

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

    // ✅ Relation polymorphique requise par GereTransmissions
    public function transmissions()
    {
        return $this->morphMany(Transmission::class, 'document');
    }

    // ====================================
    // MÉTHODES WORKFLOW (requises par WorkflowActions)
    // ====================================

    /**
     * Le mémoire peut être transmis
     */
    public function peutEtreTransmis(): bool
    {
        if ($this->estEnCoursDeTransmission()) return false;
        return in_array($this->statut, ['brouillon', 'valide']);
    }

    /**
     * Le mémoire est modifiable
     */
    public function estModifiable(): bool
    {
        return $this->statut === 'brouillon';
    }

    /**
     * Peut être modifié par l'utilisateur courant
     */
    public function peutEtreModifiePar(): bool
    {
        if ($this->estEnCoursDeTransmission()) return false;
        return $this->estModifiable();
    }

    /**
     * Vérifier si peut être transformé en DA
     */
    public function peutEtreTransformeEnDA(): bool
    {
        return $this->statut === 'valide'
            && !$this->decision_administrative_id;
    }

    // ✅ Ajouter dans MemoireDepense.php — méthodes manquantes du trait

    public function estEnCoursDeTransmission(): bool
    {
        return $this->transmissions()
            ->where('statut', 'en_attente')
            ->exists();
    }

    public function estDestinataireActuel(): bool
    {
        return $this->transmissions()
            ->where('statut', 'en_attente')
            ->where('destinataire_id', auth()->id())
            ->exists();
    }

    public function transmissionEnCours(): ?\App\Models\Transmission
    {
        return $this->transmissions()
            ->where('statut', 'en_attente')
            ->latest()
            ->first();
    }

    public function transmettreA(
        \App\Models\User $destinataire,
        string $actionAttendue,
        ?string $commentaire = null,
        array $metadata = []
    ): \App\Models\Transmission {
        if ($this->estEnCoursDeTransmission()) {
            throw new \Exception('Ce mémoire est déjà en cours de transmission.');
        }

        $transmission = \App\Models\Transmission::create([
            'document_type'    => static::class,
            'document_id'      => $this->id,
            'expediteur_id'    => auth()->id(),
            'destinataire_id'  => $destinataire->id,
            'action_attendue'  => $actionAttendue,
            'commentaire'      => $commentaire,
            'statut'           => 'en_attente',
            'priorite'         => $metadata['priorite'] ?? 'normale',
            'date_limite'      => $metadata['date_limite'] ?? null,
            'date_transmission' => now(),
        ]);

        return $transmission;
    }

    public function cloturerTransmission(?string $reponse = null): void
    {
        $transmission = $this->transmissions()
            ->where('statut', 'en_attente')
            ->where('destinataire_id', auth()->id())
            ->latest()
            ->first();

        if (!$transmission) return;

        $transmission->update([
            'statut'          => 'traite',
            'reponse'         => $reponse,
            'date_traitement' => now(),
        ]);
    }

    public function aEteTransmis(): bool
    {
        return $this->transmissions()->exists();
    }

    public function historiqueTransmissions()
    {
        return $this->transmissions()
            ->with(['expediteur', 'destinataire'])
            ->orderByDesc('created_at')
            ->get();
    }

    // ====================================
    // CALCULS
    // ====================================

    public function calculerTotaux(): void
    {
        if (!$this->relationLoaded('lignes')) {
            $this->load('lignes');
        }

        // ✅ Somme brute sans arrondi intermédiaire
        $this->montant_ht  = $this->lignes->sum(fn($l) => (float)($l->montant_ht  ?? 0));
        $this->montant_tva = $this->lignes->sum(fn($l) => (float)($l->montant_tva ?? 0));
        $this->montant_ir  = $this->lignes->sum(fn($l) => (float)($l->montant_ir  ?? 0));
        $this->montant_ttc = $this->lignes->sum(fn($l) => (float)($l->montant_ttc ?? 0));
        $this->montant_net = $this->lignes->sum(
            fn($l) => (float)($l->montant_net ?? $l->net_a_payer ?? 0)
        );

        $this->montant_lettres = NombreEnLettres::convertir($this->montant_ttc);
    }
    public function recalculerTotaux(): void
    {
        $this->calculerTotaux();
        $this->saveQuietly();
    }

    public function valider(): void
    {
        $this->statut         = 'valide';
        $this->date_signature = now();
        $this->save();
    }

    // ====================================
    // ACCESSEURS
    // ====================================

    public function getMontantNetEnLettresAttribute(): string
    {
        return NombreEnLettres::convertir($this->montant_net);
    }

    public function getMontantHtEnLettresAttribute(): string
    {
        return NombreEnLettres::convertir($this->montant_ht);
    }

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

    public function getModeSaisieLibelleAttribute(): string
    {
        return match ($this->mode_saisie ?? 'montant_nap') {
            'montant_nap'   => '📊 Montant NAP',
            'prix_unitaire' => '💰 Prix Unitaire HT',
            default         => 'Non défini',
        };
    }

    // ====================================
    // SCOPES
    // ====================================

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

    // ====================================
    // BOOT
    // ====================================

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($memoire) {
            if (!$memoire->numero) {
                $memoire->numero = static::genererNumero($memoire->exercice ?? now()->year);
            }
            if (!$memoire->exercice)       $memoire->exercice       = now()->year;
            if (!$memoire->date_memoire)   $memoire->date_memoire   = now();
            if (!$memoire->lieu_signature) $memoire->lieu_signature = 'Yaoundé';
            if (!$memoire->statut)         $memoire->statut         = 'brouillon';
        });

        static::saving(function ($memoire) {
            if ($memoire->relationLoaded('lignes') && $memoire->lignes->count() > 0) {
                $memoire->calculerTotaux();
            }
        });
    }
}
