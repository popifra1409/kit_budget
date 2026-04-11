<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MouvementBordereau extends Model
{
    use HasFactory;

    protected $fillable = [
        'bordereau_id',
        'effectue_par_id',
        'action',
        'destinataire_id',
        'statut_avant',
        'statut_apres',
        'observations',
        'motif',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Bordereau concerné
     */
    public function bordereau(): BelongsTo
    {
        return $this->belongsTo(BordereauEngagement::class);
    }

    /**
     * Utilisateur qui a effectué l'action
     */
    public function effectuePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'effectue_par_id');
    }

    /**
     * Destinataire (pour transmission)
     */
    public function destinataire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destinataire_id');
    }

    /**
     * Libellé de l'action
     */
    public function getActionLabelAttribute(): string
    {
        return match($this->action) {
            'creation' => 'Création',
            'soumission' => 'Soumission',
            'transmission' => 'Transmission',
            'reception' => 'Réception',
            'validation' => 'Validation',
            'rejet' => 'Rejet',
            'retour' => 'Retour pour correction',
            'cloture' => 'Clôture',
            'annulation' => 'Annulation',
            default => $this->action,
        };
    }

    /**
     * Scope : Par bordereau
     */
    public function scopePourBordereau($query, $bordereauId)
    {
        return $query->where('bordereau_id', $bordereauId)
                     ->orderBy('created_at', 'desc');
    }

    /**
     * Scope : Par utilisateur
     */
    public function scopeParUtilisateur($query, $userId)
    {
        return $query->where('effectue_par_id', $userId)
                     ->orderBy('created_at', 'desc');
    }

    /**
     * Créer un mouvement
     */
    public static function enregistrer(
        BordereauEngagement $bordereau,
        string $action,
        User $effectuePar,
        ?User $destinataire = null,
        ?string $observations = null,
        ?string $motif = null,
        ?array $metadata = null
    ): self {
        return self::create([
            'bordereau_id' => $bordereau->id,
            'effectue_par_id' => $effectuePar->id,
            'action' => $action,
            'destinataire_id' => $destinataire?->id,
            'statut_avant' => $bordereau->getOriginal('statut'),
            'statut_apres' => $bordereau->statut,
            'observations' => $observations,
            'motif' => $motif,
            'metadata' => $metadata,
        ]);
    }
}<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MouvementBordereau extends Model
{
    use HasFactory;

    protected $fillable = [
        'bordereau_id',
        'effectue_par_id',
        'action',
        'destinataire_id',
        'statut_avant',
        'statut_apres',
        'observations',
        'motif',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Bordereau concerné
     */
    public function bordereau(): BelongsTo
    {
        return $this->belongsTo(BordereauEngagement::class);
    }

    /**
     * Utilisateur qui a effectué l'action
     */
    public function effectuePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'effectue_par_id');
    }

    /**
     * Destinataire (pour transmission)
     */
    public function destinataire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destinataire_id');
    }

    /**
     * Libellé de l'action
     */
    public function getActionLabelAttribute(): string
    {
        return match($this->action) {
            'creation' => 'Création',
            'soumission' => 'Soumission',
            'transmission' => 'Transmission',
            'reception' => 'Réception',
            'validation' => 'Validation',
            'rejet' => 'Rejet',
            'retour' => 'Retour pour correction',
            'cloture' => 'Clôture',
            'annulation' => 'Annulation',
            default => $this->action,
        };
    }

    /**
     * Scope : Par bordereau
     */
    public function scopePourBordereau($query, $bordereauId)
    {
        return $query->where('bordereau_id', $bordereauId)
                     ->orderBy('created_at', 'desc');
    }

    /**
     * Scope : Par utilisateur
     */
    public function scopeParUtilisateur($query, $userId)
    {
        return $query->where('effectue_par_id', $userId)
                     ->orderBy('created_at', 'desc');
    }

    /**
     * Créer un mouvement
     */
    public static function enregistrer(
        BordereauEngagement $bordereau,
        string $action,
        User $effectuePar,
        ?User $destinataire = null,
        ?string $observations = null,
        ?string $motif = null,
        ?array $metadata = null
    ): self {
        return self::create([
            'bordereau_id' => $bordereau->id,
            'effectue_par_id' => $effectuePar->id,
            'action' => $action,
            'destinataire_id' => $destinataire?->id,
            'statut_avant' => $bordereau->getOriginal('statut'),
            'statut_apres' => $bordereau->statut,
            'observations' => $observations,
            'motif' => $motif,
            'metadata' => $metadata,
        ]);
    }
}