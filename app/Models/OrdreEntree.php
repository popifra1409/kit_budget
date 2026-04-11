<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdreEntree extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ordres_entree';

    protected $fillable = [
        'numero',
        'numero_ordre',
        'exercice_id',
        'reception_id',
        'fournisseur_id',
        'bon_commande_id',
        'date_oe',
        'numero_prise_en_charge',
        'date_prise_en_charge',
        'montant_total',
        'observations',
        'statut',
        'comptable_matieres_id',
        'ordonnateur_id',
        'signe_comptable',
        'signe_ordonnateur',
        'date_signature',
        'created_by',
    ];

    protected $casts = [
        'date_oe'               => 'date',
        'date_prise_en_charge'  => 'date',
        'date_signature'        => 'date',
        'montant_total'         => 'decimal:2',
        'signe_comptable'       => 'boolean',
        'signe_ordonnateur'     => 'boolean',
    ];

    // ====================================
    // RELATIONS
    // ====================================

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function reception(): BelongsTo
    {
        return $this->belongsTo(Reception::class);
    }

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class);
    }

    public function bonCommande(): BelongsTo
    {
        return $this->belongsTo(BonCommande::class, 'bon_commande_id');
    }

    public function comptableMatieres(): BelongsTo
    {
        return $this->belongsTo(User::class, 'comptable_matieres_id');
    }

    public function ordonnateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordonnateur_id');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ====================================
    // ACCESSEURS
    // ====================================

    public function getToutSigneAttribute(): bool
    {
        return $this->signe_comptable && $this->signe_ordonnateur;
    }

    public function estModifiable(): bool
    {
        return $this->statut === 'brouillon';
    }

    // ====================================
    // MÉTHODES
    // ====================================

    public function signer(string $partie): void
    {
        $champ = match ($partie) {
            'comptable'   => 'signe_comptable',
            'ordonnateur' => 'signe_ordonnateur',
            default       => throw new \Exception("Partie inconnue : {$partie}"),
        };

        $this->$champ = true;

        if ($this->tout_signe) {
            $this->statut         = 'signe';
            $this->date_signature = now()->toDateString();
        }

        $this->saveQuietly();
    }

    // ====================================
    // BOOT
    // ====================================

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($oe) {
            if (!$oe->numero) {
                $oe->numero = static::genererNumero();
            }
            if (!$oe->created_by) {
                $oe->created_by = auth()->id();
            }
        });
    }

    public static function genererNumero(): string
    {
        return \DB::transaction(function () {
            $annee   = now()->year;
            $prefixe = "OE-{$annee}-";
            $dernier = static::withTrashed()
                ->where('numero', 'like', "{$prefixe}%")
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();
            $seq = $dernier ? intval(substr($dernier->numero, -4)) + 1 : 1;
            return $prefixe . str_pad($seq, 4, '0', STR_PAD_LEFT);
        });
    }
}
