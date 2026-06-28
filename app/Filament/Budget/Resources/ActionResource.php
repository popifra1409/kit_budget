<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\ActionResource\Pages;
use App\Models\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Enums\ActionsPosition;
use App\Filament\Forms\Components\ExerciceSelect;
use App\Models\Exercice;
use Filament\Notifications\Notification;

class ActionResource extends Resource
{
    protected static ?string $model = Action::class;
    protected static ?string $navigationIcon = 'heroicon-o-bolt';
    protected static ?string $navigationLabel = 'Actions';
    protected static ?string $modelLabel = 'Action';
    protected static ?string $pluralModelLabel = 'Actions';
    protected static ?string $navigationGroup = 'Cadre Logique';
    protected static ?int $navigationSort = 2;

    // ========================================
    // PERMISSIONS
    // ========================================

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_action') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->can('view_action') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_action') ?? false;
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();
        if (!$user?->can('update_action')) return false;

        if (!$record->estModifiable()) {
            if (request()->routeIs('filament.*')) {
                Notification::make()->title('Exercice verrouillé')->warning()
                    ->body("L'exercice {$record->exercice->annee} est {$record->exercice->getBadgeStatut()}. Modifications impossibles.")
                    ->send();
            }
            return false;
        }

        return true;
    }

    public static function canDelete($record): bool
    {
        $user = auth()->user();
        if (!$user?->can('delete_action')) return false;
        return $record->estModifiable();
    }

    public static function canEditRecord($record): bool
    {
        $canEdit = static::canEdit($record);
        if (!$canEdit && $record->estLectureSeule()) {
            Notification::make()->title('Édition impossible')->warning()
                ->body("L'exercice {$record->exercice->annee} est {$record->exercice->statut}. Seul un super admin peut modifier.")
                ->send();
        }
        return $canEdit;
    }

    // ========================================
    // FORM
    // ========================================

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informations de l\'Action')
                    ->schema([
                        Forms\Components\Section::make('Exercice')
                            ->description('Exercice budgétaire de rattachement')
                            ->schema([ExerciceSelect::make()])
                            ->collapsible()
                            ->collapsed(fn($record) => $record !== null),

                        Forms\Components\Select::make('programme_id')
                            ->label('Programme')
                            ->relationship('programme', 'libelle')
                            ->required()->searchable()->preload()
                            ->getOptionLabelFromRecordUsing(fn($record) => "{$record->code} - {$record->libelle}"),

                        Forms\Components\TextInput::make('code')
                            ->label('Code')->required()->maxLength(50)->placeholder('Ex: A4'),

                        Forms\Components\TextInput::make('libelle')
                            ->label('Libellé')->required()->maxLength(255)->columnSpanFull()
                            ->placeholder('Ex: Prise en charge des maladies chroniques'),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')->rows(3)->columnSpanFull(),

                        Forms\Components\TextInput::make('ordre')
                            ->label('Ordre d\'affichage')->numeric()->default(0),

                        Forms\Components\Toggle::make('actif')->label('Actif')->default(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Objectif Spécifique')
                    ->description('Définissez l\'objectif spécifique de cette action')
                    ->schema([
                        Forms\Components\Repeater::make('objectifsSpecifiques')
                            ->relationship('objectifsSpecifiques')->label('')
                            ->schema([
                                Forms\Components\Textarea::make('libelle')
                                    ->label('Objectif spécifique')->required()->rows(2)
                                    ->placeholder('Ex: Améliorer la prise en charge des cas chroniques...'),
                                Forms\Components\TextInput::make('ordre')
                                    ->label('Ordre')->numeric()->default(0),
                            ])
                            ->columnSpanFull()->defaultItems(1)
                            ->addActionLabel('Ajouter un objectif spécifique')
                            ->collapsible()
                            ->itemLabel(fn(array $state): ?string => \Str::limit($state['libelle'] ?? 'Nouvel objectif', 50)),
                    ])
                    ->collapsible()
                    ->collapsed(fn($record) => $record !== null),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'exercice',
                'activites.taches' => function ($query) {
                    $query->where('niveau', 'tache')->with('sousTaches');
                }
            ]);
    }

    // ========================================
    // TABLE
    // ========================================

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\BadgeColumn::make('exercice.annee')
                    ->label('Exercice')->sortable()
                    ->colors([
                        'success' => fn($record) => $record->exercice instanceof \App\Models\Exercice && $record->exercice->estActif(),
                        'warning' => fn($record) => $record->exercice instanceof \App\Models\Exercice && $record->exercice->estCloture(),
                        'danger'  => fn($record) => $record->exercice instanceof \App\Models\Exercice && $record->exercice->estArchive(),
                        'gray'    => fn($record) => $record->exercice instanceof \App\Models\Exercice && $record->exercice->estBrouillon(),
                    ])
                    ->tooltip(fn($record) => $record->exercice instanceof \App\Models\Exercice ? $record->exercice->libelle : null)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('programme.code')
                    ->label('Programme')->searchable()->sortable()->badge()->color('primary'),

                Tables\Columns\TextColumn::make('code')
                    ->label('Code')->searchable()->sortable()->weight('bold'),

                Tables\Columns\TextColumn::make('libelle')
                    ->label('Libellé')->searchable()->sortable()->wrap()->limit(50),

                Tables\Columns\TextColumn::make('activites_count')
                    ->label('Activités')->counts('activites')->badge()->color('success'),

                Tables\Columns\TextColumn::make('total_ae')
                    ->label('Total AE')
                    ->formatStateUsing(
                        fn($record) =>
                        number_format($record->getTotalAe(), 0, ',', ' ') . ' FCFA'
                    )
                    ->color('warning')
                    ->tooltip('Autorisations d\'Engagement - Somme de toutes les activités')
                    ->toggleable()->sortable(false),

                Tables\Columns\TextColumn::make('total_cp')
                    ->label('Total CP')
                    ->formatStateUsing(
                        fn($record) =>
                        number_format($record->getTotalCp(), 0, ',', ' ') . ' FCFA'
                    )
                    ->color('info')
                    ->tooltip('Crédits de Paiement - Somme de toutes les activités')
                    ->toggleable()->sortable(false),

                Tables\Columns\IconColumn::make('actif')->label('Actif')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('exercice_id')
                    ->label('Exercice')
                    ->relationship('exercice', 'annee')
                    ->searchable()->preload()
                    ->placeholder('Tous les exercices')
                    ->default(fn() => Exercice::getActif()?->id),

                Tables\Filters\SelectFilter::make('programme_id')
                    ->label('Programme')
                    ->relationship('programme', 'libelle')->searchable()->preload(),

                Tables\Filters\TernaryFilter::make('actif')
                    ->label('Actif')->placeholder('Tous')
                    ->trueLabel('Actifs')->falseLabel('Inactifs'),
            ])

            // ════════════════════════════════════════════════════════
            // ✅ ACTIONS — un seul ActionGroup, aligné à gauche
            //    Pattern identique à BonCommandeResource
            // ════════════════════════════════════════════════════════
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')
                    ->button()
                    ->size('sm'),

            ], position: ActionsPosition::BeforeColumns)

            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('code', 'asc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListActions::route('/'),
            'create' => Pages\CreateAction::route('/create'),
            'edit'   => Pages\EditAction::route('/{record}/edit'),
        ];
    }
}
