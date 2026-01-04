<?php

namespace App\Filament\Forms\Components;

use App\Models\Exercice;
use Filament\Forms\Components\Select;

class ExerciceSelect
{
    public static function make(string $name = 'exercice_id'): Select
    {
        return Select::make($name)
            ->label('Exercice budgétaire')
            ->relationship(
                'exercice',
                'annee',
                fn($query) =>
                $query->whereIn('statut', ['brouillon', 'actif'])
                    ->orderBy('annee', 'desc')
            )
            ->searchable()
            ->preload()
            ->required()
            ->default(fn() => Exercice::getActif()?->id)
            ->disabled(
                fn($record) =>
                $record && method_exists($record, 'estLectureSeule') && $record->estLectureSeule()
            )
            ->helperText(
                fn($record) =>
                $record && method_exists($record, 'estLectureSeule') && $record->estLectureSeule()
                    ? '⚠️ Exercice clôturé - Modification impossible'
                    : 'Exercice dans lequel sera créé cet élément'
            )
            ->getSearchResultsUsing(function (string $search) {
                return Exercice::whereIn('statut', ['brouillon', 'actif'])
                    ->where(function ($query) use ($search) {
                        $query->where('annee', 'like', "%{$search}%")
                            ->orWhere('libelle', 'like', "%{$search}%");
                    })
                    ->orderBy('annee', 'desc')
                    ->limit(50)
                    ->get()
                    ->mapWithKeys(fn($exercice) => [
                        $exercice->id => $exercice->annee . ' - ' . $exercice->getBadgeStatut()
                    ]);
            })
            ->getOptionLabelUsing(function ($value): ?string {
                if (!$value) {
                    return null;
                }

                // Si $value est déjà un objet Exercice
                if ($value instanceof Exercice) {
                    return $value->annee . ' - ' . $value->getBadgeStatut();
                }

                // Si $value est un ID, récupérer l'exercice
                $exercice = Exercice::find($value);

                return $exercice
                    ? $exercice->annee . ' - ' . $exercice->getBadgeStatut()
                    : null;
            });
    }
}
