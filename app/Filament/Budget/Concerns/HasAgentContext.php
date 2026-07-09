<?php

namespace App\Filament\Budget\Concerns;

trait HasAgentContext
{
    protected string $agentContextType = '';
    protected string $agentContextPage = '';

    public function bootHasAgentContext(): void
    {
        if (empty($this->agentContextType) && isset($this->record)) {
            $this->agentContextType = $this->detecterTypeRecord();
        }
        if (empty($this->agentContextPage)) {
            $this->agentContextPage = $this->detecterPage();
        }
    }

    public function mountHasAgentContext(): void
    {
        $this->envoyerContexteAgent();
    }

    public function envoyerContexteAgent(): void
    {
        $record  = $this->record ?? null;
        $erreurs = session()->pull('agent_erreurs', []);

        $this->dispatch('agent-contexte', [
            'page'        => $this->agentContextPage ?: $this->detecterPage(),
            'record_id'   => $record?->id,
            'record_type' => $this->agentContextType ?: $this->detecterTypeRecord(),
            'erreurs'     => $erreurs,
            'statut'      => $record?->statut      ?? null,
            'montant'     => $record?->montant_ttc ?? $record?->montant_total ?? null,
            'numero'      => $record?->numero      ?? null,
        ]);
    }

    // ✅ instanceof au lieu de match(::class) — évite le ParseError
    protected function detecterTypeRecord(): string
    {
        if (!isset($this->record)) return '';

        $r = $this->record;

        if ($r instanceof \App\Models\BonCommande)            return 'bon_commande';
        if ($r instanceof \App\Models\DecisionAdministrative) return 'decision_administrative';
        if ($r instanceof \App\Models\Engagement)             return 'engagement';
        if ($r instanceof \App\Models\OrdonnancePaiement)     return 'ordonnance_paiement';
        if ($r instanceof \App\Models\MemoireDepense)         return 'memoire_depense';
        if ($r instanceof \App\Models\BordereauEngagement)    return 'bordereau_engagement';
        if ($r instanceof \App\Models\RegieAvance)            return 'regie_avance';
        if ($r instanceof \App\Models\PrevisionRecette)       return 'prevision_recette';
        if ($r instanceof \App\Models\LigneBudgetaire)        return 'ligne_budgetaire';

        return strtolower(class_basename($r));
    }

    protected function detecterPage(): string
    {
        $class = class_basename(static::class);

        if (str_contains($class, 'View'))   return 'vue_'      . $this->detecterTypeRecord();
        if (str_contains($class, 'Edit'))   return 'edition_'  . $this->detecterTypeRecord();
        if (str_contains($class, 'Create')) return 'creation_' . $this->detecterTypeRecord();
        if (str_contains($class, 'List'))   return 'liste_'    . $this->detecterTypeRecord();

        return strtolower($class);
    }

    public static function signalerErreurAgent(string $message): void
    {
        $erreurs   = session('agent_erreurs', []);
        $erreurs[] = '[' . now()->format('H:i') . '] ' . $message;
        session(['agent_erreurs' => array_slice($erreurs, -5)]);
    }
}
