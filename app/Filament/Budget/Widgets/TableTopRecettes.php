<?php

namespace App\Filament\Budget\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\LignePrevisionRecette;
use App\Models\PrevisionRecette;
use App\Models\Exercice;
use Illuminate\Database\Eloquent\Builder;

class TableTopRecettes extends BaseWidget
{
    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Top 10 Sources de Recettes';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('ordre')
                    ->label('#')
                    ->state(function ($rowLoop) {
                        return $rowLoop->iteration;
                    })
                    ->badge()
                    ->color(
                        fn($rowLoop) =>
                        $rowLoop->iteration <= 3 ? 'success' : 'gray'
                    ),

                Tables\Columns\TextColumn::make('code_nomenclature')
                    ->label('Code')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('libelle_nomenclature')
                    ->label('Nomenclature')
                    ->searchable()
                    ->limit(50)
                    ->wrap()
                    ->tooltip(fn($record) => $record->libelle_nomenclature),

                Tables\Columns\TextColumn::make('montant_rectifie')
                    ->label('Prévu')
                    ->money('XAF', locale: 'fr')
                    ->sortable(),

                Tables\Columns\TextColumn::make('montant_recouvre')
                    ->label('Recouvré')
                    ->money('XAF', locale: 'fr')
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\TextColumn::make('ecart')
                    ->label('Écart')
                    ->money('XAF', locale: 'fr')
                    ->sortable()
                    ->color(fn($record) => $record->ecart >= 0 ? 'success' : 'danger')
                    ->formatStateUsing(fn($state) => ($state >= 0 ? '+' : '') . number_format($state, 0, ',', ' ')),

                Tables\Columns\TextColumn::make('taux_recouvrement')
                    ->label('Taux')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn($state) => number_format($state, 1) . '%')
                    ->color(
                        fn($record) =>
                        $record->taux_recouvrement >= 90 ? 'success' : ($record->taux_recouvrement >= 70 ? 'warning' : 'danger')
                    ),

                Tables\Columns\TextColumn::make('contribution')
                    ->label('% du Total')
                    ->state(function ($record) {
                        $prevision = $record->previsionRecette;
                        $totalRecouvre = $prevision->getTotalRecouvre();
                        if ($totalRecouvre == 0) return '0%';
                        return number_format(($record->montant_recouvre / $totalRecouvre) * 100, 1) . '%';
                    })
                    ->badge()
                    ->color('info'),
            ])
            ->defaultSort('montant_recouvre', 'desc')
            ->paginated(false);
    }

    protected function getTableQuery(): Builder
    {
        $exerciceActif = Exercice::getActif();

        if (!$exerciceActif) {
            return LignePrevisionRecette::query()->whereRaw('1 = 0');
        }

        // Récupérer la prévision active
        $prevision = PrevisionRecette::where('exercice_id', $exerciceActif->id)
            ->where('statut', '!=', 'elaboration')
            ->first();

        if (!$prevision) {
            return LignePrevisionRecette::query()->whereRaw('1 = 0');
        }

        return LignePrevisionRecette::query()
            ->where('prevision_recette_id', $prevision->id)
            ->where('actif', true)
            ->orderBy('montant_recouvre', 'desc')
            ->limit(10);
    }
}
