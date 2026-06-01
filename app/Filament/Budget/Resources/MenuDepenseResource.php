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
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class MenuDepenseResource extends Resource
{
    protected static ?string $model           = RegieAvance::class;
    protected static ?string $navigationIcon  = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Menus Dépenses';
    protected static ?string $modelLabel      = 'Menu Dépense';
    protected static ?string $pluralModelLabel = 'Menus Dépenses';
    protected static ?string $navigationGroup = 'Régies & Menu Dépenses';
    protected static ?int    $navigationSort  = 2;
    protected static ?string $slug            = 'menus-depenses';

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

            // ── Section 1 : Identification ────────────────────
            Forms\Components\Section::make('Identification')
                ->schema([
                    Forms\Components\Grid::make(3)->schema([

                        Forms\Components\TextInput::make('numero')
                            ->label('Numéro')
                            ->disabled()->dehydrated()
                            ->placeholder('Généré automatiquement'),

                        // ✅ Live pour sync objet
                        Forms\Components\TextInput::make('libelle')
                            ->label('Libellé / Désignation')
                            ->required()->maxLength(255)
                            ->placeholder('Ex: Menu Dépense Fonctionnement T1')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                if (empty($get('objet'))) {
                                    $set('objet', $state);
                                }
                            })
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

            // ── Section 2 : Dotation ──────────────────────────
            // ✅ Même logique RAV mais montant_alloue = cumul des DA sources
            Forms\Components\Section::make('Dotation et décisions administratives sources')
                ->description(
                    '💡 Le montant net à décaisser est la somme de toutes les DA sources. '
                        . 'Il sera recalculé automatiquement après ajout des DA sources.'
                )
                ->schema([

                    // ── Ligne 1 : Encaisse + Net + Restant ───────
                    Forms\Components\Grid::make(3)->schema([

                        // ✅ Encaisse annuelle — saisie manuelle
                        Forms\Components\TextInput::make('encaisse_annuelle')
                            ->label('Encaisse annuelle (FCFA)')
                            ->numeric()->default(0)->prefix('FCFA')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                $encaisse = (float) ($state ?? 0);
                                $net      = (float) ($get('montant_alloue') ?? 0);
                                $set('montant_encaisse_restant', max(0, $encaisse - $net));
                            })
                            ->helperText('Montant total alloué annuellement au Menu Dépense'),

                        // ✅ Montant net à décaisser = cumul DA sources (lecture seule en édition)
                        Forms\Components\TextInput::make('montant_alloue')
                            ->label('Montant net à décaisser (FCFA)')
                            ->numeric()->default(0)->prefix('FCFA')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                $encaisse = (float) ($get('encaisse_annuelle') ?? 0);
                                $net      = (float) ($state ?? 0);
                                $set('montant_encaisse_restant', max(0, $encaisse - $net));
                            })
                            ->helperText('Recalculé automatiquement depuis les DA sources'),

                        // ✅ Encaisse restante — calculée
                        Forms\Components\Placeholder::make('montant_encaisse_restant_affiche')
                            ->label('Montant encaisse restant (FCFA)')
                            ->content(function (Get $get, $record) {
                                $encaisse = (float) ($get('encaisse_annuelle')
                                    ?? $record?->encaisse_annuelle ?? 0);
                                $net = (float) ($get('montant_alloue')
                                    ?? $record?->montant_alloue ?? 0);
                                $restant = max(0, $encaisse - $net);
                                $style = $restant <= 0
                                    ? 'color:red; font-weight:bold;'
                                    : 'color:green; font-weight:bold;';
                                return new \Illuminate\Support\HtmlString(
                                    "<span style='{$style}'>"
                                        . number_format($restant, 0, ',', ' ')
                                        . ' FCFA</span>'
                                );
                            }),
                    ]),

                    // ── Ligne 2 : Date + Objet ───────────────────
                    Forms\Components\Grid::make(2)->schema([

                        Forms\Components\DatePicker::make('date_creation')
                            ->label('Date de création')
                            ->default(now())->required(),

                        // ✅ Objet auto-rempli depuis libelle, modifiable
                        Forms\Components\Textarea::make('objet')
                            ->label('Objet du Menu Dépense')
                            ->rows(2)
                            ->placeholder('Rempli automatiquement depuis le libellé')
                            ->helperText('Récupéré depuis le libellé — modifiable')
                            ->afterStateHydrated(function ($state, Set $set, Get $get) {
                                if (empty($state) && !empty($get('libelle'))) {
                                    $set('objet', $get('libelle'));
                                }
                            }),
                    ]),

                    // ✅ Bouton sync objet ← libellé
                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('sync_objet')
                            ->label('↺ Synchroniser objet depuis libellé')
                            ->icon('heroicon-o-arrow-path')
                            ->color('gray')->size('sm')
                            ->action(function (Set $set, Get $get) {
                                $set('objet', $get('libelle'));
                            }),
                    ])->columnSpanFull(),

                    // ✅ Message informatif multi-DA
                    Forms\Components\Placeholder::make('info_sources')
                        ->label('')
                        ->content(new \Illuminate\Support\HtmlString(
                            '<div class="rounded-lg p-3 text-sm '
                                . 'bg-blue-50 dark:bg-blue-900/30 '
                                . 'text-blue-800 dark:text-blue-200 '
                                . 'border border-blue-200 dark:border-blue-700">'
                                . '<strong>📋 Étapes après création :</strong>'
                                . '<ol class="mt-2 ml-4 list-decimal leading-loose">'
                                . '<li>Cliquez <strong>Créer</strong> pour sauvegarder</li>'
                                . '<li>Onglet <strong>"Décisions sources"</strong> '
                                . '→ <strong>"Ajouter une DA source"</strong></li>'
                                . '<li>Ajoutez <strong>autant de DA</strong> que nécessaire — '
                                . 'chaque DA crée une ligne de nomenclature</li>'
                                . '<li>Le <strong>montant net à décaisser</strong> '
                                . 'sera recalculé automatiquement</li>'
                                . '</ol>'
                                . '</div>'
                        ))
                        ->columnSpanFull(),
                ]),

            // ── Section 3 : Observations ──────────────────────
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

                // ✅ Encaisse annuelle
                Tables\Columns\TextColumn::make('encaisse_annuelle')
                    ->label('Encaisse annuelle')
                    ->money('XAF')->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // ✅ Label changé
                Tables\Columns\TextColumn::make('montant_alloue')
                    ->label('Net à décaisser')
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

                // ── Suspendre ────────────────────────────────
                Tables\Actions\Action::make('suspendre')
                    ->label('Suspendre')
                    ->icon('heroicon-o-pause-circle')->color('warning')
                    ->visible(
                        fn($record) =>
                        $record->statut === 'actif'
                            && auth()->user()?->can('suspendre_menu_depense')
                    )
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['statut' => 'suspendu']);
                        Notification::make()->title('Menu Dépense suspendu')->warning()->send();
                    }),

                // ── Réactiver ────────────────────────────────
                Tables\Actions\Action::make('reactiver')
                    ->label('Réactiver')
                    ->icon('heroicon-o-play-circle')->color('success')
                    ->visible(
                        fn($record) =>
                        $record->statut === 'suspendu'
                            && auth()->user()?->can('suspendre_menu_depense')
                    )
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['statut' => 'actif']);
                        Notification::make()->title('✅ Menu Dépense réactivé')->success()->send();
                    }),

                // ── Clôturer ─────────────────────────────────
                Tables\Actions\Action::make('cloturer')
                    ->label('Clôturer')
                    ->icon('heroicon-o-lock-closed')->color('danger')
                    ->visible(
                        fn($record) =>
                        in_array($record->statut, ['actif', 'suspendu'])
                            && auth()->user()?->can('cloturer_menu_depense')
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Clôturer le Menu Dépense')
                    ->modalDescription('Cette action est irréversible.')
                    ->form([
                        Forms\Components\DatePicker::make('date_cloture')
                            ->label('Date de clôture')
                            ->default(now())->required(),
                        Forms\Components\Textarea::make('observations')
                            ->label('Observations de clôture')->rows(2),
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

                // ── Réapprovisionner ──────────────────────────
                // ✅ Multi-DA : chaque ajout incrémente montant_alloue
                Tables\Actions\Action::make('reapprovisionner')
                    ->label('Réapprovisionner')
                    ->icon('heroicon-o-arrow-path')->color('primary')
                    ->visible(
                        fn($record) =>
                        $record->statut === 'actif'
                            && auth()->user()?->can('reapprovisionner_menu_depense')
                    )
                    ->modalHeading('Réapprovisionner le Menu Dépense')
                    ->form([
                        Forms\Components\Select::make('decision_administrative_id')
                            ->label('Nouvelle DA engagée')
                            ->options(function ($record) {
                                return \App\Models\DecisionAdministrative::where('statut', 'engagee')
                                    ->where('budget_id', $record->budget_id)
                                    ->get()
                                    ->mapWithKeys(fn($da) => [
                                        $da->id => "{$da->numero} — {$da->objet} "
                                            . "(" . number_format($da->montant_net, 0, ',', ' ')
                                            . " FCFA)"
                                    ]);
                            })
                            ->required()->searchable()
                            ->helperText('Chaque DA ajoutée incrémente le montant net à décaisser'),
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
            \App\Filament\Budget\Resources\MenuDepenseResource\RelationManagers\DecisionsSourcesRelationManager::class,
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

        if (!$user) return $query->whereRaw('1 = 0');

        if ($user->hasAnyRole(['super_admin', 'admin', 'daaf', 'agence_comptable'])) {
            return $query;
        }

        if ($user->can('view_any_menu_depense')) {
            return $query;
        }

        return $query->where('responsable_id', $user->id);
    }
}
