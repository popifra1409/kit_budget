<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\GroupeNomenclatureResource\Pages;
use App\Models\GroupeNomenclature;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Enums\ActionsPosition;

class GroupeNomenclatureResource extends Resource
{
    protected static ?string $model = GroupeNomenclature::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';
    protected static ?string $navigationLabel = 'Groupes de nomenclature';
    protected static ?string $modelLabel = 'Groupe de nomenclature';
    protected static ?string $pluralModelLabel = 'Groupes de nomenclature';
    protected static ?string $navigationGroup = 'Configuration Budget';
    protected static ?int $navigationSort = 2;

    // ========================================
    // PERMISSIONS
    // ========================================

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_groupe_nomenclature') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->can('view_groupe_nomenclature') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_groupe_nomenclature') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('update_groupe_nomenclature') ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('delete_groupe_nomenclature') ?? false;
    }

    // ========================================
    // FORM
    // ========================================

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informations du groupe')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20)
                            ->placeholder('Ex: FONCT, AVANTAG, INVEST')
                            ->helperText('Code unique, utilisé en interne (majuscules recommandées)'),

                        Forms\Components\TextInput::make('libelle')
                            ->label('Libellé')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->placeholder('Ex: DÉPENSES DE FONCTIONNEMENT'),

                        Forms\Components\Select::make('type')
                            ->label('Type')
                            ->options([
                                GroupeNomenclature::TYPE_DEPENSE => 'Dépense',
                                GroupeNomenclature::TYPE_RECETTE => 'Recette',
                            ])
                            ->required()
                            ->native(false)
                            ->disabled(fn($record) => $record && !$record->estSupprimable())
                            ->helperText(
                                fn($record) => $record && !$record->estSupprimable()
                                    ? 'Type verrouillé : des lignes de nomenclature sont déjà rattachées à ce groupe.'
                                    : null
                            ),

                        Forms\Components\TextInput::make('ordre')
                            ->label('Ordre d\'affichage')
                            ->numeric()
                            ->default(0)
                            ->helperText('Détermine l\'ordre d\'affichage dans les listes et rapports'),

                        Forms\Components\Toggle::make('actif')
                            ->label('Actif')
                            ->default(true)
                            ->helperText('Un groupe inactif n\'apparaît plus dans les sélections des formulaires'),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull()
                            ->placeholder('Description ou précisions sur ce groupe (optionnel)'),
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
                Tables\Columns\TextColumn::make('ordre')
                    ->label('#')
                    ->sortable()
                    ->alignCenter()
                    ->width('40px'),

                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('libelle')
                    ->label('Libellé')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Type')
                    ->colors([
                        'danger'  => GroupeNomenclature::TYPE_DEPENSE,
                        'success' => GroupeNomenclature::TYPE_RECETTE,
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        GroupeNomenclature::TYPE_DEPENSE => 'Dépense',
                        GroupeNomenclature::TYPE_RECETTE => 'Recette',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('lignes_nomenclature_count')
                    ->label('Lignes rattachées')
                    ->counts('lignesNomenclature')
                    ->badge()
                    ->color('primary')
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('actif')
                    ->label('Actif')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Type')
                    ->options([
                        GroupeNomenclature::TYPE_DEPENSE => 'Dépense',
                        GroupeNomenclature::TYPE_RECETTE => 'Recette',
                    ]),

                Tables\Filters\TernaryFilter::make('actif')
                    ->label('Actif')
                    ->placeholder('Tous')
                    ->trueLabel('Actifs')
                    ->falseLabel('Inactifs'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),

                    Tables\Actions\DeleteAction::make()
                        ->visible(fn(GroupeNomenclature $record) => $record->estSupprimable())
                        ->requiresConfirmation()
                        ->modalDescription('Ce groupe sera définitivement supprimé.'),

                    Tables\Actions\Action::make('empecheSuppression')
                        ->label('Suppression impossible')
                        ->icon('heroicon-o-lock-closed')
                        ->color('gray')
                        ->disabled()
                        ->visible(fn(GroupeNomenclature $record) => !$record->estSupprimable())
                        ->tooltip(fn(GroupeNomenclature $record) => "{$record->nombre_lignes} ligne(s) de nomenclature rattachée(s) — détachez-les d'abord."),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')
                    ->button()
                    ->size('sm'),
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function ($records) {
                            $bloques = $records->filter(fn($r) => !$r->estSupprimable());

                            if ($bloques->isNotEmpty()) {
                                Notification::make()
                                    ->title('Suppression partielle')
                                    ->body($bloques->count() . ' groupe(s) non supprimé(s) car des lignes de nomenclature y sont rattachées : '
                                        . $bloques->pluck('code')->implode(', '))
                                    ->warning()
                                    ->send();
                            }

                            $records->filter(fn($r) => $r->estSupprimable())->each->delete();
                        }),
                ]),
            ])
            ->defaultSort('ordre', 'asc');
    }

    // ========================================
    // PAGES
    // ========================================

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListGroupeNomenclatures::route('/'),
            'create' => Pages\CreateGroupeNomenclature::route('/create'),
            'edit'   => Pages\EditGroupeNomenclature::route('/{record}/edit'),
        ];
    }
}
