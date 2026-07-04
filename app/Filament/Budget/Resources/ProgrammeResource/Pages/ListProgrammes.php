<?php

namespace App\Filament\Budget\Resources\ProgrammeResource\Pages;

use App\Filament\Budget\Resources\ProgrammeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProgrammes extends ListRecords
{
    protected static string $resource = ProgrammeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generer_cadre_logique')
                ->label('Générer Cadre Logique')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->url(fn() => route('filament.budget.resources.programmes.generer-cadre-logique')),

            Actions\CreateAction::make()
                ->label('Nouveau Programme')
                ->icon('heroicon-o-plus'),
        ];
    }
}
