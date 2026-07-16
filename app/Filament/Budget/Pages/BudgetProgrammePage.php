<?php

namespace App\Filament\Budget\Pages;

use App\Models\Exercice;
use App\Models\NomenclatureBudgetaire;
use App\Models\PrevisionBudgetProgramme;
use App\Services\BudgetProgrammeService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables;

class BudgetProgrammePage extends Page
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationLabel = 'Budget Programme';
    protected static ?string $navigationGroup = 'Gestion Budgétaire';
    protected static ?int    $navigationSort  = 5;
    protected static string  $view = 'filament.pages.budget-programme';

    public int    $anneeRef;
    public string $categorie = 'fonctionnement';
    public array  $donnees   = [];

    public function mount(): void
    {
        $exercice      = Exercice::getActif();
        $this->anneeRef = $exercice?->annee ?? now()->year;
        $this->chargerDonnees();
    }

    public function chargerDonnees(): void
    {
        $service      = new BudgetProgrammeService();
        $this->donnees = $service->collecterDonnees($this->anneeRef, $this->categorie);
    }

    protected function getHeaderActions(): array
    {
        return [
            // ✅ Changer l'année de référence
            Action::make('changer_annee')
                ->label('Année : ' . $this->anneeRef)
                ->icon('heroicon-o-calendar')
                ->color('gray')
                ->form([
                    Forms\Components\Select::make('annee')
                        ->label('Année de référence')
                        ->options(fn() => Exercice::orderByDesc('annee')
                            ->pluck('annee', 'annee')->toArray())
                        ->default($this->anneeRef)
                        ->required(),
                    Forms\Components\Select::make('categorie')
                        ->label('Catégorie')
                        ->options([
                            'fonctionnement' => 'Dépenses de Fonctionnement',
                            'investissement' => 'Dépenses d\'Investissement',
                        ])
                        ->default($this->categorie),
                ])
                ->action(function (array $data) {
                    $this->anneeRef  = (int) $data['annee'];
                    $this->categorie = $data['categorie'];
                    $this->chargerDonnees();
                }),

            // ✅ Saisir les prévisions N+1 et N+2
            Action::make('saisir_previsions')
                ->label('Saisir prévisions N+1/N+2')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->url(fn() => route('filament.budget.pages.saisie-previsions-budget-programme-page', ['annee' => $this->anneeRef])),

            // ✅ Export Excel
            Action::make('export_excel')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->url(fn() => route('budget-programme.export.excel', ['annee' => $this->anneeRef]))
                ->openUrlInNewTab(),

            // ✅ Reconduire prévisions
            Action::make('reconduire')
                ->label('Reconduire vers ' . ($this->anneeRef + 1))
                ->icon('heroicon-o-arrow-right-circle')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Reconduire les prévisions')
                ->modalDescription(
                    fn() =>
                    "Les prévisions de {$this->anneeRef} seront reconduites vers "
                        . ($this->anneeRef + 1) . " avec une progression de 5%. "
                        . "Vous pourrez les ajuster ensuite."
                )
                ->action(function () {
                    $exerciceSource = Exercice::where('annee', $this->anneeRef)->first();
                    $exerciceCible  = Exercice::where('annee', $this->anneeRef + 1)->first();

                    if (!$exerciceSource || !$exerciceCible) {
                        Notification::make()->danger()
                            ->title('Exercice introuvable')
                            ->body("Créez d'abord les exercices {$this->anneeRef} et " . ($this->anneeRef + 1))
                            ->send();
                        return;
                    }

                    $count = PrevisionBudgetProgramme::reconduireExercice(
                        $exerciceSource->id,
                        $exerciceCible->id
                    );

                    Notification::make()->success()
                        ->title('✅ Reconduction effectuée')
                        ->body("{$count} lignes reconduites vers " . ($this->anneeRef + 1))
                        ->send();
                }),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_budget_programme') ?? false;
    }
}
