<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Reception extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'receptions';

    protected $fillable = [
        'numero',
        'exercice_id',
        'fournisseur_id',
        'bon_commande_id',
        'expression_besoin_id',
        'date_reception',
        'numero_bordereau_livraison',
        'date_bordereau_livraison',
        'numero_facture',
        'date_facture',
        'montant_facture',
        'president_commission_id',
        'comptable_matieres_id',
        'service_technique_id',
        'representant_prestataire_nom',
        'observations',
        'reserves',
        'signe_prestataire',
        'signe_technique',
        'signe_comptable',
        'signe_ordonnateur',
        'date_pv',
        'statut',
        'created_by',
    ];

    protected $casts = [
        'date_reception'           => 'date',
        'date_bordereau_livraison' => 'date',
        'date_facture'             => 'date',
        'date_pv'                  => 'date',
        'montant_facture'          => 'decimal:2',
        'signe_prestataire'        => 'boolean',
        'signe_technique'          => 'boolean',
        'signe_comptable'          => 'boolean',
        'signe_ordonnateur'        => 'boolean',
    ];

    // ====================================
    // RELATIONS
    // ====================================

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class);
    }

    public function bonCommande(): BelongsTo
    {
        return $this->belongsTo(BonCommande::class, 'bon_commande_id');
    }

    public function expressionBesoin(): BelongsTo
    {
        return $this->belongsTo(ExpressionBesoin::class, 'expression_besoin_id');
    }

    public function presidentCommission(): BelongsTo
    {
        return $this->belongsTo(User::class, 'president_commission_id');
    }

    public function comptableMatieres(): BelongsTo
    {
        return $this->belongsTo(User::class, 'comptable_matieres_id');
    }

    public function serviceTechnique(): BelongsTo
    {
        return $this->belongsTo(User::class, 'service_technique_id');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneReception::class);
    }

    public function ordreEntree(): HasOne
    {
        return $this->hasOne(OrdreEntree::class);
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
        return $this->signe_prestataire
            && $this->signe_technique
            && $this->signe_comptable
            && $this->signe_ordonnateur;
    }

    public function getNombreSignatairesAttribute(): int
    {
        return (int)$this->signe_prestataire
            + (int)$this->signe_technique
            + (int)$this->signe_comptable
            + (int)$this->signe_ordonnateur;
    }

    public function getMontantTotalAttribute(): float
    {
        return (float) $this->lignes->sum('montant_total');
    }

    // ====================================
    // MÉTHODES
    // ====================================

    public function signer(string $partie): void
    {
        $champ = match ($partie) {
            'prestataire' => 'signe_prestataire',
            'technique'   => 'signe_technique',
            'comptable'   => 'signe_comptable',
            'ordonnateur' => 'signe_ordonnateur',
            default       => throw new \Exception("Partie inconnue : {$partie}"),
        };

        $this->$champ = true;

        // Si tous signés → PV signé
        if ($this->tout_signe) {
            $this->statut   = 'pv_signe';
            $this->date_pv  = now()->toDateString();
        }

        $this->saveQuietly();
    }

    /**
     * Intégrer en stock — crée l'OE et les mouvements de stock
     */
    public function integrerEnStock(): OrdreEntree
    {
        if ($this->statut !== 'pv_signe') {
            throw new \Exception('Le PV doit être signé avant intégration en stock.');
        }

        return \DB::transaction(function () {
            // 1. Créer l'Ordre d'Entrée
            $oe = OrdreEntree::create([
                'exercice_id'           => $this->exercice_id,
                'reception_id'          => $this->id,
                'fournisseur_id'        => $this->fournisseur_id,
                'bon_commande_id'       => $this->bon_commande_id,
                'date_oe'               => now()->toDateString(),
                'montant_total'         => $this->lignes->sum('montant_total'),
                'comptable_matieres_id' => $this->comptable_matieres_id,
                'ordonnateur_id'        => $this->president_commission_id,
                'created_by'            => auth()->id(),
            ]);

            // 2. Créer les mouvements de stock pour chaque ligne conforme
            foreach ($this->lignes as $ligne) {
                if ($ligne->quantite_conforme > 0) {
                    FicheStock::enregistrerMouvement(
                        $ligne->article,
                        'entree',
                        $ligne->quantite_conforme,
                        (float)$ligne->prix_unitaire,
                        [
                            'exercice_id'   => $this->exercice_id,
                            'date'          => $this->date_reception->toDateString(),
                            'motif'         => "Réception N° {$this->numero}",
                            'reference'     => $this->numero,
                            'document_type' => static::class,
                            'document_id'   => $this->id,
                        ]
                    );

                    // Mettre à jour le PUM de l'article
                    $ligne->article->mettreAJourPrixMoyen(
                        $ligne->quantite_conforme,
                        (float)$ligne->prix_unitaire
                    );
                }
            }

            // 3. Mettre à jour le statut
            $this->updateQuietly(['statut' => 'integre']);

            return $oe;
        });
    }

    // ====================================
    // BOOT
    // ====================================

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($reception) {
            if (!$reception->numero) {
                $reception->numero = static::genererNumero();
            }
            if (!$reception->created_by) {
                $reception->created_by = auth()->id();
            }
        });
    }

    public static function genererNumero(): string
    {
        return \DB::transaction(function () {
            $annee   = now()->year;
            $prefixe = "REC-{$annee}-";
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
