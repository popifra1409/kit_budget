<?php

namespace App\Filament\Budget\Widgets;

use Filament\Widgets\Widget;
use Livewire\Attributes\On;
use App\Services\AgentBudgetaireService;

class AgentBudgetaireWidget extends Widget
{
    protected static string $view = 'filament.widgets.agent-budgetaire';
    protected static bool $isLazy = false;

    // ── État du chat ──────────────────────────────────────────
    public bool   $ouvert        = false;
    public string $messageInput  = '';
    public array  $messages      = [];
    public array  $apiMessages   = [];
    public bool   $enAttente     = false;

    // ── Contexte injecté depuis la page ───────────────────────
    public string $pageCourante = '';
    public ?int   $recordId     = null;
    public string $recordType   = '';
    public array  $dernieresErreurs = [];

    // Message de bienvenue à l'ouverture
    public function ouvrir(): void
    {
        $this->ouvert = true;
        if (empty($this->messages)) {
            $user = auth()->user();
            $this->messages[] = [
                'role'    => 'assistant',
                'content' => "👋 Bonjour **{$user->name}** ! Je suis Budget Suite-Assistant.\n\n"
                    . "Je peux vous aider à :\n"
                    . "- 🔍 Comprendre pourquoi une action est bloquée\n"
                    . "- 📋 Expliquer le workflow des documents\n"
                    . "- 💰 Vérifier les disponibilités budgétaires\n"
                    . "- 🔐 Clarifier vos permissions\n\n"
                    . "Que puis-je faire pour vous ?",
                'time'    => now()->format('H:i'),
            ];
        }
    }

    public function fermer(): void { $this->ouvert = false; }

    public function viderHistorique(): void
    {
        $this->messages    = [];
        $this->apiMessages = [];
        $this->ouvrir();
    }

    // ── Envoi d'un message ────────────────────────────────────
    public function envoyer(): void
    {
        $texte = trim($this->messageInput);
        if (!$texte || $this->enAttente) return;

        $this->messageInput = '';
        $this->enAttente    = true;

        // Ajout au chat affiché
        $this->messages[] = [
            'role'    => 'user',
            'content' => $texte,
            'time'    => now()->format('H:i'),
        ];

        // Ajout à l'historique API
        $this->apiMessages[] = ['role' => 'user', 'content' => $texte];

        // Appel au service
        try {
            $service = app(AgentBudgetaireService::class);
            $reponse = $service->chat(
                $this->apiMessages,
                $this->pageCourante,
                $this->recordId,
                $this->recordType,
                $this->dernieresErreurs
            );
        } catch (\Exception $e) {
            $reponse = "❌ Une erreur est survenue : " . $e->getMessage();
        }

        // Ajout de la réponse
        $this->messages[]    = ['role' => 'assistant', 'content' => $reponse, 'time' => now()->format('H:i')];
        $this->apiMessages[] = ['role' => 'assistant', 'content' => $reponse];

        // Limiter l'historique API à 20 échanges (évite les coûts excessifs)
        if (count($this->apiMessages) > 20) {
            $this->apiMessages = array_slice($this->apiMessages, -20);
        }

        $this->enAttente = false;
    }

    // ── Suggestions rapides ───────────────────────────────────
    public function envoyerSuggestion(string $texte): void
    {
        $this->messageInput = $texte;
        $this->envoyer();
    }

    // ── Recevoir le contexte depuis la page parente ───────────
    #[On('agent-contexte')]
    public function recevoirContexte(array $data): void
    {
        $this->pageCourante     = $data['page']   ?? '';
        $this->recordId         = $data['record_id'] ?? null;
        $this->recordType       = $data['record_type'] ?? '';
        $this->dernieresErreurs = $data['erreurs'] ?? [];
    }

    public static function canView(): bool
    {
        return auth()->check();
    }
}