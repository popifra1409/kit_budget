<?php

namespace App\Filament\Marches\Resources\EvaluationResource\Pages;

use App\Filament\Marches\Resources\EvaluationResource;
use App\Models\Evaluation;
use Filament\Resources\Pages\CreateRecord;

/**
 * La fenêtre du marché, l'étape du référentiel, le barème et le doublon se vérifient
 * avant que le modèle refuse en SQL — voir VerifieSaisieEvaluation.
 */
class CreateEvaluation extends CreateRecord
{
    use VerifieSaisieEvaluation;

    protected static string $resource = EvaluationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->verifierSaisie($data);
    }

    /**
     * Retour à la grille : les notes d'une même offre se portent d'affilée, et la
     * ligne qui vient d'être saisie doit se voir — avec les étapes restantes de
     * l'offre — avant de passer à la suivante.
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
