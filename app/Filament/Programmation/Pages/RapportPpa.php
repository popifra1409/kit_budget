<?php

namespace App\Filament\Programmation\Pages;

use App\Models\PpaExercice;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms;
use Filament\Actions\Action;

class RapportPpa extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Rapports';

    protected static ?string $navigationLabel = 'Rapport PPA';

    protected static string $view = 'filament.programmation.pages.rapport-ppa';

    public ?int $ppa_exercice_id = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('ppa_exercice_id')
                ->label('PPA')
                ->options(
                    PpaExercice::with(['planStrategiqueEp', 'exercice'])
                        ->get()
                        ->mapWithKeys(fn($p) => [
                            $p->id => "{$p->numero} — {$p->planStrategiqueEp?->libelle} ({$p->exercice?->annee})",
                        ])
                )
                ->searchable()
                ->live()
                ->required(),
        ];
    }

    public function getPpa(): ?PpaExercice
    {
        if (!$this->ppa_exercice_id) {
            return null;
        }

        return PpaExercice::with(['planStrategiqueEp', 'exercice'])->find($this->ppa_exercice_id);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportPdf')
                ->label('Télécharger PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->visible(fn() => $this->ppa_exercice_id !== null)
                ->url(fn() => route('programmation.rapports.ppa.pdf', ['ppa' => $this->ppa_exercice_id]))
                ->openUrlInNewTab(),

            Action::make('exportExcel')
                ->label('Télécharger Excel')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->visible(fn() => $this->ppa_exercice_id !== null)
                ->url(fn() => route('programmation.rapports.ppa.excel', ['ppa' => $this->ppa_exercice_id]))
                ->openUrlInNewTab(),
        ];
    }
}
