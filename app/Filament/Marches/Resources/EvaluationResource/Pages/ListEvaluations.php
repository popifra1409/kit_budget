<?php

namespace App\Filament\Marches\Resources\EvaluationResource\Pages;

use App\Filament\Marches\Resources\EvaluationResource;
use App\Models\Evaluation;
use App\Models\Marche;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

/**
 * La grille du dossier : toutes les notes portées, offre par offre, étape par étape.
 */
class ListEvaluations extends ListRecords
{
    protected static string $resource = EvaluationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Porter une note')
                ->icon('heroicon-o-star'),
        ];
    }

    /**
     * Le chiffre du badge du menu se relit ici : combien de marchés sont en cours
     * d'évaluation, et combien de notes sont parties aujourd'hui. Sans ça, le nombre
     * du menu est une énigme une fois devant la liste.
     */
    public function getSubheading(): ?string
    {
        $enEvaluation = Marche::query()->where('statut', 'en_evaluation')->count();
        $aujourdhui   = Evaluation::query()->whereDate('date_evaluation', now()->toDateString())->count();

        return sprintf(
            '%s marché(s) en évaluation · %s note(s) portée(s) aujourd\'hui.',
            $enEvaluation,
            $aujourdhui,
        );
    }
}
