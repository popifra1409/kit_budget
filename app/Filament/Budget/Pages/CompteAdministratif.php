<?php
// app/Filament/Budget/Pages/CompteAdministratif.php

namespace App\Filament\Budget\Pages;

use App\Exports\CompteAdministratifExport;
use App\Models\Budget;
use App\Models\Exercice;
use App\Models\ParametresStructure;
use App\Services\Budget\CompteAdministratifService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Maatwebsite\Excel\Facades\Excel;

/** Compte administratif d'un exercice : aperçu de la synthèse, exports PDF (conseil) et Excel (travail). */
class CompteAdministratif extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $navigationGroup = 'Contrôle & clôture';
    protected static ?string $navigationLabel = 'Compte administratif';
    protected static ?string $title = 'Compte administratif';
    protected static ?int $navigationSort = 3;
    protected static string $view = 'filament.budget.pages.compte-administratif';

    public ?int $exercice_id = null;
    public ?int $budget_id = null;

    public static function canAccess(): bool
    {
        $u = auth()->user();

        return $u && ($u->hasAnyRole(['super_admin', 'admin']) || $u->can('view_compte_administratif'));
    }

    public function mount(): void
    {
        $exercice = Exercice::getActif() ?? Exercice::orderByDesc('annee')->first();
        $this->form->fill([
            'exercice_id' => $exercice?->id,
            'budget_id'   => $exercice ? $this->budgetPar($exercice->id)?->id : null,
        ]);
    }

    protected function budgetPar(?int $exerciceId): ?Budget
    {
        return $exerciceId
            ? Budget::withoutGlobalScope('exercice')->where('exercice_id', $exerciceId)->orderByDesc('actif')->orderByDesc('id')->first()
            : null;
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\Select::make('exercice_id')
                    ->label('Exercice')
                    ->options(fn() => Exercice::orderByDesc('annee')->get()->mapWithKeys(fn($e) => [$e->id => $e->annee . ' — ' . $e->getBadgeStatut()]))
                    ->required()->live()
                    ->afterStateUpdated(fn($state) => $this->budget_id = $this->budgetPar($state)?->id),
                Forms\Components\Select::make('budget_id')
                    ->label('Budget')
                    ->options(fn() => Budget::withoutGlobalScope('exercice')->where('exercice_id', $this->exercice_id)
                        ->get()->mapWithKeys(fn($b) => [$b->id => "{$b->code} — {$b->libelle}"]))
                    ->required()->live(),
            ]),
        ];
    }

    /** Données du compte administratif (calculées une fois par affichage). */
    public function donnees(): ?array
    {
        $exercice = Exercice::find($this->exercice_id);
        $budget = $this->budget_id ? Budget::withoutGlobalScope('exercice')->find($this->budget_id) : null;

        return $exercice && $budget ? app(CompteAdministratifService::class)->construire($exercice, $budget) : null;
    }

    protected function getHeaderActions(): array
    {
        $structure = fn() => ParametresStructure::where('actif', true)->first() ?? ParametresStructure::first();

        return [
            Action::make('pdf')
                ->label('Compte administratif (PDF)')->icon('heroicon-o-document-arrow-down')->color('danger')
                ->disabled(fn() => !$this->budget_id)
                ->action(function () use ($structure) {
                    $ca = $this->donnees();
                    $pdf = Pdf::loadView('pdf.compte-administratif', $ca + ['structure' => $structure()])->setPaper('a4', 'landscape');

                    return response()->streamDownload(fn() => print($pdf->output()), 'compte_administratif_' . $ca['exercice']->annee . '.pdf');
                }),

            Action::make('excel')
                ->label('Classeur Excel')->icon('heroicon-o-table-cells')->color('success')
                ->disabled(fn() => !$this->budget_id)
                ->action(function () {
                    $ca = $this->donnees();

                    return Excel::download(new CompteAdministratifExport($ca), 'compte_administratif_' . $ca['exercice']->annee . '.xlsx');
                }),
        ];
    }
}
