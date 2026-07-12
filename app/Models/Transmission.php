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
        'motif_retour',      // ✅ AJOUTER
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
        'date_traitement'   => 'datetime',
        'date_lecture'      => 'datetime',
        'date_limite'       => 'date',
        'documents_joints'  => 'array',
        'metadata'          => 'array',
    ];

    // ✅ FIX MAJEUR — booted() ne doit PAS annuler automatiquement
    // les transmissions précédentes car cela efface l'historique
    // et empêche le workflow retour/clôture de fonctionner
    public static function booted(): void
    {
        // ✅ Supprimé : le hook creating() qui annulait les transmissions en_attente
        // Cette logique est gérée manuellement dans WorkflowActions::transmettre()
        // pour un contrôle explicite et traçable
    }

    // ── Relations ─────────────────────────────────────────────
    public function document(): MorphTo
    {
        return $this->morphTo();
    }

    public function expediteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'expediteur_id');
    }

    public function destinataire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destinataire_id');
    }

    // ── Scopes ────────────────────────────────────────────────
    public function scopeEnAttente($query)
    {
        return $query->where('statut', 'en_attente');
    }

    public function scopePourDestinataire($query, int $userId)
    {
        return $query->where('destinataire_id', $userId);
    }

    public function scopeDeExpediteur($query, int $userId)
    {
        return $query->where('expediteur_id', $userId);
    }

    public function scopeNonLues($query)
    {
        return $query->whereNull('date_lecture');
    }

    public function scopeUrgentes($query)
    {
        return $query->where('priorite', 'urgente');
    }

    // ── Actions ───────────────────────────────────────────────
    public function marquerCommeLu(): void
    {
        if (!$this->date_lecture) {
            $this->update(['date_lecture' => now()]);
        }
    }

    // ✅ FIX — nullable explicite (supprime le deprecated PHP 8.4)
    public function traiter(?string $reponse = null): void
    {
        $this->update([
            'statut'          => 'traite',
            'reponse'         => $reponse,
            'date_traitement' => now(),
        ]);

        // ✅ Log — clôture de transmission
        \App\Models\ActivityLog::logAction($this, 'cloturer', [
            'document_type'  => $this->document_type,
            'document_id'    => $this->document_id,
            'expediteur'     => $this->expediteur?->name,
            'destinataire'   => $this->destinataire?->name,
            'action_attendue' => $this->action_attendue,
            'reponse'        => $reponse,
        ]);
    }

    // ✅ FIX — statut 'retourne' au lieu de 'rejete'
    // et motif stocké dans motif_retour (pas reponse)
    public function rejeter(?string $motif = null): void
    {
        $this->update([
            'statut'          => 'retourne',   // ← était 'rejete'
            'motif_retour'    => $motif,        // ← champ dédié
            'reponse'         => $motif,        // ← aussi dans reponse pour compatibilité
            'date_traitement' => now(),
        ]);
    }

    // ✅ Annuler explicitement (appelé manuellement)
    public function annuler(?string $raison = null): void
    {
        $this->update([
            'statut'          => 'annule',
            'reponse'         => $raison,
            'date_traitement' => now(),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────
    public function estEnRetard(): bool
    {
        if (!$this->date_limite || $this->statut !== 'en_attente') {
            return false;
        }
        return $this->date_limite->isPast();
    }

    public function getActionLabel(): string
    {
        return match ($this->action_attendue) {
            'validation'   => 'Validation requise',
            'engagement'   => 'Engagement requis',
            'verification' => 'Vérification requise',
            'correction'   => 'Correction requise',
            'signature'    => 'Signature requise',
            'information'  => 'Pour information',
            'liquidation'  => 'Liquidation requise',
            'paiement'     => 'Paiement requis',
            default        => $this->action_attendue,
        };
    }

    public function getStatutColor(): string
    {
        return match ($this->statut) {
            'en_attente' => 'warning',
            'traite'     => 'success',
            'retourne'   => 'warning', 
            'rejete'     => 'danger',
            'annule'     => 'gray',
            default      => 'secondary',
        };
    }

    public function getStatutLabel(): string
    {
        return match ($this->statut) {
            'en_attente' => 'En attente',
            'traite'     => 'Traité',
            'retourne'   => 'Retourné',
            'rejete'     => 'Rejeté',
            'annule'     => 'Annulé',
            default      => $this->statut,
        };
    }

    public function getPrioriteColor(): string
    {
        return match ($this->priorite) {
            'urgente' => 'danger',
            'haute'   => 'warning',
            'normale' => 'info',
            'basse'   => 'gray',
            default   => 'secondary',
        };
    }
}
