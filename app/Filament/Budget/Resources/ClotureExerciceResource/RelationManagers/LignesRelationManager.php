<?php

namespace App\Filament\Budget\Resources\ClotureExerciceResource\RelationManagers;

use App\Services\Budget\ClotureExerciceService;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Table;

/** Lignes de la clôture : dotation = payé + report retenu + annulé. Report modifiable en préparation. */
class LignesRelationManager extends RelationManager
{
    protected static string $relationship = 'lignes';
    protected static ?string $title = 'Situation des lignes, reports et annulations';

    // Pas de isReadOnly() : il désactiverait aussi la colonne modifiable « Report retenu ».
    // Aucune action de création, modification ou suppression n'est proposée.

    public function table(Table $table): Table
    {
        $preparation = fn() => $this->getOwnerRecord()->statut === 'preparation';
        $somme = fn() => Sum::make()->label('')->numeric(0);

        return $table
            ->defaultSort('code')
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('Compte')->weight('bold')->searchable(),
                Tables\Columns\TextColumn::make('libelle')->limit(35)->tooltip(fn($record) => $record->libelle)->searchable(),
                Tables\Columns\TextColumn::make('titre')->badge()->formatStateUsing(fn($state) => $state ? "T{$state}" : '—')
                    ->color(fn($state) => (int) $state === 5 ? 'info' : 'gray'),
                Tables\Columns\TextColumn::make('sousProgramme.code')->label('Sous-prog.')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('dotation')->numeric(0)->summarize($somme()),
                Tables\Columns\TextColumn::make('engage')->label('Engagé')->numeric(0)->summarize($somme())->toggleable(),
                Tables\Columns\TextColumn::make('paye')->label('Payé')->numeric(0)->summarize($somme()),
                Tables\Columns\TextColumn::make('engage_non_paye')->label('Engagé non payé')->numeric(0)->summarize($somme()),
                Tables\Columns\TextColumn::make('report_propose')->label('Report proposé')->numeric(0)->summarize($somme())->toggleable(),

                Tables\Columns\TextInputColumn::make('report_retenu')
                    ->label('Report retenu')
                    ->type('number')->rules(['numeric', 'min:0'])
                    ->disabled(fn() => !$preparation() || !auth()->user()?->can('gerer_cloture_exercice'))
                    ->updateStateUsing(function ($record, $state) {
                        try {
                            app(ClotureExerciceService::class)->modifierReport($record, (float) $state, 'Ajustement manuel');
                        } catch (\DomainException $e) {
                            Notification::make()->warning()->title('Report refusé')->body($e->getMessage())->send();
                        }
                        return $record->fresh()->report_retenu;
                    })
                    ->summarize($somme()),

                Tables\Columns\TextColumn::make('annule')->label('Annulé')->numeric(0)->summarize($somme()),
                Tables\Columns\TextColumn::make('non_reporte_non_paye')->label('Non reporté non payé')
                    ->getStateUsing(fn($record) => $record->non_reporte_non_paye)
                    ->numeric(0)->color(fn($state) => $state > 0 ? 'danger' : 'gray'),
            ])
            ->filters([
                Tables\Filters\Filter::make('avec_report')->label('Avec report')->query(fn($query) => $query->where('report_retenu', '>', 0)),
                Tables\Filters\Filter::make('dette_non_reportee')->label('Engagé non payé non reporté')
                    ->query(fn($query) => $query->whereColumn('engage_non_paye', '>', 'report_retenu')),
                Tables\Filters\SelectFilter::make('titre')->options([1 => 'Titre 1', 2 => 'Titre 2', 3 => 'Titre 3', 4 => 'Titre 4', 5 => 'Titre 5', 6 => 'Titre 6']),
            ])
            ->headerActions([])->actions([])->bulkActions([]);
    }
}
