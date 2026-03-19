<?php

namespace App\Filament\Resources\FicheControleEngagementsResource\Pages;

use App\Filament\Resources\FicheControleEngagementsResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFicheControleEngagements extends ListRecords
{
    protected static string $resource = FicheControleEngagementsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('aide')
                ->label('Guide d\'utilisation')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Fiche de Contrôle des Engagements - Guide')
                ->modalContent(view('filament.pages.fiche-controle-guide'))
                ->modalWidth('4xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fermer'),
        ];
    }
}
