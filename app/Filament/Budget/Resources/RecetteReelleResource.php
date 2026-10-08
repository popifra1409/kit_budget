<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\RecetteReelleResource\Pages;
use App\Models\RecetteReelle;
use App\Models\PrevisionRecette;
use App\Models\PrevisionRecetteMensuelle;
use App\Models\Exercice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Database\Eloquent\Builder;

class RecetteReelleResource extends Resource
{
    protected static ?string $model = RecetteReelle::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Recettes Réelles';
    protected static ?string $modelLabel      = 'Recette Réelle';
    protected static ?string $pluralModelLabel = 'Recettes Réelles';
    protected static ?string $navigationGroup = 'Contrôle & Suivi';
    protected static ?int    $navigationSort  = 10;

    private static array $moisLabels = [
        1 => 'Jan',
        2 => 'Fév',
        3 => 'Mar',
        4 => 'Avr',
        5 => 'Mai',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Aoû',
        9 => 'Sep',
        10 => 'Oct',
        11 => 'Nov',
        12 => 'Déc',
    ];

    private static array $moisOptions = [
        1 => 'Janvier',
        2 => 'Février',
        3 => 'Mars',
        4 => 'Avril',
        5 => 'Mai',
        6 => 'Juin',
        7 => 'Juillet',
        8 => 'Août',
        9 => 'Septembre',
        10 => 'Octobre',
        11 => 'Novembre',
        12 => 'Décembre',
    ];

    // ========================================
    // NAVIGATION BADGE
    // ========================================

    public static function getNavigationBadge(): ?string
    {
        try {
            $exercice = Exercice::getActif();
            if (!$exercice) return null;
            $count = RecetteReelle::where('exercice_id', $exercice->id)
                ->where('statut', 'comptabilisee')->count();
            return $count > 0 ? (string) $count : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    // ========================================
    // PERMISSIONS
    // ========================================

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->can('view_any_recette_reelle');
    }

    public static function canView($record): bool
    {
        return auth()->check() && auth()->user()->can('view_recette_reelle');
    }

    public static function canCreate(): bool
    {
        return auth()->check() && auth()->user()->can('create_recette_reelle');
    }

    public static function canEdit($record): bool
    {
        if (!auth()->check()) return false;
        return auth()->user()->can('update_recette_reelle');
    }

    public static function canDelete($record): bool
    {
        if (!auth()->check()) return false;
        if (!auth()->user()->can('delete_recette_reelle')) return false;
        return $record->statut !== 'validee';
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        // ⚠️ Pas de tri ici : il passerait avant le regroupement par mois et fragmenterait les groupes.
        //    Le tri par date est assuré par defaultSort(), à l'intérieur de chaque mois.
        $query = parent::getEloquentQuery()
            ->with(['previsionRecetteMensuelle.lignePrevisionRecette']);

        $exerciceActif = Exercice::getActif();
        if ($exerciceActif) {
            $query->where('exercice_id', $exerciceActif->id);
        }

        return $query;
    }

    // ========================================
    // FORM
    // ========================================

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('Identification')
                ->schema([
                    Forms\Components\Select::make('exercice_id')
                        ->label('Exercice')
                        ->options(fn() => Exercice::orderByDesc('annee')->pluck('annee', 'id'))
                        ->default(fn() => Exercice::getActif()?->id)
                        ->required()->live()
                        ->afterStateUpdated(fn($set) => $set('prevision_recette_mensuelle_id', null)),

                    Forms\Components\Select::make('mois')
                        ->label('Mois')
                        ->options(self::$moisOptions)
                        ->default(now()->month)
                        ->required()->live()
                        ->afterStateUpdated(fn($set) => $set('prevision_recette_mensuelle_id', null)),

                    Forms\Components\DatePicker::make('date_recette')
                        ->label('Date de la recette (constatation)')
                        ->default(now())->required(),
                ])
                ->columns(3),

            Forms\Components\Section::make('Ligne de nomenclature')
                ->schema([
                    Forms\Components\Select::make('prevision_recette_mensuelle_id')
                        ->label('Ligne de prévision (nomenclature + mois)')
                        ->options(function (Get $get) {
                            $exerciceId = $get('exercice_id');
                            $mois       = $get('mois');
                            if (!$exerciceId || !$mois) return [];

                            return PrevisionRecetteMensuelle::with('lignePrevisionRecette')
                                ->where('exercice_id', $exerciceId)
                                ->where('mois', $mois)->get()
                                ->mapWithKeys(fn($pm) => [
                                    $pm->id => "[{$pm->lignePrevisionRecette?->code_nomenclature}] {$pm->lignePrevisionRecette?->libelle_nomenclature}",
                                ]);
                        })
                        ->getSearchResultsUsing(function (string $search, Get $get) {
                            $exerciceId = $get('exercice_id');
                            $mois       = $get('mois');
                            if (!$exerciceId || !$mois) return [];

                            return PrevisionRecetteMensuelle::with('lignePrevisionRecette')
                                ->where('exercice_id', $exerciceId)
                                ->where('mois', $mois)
                                ->whereHas(
                                    'lignePrevisionRecette',
                                    fn($q) =>
                                    $q->where('libelle_nomenclature', 'ilike', "%{$search}%")
                                        ->orWhere('code_nomenclature', 'ilike', "%{$search}%")
                                )
                                ->get()
                                ->mapWithKeys(fn($pm) => [
                                    $pm->id => "[{$pm->lignePrevisionRecette?->code_nomenclature}] {$pm->lignePrevisionRecette?->libelle_nomenclature}",
                                ]);
                        })
                        ->searchable()->required()->live()
                        ->afterStateUpdated(function ($state, $set) {
                            if (!$state) return;
                            $pm = PrevisionRecetteMensuelle::with('lignePrevisionRecette')->find($state);
                            if ($pm) {
                                $set('code_nomenclature', $pm->lignePrevisionRecette?->code_nomenclature);
                                $set('libelle', $pm->lignePrevisionRecette?->libelle_nomenclature);
                                $set('_montant_restant', max(0, (float)$pm->montant_prevu - (float)$pm->montant_recouvre));
                                $set('_montant_prevu', (float) $pm->montant_prevu);
                            }
                        })
                        ->helperText("Sélectionnez l'exercice et le mois d'abord"),

                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Placeholder::make('_montant_prevu')
                            ->label('Montant prévu du mois')
                            ->content(fn($get) => $get('_montant_prevu')
                                ? number_format((float) $get('_montant_prevu'), 0, ',', ' ') . ' FCFA'
                                : '—'),

                        Forms\Components\Placeholder::make('_montant_restant')
                            ->label('Restant à recouvrer')
                            ->content(fn($get) => $get('_montant_restant') !== null
                                ? number_format((float) $get('_montant_restant'), 0, ',', ' ') . ' FCFA'
                                : '—'),

                        Forms\Components\Hidden::make('code_nomenclature'),
                    ]),
                ]),

            Forms\Components\Section::make('Recette attendue et recette encaissée')
                ->description('Le reste à recouvrer (RAR) = attendue − encaissée est calculé automatiquement. Seul l\'encaissé compte dans le recouvré.')
                ->schema([
                    Forms\Components\TextInput::make('montant_constate')
                        ->label('Recette attendue (constatée)')
                        ->helperText('Créance : facture, prise en charge, convention, subvention notifiée…')
                        ->numeric()->required()->minValue(1)->prefix('FCFA')
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, Get $get, Forms\Set $set) {
                            // À la saisie : encaissé = attendu par défaut (cas le plus fréquent), modifiable
                            if ($get('montant') === null || $get('montant') === '') {
                                $set('montant', $state);
                            }
                        }),

                    Forms\Components\TextInput::make('montant')
                        ->label('Recette encaissée')
                        ->helperText('0 si rien n\'est encore perçu ; montant partiel si la créance n\'est payée qu\'en partie.')
                        ->numeric()->required()->minValue(0)->prefix('FCFA')
                        ->lte('montant_constate')
                        ->validationMessages(['lte' => "L'encaissé ne peut pas dépasser la recette attendue."])
                        ->live(onBlur: true),

                    Forms\Components\Placeholder::make('_rar')
                        ->label('Reste à recouvrer (RAR)')
                        ->content(function (Get $get) {
                            $rar = max(0, (float) $get('montant_constate') - (float) $get('montant'));
                            return new \Illuminate\Support\HtmlString('<strong style="color:' . ($rar > 0 ? '#b45309' : '#166534') . ';">'
                                . number_format($rar, 0, ',', ' ') . ' FCFA</strong>' . ($rar > 0 ? ' — à recouvrer' : ' — soldée'));
                        }),

                    // ✅ Débiteur / payeur : référentiel des tiers, facultatif, création possible sur place
                    Forms\Components\Select::make('tiers_recette_id')
                        ->label('Débiteur / Payeur')
                        ->options(fn() => \App\Models\TiersRecette::optionsGroupees())
                        ->searchable()
                        ->required()
                        ->placeholder('Facultatif')
                        ->createOptionForm([
                            Forms\Components\TextInput::make('nom')->label('Nom')->required()->maxLength(255)
                                ->unique('tiers_recettes', 'nom', modifyRuleUsing: fn($rule) => $rule->whereNull('deleted_at')),
                            Forms\Components\Select::make('categorie')->label('Catégorie')
                                ->options(\App\Models\TiersRecette::CATEGORIES)->default('autre')->required(),
                            Forms\Components\TextInput::make('telephone')->label('Téléphone')->tel()->maxLength(50),
                        ])
                        ->createOptionUsing(fn(array $data) => \App\Models\TiersRecette::create($data + ['actif' => true])->id)
                        ->createOptionModalHeading('Nouveau débiteur / payeur'),

                    Forms\Components\Select::make('mode_paiement')
                        ->visible(fn(Get $get) => (float) $get('montant') > 0)
                        ->label('Mode de paiement')
                        ->options([
                            'virement' => 'Virement bancaire',
                            'cheque'   => 'Chèque',
                            'especes'  => 'Espèces',
                            'mobile'   => 'Mobile Money',
                            'autre'    => 'Autre',
                        ])
                        ->default('virement'),

                    Forms\Components\TextInput::make('reference_paiement')
                        ->label('Référence paiement')->maxLength(100)
                        ->visible(fn(Get $get) => (float) $get('montant') > 0),

                    Forms\Components\Textarea::make('libelle')
                        ->label('Libellé / Objet')->rows(2),

                    Forms\Components\Textarea::make('observations')
                        ->label('Observations')->rows(2),
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
                    ->label('N°')->searchable()->sortable()->badge()->color('gray'),

                Tables\Columns\TextColumn::make('date_recette')
                    ->label('Date')->date('d/m/Y')->sortable(),

                Tables\Columns\TextColumn::make('mois')
                    ->label('Mois')
                    ->formatStateUsing(fn($state) => self::$moisLabels[(int) $state] ?? '—')
                    ->badge()->color('info'),

                Tables\Columns\TextColumn::make('code_nomenclature')
                    ->label('Code')->searchable()->badge()->color('warning'),

                Tables\Columns\TextColumn::make('libelle_nomenclature')
                    ->label('Nomenclature')
                    ->getStateUsing(
                        fn($record) =>
                        $record->previsionRecetteMensuelle
                            ?->lignePrevisionRecette
                            ?->libelle_nomenclature ?? '—'
                    )
                    ->limit(35)
                    ->tooltip(
                        fn($record) =>
                        $record->previsionRecetteMensuelle
                            ?->lignePrevisionRecette
                            ?->libelle_nomenclature
                    ),

                Tables\Columns\TextColumn::make('montant_constate')
                    ->label('Attendue')->money('XAF')->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('XAF')->label('Attendue')),

                Tables\Columns\TextColumn::make('montant')
                    ->label('Encaissée')->money('XAF')->sortable()->weight('bold')->color('success')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('XAF')->label('Encaissée')),

                Tables\Columns\TextColumn::make('reste_a_recouvrer')
                    ->label('RAR')
                    ->getStateUsing(fn($record) => $record->reste_a_recouvrer)
                    ->money('XAF')
                    ->color(fn($state) => (float) $state > 0 ? 'warning' : 'gray')
                    ->summarize(Tables\Columns\Summarizers\Summarizer::make()->label('RAR')
                        ->using(fn($query) => (float) $query->sum(\Illuminate\Support\Facades\DB::raw(RecetteReelle::sqlResteARecouvrer())))
                        ->money('XAF')),

                Tables\Columns\TextColumn::make('payeur')
                    ->label('Débiteur / Payeur')->searchable()->limit(25)->toggleable()
                    ->description(fn($record) => $record->tiers?->categorie_label),

                Tables\Columns\TextColumn::make('mode_paiement')
                    ->label('Mode')->badge()->color('gray')->toggleable(),

                Tables\Columns\TextColumn::make('statut')
                    ->label('Statut')->badge()
                    ->color(fn(string $state) => match ($state) {
                        'constatee'     => 'warning',
                        'encaissee'     => 'success',
                        'comptabilisee' => 'info',
                        'validee'       => 'primary',
                        default         => 'gray',
                    })
                    ->formatStateUsing(fn(string $state) => RecetteReelle::STATUTS[$state] ?? $state),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('mois')
                    ->label('Mois')->options(self::$moisOptions),

                Tables\Filters\SelectFilter::make('statut')
                    ->label('Statut')
                    ->options(RecetteReelle::STATUTS),

                // ✅ Créances constatées non perçues (futur RAR)
                Tables\Filters\SelectFilter::make('tiers_recette_id')
                    ->label('Débiteur / Payeur')
                    ->relationship('tiers', 'nom')
                    ->searchable()->preload(),

                Tables\Filters\SelectFilter::make('categorie_tiers')
                    ->label('Catégorie de débiteur')
                    ->options(\App\Models\TiersRecette::CATEGORIES)
                    ->query(fn($query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereHas('tiers', fn($q) => $q->where('categorie', $data['value']))
                        : $query),

                Tables\Filters\Filter::make('non_percues')
                    ->label('Avec reste à recouvrer (RAR)')
                    ->query(fn($query) => $query->whereRaw(RecetteReelle::sqlResteARecouvrer() . ' > 0')),
            ])

            // ════════════════════════════════════════════════════════
            // ✅ ACTIONS — un seul ActionGroup, aligné à gauche
            //    Pattern identique à BonCommandeResource
            // ════════════════════════════════════════════════════════
            ->actions([
                Tables\Actions\ActionGroup::make([

                    // ✅ Encaissement (total ou partiel) du reste à recouvrer
                    Tables\Actions\Action::make('encaisser')
                        ->label('Encaisser')
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->visible(fn($record) => $record->reste_a_recouvrer > 0 && !in_array($record->statut, ['comptabilisee', 'validee'], true))
                        ->modalDescription(fn($record) => 'Attendue : ' . number_format((float) $record->montant_constate, 0, ',', ' ')
                            . ' · déjà encaissée : ' . number_format((float) $record->montant, 0, ',', ' ')
                            . ' · reste à recouvrer : ' . number_format($record->reste_a_recouvrer, 0, ',', ' ') . ' FCFA'
                            . ($record->payeur ? " ({$record->payeur})" : ''))
                        ->form(fn($record) => [
                            Forms\Components\TextInput::make('montant')->label('Montant encaissé')
                                ->numeric()->required()->minValue(1)->maxValue($record->reste_a_recouvrer)
                                ->default($record->reste_a_recouvrer)->prefix('FCFA'),
                            Forms\Components\DatePicker::make('date_encaissement')->label("Date d'encaissement")->default(now())->maxDate(now())->required(),
                            Forms\Components\Select::make('mode_paiement')->label('Mode de paiement')
                                ->options(['virement' => 'Virement bancaire', 'cheque' => 'Chèque', 'especes' => 'Espèces', 'mobile' => 'Mobile Money', 'autre' => 'Autre'])
                                ->default('virement')->required(),
                            Forms\Components\TextInput::make('reference_paiement')->label('Référence paiement')->maxLength(100),
                        ])
                        ->action(function ($record, array $data) {
                            $record->encaisser($data);
                            \Filament\Notifications\Notification::make()->success()->title('Encaissement enregistré')
                                ->body("{$record->numero} : reste à recouvrer " . number_format($record->fresh()->reste_a_recouvrer, 0, ',', ' ') . ' FCFA.')->send();
                        }),

                    Tables\Actions\EditAction::make(),

                    Tables\Actions\DeleteAction::make()
                        ->visible(fn($record) => $record->statut !== 'validee'),

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

                    Tables\Actions\BulkAction::make('comptabiliser')
                        ->label('Comptabiliser la sélection')
                        ->icon('heroicon-o-check-circle')
                        ->color('info')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                if ($record->statut === 'encaissee') {
                                    $record->update(['statut' => 'comptabilisee']);
                                }
                            }
                        }),
                ]),
            ])
            // ✅ Regroupement par mois de la prévision (avec sous-totaux encaissé / constaté par mois)
            ->groups([
                Tables\Grouping\Group::make('mois')
                    ->label('Mois')
                    ->getTitleFromRecordUsing(fn($record) => (self::$moisOptions[(int) $record->mois] ?? $record->mois) . ' ' . $record->annee)
                    ->orderQueryUsing(fn(Builder $query, string $direction) => $query->orderBy('annee', 'desc')->orderBy('mois', 'desc'))
                    ->collapsible(),

                Tables\Grouping\Group::make('tiers.nom')
                    ->label('Débiteur / Payeur')
                    ->collapsible(),

                Tables\Grouping\Group::make('code_nomenclature')
                    ->label('Ligne de nomenclature')
                    ->getTitleFromRecordUsing(fn($record) => $record->code_nomenclature . ' — '
                        . ($record->previsionRecetteMensuelle?->lignePrevisionRecette?->libelle_nomenclature ?? ''))
                    ->collapsible(),
            ])
            ->defaultGroup('mois')
            ->defaultSort('date_recette', 'desc');
    }

    public static function getPages(): array
    {
        $pages = [
            'index'  => Pages\ListRecetteReelles::route('/'),
            'create' => Pages\CreateRecetteReelle::route('/create'),
            'edit'   => Pages\EditRecetteReelle::route('/{record}/edit'),
        ];

        if (class_exists(Pages\SuiviRecettes::class)) {
            $pages['suivi'] = Pages\SuiviRecettes::route('/suivi');
        }

        return $pages;
    }
}
