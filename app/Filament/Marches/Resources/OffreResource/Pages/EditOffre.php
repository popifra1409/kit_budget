<?php

namespace App\Filament\Marches\Resources\OffreResource\Pages;

use App\Filament\Marches\Resources\OffreResource;
use App\Models\Offre;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

/**
 * OffreResource::canEdit() garde la page : une offre déjà décidée ne se retape pas,
 * on lève la décision d'abord. Le marché affiché est fermé à l'édition, donc le
 * contrôle du doublon ne porte que sur le soumissionnaire et s'exclut lui-même.
 */
class EditOffre extends EditRecord
{
    use VerifieRattachementOffre;

    protected static string $resource = OffreResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->verifierRattachement($data, $this->record);
    }

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    public function getSubheading(): ?string
    {
        $record = $this->record;

        return $record instanceof Offre
            ? Offre::statutLibelle($record->statut) . ' — dépôt du ' . $record->date_depot?->format('d/m/Y')
            : null;
    }
}
