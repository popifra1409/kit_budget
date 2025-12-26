<?php

namespace App\Filament\Resources\BordereauEngagementResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MouvementsRelationManager extends RelationManager
{
    protected static string $relationship = 'mouvements';

    protected static ?string $title = 'Historique des Mouvements';

    protected static ?string $label = 'Mouvement';

    protected static ?string $pluralLabel = 'Mouvements';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Placeholder::make('readonly')
                    ->label('')
                    ->content('L\'historique des mouvements est généré automatiquement.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('action')
            ->columns([
                Tables\Columns\TextColumn::make('date_action')
                    ->label('Date & Heure')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('action')
                    ->label('Action')
                    ->badge()
                    ->formatStateUsing(
                        fn($record) =>
                        $record->getIcone() . ' ' . $record->getLibelleAction()
                    )
                    ->colors([
                        'secondary' => fn($state) => $state === 'emis',
                        'info' => fn($state) => $state === 'transmis',
                        'warning' => fn($state) => $state === 'receptionne',
                        'success' => fn($state) => $state === 'valide',
                        'danger' => fn($state) => $state === 'rejete',
                        'gray' => fn($state) => in_array($state, ['retourne', 'annule']),
                    ]),

                Tables\Columns\TextColumn::make('auteur.name')
                    ->label('Effectué par')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('de')
                    ->label('De')
                    ->placeholder('-')
                    ->limit(30),

                Tables\Columns\TextColumn::make('vers')
                    ->label('Vers')
                    ->placeholder('-')
                    ->limit(30),

                Tables\Columns\TextColumn::make('commentaire')
                    ->label('Commentaire')
                    ->limit(50)
                    ->wrap()
                    ->placeholder('-'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->label('Action')
                    ->options([
                        'transmis' => 'Transmis',
                        'receptionne' => 'Réceptionné',
                        'valide' => 'Validé',
                        'rejete' => 'Rejeté',
                        'retourne' => 'Retourné',
                        'annule' => 'Annulé',
                    ]),
            ])
            ->headerActions([
                // Pas de création manuelle - automatique
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading('Détails du mouvement')
                    ->modalContent(fn($record) => view('filament.modals.mouvement-details', [
                        'mouvement' => $record
                    ])),
            ])
            ->bulkActions([
                // Pas de suppression - historique immuable
            ])
            ->defaultSort('date_action', 'desc')
            ->paginated(false); // Tous les mouvements visibles
    }

    public function isReadOnly(): bool
    {
        return true; // Empêche la création/modification manuelle
    }
}
