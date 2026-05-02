<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\MenuDepenseResource\Pages;
use App\Filament\Budget\Resources\RegieAvanceResource\RelationManagers;
use App\Models\RegieAvance;
use App\Models\Budget;
use App\Models\User;
use App\Models\Exercice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class MenuDepenseResource extends Resource
{
    protected static ?string $model = RegieAvance::class;

    protected static ?string $navigationIcon   = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel  = 'Menus Dépenses';
    protected static ?string $modelLabel       = 'Menu Dépense';
    protected static ?string $pluralModelLabel = 'Menus Dépenses';
    protected static ?string $navigationGroup  = 'Régies & Menu Dépenses';
    protected static ?int    $navigationSort   = 2;
    protected static ?string $slug             = 'menus-depenses';

    // =========================================================
    // PERMISSIONS
    // =========================================================
    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_menu_depense') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->can('view_menu_depense') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_menu_depense') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('update_menu_depense')
            && $record->statut === 'actif';
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('delete_menu_depense')
            && $record->statut === 'actif'
            && $record->montant_depense == 0;
    }

    // =========================================================
    // FORMULAIRE
    // =========================================================
    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('Identification')
                ->schema([
                    Forms\Components\Grid::make(3)->schema([

                        Forms\Components\TextInput::make('numero')
                            ->label('Numéro')
                            ->disabled()->dehydrated()
                            ->placeholder('Généré automatiquement'),

                        Forms\Components\TextInput::make('libelle')
                            ->label('Libellé / Désignation')
                            ->required()->maxLength(255)
                            ->placeholder('Ex: Menu Dépense Fonctionnement T1')
                            ->columnSpan(2),
                    ]),

                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Select::make('exercice_id')
                            ->label('Exercice')
                            ->options(fn() => Exercice::orderByDesc('annee')
                                ->pluck('annee', 'id'))
                            ->default(fn() => Exercice::getActif()?->id)
                            ->required()->searchable()->preload(),

                        Forms\Components\Select::make('budget_id')
                            ->label('Budget')
                            ->options(Budget::where('actif', true)->pluck('libelle', 'id'))
                            ->required()->searchable()->preload()->live(),

                        Forms\Components\Select::make('responsable_id')
                            ->label('Responsable')
                            ->options(fn() => User::orderBy('name')->pluck('name', 'id'))
                            ->required()->searchable()->preload()
                            ->helperText('Gestionnaire du Menu Dépense'),
                    ]),
                ]),

            // ✅ Menu Dépense : plusieurs DA possibles (multi-lignes)
            Forms\Components\Section::make('Décisions Administratives sources')
                ->description('Le Menu Dépense peut être alimenté par plusieurs DA sur différentes nomenclatures')
                ->schema([
                    Forms\Components\Select::make('decision_administrative_id')
                        ->label('DA principale (approvisionnement initial)')
                        ->options(function () {
                            return \App\Models\DecisionAdministrative::where('statut', 'engagee')
                                ->get()
                                ->mapWithKeys(fn($da) => [
                                    $da->id => "{$da->numero} — {$da->objet} "
                                        . "(" . number_format($da->montant_net, 0, ',', ' ') . " FCFA)"
                                ]);
                        })
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set) {
                            if (!$state) return;
                            $da = \App\Models\DecisionAdministrative::find($state);
                            if ($da) {
                                $set('budget_id',   $da->budget_id);
                                $set('exercice_id', $da->exercice_id);
                            }
                        })
                        ->helperText('Les montants alloués seront définis ligne par ligne')
                        ->columnSpanFull(),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('montant_alloue')
                            ->label('Montant total alloué (FCFA)')
                            ->numeric()->required()->prefix('FCFA')
                            ->helperText('Somme de toutes les DA sources'),

                        Forms\Components\DatePicker::make('date_creation')
                            ->label('Date de création')
                            ->default(now())->required(),
                    ]),
                ]),

            Forms\Components\Section::make('Observations')
                ->schema([
                    Forms\Components\Textarea::make('observations')
                        ->label('Observations')->rows(2)->columnSpanFull(),
                ])
                ->collapsible()->collapsed(),
        ]);
    }

    // =========================================================
    // TABLEAU
    // =========================================================
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('N° MDE')
                    ->searchable()->sortable()
                    ->weight('bold')->copyable(),

                Tables\Columns\TextColumn::make('libelle')
                    ->label('Désignation')
                    ->searchable()->limit(35)
                    ->tooltip(fn($record) => $record->libelle),

                Tables\Columns\TextColumn::make('responsable.name')
                    ->label('Responsable')
                    ->searchable()->sortable(),

                Tables\Columns\TextColumn::make('exercice.annee')
                    ->label('Exercice')
                    ->badge()->color('info'),

                Tables\Columns\TextColumn::make('lignes_count')
                    ->label('Lignes')
                    ->counts('lignes')
                    ->badge()->color('gray')
                    ->tooltip('Nombre de nomenclatures'),

                Tables\Columns\TextColumn::make('montant_alloue')
                    ->label('Alloué')
                    ->money('XAF')->sortable(),

                Tables\Columns\TextColumn::make('montant_depense')
                    ->label('Dépensé')
                    ->money('XAF')->color('danger'),

                Tables\Columns\TextColumn::make('montant_disponible')
                    ->label('Disponible')
                    ->money('XAF')->weight('bold')
                    ->color(
                        fn($record) =>
                        $record->montant_disponible < 0 ? 'danger' : 'success'
                    ),

                Tables\Columns\TextColumn::make('taux_consommation')
                    ->label('Consommation')
                    ->formatStateUsing(
                        fn($record) =>
                        number_format($record->taux_consommation, 1) . '%'
                    )
                    ->badge()
                    ->color(fn($record) => match (true) {
                        $record->taux_consommation >= 90 => 'danger',
                        $record->taux_consommation >= 70 => 'warning',
                        default                          => 'success',
                    }),

                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
                    ->colors([
                        'success' => 'actif',
                        'warning' => 'suspendu',
                        'danger'  => 'cloture',
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'actif'    => 'Actif',
                        'suspendu' => 'Suspendu',
                        'cloture'  => 'Clôturé',
                        default    => $state,
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('statut')
                    ->options([
                        'actif'    => 'Actif',
                        'suspendu' => 'Suspendu',
                        'cloture'  => 'Clôturé',
                    ]),
                Tables\Filters\SelectFilter::make('exercice_id')
                    ->label('Exercice')
                    ->relationship('exercice', 'annee')
                    ->default(fn() => Exercice::getActif()?->id),
                Tables\Filters\SelectFilter::make('responsable_id')
                    ->label('Responsable')
                    ->relationship('responsable', 'name')
                    ->searchable()->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('suspendre')
                    ->label('Suspendre')
                    ->icon('heroicon-o-pause-circle')->color('warning')
                    ->visible(
                        fn($record) =>
                        $record->statut === 'actif'
                            && auth()->user()?->can('suspendre_menu_depense')
                    )
                    ->requiresConfirmation()
                    ->action(fn($record) => $record->update(['statut' => 'suspendu'])),

                Tables\Actions\Action::make('reactiver')
                    ->label('Réactiver')
                    ->icon('heroicon-o-play-circle')->color('success')
                    ->visible(
                        fn($record) =>
                        $record->statut === 'suspendu'
                            && auth()->user()?->can('suspendre_menu_depense')
                    )
                    ->requiresConfirmation()
                    ->action(fn($record) => $record->update(['statut' => 'actif'])),

                Tables\Actions\Action::make('cloturer')
                    ->label('Clôturer')
                    ->icon('heroicon-o-lock-closed')->color('danger')
                    ->visible(
                        fn($record) =>
                        in_array($record->statut, ['actif', 'suspendu'])
                            && auth()->user()?->can('cloturer_menu_depense')
                    )
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\DatePicker::make('date_cloture')
                            ->label('Date de clôture')
                            ->default(now())->required(),
                        Forms\Components\Textarea::make('observations')
                            ->label('Observations')->rows(2),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'statut'       => 'cloture',
                            'date_cloture' => $data['date_cloture'],
                            'observations' => ($record->observations ?? '')
                                . "\n\n--- CLÔTURÉ LE " . now()->format('d/m/Y') . " ---\n"
                                . ($data['observations'] ?? ''),
                        ]);
                        Notification::make()->title('✅ Menu Dépense clôturé')->success()->send();
                    }),

                Tables\Actions\Action::make('reapprovisionner')
                    ->label('Réapprovisionner')
                    ->icon('heroicon-o-arrow-path')->color('primary')
                    ->visible(
                        fn($record) =>
                        $record->statut === 'actif'
                            && auth()->user()?->can('reapprovisionner_menu_depense')
                    )
                    ->form([
                        Forms\Components\Select::make('decision_administrative_id')
                            ->label('Nouvelle DA engagée')
                            ->options(function ($record) {
                                return \App\Models\DecisionAdministrative::where('statut', 'engagee')
                                    ->where('budget_id', $record->budget_id)
                                    ->get()
                                    ->mapWithKeys(fn($da) => [
                                        $da->id => "{$da->numero} — "
                                            . number_format($da->montant_net, 0, ',', ' ')
                                            . " FCFA"
                                    ]);
                            })
                            ->required()->searchable(),
                    ])
                    ->action(function ($record, array $data) {
                        $da = \App\Models\DecisionAdministrative::findOrFail(
                            $data['decision_administrative_id']
                        );
                        $record->reapprovisionner($da);
                        Notification::make()
                            ->title('✅ Menu Dépense réapprovisionné')
                            ->success()
                            ->body("+ " . number_format($da->montant_net, 0, ',', ' ') . " FCFA")
                            ->send();
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LignesRegieRelationManager::class,
            RelationManagers\DecaissementsRelationManager::class,
            RelationManagers\DepensesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMenuDepenses::route('/'),
            'create' => Pages\CreateMenuDepense::route('/create'),
            'edit'   => Pages\EditMenuDepense::route('/{record}/edit'),
            'view'   => Pages\ViewMenuDepense::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery()
            ->where('type', 'menu_depense')
            ->with(['exercice', 'responsable', 'budget', 'lignes']);

        $user = auth()->user();
        if ($user && !$user->hasAnyRole(['super_admin', 'admin', 'daaf', 'agence_comptable'])) {
            $query->where('responsable_id', $user->id);
        }

        return $query;
    }
}
