<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrevisionRecetteMensuelle extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'previsions_recettes_mensuelles';

    protected $fillable = [
        'ligne_prevision_recette_id',
        'exercice_id',
        'mois',
        'annee',
        'montant_prevu',
        'montant_recouvre',
        'ecart',
        'taux_realisation',
        'montant_cumule_prevu',
        'montant_cumule_recouvre',
        'taux_realisation_cumule',
        'actif',
        'observations',
    ];

    protected $casts = [
        'mois' => 'integer',
        'annee' => 'integer',
        'montant_prevu' => 'decimal:2',
        'montant_recouvre' => 'decimal:2',
        'ecart' => 'decimal:2',
        'taux_realisation' => 'decimal:2',
        'montant_cumule_prevu' => 'decimal:2',
        'montant_cumule_recouvre' => 'decimal:2',
        'taux_realisation_cumule' => 'decimal:2',
        'actif' => 'boolean',
    ];

    // ====================================
    // RELATIONS
    // ====================================

    public function lignePrevisionRecette(): BelongsTo
    {
        return $this->belongsTo(LignePrevisionRecette::class);
    }

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function recettesReelles(): HasMany
    {
        return $this->hasMany(RecetteReelle::class, 'prevision_recette_mensuelle_id');
    }

    // ====================================
    // ACCESSEURS
    // ====================================

    public function getNomMoisAttribute(): string
    {
        $mois = [
            1 => 'Janvier',
            2 => 'Février',
            3 => 'Mars',
            4 => 'Avril',
            5 => 'Mai',
            6 => 'Juin',
            7 => 'Juillet',
            8 => 'Août',
            9 => 'Septembre',
            10 => 'Octobre',
            11 => 'Novembre',
            12 => 'Décembre'
        ];

        return $mois[$this->mois] ?? '';
    }

    public function getPeriodeAttribute(): string
    {
        return $this->nom_mois . ' ' . $this->annee;
    }

    public function getMontantRestantAttribute(): float
    {
        return max(0, $this->montant_prevu - $this->montant_recouvre);
    }

    public function getEstSurperformanceAttribute(): bool
    {
        return $this->montant_recouvre > $this->montant_prevu;
    }

    public function getEstSousperformanceAttribute(): bool
    {
        return $this->montant_recouvre < $this->montant_prevu;
    }

    // ====================================
    // CALCULS
    // ====================================

    public function calculerMontantRecouvre(): float
    {
        $total = $this->recettesReelles()
            ->whereIn('statut', ['encaissee', 'comptabilisee', 'validee'])
            ->sum('montant');

        $this->update(['montant_recouvre' => $total]);
        return $total;
    }

    public function calculerEcart(): float
    {
        $ecart = $this->montant_recouvre - $this->montant_prevu;
        $this->update(['ecart' => $ecart]);
        return $ecart;
    }

    public function calculerTauxRealisation(): float
    {
        $taux = $this->montant_prevu > 0 ? ($this->montant_recouvre / $this->montant_prevu) * 100 : 0;
        $this->update(['taux_realisation' => $taux]);
        return $taux;
    }

    public function calculerCumules(): void
    {
        $previsions = static::where('ligne_prevision_recette_id', $this->ligne_prevision_recette_id)
            ->where('annee', $this->annee)
            ->where('mois', '<=', $this->mois)
            ->orderBy('mois')
            ->get();

        $cumulePrevu = $previsions->sum('montant_prevu');
        $cumuleRecouvre = $previsions->sum('montant_recouvre');
        $tauxCumule = $cumulePrevu > 0 ? ($cumuleRecouvre / $cumulePrevu) * 100 : 0;

        $this->update([
            'montant_cumule_prevu' => $cumulePrevu,
            'montant_cumule_recouvre' => $cumuleRecouvre,
            'taux_realisation_cumule' => $tauxCumule,
        ]);
    }

    public function recalculer(): void
    {
        $this->calculerMontantRecouvre();
        $this->calculerEcart();
        $this->calculerTauxRealisation();
        $this->calculerCumules();
    }

    public function recalculerMoisSuivants(): void
    {
        $moisSuivants = static::where('ligne_prevision_recette_id', $this->ligne_prevision_recette_id)
            ->where('annee', $this->annee)
            ->where('mois', '>=', $this->mois)
            ->orderBy('mois')
            ->get();

        foreach ($moisSuivants as $prevision) {
            $prevision->calculerCumules();
        }
    }

    // ====================================
    // MÉTHODES STATIQUES
    // ====================================

    /**
     * Créer les 12 prévisions mensuelles pour une ligne annuelle
     */
    public static function creerPrevisionsAnnuelles(LignePrevisionRecette $ligne): void
    {
        // ✅ SQL brut — évite brick/math sur decimal:2
        $ligneData = \DB::table('lignes_previsions_recettes')
            ->where('id', $ligne->id)
            ->selectRaw('id, prevision_recette_id, CAST(montant_rectifie AS FLOAT) as montant')
            ->first();

        if (!$ligneData) return;

        $prevision = \DB::table('previsions_recettes')
            ->where('id', $ligneData->prevision_recette_id)
            ->select('exercice_id')
            ->first();

        if (!$prevision) {
            throw new \RuntimeException("Prévision introuvable pour la ligne {$ligne->id}");
        }

        $exercice = \DB::table('exercices')
            ->where('id', $prevision->exercice_id)
            ->select('id', 'annee')
            ->first();

        if (!$exercice) {
            throw new \RuntimeException("Exercice introuvable pour la prévision {$prevision->exercice_id}");
        }

        // ✅ float natif — pas de BigDecimal
        $montantMensuel = round((float)$ligneData->montant / 12, 2);
        $now            = now()->toDateTimeString();

        $values   = [];
        $bindings = [];

        for ($mois = 1; $mois <= 12; $mois++) {
            $values[]   = '(?,?,?,?,?,0,?,0,0,0,0,true,?,?)';
            $bindings[] = $ligne->id;
            $bindings[] = $exercice->id;
            $bindings[] = $mois;
            $bindings[] = $exercice->annee;
            $bindings[] = $montantMensuel;
            $bindings[] = -$montantMensuel;
            $bindings[] = $now;
            $bindings[] = $now;
        }

        // ✅ 1 requête au lieu de 12
        \DB::statement("
        INSERT INTO previsions_recettes_mensuelles
            (ligne_prevision_recette_id, exercice_id, mois, annee,
             montant_prevu, montant_recouvre, ecart,
             taux_realisation, montant_cumule_prevu,
             montant_cumule_recouvre, taux_realisation_cumule,
             actif, created_at, updated_at)
        VALUES " . implode(',', $values) . "
        ON CONFLICT ON CONSTRAINT unique_ligne_mois
        DO UPDATE SET
            montant_prevu = EXCLUDED.montant_prevu,
            exercice_id   = EXCLUDED.exercice_id,
            updated_at    = EXCLUDED.updated_at
    ", $bindings);
    }

    public static function redistribuerMontant(LignePrevisionRecette $ligne, float $montantAnnuel): void
    {
        $montantMensuel = $montantAnnuel / 12;
        static::where('ligne_prevision_recette_id', $ligne->id)
            ->where('annee', $ligne->previsionRecette()->first()->exercice()->first()->annee)
            ->update(['montant_prevu' => $montantMensuel]);
    }

    // ====================================
    // SCOPES
    // ====================================

    public function scopeMois($query, int $mois)
    {
        return $query->where('mois', $mois);
    }

    public function scopeAnnee($query, int $annee)
    {
        return $query->where('annee', $annee);
    }

    public function scopePeriode($query, int $mois, int $annee)
    {
        return $query->where('mois', $mois)->where('annee', $annee);
    }

    public function scopeActives($query)
    {
        return $query->where('actif', true);
    }

    public function scopeSurperformance($query)
    {
        return $query->whereColumn('montant_recouvre', '>', 'montant_prevu');
    }

    public function scopeSousperformance($query)
    {
        return $query->whereColumn('montant_recouvre', '<', 'montant_prevu');
    }

    // ====================================
    // BOOT
    // ====================================

    protected static function boot()
    {
        parent::boot();

        static::saved(function ($prevision) {
            $prevision->calculerEcart();
            $prevision->calculerTauxRealisation();
        });
    }
}
