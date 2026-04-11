<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BordereauEngagementLigne extends Model
{
    use HasFactory;

    protected $table = 'bordereau_engagement_lignes';

    protected $fillable = [
        'bordereau_id',
        'engagement_id',
        'numero_ligne',
        'statut_ligne',
        'motif_rejet',
        'observations',
    ];

    protected $casts = [
        'numero_ligne' => 'integer',
    ];

    /**
     * Relation : Bordereau parent
     */
    public function bordereau(): BelongsTo
    {
        return $this->belongsTo(BordereauEngagement::class, 'bordereau_id');
    }

    /**
     * Relation : Engagement
     */
    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class);
    }

    /**
     * Valider cette ligne
     */
    public function valider(): void
    {
        $this->statut_ligne = 'valide';
        $this->motif_rejet = null;
        $this->save();
    }

    /**
     * Rejeter cette ligne
     */
    public function rejeter(string $motif): void
    {
        $this->statut_ligne = 'rejete';
        $this->motif_rejet = $motif;
        $this->save();
    }

    /**
     * Annuler cette ligne
     */
    public function annuler(): void
    {
        $this->statut_ligne = 'annule';
        $this->save();
    }
}
