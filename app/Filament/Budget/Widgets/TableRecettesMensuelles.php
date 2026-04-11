<?php

namespace App\Filament\Budget\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\PrevisionRecetteMensuelle;
use App\Models\Exercice;
use Illuminate\Database\Eloquent\Builder;

class TableRecettesMensuelles extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Réalisation Mensuelle par Nomenclature';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('mois')
                    ->label('Mois')
                    ->formatStateUsing(fn($state) => $this->getNomMois($state))
                    ->badge()
                    ->color(fn($record) => $record->mois == now()->month ? 'info' : 'gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('lignePrevisionRecette.code_nomenclature')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('lignePrevisionRecette.libelle_nomenclature')
                    ->label('Nomenclature')
                    ->searchable()
                    ->limit(40)
                    ->tooltip(fn($record) => $record->lignePrevisionRecette->libelle_nomenclature),

                Tables\Columns\TextColumn::make('montant_prevu')
                    ->label('Prévu')
                    ->money('XAF', locale: 'fr')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('XAF', locale: 'fr')
                            ->label('Total Prévu'),
                    ]),

                Tables\Columns\TextColumn::make('montant_recouvre')
                    ->label('Recouvré')
                    ->money('XAF', locale: 'fr')
                    ->sortable()
                    ->weight('bold')
                    ->color('success')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('XAF', locale: 'fr')
                            ->label('Total Recouvré'),
                    ]),

                Tables\Columns\TextColumn::make('ecart')
                    ->label('Écart')
                    ->money('XAF', locale: 'fr')
                    ->sortable()
                    ->color(fn($record) => $record->ecart >= 0 ? 'success' : 'danger')
                    ->weight('bold')
                    ->formatStateUsing(fn($state) => ($state >= 0 ? '+' : '') . number_format($state, 0, ',', ' ') . ' FCFA'),

                Tables\Columns\TextColumn::make('taux_realisation')
                    ->label('Taux')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn($state) => number_format($state, 1) . '%')
                    ->color(
                        fn($record) =>
                        $record->taux_realisation >= 90 ? 'success' : ($record->taux_realisation >= 70 ? 'warning' : 'danger')
                    )
                    ->summarize([
                        Tables\Columns\Summarizers\Average::make()
                            ->label('Moyenne')
                            ->formatStateUsing(fn($state) => number_format($state, 1) . '%'),
                    ]),

                Tables\Columns\TextColumn::make('montant_cumule_recouvre')
                    ->label('Cumulé')
                    ->money('XAF', locale: 'fr')
                    ->toggleable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('taux_realisation_cumule')
                    ->label('Taux Cumulé')
                    ->badge()
                    ->toggleable()
                    ->formatStateUsing(fn($state) => number_format($state, 1) . '%')
                    ->color(
                        fn($record) =>
                        $record->taux_realisation_cumule >= 90 ? 'success' : ($record->taux_realisation_cumule >= 70 ? 'warning' : 'danger')
                    ),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('mois')
                    ->label('Mois')
                    ->options([
                        1 => 'Janvier',
                        2 => 'Février',
                        3 => 'Mars',
                        4 => 'Avril',
                        5 => 'Mai',
                        6 => 'Juin',
                        7 => 'Juillet',
                        8 => 'Août',
                        9 => 'Septembre',
                        10 => 'Octobre',
                        11 => 'Novembre',
                        12 => 'Décembre'
                    ])
                    ->default(now()->month),

                Tables\Filters\Filter::make('sous_performance')
                    ->label('Sous-performance (<90%)')
                    ->query(fn($query) => $query->where('taux_realisation', '<', 90))
                    ->toggle(),

                Tables\Filters\Filter::make('surperformance')
                    ->label('Surperformance (>100%)')
                    ->query(fn($query) => $query->where('taux_realisation', '>', 100))
                    ->toggle(),
            ])
            ->defaultSort('mois')
            ->striped()
            ->paginated([10, 25, 50]);
    }

    protected function getTableQuery(): Builder
    {
        $exerciceActif = Exercice::getActif();

        if (!$exerciceActif) {
            return PrevisionRecetteMensuelle::query()->whereRaw('1 = 0');
        }

        return PrevisionRecetteMensuelle::query()
            ->where('exercice_id', $exerciceActif->id)
            ->where('actif', true)
            ->with(['lignePrevisionRecette.nomenclature']);
    }

    protected function getNomMois(int $mois): string
    {
        $moisFr = [
            1 => 'Janvier',
            2 => 'Février',
            3 => 'Mars',
            4 => 'Avril',
            5 => 'Mai',
            6 => 'Juin',
            7 => 'Juillet',
            8 => 'Août',
            9 => 'Septembre',
            10 => 'Octobre',
            11 => 'Novembre',
            12 => 'Décembre'
        ];
        return $moisFr[$mois] ?? '';
    }
}
