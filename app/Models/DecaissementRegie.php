<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DecaissementRegie extends Model
{
    protected $table = 'decaissements_regies';

    protected $fillable = [
        'regie_avance_id',
        'decision_administrative_id',
        'numero',
        'trimestre',
        'libelle_tranche',
        'montant_demande',
        'montant_accorde',
        'date_demande',
        'date_decaissement',
        'date_apurement',
        'certificat_numero',
        'certificat_fichier',
        'statut',
        'montant_depense',
        'montant_ir_collecte',
        'montant_solde',
        'observations',
        'created_by',
    ];

    protected $casts = [
        'date_demande'       => 'date',
        'date_decaissement'  => 'date',
        'date_apurement'     => 'date',
        'montant_demande'    => 'decimal:2',
        'montant_accorde'    => 'decimal:2',
        'montant_depense'    => 'decimal:2',
        'montant_ir_collecte' => 'decimal:2',
        'montant_solde'      => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function ($decaissement) {
            if (!$decaissement->numero) {
                $decaissement->numero = static::genererNumero(
                    $decaissement->regieAvance
                );
            }
            $decaissement->created_by = auth()->id();
        });

        static::saved(fn($d) => $d->regieAvance?->recalculerMontants());
        static::deleted(fn($d) => $d->regieAvance?->recalculerMontants());
    }

    // ── Relations ─────────────────────────────────────────────
    public function regieAvance(): BelongsTo
    {
        return $this->belongsTo(RegieAvance::class, 'regie_avance_id');
    }

    public function depenses(): HasMany
    {
        return $this->hasMany(DepenseRegie::class, 'decaissement_regie_id');
    }

    public function provisions(): HasMany
    {
        return $this->hasMany(ProvisionLigneRegie::class, 'decaissement_regie_id');
    }

    public function decisionAdministrative(): BelongsTo
    {
        return $this->belongsTo(DecisionAdministrative::class, 'decision_administrative_id');
    }

    // ── Numérotation ──────────────────────────────────────────
    public static function genererNumero(RegieAvance $regie): string
    {
        $annee  = substr($regie->exercice->annee ?? now()->year, -2);
        $prefix = match ($regie->type) {
            'rav'          => "DCR{$annee}",
            'menu_depense' => "DCM{$annee}",
            default        => "DCR{$annee}",
        };

        $result = \DB::selectOne("
            SELECT COALESCE(MAX(CAST(SPLIT_PART(numero, '-', 2) AS INTEGER)), 0) AS max_seq
            FROM decaissements_regies
            WHERE numero LIKE :pattern
        ", ['pattern' => "{$prefix}-%"]);

        return sprintf('%s-%05d', $prefix, ($result->max_seq ?? 0) + 1);
    }

    // ── Méthodes ──────────────────────────────────────────────
    public function recalculerDepenses(): void
    {
        // ✅ Mettre à jour montant_consomme sur chaque provision, en se basant
        //    sur la traçabilité RÉELLE (provision_consommations pour les BCR
        //    engagés en cascade + DepenseRegie pour les achats directs).
        //    Remplace l'ancienne resommation par statut qui double-comptait
        //    le TTC complet d'un BCR partiellement engagé.
        $this->load('provisions');

        foreach ($this->provisions as $prov) {
            $consommeParBcr = \App\Models\ProvisionConsommation::where('provision_ligne_regie_id', $prov->id)
                ->sum('montant');

            $consommeParDepenseDirecte = \App\Models\DepenseRegie::where('provision_ligne_regie_id', $prov->id)
                ->whereIn('statut', ['valide', 'paye'])
                ->sum('montant_ttc');

            $consommeProv = (float) $consommeParBcr + (float) $consommeParDepenseDirecte;

            $prov->updateQuietly([
                'montant_consomme'   => $consommeProv,
                'montant_disponible' => $prov->montant_provisionne - $consommeProv,
            ]);
        }

        // ✅ Mettre à jour le décaissement — dépenses réellement livrées/payées.
        //    Basé sur la consommation RÉELLE en cascade (provision_consommations),
        //    pas sur le TTC complet des BCR attribués à leur "provision principale"
        //    (qui double/mal-comptait en cas de répartition sur plusieurs décaissements).
        $statutsDepenses = ['livre', 'livre_partiellement', 'paye'];
        $provisionIds    = $this->provisions->pluck('id');

        $totalDepense = $this->depenses()
            ->whereIn('statut', ['valide', 'paye'])
            ->sum('montant_ttc');

        $totalIrDepenses = $this->depenses()
            ->whereIn('statut', ['valide', 'paye'])
            ->sum('montant_ir');

        // Consommations BCR réellement imputées aux provisions de CE décaissement,
        // limitées aux BCR au statut "réellement dépensé" — et pondérées à l'IR
        // proportionnellement à la part prise sur ce décaissement.
        $consommationsBcr = \App\Models\ProvisionConsommation::whereIn('provision_ligne_regie_id', $provisionIds)
            ->with('bonCommandeRegie')
            ->get()
            ->filter(fn($c) => $c->bonCommandeRegie && in_array($c->bonCommandeRegie->statut, $statutsDepenses));

        $totalBcr   = (float) $consommationsBcr->sum('montant');
        $totalIrBcr = (float) $consommationsBcr->sum(function ($c) {
            $bcr = $c->bonCommandeRegie;
            if ((float) $bcr->montant_ttc <= 0) {
                return 0;
            }
            // Part d'IR proportionnelle au montant réellement pris sur ce décaissement
            return (float) $c->montant * ((float) $bcr->montant_ir / (float) $bcr->montant_ttc);
        });
        $totalDepenseGlobal = $totalDepense + $totalBcr;
        $totalIr            = $totalIrDepenses + $totalIrBcr;

        $this->updateQuietly([
            'montant_depense'     => $totalDepenseGlobal,
            'montant_ir_collecte' => $totalIr,
            'montant_solde'       => ($this->montant_accorde ?? 0) - $totalDepenseGlobal,
        ]);

        // ✅ Mettre à jour la régie parente
        $regie = $this->regieAvance;
        if ($regie) {
            $totalDepenseRegie = $regie->decaissements()
                ->whereIn('statut', ['verse', 'apure'])
                ->sum('montant_depense');

            $regie->updateQuietly([
                'montant_depense' => $totalDepenseRegie,
            ]);
        }
    }

    // =========================================================
    // ✅ CRÉER LES PROVISIONS AUTOMATIQUEMENT — déplacé depuis
    // DecaissementsRelationManager pour être réutilisable (notamment
    // par RegieAvance::reapprovisionner(), qui crée une tranche sans
    // passer par l'action "Accorder & Verser" du RelationManager).
    // =========================================================
    public function creerProvisions(RegieAvance $regie, float $montantAccorde): void
    {
        $lignes = $regie->lignes()->with('nomenclature')->get();

        if ($lignes->isEmpty()) {
            return;
        }

        // ── RAV : 1 seule ligne → provision automatique totale ──
        if ($regie->type === 'rav' || $lignes->count() === 1) {
            $ligne = $lignes->first();

            $existante = ProvisionLigneRegie::where([
                'decaissement_regie_id' => $this->id,
                'ligne_regie_avance_id' => $ligne->id,
            ])->first();

            if (!$existante) {
                ProvisionLigneRegie::create([
                    'decaissement_regie_id' => $this->id,
                    'ligne_regie_avance_id' => $ligne->id,
                    'montant_provisionne'   => $montantAccorde,
                    'montant_consomme'      => 0,
                    'montant_disponible'    => $montantAccorde,
                ]);
            }

            return;
        }

        // ── Menu Dépense : N lignes → répartition proportionnelle ──
        $totalAlloue = $lignes->sum('montant_alloue');

        if ($totalAlloue <= 0) {
            $montantParLigne = round($montantAccorde / $lignes->count(), 2);
            foreach ($lignes as $index => $ligne) {
                $montant = ($index === $lignes->count() - 1)
                    ? $montantAccorde - ($montantParLigne * ($lignes->count() - 1))
                    : $montantParLigne;

                $this->creerOuMettreAJourProvision($ligne, $montant);
            }
            return;
        }

        $totalCree   = 0;
        $lignesArray = $lignes->values();

        foreach ($lignesArray as $index => $ligne) {
            $proportion = (float) $ligne->montant_alloue / $totalAlloue;

            if ($index === $lignesArray->count() - 1) {
                $montant = round($montantAccorde - $totalCree, 2);
            } else {
                $montant = round($montantAccorde * $proportion, 2);
            }

            $totalCree += $montant;
            $this->creerOuMettreAJourProvision($ligne, $montant);
        }
    }

    public function creerOuMettreAJourProvision(LigneRegieAvance $ligne, float $montant): void
    {
        $existante = ProvisionLigneRegie::where([
            'decaissement_regie_id' => $this->id,
            'ligne_regie_avance_id' => $ligne->id,
        ])->first();

        if ($existante) {
            $existante->updateQuietly([
                'montant_provisionne' => $montant,
                'montant_disponible'  => $montant - $existante->montant_consomme,
            ]);
        } else {
            ProvisionLigneRegie::create([
                'decaissement_regie_id' => $this->id,
                'ligne_regie_avance_id' => $ligne->id,
                'montant_provisionne'   => $montant,
                'montant_consomme'      => 0,
                'montant_disponible'    => $montant,
            ]);
        }
    }
}
