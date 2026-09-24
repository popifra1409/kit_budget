<?php

namespace App\Filament\SuiviEvaluation\Resources\RapportAnnuelPerformanceResource\Pages;

use App\Filament\SuiviEvaluation\Resources\RapportAnnuelPerformanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewRapportAnnuelPerformance extends ViewRecord
{
    protected static string $resource = RapportAnnuelPerformanceResource::class;

    protected static string $view = 'filament.suivi-evaluation.pages.view-rap';

    public function getEtat()
    {
        return once(fn() => $this->record->getEtatMiseEnOeuvre());
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->visible(fn() => $this->record->estModifiable()),
            Actions\Action::make('pdf')->label('PDF')->icon('heroicon-o-document-arrow-down')->color('danger')
                ->url(fn() => route('suivi-evaluation.rapports.rap.pdf', $this->record))->openUrlInNewTab(),
            Actions\Action::make('excel')->label('Excel')->icon('heroicon-o-table-cells')->color('success')
                ->url(fn() => route('suivi-evaluation.rapports.rap.excel', $this->record))->openUrlInNewTab(),
        ];
    }
}
