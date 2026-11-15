<?php

namespace App\Filament\Planification\Resources\SousProgrammeEpResource\Pages;

use App\Filament\Planification\Resources\SousProgrammeEpResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSousProgrammeEps extends ListRecords
{
    protected static string $resource = SousProgrammeEpResource::class;

    /**
     * B1b — la création passe par la page complète, pas par un modale : un sous-programme de
     * l'EP choisit son plan stratégique ET son programme du MINSANTE, deux décisions qui se
     * prennent avec les aides écrites sous les yeux.
     *
     * `CreateAction` n'interroge pas `Resource::canCreate()` (Filament v3) : sans `visible()`,
     * un compte en lecture seule verrait un bouton qui crée. La page `/create`, elle, refuse
     * par un 403 — le bouton masque, l'accès refuse.
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label("Nouveau sous-programme de l'EP")
                ->icon('heroicon-o-plus')
                ->url(fn() => SousProgrammeEpResource::getUrl('create'))
                ->visible(fn() => auth()->user()?->can('create_sous_programme_ep')),
        ];
    }
}
