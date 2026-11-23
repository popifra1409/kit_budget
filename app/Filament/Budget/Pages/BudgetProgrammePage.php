<?php

namespace App\Filament\Budget\Pages;

use App\Models\Exercice;
use App\Models\NomenclatureBudgetaire;
use App\Models\PrevisionBudgetProgramme;
use App\Services\Budget\ControlePlafondCbmt;
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
    protected static ?string $navigationGroup = 'Budget & crédits';
    protected static ?int    $navigationSort  = 4;
    protected static string  $view = 'filament.pages.budget-programme';

    public int    $anneeRef;
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
        $this->donnees = $service->collecterDonnees($this->anneeRef);
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
                ])
                ->action(function (array $data) {
                    $this->anneeRef  = (int) $data['annee'];
                    $this->chargerDonnees();
                }),

            // ✅ Saisir les prévisions N+1 et N+2 — Dépenses
            Action::make('saisir_previsions_depenses')
                ->label('Saisir prévisions Dépenses')
                ->icon('heroicon-o-pencil-square')
                ->color('danger')
                ->url(fn() => route('filament.budget.pages.saisie-previsions-budget-programme-page', ['annee' => $this->anneeRef, 'type' => 'depense'])),

            // ✅ Saisir les prévisions N+1 et N+2 — Recettes
            Action::make('saisir_previsions_recettes')
                ->label('Saisir prévisions Recettes')
                ->icon('heroicon-o-pencil-square')
                ->color('success')
                ->url(fn() => route('filament.budget.pages.saisie-previsions-budget-programme-page', ['annee' => $this->anneeRef, 'type' => 'recette'])),

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
                    "Les projections saisies sous l'exercice {$this->anneeRef} sont recopiées un an plus loin"
                        . " (×1,05) sous l'exercice " . ($this->anneeRef + 1) . ", dans la limite des plafonds du CBMT. "
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

                    $resultat = PrevisionBudgetProgramme::reconduireExercice(
                        $exerciceSource->id,
                        $exerciceCible->id
                    );

                    $controlle = app(ControlePlafondCbmt::class);
                    $refusees = 0;
                    foreach ($resultat['refus'] as $refus) {
                        $refusees += (int) $refus['nb_lignes'];
                        Notification::make()->danger()
                            ->title('Plafond CBMT — ' . ($refus['libelle'] ?? 'titre indéterminé')
                                . " · {$refus['annee']}")
                            ->body($controlle->messageRefus($refus))
                            ->send();
                    }

                    if ($resultat['ecrits'] === 0) {
                        Notification::make()->danger()
                            ->title('Aucune ligne reconduite')
                            ->body($refusees === 0
                                ? "Aucune prévision à reconduire depuis {$this->anneeRef}."
                                : "{$refusees} ligne(s) refusée(s) par le plafond du CBMT.")
                            ->send();
                        return;
                    }

                    Notification::make()->success()
                        ->title('✅ Reconduction effectuée')
                        ->body("{$resultat['ecrits']} ligne(s) reconduite(s) vers "
                            . ($this->anneeRef + 1)
                            . ($refusees === 0 ? '' : ", {$refusees} refusée(s) — voir les notices ci-dessus."))
                        ->send();
                }),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_budget_programme') ?? false;
    }
}
