<?php

namespace App\Filament\Planification\Pages;

use App\Models\PlanStrategiqueEp;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms;
use Filament\Actions\Action;

class RapportIdentificationSousProgrammes extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Rapports';

    protected static ?string $navigationLabel = 'Identification des Sous-Programmes';

    protected static string $view = 'filament.planification.pages.rapport-identification-sous-programmes';

    public ?int $plan_strategique_ep_id = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('plan_strategique_ep_id')
                ->label('Plan Stratégique de Performance (PSP)')
                ->options(PlanStrategiqueEp::pluck('libelle', 'id'))
                ->searchable()
                ->live()
                ->required(),
        ];
    }

    public function getPsp(): ?PlanStrategiqueEp
    {
        if (!$this->plan_strategique_ep_id) {
            return null;
        }

        return PlanStrategiqueEp::with([
            'sousProgrammes.responsable',
            'sousProgrammes.programmeBudgetaire',
            'sousProgrammes.indicateurs.valeurs',
        ])->find($this->plan_strategique_ep_id);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportPdf')
                ->label('Télécharger PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->visible(fn() => $this->plan_strategique_ep_id !== null)
                ->url(fn() => route('planification.rapports.identification-sous-programmes.pdf', [
                    'psp' => $this->plan_strategique_ep_id,
                ]))
                ->openUrlInNewTab(),

            Action::make('exportExcel')
                ->label('Télécharger Excel')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->visible(fn() => $this->plan_strategique_ep_id !== null)
                ->url(fn() => route('planification.rapports.identification-sous-programmes.excel', [
                    'psp' => $this->plan_strategique_ep_id,
                ]))
                ->openUrlInNewTab(),
        ];
    }
}
