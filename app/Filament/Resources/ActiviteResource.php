<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActiviteResource\Pages;
use App\Models\Activite;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Forms\Components\ExerciceSelect;
use App\Models\Exercice;
use Filament\Notifications\Notification;


class ActiviteResource extends Resource
{
    protected static ?string $model = Activite::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Activités';

    protected static ?string $modelLabel = 'Activité';

    protected static ?string $pluralModelLabel = 'Activités';

    protected static ?string $navigationGroup = 'Cadre Logique';

    protected static ?int $navigationSort = 3;

    /**
     * ================================
     * Permissions - ActiviteResource
     * ================================
     */

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_activite') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->can('view_activite') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_activite') ?? false;
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();

        if (!$user?->can('update_activite')) {
            return false;
        }

        // Règle métier : exercice modifiable
        if (!$record->estModifiable()) {
            if (request()->routeIs('filament.*')) {
                Notification::make()
                    ->title('Exercice verrouillé')
                    ->warning()
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

        if (!$user?->can('delete_activite')) {
            return false;
        }

        return $record->estModifiable();
    }

    /**
     * Message personnalisé quand édition impossible
     */
    public static function canEditRecord($record): bool
    {
        $canEdit = static::canEdit($record);

        if (!$canEdit && $record->estLectureSeule()) {
            Notification::make()
                ->title('Édition impossible')
                ->warning()
                ->body("L'exercice {$record->exercice->annee} est {$record->exercice->statut}. Seul un super admin peut modifier.")
                ->send();
        }

        return $canEdit;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Exercice')
                    ->description('Exercice budgétaire de rattachement')
                    ->schema([
                        ExerciceSelect::make(),
                    ])
                    ->collapsible()
                    ->collapsed(fn($record) => $record !== null),

                Forms\Components\Section::make('Informations de l\'Activité')
                    ->schema([
                        Forms\Components\Select::make('action_id')
                            ->label('Action')
                            ->relationship('action', 'libelle')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->getOptionLabelFromRecordUsing(fn($record) => "{$record->programme->code} > {$record->code} - {$record->libelle}"),

                        Forms\Components\TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->maxLength(50)
                            ->placeholder('Ex: ACT1'),

                        Forms\Components\TextInput::make('libelle')
                            ->label('Libellé')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->placeholder('Ex: Améliorer la prise en charge des maladies'),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('ordre')
                            ->label('Ordre d\'affichage')
                            ->numeric()
                            ->default(0),

                        Forms\Components\Toggle::make('actif')
                            ->label('Actif')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'exercice',
                'action.programme',
                'taches.sousTaches'
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\BadgeColumn::make('exercice.annee')
                    ->label('Exercice')
                    ->sortable()
                    ->colors([
                        'success' => fn($record) => $record->exercice?->estActif(),
                        'warning' => fn($record) => $record->exercice?->estCloture(),
                        'danger' => fn($record) => $record->exercice?->estArchive(),
                        'gray' => fn($record) => $record->exercice?->estBrouillon(),
                    ])
                    ->toggleable(),

                Tables\Columns\TextColumn::make('action.programme.code')
                    ->label('Programme')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('action.code')
                    ->label('Action')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('libelle')
                    ->label('Libellé')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(50),

                Tables\Columns\TextColumn::make('taches_count')
                    ->label('Tâches')
                    ->state(function ($record) {
                        $taches = $record->taches->where('niveau', 'tache')->count();
                        $sousTaches = $record->taches->where('niveau', 'sous_tache')->count();
                        return $taches . ($sousTaches > 0 ? " ({$sousTaches})" : '');
                    })
                    ->badge()
                    ->color('success')
                    ->tooltip(
                        fn($record) =>
                        $record->taches->where('niveau', 'tache')->count() . ' tâche(s), ' .
                            $record->taches->where('niveau', 'sous_tache')->count() . ' sous-tâche(s)'
                    ),

                Tables\Columns\TextColumn::make('budget_ae')
                    ->label('Budget (AE)')
                    ->state(function ($record) {
                        // Calcul direct sur la collection déjà chargée
                        return $record->taches
                            ->where('niveau', 'tache')
                            ->sum(fn($tache) => $tache->sousTaches->sum('ae'));
                    })
                    ->formatStateUsing(
                        fn($state) =>
                        $state > 0
                            ? number_format($state, 0, ',', ' ') . ' FCFA'
                            : '—'
                    )
                    ->color(fn($state) => $state > 0 ? 'success' : 'gray')
                    ->sortable(false),

                Tables\Columns\TextColumn::make('budget_cp')
                    ->label('Budget (CP)')
                    ->state(function ($record) {
                        return $record->taches
                            ->where('niveau', 'tache')
                            ->sum(fn($tache) => $tache->sousTaches->sum('cp'));
                    })
                    ->formatStateUsing(
                        fn($state) =>
                        $state > 0
                            ? number_format($state, 0, ',', ' ') . ' FCFA'
                            : '—'
                    )
                    ->color(fn($state) => $state > 0 ? 'warning' : 'gray')
                    ->sortable(false)
                    ->toggleable(),

                Tables\Columns\IconColumn::make('actif')
                    ->label('Actif')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('exercice_id')
                    ->label('Exercice')
                    ->relationship('exercice', 'annee')
                    ->searchable()
                    ->preload()
                    ->placeholder('Tous les exercices')
                    ->default(fn() => Exercice::getActif()?->id),

                Tables\Filters\SelectFilter::make('action_id')
                    ->label('Action')
                    ->relationship('action', 'libelle')
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('actif')
                    ->label('Actif')
                    ->placeholder('Tous')
                    ->trueLabel('Actifs')
                    ->falseLabel('Inactifs'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('code', 'asc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivites::route('/'),
            'create' => Pages\CreateActivite::route('/create'),
            'edit' => Pages\EditActivite::route('/{record}/edit'),
        ];
    }
}
