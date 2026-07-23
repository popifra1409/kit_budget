<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\NomenclatureBudgetaireResource\Pages;
use App\Filament\Budget\Resources\NomenclatureBudgetaireResource\RelationManagers;
use App\Models\NomenclatureBudgetaire;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Enums\ActionsPosition;
use App\Filament\Forms\Components\ExerciceSelect;
use App\Models\Exercice;

class NomenclatureBudgetaireResource extends Resource
{
    protected static ?string $model = NomenclatureBudgetaire::class;
    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $navigationLabel = 'Nomenclature Budgétaire';
    protected static ?string $modelLabel = 'Nomenclature';
    protected static ?string $pluralModelLabel = 'Nomenclatures';
    protected static ?string $navigationGroup = 'Configuration Budget';
    protected static ?int $navigationSort = 1;

    // ========================================
    // PERMISSIONS
    // ========================================

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_nomenclature_budgetaire') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->can('view_nomenclature_budgetaire') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_nomenclature_budgetaire') ?? false;
    }

    public static function canEdit($record): bool
    {
        if (!auth()->user()?->can('update_nomenclature_budgetaire')) return false;
        return $record->estModifiable();
    }

    public static function canDelete($record): bool
    {
        if (!auth()->user()?->can('delete_nomenclature_budgetaire')) return false;
        return $record->estModifiable();
    }

    public static function canActiver($record): bool
    {
        return auth()->user()?->can('update_nomenclature_budgetaire') ?? false;
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['exercice', 'groupe']);
    }

    // ========================================
    // FORM
    // ========================================

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informations principales')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Code')->required()->maxLength(20)
                            ->placeholder('Ex: 62, 620, 620000'),

                        Forms\Components\TextInput::make('libelle')
                            ->label('Libellé')->required()->maxLength(255)->columnSpanFull()
                            ->placeholder('Ex: CHARGES DE PERSONNEL, SALAIRES DE BASE'),

                        Forms\Components\Select::make('classe')
                            ->label('Classe comptable OHADA')
                            ->options([
                                '1' => 'Classe 1 - Comptes de capitaux (Flexible)',
                                '2' => 'Classe 2 - Comptes d\'actif immobilisé',
                                '3' => 'Classe 3 - Comptes de stocks',
                                '4' => 'Classe 4 - Comptes de tiers',
                                '5' => 'Classe 5 - Comptes de trésorerie',
                                '6' => 'Classe 6 - Comptes de charges (DÉPENSES uniquement)',
                                '7' => 'Classe 7 - Comptes de produits (RECETTES uniquement)',
                                '8' => 'Classe 8 - Comptes spéciaux',
                                '9' => 'Classe 9 - Comptes analytiques',
                            ])
                            ->required()->searchable()->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state === '6')      $set('type', 'depense');
                                elseif ($state === '7')  $set('type', 'recette');
                            })
                            ->helperText(function (callable $get) {
                                return match ($get('classe')) {
                                    '6'     => '⚠️ Classe 6 = Dépenses UNIQUEMENT',
                                    '7'     => '⚠️ Classe 7 = Recettes UNIQUEMENT',
                                    '1'     => '💡 Classe 1 = Type flexible (Recette ou Dépense selon le contexte)',
                                    default => '💡 Sélectionnez le type budgétaire approprié',
                                };
                            }),

                        Forms\Components\Select::make('type')
                            ->label('Type budgétaire')
                            ->options(function (callable $get) {
                                $classe = $get('classe');
                                if ($classe === '6') return ['depense' => 'Dépense (imposé pour Classe 6)'];
                                if ($classe === '7') return ['recette' => 'Recette (imposé pour Classe 7)'];
                                return ['depense' => 'Dépense', 'recette' => 'Recette'];
                            })
                            ->required()
                            ->disabled(fn(callable $get) => in_array($get('classe'), ['6', '7']))
                            ->dehydrated()
                            ->helperText(function (callable $get) {
                                return match ($get('classe')) {
                                    '6'     => '✓ Type défini automatiquement : Dépense',
                                    '7'     => '✓ Type défini automatiquement : Recette',
                                    '1'     => 'Ex: FONDS DE RESERVE peut être Recette ou Dépense selon le cas',
                                    default => 'Choisissez le type approprié pour cette nomenclature',
                                };
                            }),

                        Forms\Components\Select::make('groupe_id')
                            ->label('Groupe de nomenclature')
                            ->options(function (callable $get) {
                                $type = $get('type');

                                return \App\Models\GroupeNomenclature::query()
                                    ->actif()
                                    ->when($type, fn($query) => $query->where('type', $type))
                                    ->ordonne()
                                    ->pluck('libelle', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText(
                                fn(callable $get) => $get('type')
                                    ? 'Seuls les groupes de type « ' . ($get('type') === 'depense' ? 'Dépense' : 'Recette') . ' » sont proposés.'
                                    : 'Sélectionnez d\'abord la classe/le type pour filtrer les groupes disponibles.'
                            )
                            ->rule(function (callable $get) {
                                return function (string $attribute, $value, \Closure $fail) use ($get) {
                                    if (!$value) {
                                        return;
                                    }

                                    $groupe = \App\Models\GroupeNomenclature::find($value);
                                    $type = $get('type');

                                    if ($groupe && $type && $groupe->type !== $type) {
                                        $fail("Le groupe sélectionné correspond au type '{$groupe->type}', "
                                            . "incompatible avec le type '{$type}' choisi pour cette ligne.");
                                    }
                                };
                            }),

                        Forms\Components\Select::make('niveau')
                            ->label('Niveau hiérarchique')
                            ->options([
                                'chapitre'    => 'Chapitre (ex: 60, 62)',
                                'article'     => 'Article (ex: 620, 600)',
                                'paragraphe'  => 'Paragraphe (ex: 620000)',
                                'classe'      => 'Classe',
                                'compte'      => 'Compte',
                                'sous_compte' => 'Sous-compte',
                                'ligne'       => 'Ligne',
                            ])
                            ->required()->searchable()->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state === 'chapitre') $set('parent_id', null);
                            })
                            ->helperText('Hiérarchie budgétaire : Chapitre > Article > Paragraphe'),

                        Forms\Components\Select::make('parent_id')
                            ->label('Parent')
                            ->searchable()->preload()
                            ->placeholder('Aucun parent (niveau chapitre)')
                            ->getSearchResultsUsing(function (string $search, callable $get) {
                                $niveau        = $get('niveau');
                                $parentNiveaux = [
                                    'article'     => ['chapitre'],
                                    'paragraphe'  => ['article', 'chapitre'],
                                    'classe'      => ['chapitre'],
                                    'compte'      => ['chapitre', 'classe'],
                                    'sous_compte' => ['classe', 'compte'],
                                    'ligne'       => ['compte', 'sous_compte', 'paragraphe'],
                                ];
                                $query = \App\Models\NomenclatureBudgetaire::where(function ($q) use ($search) {
                                    $q->where('libelle', 'like', "%{$search}%")
                                        ->orWhere('code', 'like', "%{$search}%");
                                });
                                if (isset($parentNiveaux[$niveau])) {
                                    $query->whereIn('niveau', $parentNiveaux[$niveau]);
                                }
                                return $query->limit(50)->get()
                                    ->mapWithKeys(fn($item) => [
                                        $item->id => "{$item->code} - {$item->libelle} ({$item->niveau})"
                                    ]);
                            })
                            ->getOptionLabelUsing(
                                fn($value): ?string =>
                                \App\Models\NomenclatureBudgetaire::find($value)?->code . ' - ' .
                                    \App\Models\NomenclatureBudgetaire::find($value)?->libelle . ' (' .
                                    \App\Models\NomenclatureBudgetaire::find($value)?->niveau . ')'
                            )
                            ->disabled(fn(callable $get) => $get('niveau') === 'chapitre')
                            ->helperText(function (callable $get) {
                                return match ($get('niveau')) {
                                    'chapitre'   => 'Chapitre n\'a pas de parent',
                                    'article'    => 'Sélectionner un Chapitre',
                                    'paragraphe' => 'Sélectionner un Article (si existe) ou directement un Chapitre',
                                    default      => 'Sélectionner le parent dans la hiérarchie',
                                };
                            }),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('💡 Règles de cohérence Classe/Type')
                    ->description('Comprendre les règles de correspondance')
                    ->schema([
                        Forms\Components\Placeholder::make('regles_classe_type')
                            ->label('')
                            ->content(new \Illuminate\Support\HtmlString('
                <div class="text-sm space-y-3 bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
                    <div class="flex items-start space-x-2">
                        <span class="text-red-500 font-bold">•</span>
                        <div>
                            <span class="font-semibold text-red-600 dark:text-red-400">Classe 6</span>
                            <span class="text-gray-700 dark:text-gray-300"> = Type <strong>Dépense</strong> uniquement (obligatoire)</span>
                        </div>
                    </div>
                    <div class="flex items-start space-x-2">
                        <span class="text-green-500 font-bold">•</span>
                        <div>
                            <span class="font-semibold text-green-600 dark:text-green-400">Classe 7</span>
                            <span class="text-gray-700 dark:text-gray-300"> = Type <strong>Recette</strong> uniquement (obligatoire)</span>
                        </div>
                    </div>
                    <div class="flex items-start space-x-2">
                        <span class="text-blue-500 font-bold">•</span>
                        <div>
                            <span class="font-semibold text-blue-600 dark:text-blue-400">Classe 1, 2, 3, 4, 5, 8, 9</span>
                            <span class="text-gray-700 dark:text-gray-300"> = Type <strong>Flexible</strong> (Dépense ou Recette selon le contexte)</span>
                        </div>
                    </div>
                    <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                        <p class="text-xs text-gray-600 dark:text-gray-400">
                            <strong>Exemple :</strong> Classe 1 "FONDS DE RESERVE AU FONCTIONNEMENT" peut être une Recette
                            (encaissement) ou une Dépense (décaissement) selon le sens de l\'opération.
                        </p>
                    </div>
                </div>'))
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(fn($record) => $record !== null),

                Forms\Components\Section::make('Exercice')
                    ->description('Exercice budgétaire de rattachement')
                    ->schema([ExerciceSelect::make()])
                    ->collapsible()
                    ->collapsed(fn($record) => $record !== null),

                Forms\Components\Section::make('Mise en vigueur')
                    ->schema([
                        Forms\Components\DatePicker::make('date_mise_en_vigueur')
                            ->label('Date de mise en vigueur')->default(now()->startOfYear())->required(),
                        Forms\Components\TextInput::make('exercice')
                            ->label('Exercice (année)')->numeric()->disabled()->dehydrated(false)
                            ->default(fn() => Exercice::getActif()?->annee)
                            ->helperText('Auto-rempli depuis l\'exercice sélectionné'),
                    ])
                    ->columns(2)->collapsible()->collapsed(),

                Forms\Components\Section::make('Historisation')
                    ->description('Traçabilité des modifications')
                    ->schema([
                        Forms\Components\TextInput::make('code_precedent')
                            ->label('Code précédent')->maxLength(20)->placeholder('Si le code a changé'),

                        Forms\Components\Select::make('version_precedente_id')
                            ->label('Version précédente')
                            ->relationship('versionPrecedente', 'code')
                            ->searchable()->preload()
                            ->placeholder('Aucune version précédente')
                            ->getSearchResultsUsing(function (string $search) {
                                return \App\Models\NomenclatureBudgetaire::where('code', 'like', "%{$search}%")
                                    ->orWhere('libelle', 'like', "%{$search}%")
                                    ->limit(50)->get()
                                    ->mapWithKeys(fn($item) => [
                                        $item->id => "{$item->code} - {$item->libelle} (v{$item->version})"
                                    ]);
                            }),

                        Forms\Components\Textarea::make('motif_modification')
                            ->label('Motif de modification')->rows(3)->columnSpanFull()
                            ->placeholder('Raison de la modification ou de la nouvelle version'),
                    ])
                    ->columns(2)->collapsible()->collapsed(),

                Forms\Components\Section::make('Métadonnées')
                    ->schema([
                        Forms\Components\TextInput::make('version')
                            ->label('Version')->numeric()->default(1)->minValue(1)
                            ->helperText('Numéro de version de cette nomenclature'),
                        Forms\Components\TextInput::make('ordre')
                            ->label('Ordre d\'affichage')->numeric()->default(0)
                            ->helperText('Ordre dans les listes'),
                        Forms\Components\Toggle::make('actif')
                            ->label('Actif')->default(true)->inline(false)
                            ->helperText('Nomenclature active et utilisable'),
                    ])
                    ->columns(3)->collapsible()->collapsed(),
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

                Tables\Columns\TextColumn::make('code')
                    ->label('Code')->searchable()->sortable()->weight('bold'),

                Tables\Columns\TextColumn::make('libelle')
                    ->label('Libellé')->searchable()->sortable()->limit(50)->wrap(),

                Tables\Columns\TextColumn::make('classe')
                    ->label('Classe')->badge()
                    ->color(fn(string $state): string => match ($state) {
                        '6'     => 'danger',
                        '7'     => 'success',
                        '1'     => 'info',
                        default => 'gray',
                    })
                    ->icon(fn(string $state): ?string => match ($state) {
                        '1'     => 'heroicon-o-arrows-right-left',
                        default => null,
                    })
                    ->tooltip(fn($record): string => match ($record->classe) {
                        '6'     => 'Dépenses uniquement',
                        '7'     => 'Recettes uniquement',
                        '1'     => 'Type flexible (Recette ou Dépense)',
                        default => 'Classe ' . $record->classe,
                    }),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Type')
                    ->colors(['danger' => 'depense', 'success' => 'recette']),

                Tables\Columns\TextColumn::make('groupe.libelle')
                    ->label('Groupe')
                    ->placeholder('—')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('niveau')
                    ->label('Niveau')
                    ->colors([
                        'danger'    => 'chapitre',
                        'warning'   => 'article',
                        'info'      => 'paragraphe',
                        'success'   => 'compte',
                        'primary'   => 'sous_compte',
                        'secondary' => 'ligne',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'chapitre'    => 'Chapitre',
                        'article'     => 'Article',
                        'paragraphe'  => 'Paragraphe',
                        'classe'      => 'Classe',
                        'compte'      => 'Compte',
                        'sous_compte' => 'Sous-compte',
                        'ligne'       => 'Ligne',
                        default       => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('parent.code')
                    ->label('Parent')->placeholder('-')
                    ->formatStateUsing(
                        fn($record) =>
                        $record->parent
                            ? $record->parent->code . ' - ' . \Str::limit($record->parent->libelle, 20)
                            : '-'
                    ),

                Tables\Columns\TextColumn::make('date_mise_en_vigueur')
                    ->label('Mise en vigueur')->date('d/m/Y')->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('actif')->label('Actif')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('classe_type')
                    ->label('Classe + Type')
                    ->options([
                        '6_depense' => 'Classe 6 - Dépenses',
                        '7_recette' => 'Classe 7 - Recettes',
                        '1_recette' => 'Classe 1 - Recettes (flexible)',
                        '1_depense' => 'Classe 1 - Dépenses (flexible)',
                        'flexible'  => 'Toutes classes flexibles (1-5, 8-9)',
                    ])
                    ->query(function ($query, $state) {
                        return match ($state['value'] ?? null) {
                            '6_depense' => $query->where('classe', '6')->where('type', 'depense'),
                            '7_recette' => $query->where('classe', '7')->where('type', 'recette'),
                            '1_recette' => $query->where('classe', '1')->where('type', 'recette'),
                            '1_depense' => $query->where('classe', '1')->where('type', 'depense'),
                            'flexible'  => $query->whereIn('classe', ['1', '2', '3', '4', '5', '8', '9']),
                            default     => $query,
                        };
                    }),

                Tables\Filters\SelectFilter::make('exercice_id')
                    ->label('Exercice')
                    ->relationship('exercice', 'annee')
                    ->searchable()->preload()
                    ->placeholder('Tous les exercices')
                    ->default(fn() => Exercice::getActif()?->id),

                Tables\Filters\SelectFilter::make('classe')
                    ->label('Classe')
                    ->options([
                        '1' => 'Classe 1',
                        '2' => 'Classe 2',
                        '3' => 'Classe 3',
                        '4' => 'Classe 4',
                        '5' => 'Classe 5',
                        '6' => 'Classe 6 - Dépenses',
                        '7' => 'Classe 7 - Recettes',
                        '8' => 'Classe 8',
                        '9' => 'Classe 9',
                    ]),

                Tables\Filters\SelectFilter::make('type')
                    ->label('Type')
                    ->options(['depense' => 'Dépense', 'recette' => 'Recette']),

                Tables\Filters\SelectFilter::make('groupe_id')
                    ->label('Groupe')
                    ->relationship('groupe', 'libelle')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('niveau')
                    ->label('Niveau')
                    ->options([
                        'classe' => 'Classe',
                        'compte' => 'Compte',
                        'sous_compte' => 'Sous-compte',
                        'ligne' => 'Ligne',
                    ]),

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
            ->groups([
                Tables\Grouping\Group::make('groupe.libelle')
                    ->label('Groupe de nomenclature')
                    ->getTitleFromRecordUsing(fn($record) => $record->groupe?->libelle ?? '⚠️ Non classées / Hors groupe')
                    ->getKeyFromRecordUsing(fn($record) => $record->groupe
                        ? str_pad((string) $record->groupe->ordre, 4, '0', STR_PAD_LEFT) . '-' . $record->groupe->libelle
                        : '9999-Hors groupe')
                    ->collapsible(),
            ])
            ->defaultGroup('groupe.libelle')
            ->defaultSort('code', 'asc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TachesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'      => Pages\ListNomenclatureBudgetaires::route('/'),
            'hierarchie' => Pages\HierarchieNomenclatureBudgetaire::route('/hierarchie'),
            'create'     => Pages\CreateNomenclatureBudgetaire::route('/create'),
            'edit'       => Pages\EditNomenclatureBudgetaire::route('/{record}/edit'),
            'import'     => Pages\ImportNomenclatureBudgetaire::route('/import'),
        ];
    }
}
