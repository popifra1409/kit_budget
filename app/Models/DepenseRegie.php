<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepenseRegie extends Model
{
    use SoftDeletes;

    protected $table = 'depenses_regies';

    protected $fillable = [
        'regie_avance_id',
        'decaissement_regie_id',
        'ligne_regie_avance_id',
        'numero',
        'numero_facture',
        'date_depense',
        'objet',
        'type_depense',
        'fournisseur_id',
        'fournisseur_libre',
        'montant_ht',
        'taux_tva',
        'montant_tva',
        'montant_ttc',
        'taux_ir',
        'montant_ir',
        'net_a_payer',
        'statut',
        'justificatif_fichier',
        'observations',
        'created_by',
        'updated_by',
        'mode_saisie',
        'provision_ligne_regie_id',
    ];

    protected $casts = [
        'date_depense' => 'date',
        'montant_ht'   => 'decimal:2',
        'montant_tva'  => 'decimal:2',
        'montant_ttc'  => 'decimal:2',
        'montant_ir'   => 'decimal:2',
        'net_a_payer'  => 'decimal:2',
        'taux_tva'     => 'decimal:2',
        'taux_ir'      => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function ($depense) {
            if (!$depense->numero) {
                $depense->numero = static::genererNumero($depense->regieAvance);
            }
            $depense->created_by = auth()->id();
        });

        static::saving(function ($depense) {
            // L'achat direct ne connaît sa tranche que par la provision qu'il a
            // débitée : sans ce rattachement, le « dépensé » de la tranche et de
            // la régie ignore l'achat (somme sur decaissement_regie_id).
            if (!$depense->decaissement_regie_id && $depense->provision_ligne_regie_id) {
                $depense->decaissement_regie_id =
                    $depense->provisionLigneRegie?->decaissement_regie_id;
            }
        });
        // Les recalculs d'agrégats appartiennent à DepenseRegieObserver : c'est
        // lui qui doit aligner les totaux avant de resommer provision, tranche
        // et régie, qui lisent tous montant_ttc de l'en-tête.
    }

    // =========================================================
    // TOTAUX ET AGRÉGATS
    // =========================================================

    /**
     * L'achat direct est chiffré par lignes, mais c'est le montant_ttc de
     * l'en-tête que la provision débite et que l'encaisse somme. Sans ce
     * recopié, valider un achat depuis la fiche régie débite 0 FCFA : le
     * dépensé reste nul et le disponible ne bouge pas.
     */
    public function alignerTotauxSurLignes(): void
    {
        $totaux = [
            'montant_ht'  => round((float) $this->lignes()->sum('montant_ht'), 2),
            'montant_tva' => round((float) $this->lignes()->sum('montant_tva'), 2),
            'montant_ttc' => round((float) $this->lignes()->sum('montant_ttc'), 2),
            'montant_ir'  => round((float) $this->lignes()->sum('montant_ir'), 2),
            'net_a_payer' => round((float) $this->lignes()->sum('montant_net'), 2),
        ];

        // Dépense saisie sans lignes chiffrées (saisie rapide depuis la régie) :
        // ses montants d'en-tête sont la saisie elle-même, on n'écrit rien.
        if ($totaux['montant_ttc'] <= 0) {
            return;
        }

        $ecart = false;
        foreach ($totaux as $champ => $valeur) {
            if (abs((float) $this->{$champ} - $valeur) > 0.005) {
                $ecart = true;
                break;
            }
        }
        if (!$ecart) {
            return;
        }

        // updateQuietly : pas de récursion dans saved(), les agrégats sont
        // rafraîchis par l'appelant.
        $this->updateQuietly($totaux);
    }

    /**
     * Chaîne d'agrégats dans l'ordre : provision → ligne de mini-budget →
     * tranche → régie. Chacun relit montant_ttc de l'en-tête, d'où
     * alignerTotauxSurLignes() appelé avant.
     */
    public function rafraichirAgregeats(): void
    {
        $this->provisionLigneRegie?->recalculer();
        $this->ligneRegieAvance?->recalculerMontants();

        // Un achat direct n'est rattaché à une tranche que par sa provision :
        // decaissement_regie_id reste NULL à la saisie, sans ce fallback la
        // tranche ne recalcule ni son dépensé ni le disponible de ses provisions.
        $decaissement = $this->decaissementRegie
            ?? $this->provisionLigneRegie?->decaissement;
        $decaissement?->recalculerDepenses();

        $this->regieAvance?->recalculerMontants();
    }

    // ── Relations ─────────────────────────────────────────────
    public function regieAvance(): BelongsTo
    {
        return $this->belongsTo(RegieAvance::class, 'regie_avance_id');
    }

    public function decaissementRegie(): BelongsTo
    {
        return $this->belongsTo(DecaissementRegie::class, 'decaissement_regie_id');
    }

    public function ligneRegieAvance(): BelongsTo
    {
        return $this->belongsTo(LigneRegieAvance::class, 'ligne_regie_avance_id');
    }

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class);
    }

    public function bonCommandeRegie(): BelongsTo
    {
        return $this->belongsTo(BonCommandeRegie::class, 'bon_commande_regie_id');
    }

    public function lignes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LigneDepenseRegie::class)->orderBy('numero_ligne');
    }

    public function provisionLigneRegie(): BelongsTo
    {
        return $this->belongsTo(
            \App\Models\ProvisionLigneRegie::class,
            'provision_ligne_regie_id'
        );
    }

    // ── Numérotation ──────────────────────────────────────────
    public static function genererNumero(RegieAvance $regie): string
    {
        $annee  = substr($regie->exercice->annee ?? now()->year, -2);
        $prefix = match ($regie->type) {
            'rav'          => "DR{$annee}",
            'menu_depense' => "DM{$annee}",
            default        => "DR{$annee}",
        };

        $result = \DB::selectOne("
            SELECT COALESCE(MAX(CAST(SPLIT_PART(numero, '-', 2) AS INTEGER)), 0) AS max_seq
            FROM depenses_regies
            WHERE numero LIKE :pattern
        ", ['pattern' => "{$prefix}-%"]);

        return sprintf('%s-%05d', $prefix, ($result->max_seq ?? 0) + 1);
    }

    // ── Méthodes ──────────────────────────────────────────────
    public function estAchatDirect(): bool
    {
        return $this->type_depense === 'achat_direct';
    }

    public function estBonCommande(): bool
    {
        return $this->type_depense === 'bon_commande';
    }
}
