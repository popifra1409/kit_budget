<?php

namespace App\Filament\Budget\Resources\RecetteReelleResource\Pages;

use App\Filament\Budget\Resources\RecetteReelleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRecetteReelles extends ListRecords
{
    protected static string $resource = RecetteReelleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // ✅ url() dans une closure → évalué au clic, pas au boot
            Actions\Action::make('tableau_suivi')
                ->label('Tableau de suivi')
                ->icon('heroicon-o-chart-bar')
                ->color('info')
                ->outlined()
                ->url(fn() => RecetteReelleResource::getUrl('suivi')),

            Actions\CreateAction::make(),
        ];
    }
}