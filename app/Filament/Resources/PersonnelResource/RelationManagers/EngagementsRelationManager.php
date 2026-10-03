<?php

namespace App\Filament\Resources\PersonnelResource\RelationManagers;

use App\Filament\Budget\Resources\EngagementResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fiche agent — onglet « Engagements » : tous les engagements dont l'agent est
 * bénéficiaire (issus d'une DA ou manuels), tous exercices confondus. Lecture seule.
 */
class EngagementsRelationManager extends RelationManager
{
    protected static string $relationship = 'engagementsBeneficiaire';
    protected static ?string $title = 'Engagements';
    protected static ?string $icon = 'heroicon-o-banknotes';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query->withoutGlobalScope('exercice')->with(['nomenclaturePrincipale']))
            ->recordTitleAttribute('numero')
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('N° Engagement')->weight('bold')->copyable()->searchable()->sortable(),

                Tables\Columns\TextColumn::make('date_engagement')
                    ->label('Date')->date('d/m/Y')->sortable(),

                Tables\Columns\TextColumn::make('type_document')
                    ->label('Origine')
                    ->getStateUsing(fn($record) => $record->getTypeLabel())
                    ->badge()->color('gray'),

                Tables\Columns\TextColumn::make('objet')
                    ->label('Objet')->limit(40)->tooltip(fn($record) => $record->objet)->searchable(),

                Tables\Columns\TextColumn::make('nomenclaturePrincipale.code')
                    ->label('Ligne')->placeholder('—')->toggleable(),

                Tables\Columns\TextColumn::make('montant_engage')
                    ->label('Montant engagé')->money('XAF')->sortable(),

                Tables\Columns\TextColumn::make('statut')
                    ->label('Statut')->badge()
                    ->formatStateUsing(fn($state) => match ($state) {
                        'provisoire' => 'Provisoire',
                        'definitif'  => 'Définitif',
                        'solde'      => 'Soldé',
                        'annule'     => 'Annulé',
                        default      => ucfirst((string) $state),
                    })
                    ->color(fn($state) => match ($state) {
                        'definitif'  => 'success',
                        'provisoire' => 'warning',
                        'solde'      => 'info',
                        'annule'     => 'danger',
                        default      => 'gray',
                    }),
            ])
            ->defaultSort('date_engagement', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('statut')
                    ->options([
                        'provisoire' => 'Provisoire',
                        'definitif'  => 'Définitif',
                        'solde'      => 'Soldé',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('voir')
                    ->label('Voir')->icon('heroicon-o-eye')->color('info')
                    ->url(fn($record) => EngagementResource::getUrl('view', ['record' => $record], panel: 'budget'))
                    ->openUrlInNewTab(),
            ], position: ActionsPosition::BeforeColumns)
            ->headerActions([])
            ->bulkActions([]);
    }
}
