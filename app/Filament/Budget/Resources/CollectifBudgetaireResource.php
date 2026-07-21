<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\CollectifBudgetaireResource\Pages;
use App\Filament\Budget\Resources\CollectifBudgetaireResource\RelationManagers\MouvementsRelationManager;
use App\Models\CollectifBudgetaire;
use App\Models\Exercice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class CollectifBudgetaireResource extends Resource
{
    protected static ?string $model = CollectifBudgetaire::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';
    protected static ?string $navigationLabel = 'Collectifs Budgétaires';
    protected static ?string $modelLabel = 'Collectif';
    protected static ?string $pluralModelLabel = 'Collectifs Budgétaires';
    protected static ?string $navigationGroup = 'Gestion Budgétaire';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informations générales')
                    ->schema([
                        Forms\Components\Select::make('exercice_id')
                            ->label('Exercice')
                            ->relationship('exercice', 'annee')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->default(fn() => Exercice::getActif()?->id),

                        Forms\Components\TextInput::make('numero')
                            ->label('Numéro')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->placeholder('Ex: CB-2026-001')
                            ->helperText('Numéro unique du collectif'),

                        Forms\Components\TextInput::make('libelle')
                            ->label('Objet')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\DatePicker::make('date_collectif')
                            ->label('Date du collectif')
                            ->required()
                            ->default(now()),

                        Forms\Components\DatePicker::make('date_adoption')
                            ->label('Date d\'adoption')
                            ->nullable(),

                        Forms\Components\Select::make('statut')
                            ->label('Statut')
                            ->options([
                                'projet' => 'Projet',
                                'adopte' => 'Adopté',
                                'annule' => 'Annulé',
                            ])
                            ->required()
                            ->default('projet'),

                        Forms\Components\Textarea::make('observations')
                            ->label('Observations')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('Numéro')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('libelle')
                    ->label('Objet')
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->wrap(),

                Tables\Columns\BadgeColumn::make('exercice.annee')
                    ->label('Exercice')
                    ->sortable()
                    ->colors([
                        'success' => fn($record) => $record->exercice?->estActif(),
                        'warning' => fn($record) => $record->exercice?->estCloture(),
                    ]),

                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
                    ->colors([
                        'secondary' => 'projet',
                        'success'   => 'adopte',
                        'danger'    => 'annule',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'projet' => 'Projet',
                        'adopte' => 'Adopté',
                        'annule' => 'Annulé',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('date_collectif')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('mouvements_count')
                    ->label('Mouvements')
                    ->counts('mouvements')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('exercice_id')
                    ->label('Exercice')
                    ->relationship('exercice', 'annee')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('statut')
                    ->label('Statut')
                    ->options([
                        'projet' => 'Projet',
                        'adopte' => 'Adopté',
                        'annule' => 'Annulé',
                    ]),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make()
                        ->visible(fn($record) => $record->estModifiable()),

                    // Action pour adopter/appliquer le collectif
                    Tables\Actions\Action::make('adopter')
                        ->label('Adopter')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn($record) => $record->statut === 'projet')
                        ->action(function ($record) {
                            $record->appliquer();
                            Notification::make()
                                ->title('Collectif adopté')
                                ->body('Le collectif a été appliqué avec succès.')
                                ->success()
                                ->send();
                        }),

                    // Action pour annuler le collectif
                    Tables\Actions\Action::make('annuler')
                        ->label('Annuler')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn($record) => $record->statut === 'adopte')
                        ->action(function ($record) {
                            $record->annuler();
                            Notification::make()
                                ->title('Collectif annulé')
                                ->body('Le collectif a été annulé et les modifications ont été révoquées.')
                                ->warning()
                                ->send();
                        }),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')
                    ->button()
                    ->size('sm'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn() => auth()->user()->can('delete_collectif_budgetaire')),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            MouvementsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCollectifBudgetaires::route('/'),
            'create' => Pages\CreateCollectifBudgetaire::route('/create'),
            'edit' => Pages\EditCollectifBudgetaire::route('/{record}/edit'),
            'view' => Pages\ViewCollectifBudgetaire::route('/{record}'),
        ];
    }

    // Permissions (à adapter)
    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_collectif_budgetaire') ?? false;
    }
    // ... autres permissions
}