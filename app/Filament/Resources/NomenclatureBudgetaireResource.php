<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NomenclatureBudgetaireResource\Pages;
use App\Filament\Resources\NomenclatureBudgetaireResource\RelationManagers;
use App\Models\NomenclatureBudgetaire;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
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

    /**
     * Permissions - Référentiel géré par SA/CS
     */
    public static function canViewAny(): bool
    {
        return auth()->check() ? auth()->user()->hasAnyRole([
            'super_admin',
            'operateur_budget',
            'chef_service_budget',
            'sous_directeur_budget',
            'directeur_general',
            // 'controleur_financier',
            // 'agence_comptable'
        ]) : false;
    }

    public static function canCreate(): bool
    {
        return auth()->check() ? auth()->user()->hasAnyRole([
            'super_admin',
            'chef_service_budget'
        ]) : false;
    }

    public static function canEdit($record): bool
    {
        if (!auth()->check()) {
            return false;
        }

        $user = auth()->user();

        // Super admin peut toujours éditer
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // Chef service budget : seulement si exercice modifiable
        if ($user->hasRole('chef_service_budget')) {
            return $record->estModifiable();
        }

        return false;
    }

    public static function canDelete($record): bool
    {
        if (!auth()->check()) {
            return false;
        }

        $user = auth()->user();

        // Seul super admin peut supprimer
        if (!$user->hasRole('super_admin')) {
            return false;
        }

        // Même super admin : seulement si exercice modifiable
        return $record->estModifiable();
    }

    public static function canView($record): bool
    {
        return auth()->check() ? auth()->user()->hasAnyRole([
            'super_admin',
            'operateur_budget',
            'chef_service_budget',
            'sous_directeur_budget',
            'directeur_general',
            // 'controleur_financier',
            // 'agence_comptable'
        ]) : false;
    }

    /**
     * Action spéciale : Activer/désactiver
     */
    public static function canActiver($record): bool
    {
        return auth()->check() ? auth()->user()->hasAnyRole([
            'super_admin',
            'chef_service_budget'
        ]) : false;
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with('exercice');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informations principales')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->maxLength(20)
                            ->placeholder('Ex: 62, 620, 620000'),

                        Forms\Components\TextInput::make('libelle')
                            ->label('Libellé')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->placeholder('Ex: CHARGES DE PERSONNEL, SALAIRES DE BASE'),

                        Forms\Components\Select::make('classe')
                            ->label('Classe comptable OHADA')
                            ->options([
                                '1' => 'Classe 1 - Comptes de capitaux',
                                '2' => 'Classe 2 - Comptes d\'actif immobilisé',
                                '3' => 'Classe 3 - Comptes de stocks',
                                '4' => 'Classe 4 - Comptes de tiers',
                                '5' => 'Classe 5 - Comptes de trésorerie',
                                '6' => 'Classe 6 - Comptes de charges (DÉPENSES)',
                                '7' => 'Classe 7 - Comptes de produits (RECETTES)',
                                '8' => 'Classe 8 - Comptes spéciaux',
                                '9' => 'Classe 9 - Comptes analytiques',
                            ])
                            ->required()
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                // Auto-définir le type selon la classe
                                if ($state === '6') {
                                    $set('type', 'depense');
                                } elseif ($state === '7') {
                                    $set('type', 'recette');
                                }
                            })
                            ->helperText('Classe 6 = Dépenses | Classe 7 = Recettes'),

                        Forms\Components\Select::make('type')
                            ->label('Type budgétaire')
                            ->options([
                                'depense' => 'Dépense (Classe 6)',
                                'recette' => 'Recette (Classe 7)',
                            ])
                            ->required()
                            ->disabled(fn(callable $get) => in_array($get('classe'), ['6', '7']))
                            ->dehydrated() // Important : pour sauvegarder même si disabled
                            ->helperText('Auto-défini pour Classe 6 et 7'),

                        Forms\Components\Select::make('niveau')
                            ->label('Niveau hiérarchique')
                            ->options([
                                'chapitre' => 'Chapitre (ex: 60, 62)',
                                'article' => 'Article (ex: 620, 600)',
                                'paragraphe' => 'Paragraphe (ex: 620000)',
                                'classe' => 'Classe',
                                'compte' => 'Compte',
                                'sous_compte' => 'Sous-compte',
                                'ligne' => 'Ligne',
                            ])
                            ->required()
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                // Si on sélectionne "chapitre", pas de parent
                                if ($state === 'chapitre') {
                                    $set('parent_id', null);
                                }
                            })
                            ->helperText('Hiérarchie budgétaire : Chapitre > Article > Paragraphe'),

                        Forms\Components\Select::make('parent_id')
                            ->label('Parent')
                            ->searchable()
                            ->preload()
                            ->placeholder('Aucun parent (niveau chapitre)')
                            ->getSearchResultsUsing(function (string $search, callable $get) {
                                $niveau = $get('niveau');

                                // Filtrer les parents possibles selon le niveau
                                $parentNiveaux = [
                                    'article' => ['chapitre'], // Article peut seulement avoir Chapitre comme parent
                                    'paragraphe' => ['article', 'chapitre'], // ← MODIFIÉ : Paragraphe peut avoir Article OU Chapitre
                                    'classe' => ['chapitre'],
                                    'compte' => ['chapitre', 'classe'],
                                    'sous_compte' => ['classe', 'compte'],
                                    'ligne' => ['compte', 'sous_compte', 'paragraphe'],
                                ];

                                $query = \App\Models\NomenclatureBudgetaire::where(function ($q) use ($search) {
                                    $q->where('libelle', 'like', "%{$search}%")
                                        ->orWhere('code', 'like', "%{$search}%");
                                });

                                // Filtrer par niveaux parents autorisés
                                if (isset($parentNiveaux[$niveau])) {
                                    $query->whereIn('niveau', $parentNiveaux[$niveau]);
                                }

                                return $query->limit(50)
                                    ->get()
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
                                $niveau = $get('niveau');
                                return match ($niveau) {
                                    'chapitre' => 'Chapitre n\'a pas de parent',
                                    'article' => 'Sélectionner un Chapitre',
                                    'paragraphe' => 'Sélectionner un Article (si existe) ou directement un Chapitre',
                                    default => 'Sélectionner le parent dans la hiérarchie',
                                };
                            }),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Exercice')
                    ->description('Exercice budgétaire de rattachement')
                    ->schema([
                        ExerciceSelect::make(),
                    ])
                    ->collapsible()
                    ->collapsed(fn($record) => $record !== null),

                Forms\Components\Section::make('Mise en vigueur')
                    ->schema([
                        Forms\Components\DatePicker::make('date_mise_en_vigueur')
                            ->label('Date de mise en vigueur')
                            ->default(now()->startOfYear())
                            ->required(),

                        Forms\Components\TextInput::make('exercice')
                            ->label('Exercice (année)')
                            ->numeric()
                            ->disabled()  // ← Désactivé car géré par exercice_id
                            ->dehydrated(false)  // ← Ne pas sauvegarder
                            ->default(fn() => Exercice::getActif()?->annee)
                            ->helperText('Auto-rempli depuis l\'exercice sélectionné'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),

                Forms\Components\Section::make('Historisation')
                    ->description('Traçabilité des modifications')
                    ->schema([
                        Forms\Components\TextInput::make('code_precedent')
                            ->label('Code précédent')
                            ->maxLength(20)
                            ->placeholder('Si le code a changé'),

                        Forms\Components\Select::make('version_precedente_id')
                            ->label('Version précédente')
                            ->relationship('versionPrecedente', 'code')
                            ->searchable()
                            ->preload()
                            ->placeholder('Aucune version précédente')
                            ->getSearchResultsUsing(function (string $search) {
                                return \App\Models\NomenclatureBudgetaire::where('code', 'like', "%{$search}%")
                                    ->orWhere('libelle', 'like', "%{$search}%")
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn($item) => [
                                        $item->id => "{$item->code} - {$item->libelle} (v{$item->version})"
                                    ]);
                            }),

                        Forms\Components\Textarea::make('motif_modification')
                            ->label('Motif de modification')
                            ->rows(3)
                            ->columnSpanFull()
                            ->placeholder('Raison de la modification ou de la nouvelle version'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),

                Forms\Components\Section::make('Métadonnées')
                    ->schema([
                        Forms\Components\TextInput::make('version')
                            ->label('Version')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->helperText('Numéro de version de cette nomenclature'),

                        Forms\Components\TextInput::make('ordre')
                            ->label('Ordre d\'affichage')
                            ->numeric()
                            ->default(0)
                            ->helperText('Ordre dans les listes'),

                        Forms\Components\Toggle::make('actif')
                            ->label('Actif')
                            ->default(true)
                            ->inline(false)
                            ->helperText('Nomenclature active et utilisable'),
                    ])
                    ->columns(3)
                    ->collapsible()
                    ->collapsed(),
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
                        'success' => fn($record) =>
                        $record->exercice instanceof \App\Models\Exercice && $record->exercice->estActif(),
                        'warning' => fn($record) =>
                        $record->exercice instanceof \App\Models\Exercice && $record->exercice->estCloture(),
                        'danger' => fn($record) =>
                        $record->exercice instanceof \App\Models\Exercice && $record->exercice->estArchive(),
                        'gray' => fn($record) =>
                        $record->exercice instanceof \App\Models\Exercice && $record->exercice->estBrouillon(),
                    ])
                    ->tooltip(
                        fn($record) =>
                        $record->exercice instanceof \App\Models\Exercice
                            ? $record->exercice->libelle
                            : null
                    )
                    ->toggleable(),

                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('libelle')
                    ->label('Libellé')
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->wrap(),

                Tables\Columns\TextColumn::make('classe')
                    ->label('Classe')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        '6' => 'danger',
                        '7' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Type')
                    ->colors([
                        'danger' => 'depense',
                        'success' => 'recette',
                    ]),

                Tables\Columns\BadgeColumn::make('niveau')
                    ->label('Niveau')
                    ->colors([
                        'danger' => 'chapitre',
                        'warning' => 'article',
                        'info' => 'paragraphe',
                        'success' => 'compte',
                        'primary' => 'sous_compte',
                        'secondary' => 'ligne',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'chapitre' => 'Chapitre',
                        'article' => 'Article',
                        'paragraphe' => 'Paragraphe',
                        'classe' => 'Classe',
                        'compte' => 'Compte',
                        'sous_compte' => 'Sous-compte',
                        'ligne' => 'Ligne',
                        default => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('parent.code')
                    ->label('Parent')
                    ->placeholder('-')
                    ->formatStateUsing(
                        fn($record) =>
                        $record->parent ? $record->parent->code . ' - ' . \Str::limit($record->parent->libelle, 20) : '-'
                    ),

                // Tables\Columns\TextColumn::make('exercice')
                //     ->label('Exercice')
                //     ->sortable()
                //     ->badge()
                //     ->color('info'),

                Tables\Columns\TextColumn::make('date_mise_en_vigueur')
                    ->label('Mise en vigueur')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

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
                    ->options([
                        'depense' => 'Dépense',
                        'recette' => 'Recette',
                    ]),

                Tables\Filters\SelectFilter::make('niveau')
                    ->label('Niveau')
                    ->options([
                        'classe' => 'Classe',
                        'compte' => 'Compte',
                        'sous_compte' => 'Sous-compte',
                        'ligne' => 'Ligne',
                    ]),

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
            RelationManagers\TachesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNomenclatureBudgetaires::route('/'),
            'hierarchie' => Pages\HierarchieNomenclatureBudgetaire::route('/hierarchie'),
            'create' => Pages\CreateNomenclatureBudgetaire::route('/create'),
            'edit' => Pages\EditNomenclatureBudgetaire::route('/{record}/edit'),
            'import' => Pages\ImportNomenclatureBudgetaire::route('/import'),
        ];
    }
}
