<?php

namespace App\Filament\Budget\Pages;

use App\Models\CollectifBudgetaire;
use App\Services\Budget\ReinitialisationCollectifsService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Sélectionner un ou plusieurs collectifs, voir précisément ce que leur
 * annulation modifierait (aperçu), puis exécuter.
 *
 * Volontairement ouvert aux collectifs « adoptés » — c'est le cas d'usage —
 * mais bloqué par le service lorsqu'un retrait ferait passer une ligne sous
 * zéro : disponible négatif après désengagement, recouvrement supérieur à la
 * prévision restante, ou ligne créée encore portée par des écritures extérieures.
 */
class ReinitialisationCollectifsPage extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-arrow-uturn-left';
    protected static ?string $navigationLabel = 'Réinitialiser les collectifs';
    protected static ?string $title           = 'Réinitialisation des collectifs budgétaires';
    protected static ?string $navigationGroup = 'Gestion Budgétaire';
    protected static ?int    $navigationSort  = 9;
    protected static string  $view = 'filament.pages.reinitialisation-collectifs';

    /** @var array<int, int> */
    public array $selectionnes = [];

    public ?array $rapport = null;

    /**
     * Les lignes qui retombent sous ce qui est déjà engagé ne bloquent plus :
     * le retrait est exécuté quand même et la ligne ressort surengagée. Réservé
     * aux remises à plat, les engagements eux-mêmes ne sont pas touchés.
     */
    public bool $tolererSurengagement = false;

    /** @var list<array{id: int, numero: ?string, libelle: string, date: ?string, statut: string, mouvements: int}> */
    public array $lignesCollectifs = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('delete_collectif_budgetaire') ?? false;
    }

    public function mount(): void
    {
        $this->charger();
    }

    /**
     * Collectifs de l'exercice courant, du plus récent au plus ancien.
     */
    public function charger(): void
    {
        $this->lignesCollectifs = CollectifBudgetaire::withCount('mouvements')
            ->orderByDesc('date_collectif')
            ->orderByDesc('id')
            ->get()
            ->map(fn(CollectifBudgetaire $c) => [
                'id'         => (int) $c->id,
                'numero'     => $c->numero,
                'libelle'    => $c->libelle,
                'date'       => $c->date_collectif?->format('d/m/Y'),
                'statut'     => (string) $c->statut,
                'mouvements' => (int) $c->mouvements_count,
            ])
            ->values()
            ->all();
    }

    public function toggle(int $id): void
    {
        $courants = $this->selectionnesTries();
        $position = array_search($id, $courants, true);

        if ($position === false) {
            $courants[] = $id;
        } else {
            unset($courants[$position]);
        }

        sort($courants);
        $this->selectionnes = $courants;

        $this->actualiserApercu();
    }

    public function toutSelectionner(): void
    {
        $this->selectionnes = array_map(fn($l) => $l['id'], $this->lignesCollectifs);
        $this->actualiserApercu();
    }

    public function viderSelection(): void
    {
        $this->selectionnes = [];
        $this->rapport = null;
    }

    /**
     * L'aperçu suit la sélection : le bouton d'exécution s'active dès qu'il est
     * sûr qu'aucun blocage n'empêche la réinitialisation.
     */
    public function analyser(): void
    {
        $this->actualiserApercu();
    }

    /** Hook Livewire : changer l'option recalcule l'aperçu immédiatement. */
    public function updatedTolererSurengagement(): void
    {
        $this->actualiserApercu();
    }

    private function actualiserApercu(): void
    {
        $this->rapport = $this->selectionnes === []
            ? null
            : app(ReinitialisationCollectifsService::class)
                ->analyser($this->selectionnes, $this->tolererSurengagement);
    }

    public function reinitialiser(): void
    {
        if (!$this->peutExecuter()) {
            Notification::make()
                ->danger()
                ->title('Réinitialisation impossible')
                ->body('Relancez d’abord l’analyse, ou levez les blocages signalés.')
                ->send();
            return;
        }

        try {
            $rapport = app(ReinitialisationCollectifsService::class)
                ->reinitialiser($this->selectionnes, auth()->user(), $this->tolererSurengagement);
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Réinitialisation annulée')
                ->body($e->getMessage())
                ->send();
            return;
        }

        $ecartes = (int) ($rapport['nb_ecartes'] ?? 0);
        $avertissements = (int) ($rapport['nb_avertissements'] ?? 0);

        $this->selectionnes = [];
        $this->rapport = null;
        $this->charger();

        Notification::make()
            ->success()
            ->title('Réinitialisation effectuée')
            ->body($rapport['nb_collectifs'] . ' collectif(s) annulé(s) et supprimé(s), lignes budgétaires et '
                . 'tableau de bord rétablis.'
                . ($ecartes > 0 ? " {$ecartes} collectif(s) restent en place : leurs blocages ne sont pas levés." : '')
                . ($avertissements > 0 ? " {$avertissements} ligne(s) ressortent surengagée(s), à réabonder." : ''))
            ->send();
    }

    public function peutExecuter(): bool
    {
        // Un collectif bloqué ne doit pas tenir les autres en échec : il suffit
        // qu'au moins un soit prêt. Les bloqués sont laissés inchangés.
        if (($this->rapport['nb_prets'] ?? 0) === 0) {
            return false;
        }

        $analyse = $this->idsDuRapport();

        // L'aperçu n'est valable que s'il porte exactement sur la sélection actuelle.
        return $analyse !== [] && $analyse === $this->selectionnesTries();
    }

    private function descriptionConfirmation(): string
    {
        $prets   = (int) ($this->rapport['nb_prets'] ?? 0);
        $ecartes = (int) ($this->rapport['nb_ecartes'] ?? 0);
        $surengagees = (int) ($this->rapport['nb_surengagees'] ?? 0);

        return "Les {$prets} collectif(s) sans blocage seront annulés puis supprimés : effets recalculés sur les "
            . 'lignes touchées, lignes et virements créés par ces collectifs supprimés logiquement.'
            . ($ecartes > 0 ? " {$ecartes} collectif(s) encore bloqué(s) resteront inchangés." : '')
            . ($surengagees > 0
                ? " Attention : {$surengagees} ligne(s) auront moins de crédits que ce qui est déjà engagé — elles "
                    . 'ressortiront surengagées et devront être réabondées.'
                : '')
            . ' Cette action est définitive.';
    }

    /**
     * @return list<int>
     */
    public function selectionnesTries(): array
    {
        $ids = array_map('intval', $this->selectionnes);
        sort($ids);
        return $ids;
    }

    /**
     * @return list<int>
     */
    private function idsDuRapport(): array
    {
        $ids = array_map(fn($c) => (int) $c['id'], $this->rapport['collectifs'] ?? []);
        sort($ids);
        return $ids;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('analyser')
                ->label('Actualiser l\'aperçu')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn() => $this->analyser())
                ->disabled(fn() => $this->selectionnes === []),

            Action::make('reinitialiser')
                ->label('Réinitialiser la sélection')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Réinitialiser les collectifs sélectionnés ?')
                ->modalDescription(fn() => $this->descriptionConfirmation())
                ->modalSubmitActionLabel('Confirmer la réinitialisation')
                ->action(fn() => $this->reinitialiser())
                ->disabled(fn() => !$this->peutExecuter()),
        ];
    }
}
