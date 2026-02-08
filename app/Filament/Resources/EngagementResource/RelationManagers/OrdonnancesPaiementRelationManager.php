<?php

namespace App\Filament\Resources\EngagementResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class OrdonnancesPaiementRelationManager extends RelationManager
{
    protected static string $relationship = 'ordonnancesPaiement';

    protected static ?string $recordTitleAttribute = 'numero';

    protected static ?string $title = 'Ordonnances de Paiement';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('numero')
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('N° OP')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\BadgeColumn::make('type_ordonnance')
                    ->label('Type')
                    ->colors([
                        'success' => 'standard',
                        'warning' => 'impot',
                        'info' => 'avance',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'standard' => 'Standard',
                        'impot' => 'Impôt',
                        'avance' => 'Avance',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('beneficiaire.raison_sociale')
                    ->label('Bénéficiaire')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('montant_ordonnance')
                    ->label('Montant')
                    ->money('XAF')
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
                    ->colors([
                        'secondary' => 'brouillon',
                        'warning' => 'emise',
                        'success' => 'visee',
                        'primary' => 'payee',
                        'danger' => 'annulee',
                    ]),

                Tables\Columns\TextColumn::make('date_emission')
                    ->label('Date émission')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type_ordonnance')
                    ->label('Type')
                    ->options([
                        'standard' => 'Standard',
                        'impot' => 'Impôt',
                        'avance' => 'Avance',
                    ]),
            ])
            ->headerActions([
                // Pas de création directe, on utilise le bouton dédié
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
