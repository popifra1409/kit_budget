<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\TypeEngagementResource\Pages;
use App\Models\TypeEngagement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TypeEngagementResource extends Resource
{
    protected static ?string $model = TypeEngagement::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Types d\'Engagement';

    protected static ?string $modelLabel = 'Type d\'Engagement';

    protected static ?string $pluralModelLabel = 'Types d\'Engagement';

    protected static ?string $navigationGroup = 'Paramétrage';

    protected static ?int $navigationSort = 10;

    /**
     * Permissions – Types d’Engagement
     */
    public static function canViewAny(): bool
    {
        return auth()->check()
            && auth()->user()->can('view_any_type_engagement');
    }

    public static function canView($record): bool
    {
        return auth()->check()
            && auth()->user()->can('view_type_engagement');
    }

    public static function canCreate(): bool
    {
        return auth()->check()
            && auth()->user()->can('create_type_engagement');
    }

    public static function canEdit($record): bool
    {
        return auth()->check()
            && auth()->user()->can('update_type_engagement');
    }

    public static function canDelete($record): bool
    {
        if (!auth()->check()) {
            return false;
        }

        if (!auth()->user()->can('delete_type_engagement')) {
            return false;
        }

        // 🔒 Règle métier : type déjà utilisé
        if ($record->engagements()->exists()) {
            \Filament\Notifications\Notification::make()
                ->title('Suppression impossible')
                ->warning()
                ->body('Ce type d’engagement est déjà utilisé.')
                ->send();

            return false;
        }

        return true;
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informations générales')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->placeholder('BC, LC, MARCHE, etc.')
                            ->helperText('Code unique du type d\'engagement'),

                        Forms\Components\TextInput::make('libelle')
                            ->label('Libellé')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ex: Bon de Commande Administratif'),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Seuils de montant')
                    ->description('Définir la fourchette de montants pour ce type')
                    ->schema([
                        Forms\Components\TextInput::make('montant_min')
                            ->label('Montant minimum (FCFA)')
                            ->numeric()
                            ->prefix('FCFA')
                            ->placeholder('0')
                            ->helperText('Laissez vide pour aucune limite inférieure'),

                        Forms\Components\TextInput::make('montant_max')
                            ->label('Montant maximum (FCFA)')
                            ->numeric()
                            ->prefix('FCFA')
                            ->placeholder('Pas de limite')
                            ->helperText('Laissez vide pour aucune limite supérieure'),

                        Forms\Components\Placeholder::make('exemple_montant')
                            ->label('Exemple')
                            ->content(function (Forms\Get $get) {
                                $min = $get('montant_min');
                                $max = $get('montant_max');

                                if ($min && $max) {
                                    return "Ce type s'applique pour les montants entre " .
                                        number_format($min, 0, ',', ' ') . " et " .
                                        number_format($max, 0, ',', ' ') . " FCFA";
                                } elseif ($min) {
                                    return "Ce type s'applique pour les montants ≥ " .
                                        number_format($min, 0, ',', ' ') . " FCFA";
                                } elseif ($max) {
                                    return "Ce type s'applique pour les montants < " .
                                        number_format($max, 0, ',', ' ') . " FCFA";
                                }

                                return "Aucune limite de montant définie";
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Règles de calcul IR')
                    ->description('Définir comment l\'IR est calculée pour ce type')
                    ->schema([
                        Forms\Components\Select::make('mode_calcul_ir')
                            ->label('Mode de calcul IR')
                            ->options([
                                'fixe' => 'Taux fixe (peu importe le régime)',
                                'selon_regime' => 'Selon le régime fiscal',
                                'aucun' => 'Pas d\'IR',
                            ])
                            ->required()
                            ->default('selon_regime')
                            ->live()
                            ->helperText('Comment l\'IR doit être calculée'),

                        Forms\Components\TextInput::make('taux_ir_fixe')
                            ->label('Taux IR fixe (%)')
                            ->numeric()
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100)
                            ->visible(fn(Forms\Get $get) => $get('mode_calcul_ir') === 'fixe')
                            ->required(fn(Forms\Get $get) => $get('mode_calcul_ir') === 'fixe')
                            ->helperText('Ex: 5,5% pour BC Administratif'),

                        Forms\Components\TextInput::make('taux_ir_regime_reel')
                            ->label('Taux IR - Régime Réel (%)')
                            ->numeric()
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100)
                            ->visible(fn(Forms\Get $get) => $get('mode_calcul_ir') === 'selon_regime')
                            ->required(fn(Forms\Get $get) => $get('mode_calcul_ir') === 'selon_regime')
                            ->helperText('Ex: 2,2% pour Marchés'),

                        Forms\Components\TextInput::make('taux_ir_regime_simplifie')
                            ->label('Taux IR - Régime Simplifié (%)')
                            ->numeric()
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100)
                            ->visible(fn(Forms\Get $get) => $get('mode_calcul_ir') === 'selon_regime')
                            ->required(fn(Forms\Get $get) => $get('mode_calcul_ir') === 'selon_regime')
                            ->helperText('Ex: 5,5% pour Marchés'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Statut et ordre')
                    ->schema([
                        Forms\Components\Toggle::make('actif')
                            ->label('Actif')
                            ->default(true)
                            ->helperText('Désactiver pour masquer ce type dans les sélections'),

                        Forms\Components\TextInput::make('ordre')
                            ->label('Ordre d\'affichage')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->helperText('Plus le nombre est petit, plus il apparaît en premier'),
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
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('libelle')
                    ->label('Libellé')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('montant_min')
                    ->label('Min')
                    ->money('XAF')
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('montant_max')
                    ->label('Max')
                    ->money('XAF')
                    ->sortable()
                    ->placeholder('∞')
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('mode_calcul_ir')
                    ->label('Mode IR')
                    ->colors([
                        'primary' => 'fixe',
                        'warning' => 'selon_regime',
                        'secondary' => 'aucun',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'fixe' => 'Fixe',
                        'selon_regime' => 'Selon régime',
                        'aucun' => 'Aucun',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('taux_ir_display')
                    ->label('Taux IR')
                    ->getStateUsing(function ($record) {
                        if ($record->mode_calcul_ir === 'fixe') {
                            return $record->taux_ir_fixe . '%';
                        } elseif ($record->mode_calcul_ir === 'selon_regime') {
                            return "Réel: {$record->taux_ir_regime_reel}% | Simplifié: {$record->taux_ir_regime_simplifie}%";
                        }
                        return '-';
                    })
                    ->wrap(),

                Tables\Columns\IconColumn::make('actif')
                    ->label('Actif')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('ordre')
                    ->label('Ordre')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('actif')
                    ->label('Actif')
                    ->placeholder('Tous')
                    ->trueLabel('Actifs uniquement')
                    ->falseLabel('Inactifs uniquement'),

                Tables\Filters\SelectFilter::make('mode_calcul_ir')
                    ->label('Mode calcul IR')
                    ->options([
                        'fixe' => 'Taux fixe',
                        'selon_regime' => 'Selon régime',
                        'aucun' => 'Pas d\'IR',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('ordre', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTypeEngagements::route('/'),
            'create' => Pages\CreateTypeEngagement::route('/create'),
            'edit' => Pages\EditTypeEngagement::route('/{record}/edit'),
            'view' => Pages\ViewTypeEngagement::route('/{record}'),
        ];
    }
}
