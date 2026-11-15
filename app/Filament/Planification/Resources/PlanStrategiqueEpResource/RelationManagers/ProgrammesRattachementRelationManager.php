<?php

namespace App\Filament\Planification\Resources\PlanStrategiqueEpResource\RelationManagers;

use App\Filament\Planification\Resources\ProgrammeResource;
use App\Models\Programme;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * B1b — l'autre moitié du vocabulaire, en lecture seule.
 *
 * Un plan stratégique de l'hôpital ne choisit pas ses programmes : il les reçoit du MINSANTE,
 * et il n'en reçoit que ceux qu'un sous-programme de l'EP décompose déjà. C'est pourquoi cette
 * table n'a ni « Attacher » ni ligne modifiable : la seule écriture qui ajoute ici passe par la
 * création d'un sous-programme de l'EP rattaché (onglet voisin ou menu « Sous-programmes de l'EP »).
 */
class ProgrammesRattachementRelationManager extends RelationManager
{
    protected static string $relationship = 'programmesRattachement';

    protected static ?string $title = 'Programmes de rattachement (MINSANTE)';

    protected static ?string $recordTitleAttribute = 'code';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Programme de la tutelle')
                    ->badge()
                    ->color('success')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('libelle')
                    ->label('Intitulé')
                    ->searchable()
                    ->wrap(),
                // Le sous-programme porteur est le lien lui-même : rien de plus à saisir.
                Tables\Columns\TextColumn::make('pivot.code')
                    ->label("Décomposé par")
                    ->description(fn(Programme $record) => $record->pivot?->libelle)
                    ->searchable(),
                Tables\Columns\TextColumn::make('pivot.type')
                    ->label('Nature')
                    ->badge()
                    ->color(fn(?string $state) => $state === 'support' ? 'warning' : 'primary')
                    ->formatStateUsing(fn(?string $state) => $state === 'support' ? 'Support' : 'Opérationnel'),
                Tables\Columns\TextColumn::make('exercice.annee')
                    ->label('Exercice classifié')
                    ->sortable(),
            ])
            ->emptyStateHeading("Aucun programme de la tutelle n'est rattaché à ce plan")
            ->emptyStateDescription("Rattachez un sous-programme de l'EP à un programme budgétaire du MINSANTE : il apparaîtra ici.")
            ->headerActions([])
            ->actions([
                // Lien, pas édition : la ligne modifiable reste le sous-programme de l'EP.
                Tables\Actions\Action::make('voirSousProgramme')
                    ->label("Voir le sous-programme de l'EP")
                    ->icon('heroicon-o-list-bullet')
                    ->color('gray')
                    ->url(fn(Programme $record) => ProgrammeResource::urlSousProgrammesRattaches($record)),
            ])
            ->bulkActions([]);
    }
}
