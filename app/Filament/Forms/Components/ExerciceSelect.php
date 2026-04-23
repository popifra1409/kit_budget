<?php

namespace App\Filament\Forms\Components;

use App\Models\Exercice;
use Filament\Forms\Components\Select;

class ExerciceSelect
{
    public static function make(
        string $name      = 'exercice_id',
        bool   $avecReports = false  // ← NOUVEAU paramètre
    ): Select {
        // ✅ Statuts autorisés selon le contexte
        $statuts = $avecReports
            ? ['brouillon', 'actif', 'cloture']
            : ['brouillon', 'actif'];

        return Select::make($name)
            ->label($avecReports ? 'Exercice budgétaire (reports inclus)' : 'Exercice budgétaire')
            ->relationship(
                'exercice',
                'annee',
                fn($query) => $query->whereIn('statut', $statuts)->orderBy('annee', 'desc')
            )
            ->searchable()
            ->preload()
            ->required()
            ->default(fn() => Exercice::getActif()?->id)
            ->disabled(
                fn($record) =>
                $record && method_exists($record, 'estLectureSeule') && $record->estLectureSeule()
            )
            ->helperText(function ($record) use ($avecReports) {
                if ($record && method_exists($record, 'estLectureSeule') && $record->estLectureSeule()) {
                    return '⚠️ Exercice clôturé - Modification impossible';
                }
                return $avecReports
                    ? '📅 Sélectionnez l\'exercice d\'origine pour les documents reportés (ex: 2025)'
                    : 'Exercice dans lequel sera créé cet élément';
            })
            ->getSearchResultsUsing(function (string $search) use ($statuts) {
                return Exercice::whereIn('statut', $statuts)
                    ->where(function ($query) use ($search) {
                        $query->where('annee', 'like', "%{$search}%")
                            ->orWhere('libelle', 'like', "%{$search}%");
                    })
                    ->orderBy('annee', 'desc')
                    ->limit(50)
                    ->get()
                    ->mapWithKeys(fn($exercice) => [
                        $exercice->id => $exercice->annee
                            . ' - ' . $exercice->getBadgeStatut()
                            . ($exercice->statut === 'cloture' ? ' 📋 (report)' : '')
                    ]);
            })
            ->getOptionLabelUsing(function ($value) {
                if (!$value) return null;
                if ($value instanceof Exercice) {
                    return $value->annee . ' - ' . $value->getBadgeStatut();
                }
                $exercice = Exercice::find($value);
                return $exercice
                    ? $exercice->annee . ' - ' . $exercice->getBadgeStatut()
                    . ($exercice->statut === 'cloture' ? ' 📋 (report)' : '')
                    : null;
            });
    }
}
