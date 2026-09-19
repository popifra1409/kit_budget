<?php
// app/Filament/Planification/Pages/RapportActivitesSousProgramme.php

namespace App\Filament\Planification\Pages;

use App\Models\Action;
use App\Models\SousProgrammeEp;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms;
use Filament\Actions\Action as PageAction;

class RapportActivitesSousProgramme extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Rapports';

    protected static ?string $navigationLabel = 'Activités d\'un Sous-Programme';

    protected static string $view = 'filament.planification.pages.rapport-activites-sous-programme';

    public ?int $sous_programme_ep_id = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('sous_programme_ep_id')
                ->label('Sous-Programme')
                ->options(SousProgrammeEp::pluck('libelle', 'id'))
                ->searchable()
                ->live()
                ->required(),
        ];
    }

    public function getSousProgramme(): ?SousProgrammeEp
    {
        if (!$this->sous_programme_ep_id) {
            return null;
        }

        return SousProgrammeEp::with(['responsable', 'indicateurs'])
            ->find($this->sous_programme_ep_id);
    }

    public function getActivites()
    {
        $sp = $this->getSousProgramme();
        if (!$sp) {
            return collect();
        }

        $actionIds = Action::where('programme_id', $sp->programme_budgetaire_id)->pluck('id');

        return \App\Models\Activite::whereIn('action_id', $actionIds)
            ->with(['responsable', 'indicateurs'])
            ->orderBy('ordre')
            ->get();
    }

    protected function getHeaderActions(): array
    {
        return [
            PageAction::make('exportPdf')
                ->label('Télécharger PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->visible(fn() => $this->sous_programme_ep_id !== null)
                ->url(fn() => route('planification.rapports.activites-sous-programme.pdf', [
                    'sousProgramme' => $this->sous_programme_ep_id,
                ]))
                ->openUrlInNewTab(),

            PageAction::make('exportExcel')
                ->label('Télécharger Excel')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->visible(fn() => $this->sous_programme_ep_id !== null)
                ->url(fn() => route('planification.rapports.activites-sous-programme.excel', [
                    'sousProgramme' => $this->sous_programme_ep_id,
                ]))
                ->openUrlInNewTab(),
        ];
    }
}
