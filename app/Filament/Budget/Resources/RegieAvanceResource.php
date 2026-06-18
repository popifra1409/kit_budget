<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\RegieAvanceResource\Pages;
use App\Filament\Budget\Resources\RegieAvanceResource\RelationManagers;
use App\Models\RegieAvance;
use App\Models\Budget;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use App\Models\Exercice;
use Filament\Tables\Enums\ActionsPosition;

class RegieAvanceResource extends Resource
{
    protected static ?string $model           = RegieAvance::class;
    protected static ?string $navigationIcon  = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Régies d\'Avance';
    protected static ?string $modelLabel      = 'Régie d\'Avance';
    protected static ?string $pluralModelLabel = 'Régies d\'Avance';
    protected static ?string $navigationGroup = 'Régies & Menu Dépenses';
    protected static ?int    $navigationSort  = 1;
    protected static ?string $recordTitleAttribute = 'numero';

    // =========================================================
    // PERMISSIONS
    // =========================================================
    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_regie_avance') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->can('view_regie_avance') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_regie_avance') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('update_regie_avance')
            && in_array($record->statut, ['actif']);
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('delete_regie_avance')
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
                            ->disabled()
                            ->dehydrated()
                            ->placeholder('Généré automatiquement'),

                        // ✅ Live + afterStateUpdated → sync objet si vide
                        Forms\Components\TextInput::make('libelle')
                            ->label('Libellé / Désignation')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ex: Régie d\'avance principale DAAF')
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
                            ->required()
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('budget_id')
                            ->label('Budget')
                            ->options(Budget::where('actif', true)->pluck('libelle', 'id'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live(),

                        Forms\Components\Select::make('responsable_id')
                            ->label('Responsable')
                            ->options(fn() => User::orderBy('name')->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText('Utilisateur gestionnaire de cette régie'),
                    ]),
                ]),

            // ── Section 2 : Décision Administrative source ────
            Forms\Components\Section::make('Dotation et décision administrative source')
                ->description(
                    '💡 Après création de la régie, associez la DA source '
                        . 'depuis l\'onglet "Décision source" de la fiche.'
                )
                ->schema([

                    // ── Ligne 1 : Encaisse + Net à décaisser + Restant ──
                    Forms\Components\Grid::make(3)->schema([

                        // ✅ Encaisse annuelle — nouveau champ
                        Forms\Components\TextInput::make('encaisse_annuelle')
                            ->label('Encaisse annuelle (FCFA)')
                            ->numeric()
                            ->default(0)
                            ->prefix('FCFA')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                $encaisse = (float) ($state ?? 0);
                                $net      = (float) ($get('montant_alloue') ?? 0);
                                $set('montant_encaisse_restant', max(0, $encaisse - $net));
                            })
                            ->helperText('Montant total alloué annuellement à la régie'),


                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('sync_depuis_da')
                                ->label('↺ Sync montant net depuis DA associée')
                                ->icon('heroicon-o-arrow-path')
                                ->color('info')
                                ->size('sm')
                                ->visible(
                                    fn(Get $get, $record) =>
                                    $record?->decision_administrative_id !== null
                                )
                                ->action(function (Set $set, $record) {
                                    $da = \App\Models\DecisionAdministrative::find(
                                        $record?->decision_administrative_id
                                    );
                                    if (!$da) return;

                                    // ✅ Uniquement montant_alloue
                                    $set('montant_alloue', $da->montant_net);
                                    // ❌ encaisse_annuelle non touchée

                                    \Filament\Notifications\Notification::make()
                                        ->title('✅ Montant net syncé depuis ' . $da->numero)
                                        ->body(number_format($da->montant_net, 0, ',', ' ') . ' FCFA')
                                        ->success()->send();
                                }),
                        ])->columnSpanFull(),

                        // ✅ Montant net à décaisser (anciennement "Montant alloué")
                        Forms\Components\TextInput::make('montant_alloue')
                            ->label('Montant net à décaisser (FCFA)')
                            ->numeric()
                            ->default(0)
                            ->prefix('FCFA')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                $encaisse = (float) ($get('encaisse_annuelle') ?? 0);
                                $net      = (float) ($state ?? 0);
                                $set('montant_encaisse_restant', max(0, $encaisse - $net));
                            })
                            ->helperText('Sera mis à jour après association de la DA source.'),

                        // ✅ Montant encaisse restant — calculé, lecture seule
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

                    // ── Ligne 2 : Date + Objet ──────────────────────────
                    Forms\Components\Grid::make(2)->schema([

                        Forms\Components\DatePicker::make('date_creation')
                            ->label('Date de création')
                            ->default(now())
                            ->required(),

                        // ✅ Objet — auto-rempli depuis libelle, modifiable
                        Forms\Components\Textarea::make('objet')
                            ->label('Objet de la régie')
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
                            ->color('gray')
                            ->size('sm')
                            ->action(function (Set $set, Get $get) {
                                $set('objet', $get('libelle'));
                            }),
                    ])->columnSpanFull(),

                    Forms\Components\Placeholder::make('info_source')
                        ->label('')
                        ->content(new \Illuminate\Support\HtmlString(
                            '<div class="rounded-lg p-3 text-sm '
                                . 'bg-blue-50 dark:bg-blue-900/30 '
                                . 'text-blue-800 dark:text-blue-200 '
                                . 'border border-blue-200 dark:border-blue-700">'
                                . '<strong>📋 Étapes après création :</strong>'
                                . '<ol class="mt-2 ml-4 list-decimal leading-loose">'
                                . '<li>Cliquez <strong>Créer</strong> pour sauvegarder la régie</li>'
                                . '<li>Dans la fiche → onglet <strong>"Décision source"</strong> '
                                . '→ <strong>"Associer une DA"</strong></li>'
                                . '<li>Sélectionnez la DA engagée — montant et ligne budgétaire '
                                . 'seront récupérés automatiquement</li>'
                                . '</ol>'
                                . '</div>'
                        ))
                        ->columnSpanFull(),
                ]),

            // ── Section 3 : Observations ──────────────────────
            Forms\Components\Section::make('Observations')
                ->schema([
                    Forms\Components\Textarea::make('observations')
                        ->label('Observations')
                        ->rows(2)
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->collapsed(),
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
                    ->label('N° RAV')
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

                // ✅ Encaisse annuelle
                Tables\Columns\TextColumn::make('encaisse_annuelle')
                    ->label('Encaisse annuelle')
                    ->money('XAF')->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // ✅ Label changé
                Tables\Columns\TextColumn::make('montant_alloue')
                    ->label('Net à décaisser')
                    ->money('XAF')->sortable(),

                Tables\Columns\TextColumn::make('montant_decaisse')
                    ->label('Décaissé')
                    ->money('XAF')->color('warning'),

                Tables\Columns\TextColumn::make('montant_depense')
                    ->label('Dépensé')
                    ->money('XAF')->color('danger'),

                Tables\Columns\TextColumn::make('montant_disponible')
                    ->label('Disponible')
                    ->money('XAF')->weight('bold')
                    ->color(fn($record) => $record->montant_disponible < 0
                        ? 'danger' : 'success'),

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
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),

                    // ── Suspendre ────────────────────────────────
                    Tables\Actions\Action::make('suspendre')
                        ->label('Suspendre')
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->visible(
                            fn($record) =>
                            $record->statut === 'actif'
                                && auth()->user()?->can('suspendre_regie_avance')
                        )
                        ->requiresConfirmation()
                        ->modalHeading('Suspendre la régie')
                        ->modalDescription(
                            'La régie sera suspendue — aucune dépense ne pourra être enregistrée.'
                        )
                        ->action(function ($record) {
                            $record->update(['statut' => 'suspendu']);
                            Notification::make()->title('Régie suspendue')->warning()->send();
                        }),

                    // ── Réactiver ────────────────────────────────
                    Tables\Actions\Action::make('reactiver')
                        ->label('Réactiver')
                        ->icon('heroicon-o-play-circle')
                        ->color('success')
                        ->visible(
                            fn($record) =>
                            $record->statut === 'suspendu'
                                && auth()->user()?->can('suspendre_regie_avance')
                        )
                        ->requiresConfirmation()
                        ->action(function ($record) {
                            $record->update(['statut' => 'actif']);
                            Notification::make()->title('Régie réactivée')->success()->send();
                        }),

                    // ── Clôturer ─────────────────────────────────
                    Tables\Actions\Action::make('cloturer')
                        ->label('Clôturer')
                        ->icon('heroicon-o-lock-closed')
                        ->color('danger')
                        ->visible(
                            fn($record) =>
                            in_array($record->statut, ['actif', 'suspendu'])
                                && auth()->user()?->can('cloturer_regie_avance')
                        )
                        ->requiresConfirmation()
                        ->modalHeading('Clôturer la régie')
                        ->modalDescription(
                            'La régie sera définitivement clôturée. Cette action est irréversible.'
                        )
                        ->form([
                            Forms\Components\DatePicker::make('date_cloture')
                                ->label('Date de clôture')
                                ->default(now())
                                ->required(),
                            Forms\Components\Textarea::make('observations')
                                ->label('Observations de clôture')
                                ->rows(2),
                        ])
                        ->action(function ($record, array $data) {
                            $record->update([
                                'statut'       => 'cloture',
                                'date_cloture' => $data['date_cloture'],
                                'observations' => ($record->observations ?? '')
                                    . "\n\n--- CLÔTURÉE LE " . now()->format('d/m/Y') . " ---\n"
                                    . ($data['observations'] ?? ''),
                            ]);
                            Notification::make()->title('✅ Régie clôturée')->success()->send();
                        }),

                    // ── Réapprovisionner ──────────────────────────
                    Tables\Actions\Action::make('reapprovisionner')
                        ->label('Réapprovisionner')
                        ->icon('heroicon-o-arrow-path')
                        ->color('primary')
                        ->visible(
                            fn($record) =>
                            $record->statut === 'actif'
                                && auth()->user()?->can('reapprovisionner_regie_avance')
                        )
                        ->modalHeading('Réapprovisionner la régie')
                        ->form([
                            Forms\Components\Select::make('decision_administrative_id')
                                ->label('Nouvelle DA engagée')
                                ->options(function () {
                                    return \App\Models\DecisionAdministrative::where('statut', 'engagee')
                                        ->get()
                                        ->mapWithKeys(fn($da) => [
                                            $da->id => "{$da->numero} — {$da->objet} "
                                                . "(" . number_format($da->montant_net, 0, ',', ' ') . " FCFA)"
                                        ]);
                                })
                                ->helperText('DA engagée source — les montants seront pré-remplis')
                                ->required()
                                ->searchable(),
                        ])
                        ->action(function ($record, array $data) {
                            $da = \App\Models\DecisionAdministrative::findOrFail(
                                $data['decision_administrative_id']
                            );
                            $record->reapprovisionner($da);
                            Notification::make()
                                ->title('✅ Régie réapprovisionnée')
                                ->success()
                                ->body("+ " . number_format($da->montant_net, 0, ',', ' ') . " FCFA")
                                ->send();
                        }),
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
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\DecisionSourceRavRelationManager::class,
            RelationManagers\LignesRegieRelationManager::class,
            RelationManagers\DecaissementsRelationManager::class,
            RelationManagers\DepensesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRegiesAvances::route('/'),
            'create' => Pages\CreateRegieAvance::route('/create'),
            'edit'   => Pages\EditRegieAvance::route('/{record}/edit'),
            'view'   => Pages\ViewRegieAvance::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery()
            ->where('type', 'rav')
            ->with(['exercice', 'responsable', 'budget']);

        $user = auth()->user();

        if (!$user) return $query->whereRaw('1 = 0');

        // ✅ Supervision : voit tout
        if ($user->hasAnyRole(['super_admin', 'admin', 'daaf', 'agent_comptable'])) {
            return $query;
        }

        // ✅ Permission view_any = voit toutes les régies
        if ($user->can('view_any_regie_avance')) {
            return $query;
        }

        // ✅ Autres : uniquement ses régies (responsable)
        return $query->where('responsable_id', $user->id);
    }
}
