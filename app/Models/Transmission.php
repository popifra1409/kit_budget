<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Transmission extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'document_type',
        'document_id',
        'expediteur_id',
        'destinataire_id',
        'action_attendue',
        'statut',
        'commentaire',
        'reponse',
        'priorite',
        'date_limite',
        'date_transmission',
        'date_traitement',
        'date_lecture',
        'documents_joints',
        'metadata',
    ];

    protected $casts = [
        'date_transmission' => 'datetime',
        'date_traitement' => 'datetime',
        'date_lecture' => 'datetime',
        'date_limite' => 'date',
        'documents_joints' => 'array',
        'metadata' => 'array',
    ];

    public static function booted()
    {
        static::creating(function ($transmission) {
            self::where('document_type', $transmission->document_type)
                ->where('document_id', $transmission->document_id)
                ->where('statut', 'en_attente')
                ->update([
                    'statut' => 'annule',
                    'date_traitement' => now(),
                ]);
        });
    }

    /**
     * Relation polymorphique : Document transmis
     */
    public function document(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Relation : Expéditeur
     */
    public function expediteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'expediteur_id');
    }

    /**
     * Relation : Destinataire
     */
    public function destinataire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destinataire_id');
    }

    /**
     * Scope : Transmissions en attente
     */
    public function scopeEnAttente($query)
    {
        return $query->where('statut', 'en_attente');
    }

    /**
     * Scope : Transmissions pour un destinataire
     */
    public function scopePourDestinataire($query, int $userId)
    {
        return $query->where('destinataire_id', $userId);
    }

    /**
     * Scope : Transmissions d'un expéditeur
     */
    public function scopeDeExpediteur($query, int $userId)
    {
        return $query->where('expediteur_id', $userId);
    }

    /**
     * Scope : Transmissions non lues
     */
    public function scopeNonLues($query)
    {
        return $query->whereNull('date_lecture');
    }

    /**
     * Scope : Transmissions urgentes
     */
    public function scopeUrgentes($query)
    {
        return $query->where('priorite', 'urgente');
    }

    /**
     * Marquer comme lu
     */
    public function marquerCommeLu(): void
    {
        if (!$this->date_lecture) {
            $this->date_lecture = now();
            $this->save();
        }
    }

    /**
     * Traiter la transmission
     */
    public function traiter(string $reponse = null): void
    {
        $this->statut = 'traite';
        $this->date_traitement = now();
        if ($reponse) {
            $this->reponse = $reponse;
        }
        $this->save();
    }

    /**
     * Rejeter la transmission
     */
    public function rejeter(string $motif): void
    {
        $this->statut = 'rejete';
        $this->date_traitement = now();
        $this->reponse = $motif;
        $this->save();
    }

    /**
     * Vérifier si en retard
     */
    public function estEnRetard(): bool
    {
        if (!$this->date_limite || $this->statut !== 'en_attente') {
            return false;
        }

        return $this->date_limite->isPast();
    }

    /**
     * Obtenir le label de l'action
     */
    public function getActionLabel(): string
    {
        return match ($this->action_attendue) {
            'validation' => 'Validation requise',
            'engagement' => 'Engagement requis',
            'verification' => 'Vérification requise',
            'correction' => 'Correction requise',
            'signature' => 'Signature requise',
            'information' => 'Pour information',
            'liquidation' => 'Liquidation requise',
            'paiement' => 'Paiement requis',
            default => $this->action_attendue,
        };
    }

    /**
     * Obtenir la couleur du badge selon statut
     */
    public function getStatutColor(): string
    {
        return match ($this->statut) {
            'en_attente' => 'warning',
            'traite' => 'success',
            'rejete' => 'danger',
            'annule' => 'gray',
            default => 'secondary',
        };
    }

    /**
     * Obtenir la couleur de la priorité
     */
    public function getPrioriteColor(): string
    {
        return match ($this->priorite) {
            'urgente' => 'danger',
            'haute' => 'warning',
            'normale' => 'info',
            'basse' => 'gray',
            default => 'secondary',
        };
    }
}
