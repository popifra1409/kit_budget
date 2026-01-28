<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class PieceDossier extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pieces_dossier';

    protected $fillable = [
        'dossier_id',
        'type_piece',
        'document_type',
        'document_id',
        'nom_fichier',
        'chemin_fichier',
        'type_mime',
        'taille',
        'valide',
        'valide_par',
        'date_validation',
        'commentaire',
        'metadata',
        'ajoute_par',
        'date_ajout',
    ];

    protected $casts = [
        'valide' => 'boolean',
        'date_validation' => 'datetime',
        'date_ajout' => 'datetime',
        'metadata' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function dossier(): BelongsTo
    {
        return $this->belongsTo(DossierFournisseur::class, 'dossier_id');
    }

    public function document(): MorphTo
    {
        return $this->morphTo();
    }

    public function ajoutePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ajoute_par');
    }

    public function validePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopeValides($query)
    {
        return $query->where('valide', true);
    }

    public function scopeEnAttente($query)
    {
        return $query->where('valide', false);
    }

    public function scopeParType($query, string $type)
    {
        return $query->where('type_piece', $type);
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSEURS
    |--------------------------------------------------------------------------
    */

    public function getTypePieceLabelAttribute(): string
    {
        return match ($this->type_piece) {
            'bon_commande' => 'Bon de Commande',
            'engagement' => 'Engagement',
            'facture_proforma' => 'Facture Proforma',
            'facture_definitive' => 'Facture Définitive',
            'bordereau_livraison' => 'Bordereau de Livraison',
            'pv_reception' => 'PV de Réception',
            'certificat_service_fait' => 'Certificat Service Fait',
            'ordre_paiement' => 'Ordre de Paiement',
            'justificatif_paiement' => 'Justificatif de Paiement',
            'piece_comptable' => 'Pièce Comptable',
            'autre_document' => 'Autre Document',
            default => $this->type_piece,
        };
    }

    public function getTailleFormateeAttribute(): string
    {
        if (!$this->taille) {
            return 'N/A';
        }

        $units = ['o', 'Ko', 'Mo', 'Go'];
        $power = $this->taille > 0 ? floor(log($this->taille, 1024)) : 0;

        return number_format($this->taille / pow(1024, $power), 2, ',', ' ') . ' ' . $units[$power];
    }

    public function getUrlTelechargementAttribute(): string
    {
        return route('dossier-fournisseur.piece.download', $this->id);
    }

    public function getIconAttribute(): string
    {
        if (!$this->type_mime) {
            return 'heroicon-o-document';
        }

        return match (true) {
            str_contains($this->type_mime, 'pdf') => 'heroicon-o-document-text',
            str_contains($this->type_mime, 'image') => 'heroicon-o-photo',
            str_contains($this->type_mime, 'word') || str_contains($this->type_mime, 'document') => 'heroicon-o-document',
            str_contains($this->type_mime, 'excel') || str_contains($this->type_mime, 'spreadsheet') => 'heroicon-o-table-cells',
            str_contains($this->type_mime, 'zip') || str_contains($this->type_mime, 'compressed') => 'heroicon-o-archive-box',
            default => 'heroicon-o-document',
        };
    }

    public function getColorAttribute(): string
    {
        if (!$this->type_mime) {
            return 'gray';
        }

        return match (true) {
            str_contains($this->type_mime, 'pdf') => 'danger',
            str_contains($this->type_mime, 'image') => 'success',
            str_contains($this->type_mime, 'word') || str_contains($this->type_mime, 'document') => 'info',
            str_contains($this->type_mime, 'excel') || str_contains($this->type_mime, 'spreadsheet') => 'success',
            str_contains($this->type_mime, 'zip') || str_contains($this->type_mime, 'compressed') => 'warning',
            default => 'gray',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | MÉTHODES MÉTIER
    |--------------------------------------------------------------------------
    */

    /**
     * Valider la pièce
     */
    public function valider(User $validateur = null): void
    {
        $this->valide = true;
        $this->valide_par = $validateur?->id ?? auth()->id();
        $this->date_validation = now();
        $this->save();
    }

    /**
     * Invalider la pièce
     */
    public function invalider(): void
    {
        $this->valide = false;
        $this->valide_par = null;
        $this->date_validation = null;
        $this->save();
    }

    /**
     * Télécharger le fichier
     */
    public function telecharger()
    {
        if (!Storage::exists($this->chemin_fichier)) {
            abort(404, 'Fichier introuvable');
        }

        return Storage::download($this->chemin_fichier, $this->nom_fichier);
    }

    /**
     * Supprimer le fichier physique
     */
    public function supprimerFichier(): bool
    {
        if (Storage::exists($this->chemin_fichier)) {
            return Storage::delete($this->chemin_fichier);
        }

        return true;
    }

    /**
     * Boot method pour gérer la suppression du fichier
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($piece) {
            $piece->supprimerFichier();
        });
    }

    /**
     * Vérifier si la pièce est une image
     */
    public function estImage(): bool
    {
        return $this->type_mime && str_contains($this->type_mime, 'image');
    }

    /**
     * Vérifier si la pièce est un PDF
     */
    public function estPdf(): bool
    {
        return $this->type_mime && str_contains($this->type_mime, 'pdf');
    }
}
