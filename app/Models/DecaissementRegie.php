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
        // ✅ Statuts considérés comme "réellement dépensés"
        $statutsDepenses = ['livre', 'livre_partiellement', 'paye'];

        // Dépenses directes (AchatDirect) rattachées à ce décaissement
        $totalDepense = $this->depenses()
            ->whereIn('statut', ['valide', 'paye'])
            ->sum('montant_ttc');

        $totalIrDepenses = $this->depenses()
            ->whereIn('statut', ['valide', 'paye'])
            ->sum('montant_ir');

        // ✅ BCR réellement dépensés : uniquement livre/paye — PAS valide
        $bcrDepenses = \App\Models\BonCommandeRegie::whereHas(
            'provisionLigneRegie',
            fn($q) => $q->where('decaissement_regie_id', $this->id)
        )
            ->whereIn('statut', $statutsDepenses)
            ->get();

        $totalBcr   = $bcrDepenses->sum('montant_ttc');
        $totalIrBcr = $bcrDepenses->sum('montant_ir');

        // ✅ BCR engagés (valide + engage) = montant réservé mais pas encore payé
        $bcrEngage = \App\Models\BonCommandeRegie::whereHas(
            'provisionLigneRegie',
            fn($q) => $q->where('decaissement_regie_id', $this->id)
        )
            ->where('statut', 'valide')
            ->where('engage', true)
            ->sum('montant_ttc');

        $totalDepenseGlobal = $totalDepense + $totalBcr;
        $totalIr            = $totalIrDepenses + $totalIrBcr;

        // ✅ Mettre à jour montant_consomme sur chaque provision
        $this->load('provisions');
        foreach ($this->provisions as $prov) {
            $consommeProv =
                \App\Models\BonCommandeRegie::where('provision_ligne_regie_id', $prov->id)
                ->whereIn('statut', $statutsDepenses)
                ->sum('montant_ttc')
                + \App\Models\DepenseRegie::where('provision_ligne_regie_id', $prov->id)
                ->whereIn('statut', ['valide', 'paye'])
                ->sum('montant_ttc');

            $engageProv = \App\Models\BonCommandeRegie::where('provision_ligne_regie_id', $prov->id)
                ->where('statut', 'valide')
                ->where('engage', true)
                ->sum('montant_ttc');

            $prov->updateQuietly([
                'montant_consomme'   => $consommeProv,
                'montant_disponible' => $prov->montant_provisionne - $consommeProv,
            ]);
        }

        // ✅ Mettre à jour le décaissement
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
}
