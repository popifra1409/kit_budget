<?php

namespace App\Filament\Budget\Concerns;

trait HasAgentContext
{
    protected string $agentContextType = '';
    protected string $agentContextPage = '';

    // =========================================================
    // ✅ UNIQUEMENT mountHasAgentContext — pas de boot()
    //    boot() s'exécute avant l'initialisation de $table
    //    sur ListRecords → "must not be accessed before initialization"
    //    mount() s'exécute après que tout soit prêt
    // =========================================================
    public function mountHasAgentContext(): void
    {
        // Sécurité — ne rien faire si le composant n'est pas prêt
        try {
            $this->envoyerContexteAgent();
        } catch (\Throwable $e) {
            // Silencieux — l'agent est optionnel, ne doit pas bloquer la page
        }
    }

    public function envoyerContexteAgent(): void
    {
        // ✅ isset() avant toute lecture de $this->record
        $record  = isset($this->record) ? $this->record : null;
        $erreurs = session()->pull('agent_erreurs', []);

        $type = $this->agentContextType ?: $this->detecterTypeRecord($record);
        $page = $this->agentContextPage ?: $this->detecterPage($type);

        $this->dispatch('agent-contexte', [
            'page'        => $page,
            'record_id'   => $record?->id,
            'record_type' => $type,
            'erreurs'     => $erreurs,
            'statut'      => $record?->statut      ?? null,
            'montant'     => $record?->montant_ttc ?? $record?->montant_total ?? null,
            'numero'      => $record?->numero      ?? null,
        ]);
    }

    // ✅ $record passé en paramètre (pas de $this->record direct)
    protected function detecterTypeRecord($record = null): string
    {
        if (!$record) return '';

        if ($record instanceof \App\Models\BonCommande)            return 'bon_commande';
        if ($record instanceof \App\Models\DecisionAdministrative) return 'decision_administrative';
        if ($record instanceof \App\Models\Engagement)             return 'engagement';
        if ($record instanceof \App\Models\OrdonnancePaiement)     return 'ordonnance_paiement';
        if ($record instanceof \App\Models\MemoireDepense)         return 'memoire_depense';
        if ($record instanceof \App\Models\BordereauEngagement)    return 'bordereau_engagement';
        if ($record instanceof \App\Models\RegieAvance)            return 'regie_avance';
        if ($record instanceof \App\Models\PrevisionRecette)       return 'prevision_recette';
        if ($record instanceof \App\Models\LigneBudgetaire)        return 'ligne_budgetaire';

        return strtolower(class_basename($record));
    }

    protected function detecterPage(string $type = ''): string
    {
        $class = class_basename(static::class);

        if (str_contains($class, 'View'))   return 'vue_'      . $type;
        if (str_contains($class, 'Edit'))   return 'edition_'  . $type;
        if (str_contains($class, 'Create')) return 'creation_' . $type;
        if (str_contains($class, 'List'))   return 'liste_'    . $type;

        return strtolower($class);
    }

    public static function signalerErreurAgent(string $message): void
    {
        $erreurs   = session('agent_erreurs', []);
        $erreurs[] = '[' . now()->format('H:i') . '] ' . $message;
        session(['agent_erreurs' => array_slice($erreurs, -5)]);
    }
}
