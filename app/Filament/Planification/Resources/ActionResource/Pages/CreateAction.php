<?php

namespace App\Filament\Planification\Resources\ActionResource\Pages;

use App\Filament\Planification\Resources\ActionResource;
use App\Filament\Planification\Resources\Concerns\CreationActionSousProgramme;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateAction extends CreateRecord
{
    protected static string $resource = ActionResource::class;

    /**
     * Le sous-programme de l'EP choisi à l'écran porte le programme du MINSANTE : c'est ici qu'il
     * se dérive, pas dans un `saving()` du modèle — un garde qui corrige la saisie fait croire à
     * l'écran qu'elle a été enregistrée telle quelle.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return CreationActionSousProgramme::ranger($data);
    }
}
