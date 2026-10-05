<?php
// app/Services/Budget/MouvementCreditService.php

namespace App\Services\Budget;

use App\Models\Budget;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\SousProgrammeEp;
use App\Models\Tache;
use App\Models\VirementBudgetaire;
use App\Services\ParametresExecution;
use DomainException;

/**
 * Mouvements de crédits (aménagements du principe de spécialité).
 *
 *  - Type : fongibilité (même sous-programme) ou virement (sous-programmes différents),
 *    déduit des lignes source et destination ; transfert : saisi explicitement.
 *  - Plafond : cumul annuel des VIREMENTS de gestion ≤ x % des crédits ouverts
 *    (taux, base et mode bloquant/avertissement : paramètres d'exécution).
 *    Les virements issus d'un collectif budgétaire n'y entrent pas.
 *  - Décideur requis selon le type (paramètres d'exécution).
 */
class MouvementCreditService
{
    /** Correspondance programme budgétaire → sous-programme stratégique, par exercice. */
    protected array $carteProgrammes = [];

    // ════════════════════════════════════════════════════════
    // SOUS-PROGRAMME D'UNE LIGNE ET TYPE DU MOUVEMENT
    // ════════════════════════════════════════════════════════

    /**
     * Sous-programme stratégique d'une ligne budgétaire, via les tâches qui portent son compte :
     * tâche → activité → action → programme → sous-programme (règle générique du Module 01).
     * Renvoie null si aucun, ou si le compte est porté par plusieurs sous-programmes (ambigu).
     */
    public function sousProgrammeDe(?LigneBudgetaire $ligne): ?SousProgrammeEp
    {
        if (!$ligne?->nomenclature_id) {
            return null;
        }

        $exerciceId = $ligne->budget?->exercice_id ?? Exercice::getActif()?->id;
        $carte = $this->carte($exerciceId);

        $sousProgrammes = Tache::withoutGlobalScope('exercice')
            ->where('nomenclature_id', $ligne->nomenclature_id)
            ->with(['activite' => fn($q) => $q->withoutGlobalScope('exercice')->with(['action' => fn($a) => $a->withoutGlobalScope('exercice')])])
            ->get()
            ->map(fn($t) => $carte[$t->activite?->action?->programme_id] ?? null)
            ->filter()
            ->unique()
            ->values();

        return $sousProgrammes->count() === 1 ? SousProgrammeEp::find($sousProgrammes->first()) : null;
    }

    /** programme_id => sous_programme_ep_id, pour un exercice (règle générique programmesPourExercice). */
    protected function carte(?int $exerciceId): array
    {
        if (!$exerciceId) {
            return [];
        }

        if (isset($this->carteProgrammes[$exerciceId])) {
            return $this->carteProgrammes[$exerciceId];
        }

        // ⚠️ Boucle explicite : flatMap() fusionne avec array_merge(), qui RENUMÉROTE les clés
        //    numériques (les identifiants de programmes seraient perdus).
        $carte = [];
        foreach (SousProgrammeEp::all() as $sp) {
            foreach ($sp->programmesPourExercice($exerciceId) as $programmeId) {
                $carte[$programmeId] = $sp->id;
            }
        }

        return $this->carteProgrammes[$exerciceId] = $carte;
    }

    /**
     * Type et sous-programmes d'un mouvement entre deux lignes.
     * @return array{type: string, sp_source: ?SousProgrammeEp, sp_destination: ?SousProgrammeEp, determine: bool}
     */
    public function qualifier(?LigneBudgetaire $source, ?LigneBudgetaire $destination): array
    {
        $spSource = $this->sousProgrammeDe($source);
        $spDestination = $this->sousProgrammeDe($destination);
        $determine = $spSource !== null && $spDestination !== null;

        return [
            // Par prudence, un mouvement dont un sous-programme est inconnu est traité en virement (plafonné)
            'type'           => $determine && $spSource->id === $spDestination->id ? 'fongibilite' : 'virement',
            'sp_source'      => $spSource,
            'sp_destination' => $spDestination,
            'determine'      => $determine,
        ];
    }

    /** Type effectif d'un mouvement (enregistré, sinon déduit). */
    public function typeDe(VirementBudgetaire $v): string
    {
        return $v->type_mouvement ?: $this->qualifier($v->ligneSource, $v->ligneDestination)['type'];
    }

    public static function libelleType(?string $type): string
    {
        return config("execution.types_mouvement.{$type}.libelle", $type ?? '—');
    }

    // ════════════════════════════════════════════════════════
    // DÉCIDEUR
    // ════════════════════════════════════════════════════════

    public function decideurRequis(string $type): ?string
    {
        $parametre = config("execution.types_mouvement.{$type}.parametre_decideur");

        return $parametre ? ParametresExecution::get($parametre) : null;
    }

    public static function optionsDecideurs(): array
    {
        return config('execution.parametres.decideur_virement.options', []);
    }

    // ════════════════════════════════════════════════════════
    // PLAFOND
    // ════════════════════════════════════════════════════════

    /** Crédits ouverts du budget, selon la base paramétrée (initial ou actualisé). */
    public function creditsOuverts(int $budgetId): float
    {
        return ParametresExecution::get('base_plafonds') === 'actualise'
            ? (float) app(HistoriqueLigneBudgetaireService::class)->syntheseBudgets([$budgetId])['budget_actualise']
            : (float) LigneBudgetaire::withoutGlobalScope('exercice')->where('budget_id', $budgetId)->sum('budget_initial');
    }

    /** Cumul annuel des VIREMENTS de gestion approuvés ou exécutés sur le budget. */
    public function cumulVirements(int $budgetId, ?int $sauf = null): float
    {
        return (float) VirementBudgetaire::withoutGlobalScope('exercice')
            ->where('budget_id', $budgetId)
            ->where(fn($q) => $q->whereNull('origine')->orWhere('origine', '!=', 'collectif'))
            ->whereIn('statut', ['approuve', 'execute'])
            ->when($sauf, fn($q) => $q->whereKeyNot($sauf))
            ->with(['ligneSource', 'ligneDestination'])
            ->get()
            ->filter(fn($v) => $this->typeDe($v) === 'virement')
            ->sum('montant');
    }

    /**
     * Contrôle du plafond pour un mouvement (montant ajouté au cumul existant).
     * @return array{plafonne: bool, credits_ouverts: float, cumul_avant: float, cumul_apres: float,
     *               taux_apres: float, plafond_pct: float, plafond_montant: float, ok: bool, bloquant: bool, base: string}
     */
    public function controlerPlafond(int $budgetId, string $type, float $montant, ?int $sauf = null, string $origine = 'gestion'): array
    {
        $plafonne = (bool) config("execution.types_mouvement.{$type}.plafonne") && $origine !== 'collectif';
        $credits = $this->creditsOuverts($budgetId);
        $pct = (float) ParametresExecution::get('plafond_virements_pct');
        $avant = $plafonne ? $this->cumulVirements($budgetId, $sauf) : 0.0;
        $apres = $avant + ($plafonne ? $montant : 0);

        return [
            'plafonne'        => $plafonne,
            'credits_ouverts' => $credits,
            'base'            => ParametresExecution::get('base_plafonds'),
            'cumul_avant'     => $avant,
            'cumul_apres'     => $apres,
            'taux_apres'      => $credits > 0 ? round($apres / $credits * 100, 3) : 0.0,
            'plafond_pct'     => $pct,
            'plafond_montant' => round($credits * $pct / 100, 0),
            'ok'              => !$plafonne || $apres <= $credits * $pct / 100 + 0.01,
            'bloquant'        => ParametresExecution::get('depassement_plafond') === 'bloquant',
        ];
    }

    public function resumePlafond(array $c): string
    {
        if (!$c['plafonne']) {
            return 'Non soumis au plafond des virements.';
        }

        $f = fn($m) => number_format($m, 0, ',', ' ');

        return ($c['ok'] ? '✅' : ($c['bloquant'] ? '❌' : '⚠️'))
            . " Cumul des virements après ce mouvement : {$f($c['cumul_apres'])} FCFA, soit {$c['taux_apres']} % des crédits ouverts"
            . " ({$f($c['credits_ouverts'])} FCFA, base " . ($c['base'] === 'actualise' ? 'actualisée' : 'initiale') . ")"
            . " — plafond {$c['plafond_pct']} % ({$f($c['plafond_montant'])} FCFA).";
    }

    // ════════════════════════════════════════════════════════
    // ANALYSE ET IMPACT (« avant »)
    // ════════════════════════════════════════════════════════

    /** Situation d'une ligne : dotation actualisée, engagé, disponible, taux d'exécution. */
    public function situationLigne(?LigneBudgetaire $ligne): ?array
    {
        if (!$ligne) {
            return null;
        }

        $dotation = (float) $ligne->getBudgetRectifieReel();
        $engage = (float) $ligne->engage;

        return [
            'code'       => $ligne->nomenclature?->code,
            'dotation'   => $dotation,
            'engage'     => $engage,
            'disponible' => (float) $ligne->disponible_engagement,
            'taux'       => $dotation > 0 ? round($engage / $dotation * 100, 1) : 0.0,
        ];
    }

    /** Éléments de performance du sous-programme touché (activités et indicateurs). */
    public function impactPerformance(?SousProgrammeEp $sp, ?int $exerciceId = null): ?array
    {
        if (!$sp) {
            return null;
        }

        $activites = $sp->activitesPourExercice($exerciceId)->get();

        return [
            'sous_programme' => "{$sp->code} — {$sp->libelle}",
            'activites'      => $activites->count(),
            'indicateurs'    => $sp->indicateurs()->count()
                + \App\Models\Indicateur::whereIn('indicateurable_type', ['activite', \App\Models\Activite::class])
                ->whereIn('indicateurable_id', $activites->pluck('id'))->count(),
        ];
    }

    // ════════════════════════════════════════════════════════
    // APPROBATION (« pendant »)
    // ════════════════════════════════════════════════════════

    /**
     * Contrôles avant approbation d'un mouvement de gestion :
     *  - acte formel (référence et date) ;
     *  - plafond (bloquant ou avertissement selon le paramètre).
     * Renvoie le contrôle de plafond, à figer sur le mouvement.
     */
    public function verifierAvantApprobation(VirementBudgetaire $v): array
    {
        if ($v->origine === 'collectif') {
            return ['plafonne' => false];
        }

        if (blank($v->reference_decision) || blank($v->date_acte)) {
            throw new DomainException("Acte formel requis : renseignez la référence et la date de la décision avant l'approbation.");
        }

        $type = $this->typeDe($v);
        $controle = $this->controlerPlafond((int) $v->budget_id, $type, (float) $v->montant, $v->id, $v->origine ?? 'gestion');

        if (!$controle['ok'] && $controle['bloquant']) {
            throw new DomainException('Plafond des virements dépassé : ' . $this->resumePlafond($controle));
        }

        return $controle + ['type' => $type, 'date_controle' => now()->toDateTimeString()];
    }
}
