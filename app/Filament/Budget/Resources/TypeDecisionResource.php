<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\TypeDecisionResource\Pages;
use App\Models\TypeDecision;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TypeDecisionResource extends Resource
{
    protected static ?string $model = TypeDecision::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Types de Décision';

    protected static ?string $modelLabel = 'Type de Décision';

    protected static ?string $pluralModelLabel = 'Types de Décision';

    protected static ?string $navigationGroup = 'Paramétrage';

    protected static ?int $navigationSort = 10;

    /**
     * Permissions – Types de Décision
     */
    public static function canViewAny(): bool
    {
        return auth()->check()
            && auth()->user()->can('view_any_type_decision');
    }

    public static function canView($record): bool
    {
        return auth()->check()
            && auth()->user()->can('view_type_decision');
    }

    public static function canCreate(): bool
    {
        return auth()->check()
            && auth()->user()->can('create_type_decision');
    }

    public static function canEdit($record): bool
    {
        return auth()->check()
            && auth()->user()->can('update_type_decision');
    }

    public static function canDelete($record): bool
    {
        if (!auth()->check()) {
            return false;
        }

        if (!auth()->user()->can('delete_type_decision')) {
            return false;
        }

        // Règle métier : ne pas supprimer si utilisé
        if ($record->decisionsAdministratives()->exists()) {
            \Filament\Notifications\Notification::make()
                ->title('Suppression impossible')
                ->warning()
                ->body('Ce type de décision est déjà utilisé.')
                ->send();

            return false;
        }

        return true;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informations')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->placeholder('Ex: ARRETE')
                            ->helperText('Code unique en majuscules'),

                        Forms\Components\TextInput::make('libelle')
                            ->label('Libellé')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ex: Arrêté'),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull()
                            ->placeholder('Description du type de décision'),

                        Forms\Components\TextInput::make('ordre')
                            ->label('Ordre d\'affichage')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->helperText('Permet de trier les types dans les listes'),

                        Forms\Components\Toggle::make('actif')
                            ->label('Actif')
                            ->default(true)
                            ->helperText('Désactiver pour masquer ce type sans le supprimer'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('libelle')
                    ->label('Libellé')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('ordre')
                    ->label('Ordre')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('actif')
                    ->label('Actif')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('decisionsAdministratives_count')
                    ->label('Utilisations')
                    ->counts('decisionsAdministratives')
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('actif')
                    ->label('Actif')
                    ->placeholder('Tous')
                    ->trueLabel('Actifs uniquement')
                    ->falseLabel('Inactifs uniquement'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (TypeDecision $record) {
                        if ($record->decisionsAdministratives()->count() > 0) {
                            throw new \Exception('Impossible de supprimer ce type : il est utilisé par des décisions administratives.');
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('ordre');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTypeDecisions::route('/'),
            'create' => Pages\CreateTypeDecision::route('/create'),
            'edit' => Pages\EditTypeDecision::route('/{record}/edit'),
        ];
    }
}
