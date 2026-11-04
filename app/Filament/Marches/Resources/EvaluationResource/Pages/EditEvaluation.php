<?php

namespace App\Filament\Marches\Resources\EvaluationResource\Pages;

use App\Filament\Marches\Resources\EvaluationResource;
use App\Models\Evaluation;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

/**
 * EvaluationResource::canEdit() garde la page : une note d'un marché déjà attribué
 * ne se retouche pas, on lève l'attribution d'abord. L'offre et l'étape sont fermées
 * à l'édition — le couple fait l'identité de la note — donc le contrôle du doublon
 * ne porte plus que sur la fenêtre, le barème et la main qui corrige.
 */
class EditEvaluation extends EditRecord
{
    use VerifieSaisieEvaluation;

    protected static string $resource = EvaluationResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->verifierSaisie($data, $this->record);
    }

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    public function getSubheading(): ?string
    {
        $record = $this->record;

        return $record instanceof Evaluation
            ? ($record->element?->libelle ?? '—') . ' — ' . ($record->offre?->numero ?? '—')
                . ' · note portée le ' . ($record->date_evaluation?->format('d/m/Y H:i') ?? '—')
                . ' par ' . ($record->evaluateur?->name ?? '—')
            : null;
    }
}
