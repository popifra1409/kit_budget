<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\CollectifBudgetaireResource\Pages;
use App\Filament\Budget\Resources\CollectifBudgetaireResource\RelationManagers\MouvementsRelationManager;
use App\Models\CollectifBudgetaire;
use App\Models\Exercice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CollectifBudgetaireResource extends Resource
{
    protected static ?string $model = CollectifBudgetaire::class;

    // Navigation
    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationLabel = 'Collectifs budgétaires';
    protected static ?string $modelLabel = 'Collectif budgétaire';
    protected static ?string $pluralModelLabel = 'Collectifs budgétaires';
    protected static ?string $navigationGroup = 'Gestion Budgétaire';
    protected static ?int $navigationSort = 3;

    // ========================================
    // PERMISSIONS
    // ========================================

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_collectif_budgetaire') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->can('view_collectif_budgetaire') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_collectif_budgetaire') ?? false;
    }

    public static function canEdit($record): bool
    {
        if (!auth()->user()?->can('update_collectif_budgetaire')) return false;
        if (!$record->estModifiable()) {
            Notification::make()
                ->title('Collectif non modifiable')->warning()
                ->body("Ce collectif est en statut {$record->statut} et ne peut plus être modifié.")
                ->send();
            return false;
        }
        return true;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('delete_collectif_budgetaire') && $record->estModifiable();
    }

    public static function canEditRecord($record): bool
    {
        $canEdit = static::canEdit($record);
        if (!$canEdit && !$record->estModifiable()) {
            Notification::make()
                ->title('Collectif non modifiable')
                ->warning()
                ->body("Ce collectif est en statut {$record->statut} et ne peut plus être modifié.")
                ->send();
        }
        return $canEdit;
    }

    // Actions personnalisées
    public static function canAdopter($record): bool
    {
        return auth()->user()?->can('adopter_collectif_budgetaire')
            && $record->statut === 'projet';
    }

    public static function canAnnuler($record): bool
    {
        return auth()->user()?->can('annuler_collectif_budgetaire')
            && $record->statut === 'adopte';
    }

    // ========================================
    // FORM
    // ========================================

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
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->placeholder('CB-2026-001')
                            ->helperText('Numéro unique du collectif'),

                        Forms\Components\TextInput::make('libelle')
                            ->label('Libellé')
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
                            ->default('projet')
                            ->disabled(fn($record) => $record && !$record->estModifiable()),

                        Forms\Components\Textarea::make('observations')
                            ->label('Observations')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    // ========================================
    // TABLE
    // ========================================

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
                    ->label('Libellé')
                    ->searchable()
                    ->limit(50)
                    ->wrap(),

                Tables\Columns\TextColumn::make('exercice.annee')
                    ->label('Exercice')
                    ->sortable()
                    ->badge(),

                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
                    ->colors([
                        'secondary' => 'projet',
                        'success'   => 'adopte',
                        'danger'    => 'annule',
                    ]),

                Tables\Columns\TextColumn::make('date_collectif')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('mouvements_count')
                    ->label('Mouvements')
                    ->counts('mouvements')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('createur.name')
                    ->label('Créé par')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('exercice_id')
                    ->label('Exercice')
                    ->relationship('exercice', 'annee')
                    ->searchable()
                    ->preload()
                    ->default(fn() => Exercice::getActif()?->id),

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
                    Tables\Actions\EditAction::make(),

                    // Action pour adopter le collectif
                    Tables\Actions\Action::make('adopter')
                        ->label('Adopter')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Adopter le collectif')
                        ->modalDescription('Cette action appliquera les modifications sur les budgets et prévisions.')
                        ->visible(fn($record) => static::canAdopter($record))
                        ->action(function ($record) {
                            $record->statut = 'adopte';
                            $record->date_adoption = now();
                            $record->save();
                            $record->appliquer();
                            Notification::make()
                                ->title('Collectif adopté et appliqué avec succès')
                                ->success()
                                ->send();
                        }),

                    // Action pour annuler le collectif (si déjà adopté)
                    Tables\Actions\Action::make('annuler')
                        ->label('Annuler')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Annuler le collectif')
                        ->modalDescription('Cette action annulera toutes les modifications apportées par ce collectif.')
                        ->visible(fn($record) => static::canAnnuler($record))
                        ->action(function ($record) {
                            $record->annuler();
                            Notification::make()
                                ->title('Collectif annulé avec succès')
                                ->success()
                                ->send();
                        }),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->button()
                    ->size('sm'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    // ========================================
    // RELATIONS
    // ========================================

    public static function getRelations(): array
    {
        return [
            MouvementsRelationManager::class,
        ];
    }

    // ========================================
    // PAGES
    // ========================================

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCollectifBudgetaires::route('/'),
            'create' => Pages\CreateCollectifBudgetaire::route('/create'),
            'edit' => Pages\EditCollectifBudgetaire::route('/{record}/edit'),
            'view' => Pages\ViewCollectifBudgetaire::route('/{record}'),
        ];
    }
}
