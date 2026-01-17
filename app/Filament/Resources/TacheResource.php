<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TacheResource\Pages;
use App\Models\Tache;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Forms\Components\ExerciceSelect;
use App\Models\Exercice;

class TacheResource extends Resource
{
    protected static ?string $model = Tache::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Tâches';

    protected static ?string $modelLabel = 'Tâche';

    protected static ?string $pluralModelLabel = 'Tâches';

    protected static ?string $navigationGroup = 'Cadre Logique';

    protected static ?int $navigationSort = 4;

    /**
     * Permissions - Gestion quotidienne par OB/CS
     */
    public static function canViewAny(): bool
    {
        return auth()->check() ? auth()->user()->hasAnyRole([
            'super_admin',
            // 'operateur_budget',
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
            // 'operateur_budget',
            'chef_service_budget'
        ]) : false;
    }

    public static function canEdit($record): bool
    {
        if (!auth()->check()) {
            return false;
        }

        $user = auth()->user();

        // Super admin OK
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // Vérifier le rôle
        if (!$user->hasAnyRole(['chef_service_budget'])) {
            return false;
        }

        // Vérifier l'exercice
        if (!$record->estModifiable()) {
            // Optionnel : notifier l'utilisateur
            if (request()->routeIs('filament.*')) {
                \Filament\Notifications\Notification::make()
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
        // 1. Vérifier que l'utilisateur est connecté
        if (!auth()->check()) {
            return false;
        }

        $user = auth()->user();

        // 2. Seul super admin peut supprimer
        if (!$user->hasRole('super_admin')) {
            return false;
        }

        // 3. Même super admin ne peut pas supprimer sur exercice archivé
        // (sauf si on veut autoriser, dans ce cas retourner true directement)
        return $record->estModifiable();
    }

    public static function canEditRecord($record): bool
    {
        $canEdit = static::canEdit($record);

        if (!$canEdit && $record->estLectureSeule()) {
            \Filament\Notifications\Notification::make()
                ->title('Édition impossible')
                ->warning()
                ->body("L'exercice {$record->exercice->annee} est {$record->exercice->statut}. Seul un super admin peut modifier.")
                ->send();
        }

        return $canEdit;
    }

    public static function canView($record): bool
    {
        return auth()->check() ? auth()->user()->hasAnyRole([
            'super_admin',
            // 'operateur_budget',
            'chef_service_budget',
            'sous_directeur_budget',
            'directeur_general',
            // 'controleur_financier',
            // 'agence_comptable'
        ]) : false;
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

                Forms\Components\Section::make('Hiérarchie')
                    ->description('Définir si c\'est une tâche principale ou une sous-tâche')
                    ->schema([
                        Forms\Components\Select::make('niveau')
                            ->label('Niveau')
                            ->options([
                                'tache' => 'Tâche principale',
                                'sous_tache' => 'Sous-tâche',
                            ])
                            ->required()
                            ->default('tache')
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                // Si tâche principale, pas de parent
                                if ($state === 'tache') {
                                    $set('parent_id', null);
                                }
                            })
                            ->helperText('Une sous-tâche doit être rattachée à une tâche principale'),

                        Forms\Components\Select::make('parent_id')
                            ->label('Tâche parent')
                            ->searchable()
                            ->preload()
                            ->placeholder('Aucune (Tâche principale)')
                            ->disabled(fn(callable $get) => $get('niveau') === 'tache')
                            ->required(fn(callable $get) => $get('niveau') === 'sous_tache')
                            ->helperText('Sélectionner la tâche principale pour cette sous-tâche')
                            ->getSearchResultsUsing(function (string $search) {
                                return \App\Models\Tache::where('niveau', 'tache')
                                    ->where(function ($q) use ($search) {
                                        $q->where('libelle', 'like', "%{$search}%")
                                            ->orWhere('code', 'like', "%{$search}%");
                                    })
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn($item) => [
                                        $item->id => "{$item->code} - {$item->libelle}"
                                    ]);
                            })
                            ->getOptionLabelUsing(
                                fn($value): ?string =>
                                \App\Models\Tache::find($value)?->code . ' - ' .
                                    \App\Models\Tache::find($value)?->libelle
                            ),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(fn($record) => $record !== null && $record->niveau === 'tache'),

                Forms\Components\Section::make('Liaison')
                    ->schema([
                        Forms\Components\Select::make('activite_id')
                            ->label('Activité')
                            ->relationship('activite', 'libelle')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->getOptionLabelFromRecordUsing(
                                fn($record) =>
                                "{$record->action->programme->code} > {$record->action->code} > {$record->code} - {$record->libelle}"
                            ),

                        Forms\Components\Select::make('nomenclature_id')
                            ->label('Nomenclature Budgétaire')
                            ->required(fn(callable $get) => $get('niveau') === 'sous_tache')
                            ->disabled(fn(callable $get) => $get('niveau') === 'tache')
                            ->searchable()
                            ->preload()
                            ->getSearchResultsUsing(function (string $search) {
                                return \App\Models\NomenclatureBudgetaire::where('type', 'depense')
                                    ->where('actif', true)
                                    ->where(function ($query) use ($search) {
                                        $query->where('libelle', 'like', "%{$search}%")
                                            ->orWhere('code', 'like', "%{$search}%");
                                    })
                                    ->orderBy('code')
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn($item) => [$item->id => "{$item->code} - {$item->libelle}"]);
                            })
                            ->getOptionLabelUsing(
                                fn($value): ?string =>
                                \App\Models\NomenclatureBudgetaire::find($value)?->code . ' - ' .
                                    \App\Models\NomenclatureBudgetaire::find($value)?->libelle
                            )
                            ->helperText('Obligatoire pour les sous-tâches uniquement (dépenses)'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Informations de la Tâche')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->maxLength(50)
                            ->placeholder('Ex: T1 ou T1-01'),

                        Forms\Components\TextInput::make('libelle')
                            ->label('Libellé')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->placeholder('Ex: Achat de médicaments'),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Gestion et Responsabilité')
                    ->schema([
                        Forms\Components\TextInput::make('delai')
                            ->label('Délai de réalisation')
                            ->maxLength(255)
                            ->placeholder('Ex: 3 mois, T1 2026'),

                        Forms\Components\TextInput::make('guichet')
                            ->label('Guichet')
                            ->maxLength(255)
                            ->placeholder('Ex: Guichet Pharmacie'),

                        Forms\Components\Select::make('service_id')
                            ->label('Service responsable')
                            ->relationship('service', 'nom')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('code')
                                    ->label('Code')
                                    ->required()
                                    ->unique('services', 'code')
                                    ->maxLength(50),
                                Forms\Components\TextInput::make('nom')
                                    ->label('Nom')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('responsable')
                                    ->label('Responsable')
                                    ->maxLength(255),
                            ])
                            ->helperText('Service responsable de la tâche'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Budget')
                    ->description(
                        fn(callable $get) =>
                        $get('niveau') === 'tache'
                            ? 'Les montants sont calculés automatiquement à partir des sous-tâches'
                            : 'Saisir les montants pour cette sous-tâche'
                    )
                    ->schema([
                        Forms\Components\Placeholder::make('info_budget_tache')
                            ->label('')
                            ->content('ℹ️ Pour une tâche principale, AE et CP sont calculés automatiquement comme la somme des sous-tâches')
                            ->visible(fn(callable $get) => $get('niveau') === 'tache'),

                        Forms\Components\TextInput::make('ae')
                            ->label('AE (Autorisation d\'Engagement)')
                            ->required(fn(callable $get) => $get('niveau') === 'sous_tache')
                            ->disabled(fn(callable $get) => $get('niveau') === 'tache')
                            ->dehydrated(fn(callable $get) => $get('niveau') === 'sous_tache') // Ne sauvegarder que pour sous-tâches
                            ->numeric()
                            ->default(0)
                            ->prefix('FCFA')
                            ->placeholder(
                                fn(callable $get) =>
                                $get('niveau') === 'tache' ? 'Calculé automatiquement' : '0'
                            )
                            ->helperText(
                                fn(callable $get) =>
                                $get('niveau') === 'tache'
                                    ? 'Somme des AE des sous-tâches'
                                    : 'Montant de l\'autorisation d\'engagement'
                            )
                            ->afterStateHydrated(function ($component, $state, $record) {
                                // Afficher le total calculé pour les tâches principales
                                if ($record && $record->estTachePrincipale()) {
                                    $component->state($record->getTotalAe());
                                }
                            }),

                        Forms\Components\TextInput::make('cp')
                            ->label('CP (Crédit de Paiement)')
                            ->required(fn(callable $get) => $get('niveau') === 'sous_tache')
                            ->disabled(fn(callable $get) => $get('niveau') === 'tache')
                            ->dehydrated(fn(callable $get) => $get('niveau') === 'sous_tache') // Ne sauvegarder que pour sous-tâches
                            ->numeric()
                            ->default(0)
                            ->prefix('FCFA')
                            ->placeholder(
                                fn(callable $get) =>
                                $get('niveau') === 'tache' ? 'Calculé automatiquement' : '0'
                            )
                            ->helperText(
                                fn(callable $get) =>
                                $get('niveau') === 'tache'
                                    ? 'Somme des CP des sous-tâches'
                                    : 'Montant du crédit de paiement'
                            )
                            ->afterStateHydrated(function ($component, $state, $record) {
                                // Afficher le total calculé pour les tâches principales
                                if ($record && $record->estTachePrincipale()) {
                                    $component->state($record->getTotalCp());
                                }
                            }),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('💰 Budget calculé automatiquement')
                    ->description('Somme des montants de toutes les sous-tâches')
                    ->schema([
                        Forms\Components\Placeholder::make('resume_budget')
                            ->label('')
                            ->content(function ($record) {
                                if (!$record || !$record->sousTaches->count()) {
                                    return new \Illuminate\Support\HtmlString('
                        <div class="text-center text-gray-500 dark:text-gray-400 py-4">
                            Aucune sous-tâche. Ajoutez des sous-tâches pour voir les totaux.
                        </div>
                    ');
                                }

                                $count = $record->sousTaches->count();
                                $ae = number_format($record->getTotalAe(), 0, ',', ' ');
                                $cp = number_format($record->getTotalCp(), 0, ',', ' ');

                                return new \Illuminate\Support\HtmlString("
                    <div class='grid grid-cols-1 md:grid-cols-3 gap-4'>
                        <!-- Sous-tâches -->
                        <div class='rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20 p-4 text-center'>
                            <div class='text-sm font-medium text-blue-600 dark:text-blue-400 mb-2'>
                                📋 Sous-tâches
                            </div>
                            <div class='text-3xl font-bold text-blue-700 dark:text-blue-300'>
                                {$count}
                            </div>
                        </div>

                        <!-- Total AE -->
                        <div class='rounded-lg border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20 p-4 text-center'>
                            <div class='text-sm font-medium text-green-600 dark:text-green-400 mb-2'>
                                💵 Total AE
                            </div>
                            <div class='text-2xl font-bold text-green-700 dark:text-green-300'>
                                {$ae} <span class='text-sm'>FCFA</span>
                            </div>
                        </div>

                        <!-- Total CP -->
                        <div class='rounded-lg border border-orange-200 dark:border-orange-800 bg-orange-50 dark:bg-orange-900/20 p-4 text-center'>
                            <div class='text-sm font-medium text-orange-600 dark:text-orange-400 mb-2'>
                                💰 Total CP
                            </div>
                            <div class='text-2xl font-bold text-orange-700 dark:text-orange-300'>
                                {$cp} <span class='text-sm'>FCFA</span>
                            </div>
                        </div>
                    </div>

                    <div class='mt-4 text-xs text-center text-gray-600 dark:text-gray-400'>
                        💡 Ces montants sont calculés automatiquement à partir des {$count} sous-tâche(s)
                    </div>
                ");
                            }),
                    ])
                    ->visible(
                        fn(callable $get, $record) =>
                        $get('niveau') === 'tache' && $record && $record->sousTaches->count() > 0
                    )
                    ->collapsible()
                    ->collapsed(false),

                Forms\Components\Section::make('Résultats et Indicateurs')
                    ->schema([
                        Forms\Components\Textarea::make('resultat_attendu')
                            ->label('Résultat attendu')
                            ->rows(2)
                            ->placeholder('Ex: Disponibilité permanente des médicaments essentiels'),

                        Forms\Components\Textarea::make('indicateur_resultat')
                            ->label('Indicateur de résultat')
                            ->rows(2)
                            ->placeholder('Ex: Tous les patients satisfaits, Réduction du nombre de malades'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Métadonnées')
                    ->schema([
                        Forms\Components\TextInput::make('ordre')
                            ->label('Ordre d\'affichage')
                            ->numeric()
                            ->default(0),

                        Forms\Components\Toggle::make('actif')
                            ->label('Actif')
                            ->default(true),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with('exercice');
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

                Tables\Columns\BadgeColumn::make('niveau')
                    ->label('Niveau')
                    ->colors([
                        'primary' => 'tache',
                        'success' => 'sous_tache',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'tache' => 'Tâche',
                        'sous_tache' => 'Sous-tâche',
                        default => ucfirst($state),
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('parent.code')
                    ->label('Parent')
                    ->searchable()
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->toggleable()
                    ->tooltip(fn($record) => $record->parent ? $record->parent->libelle : null),

                Tables\Columns\TextColumn::make('activite.action.programme.code')
                    ->label('Prog.')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('activite.action.code')
                    ->label('Action')
                    ->searchable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('activite.code')
                    ->label('Activité')
                    ->searchable()
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('libelle')
                    ->label('Libellé')
                    ->searchable()
                    ->wrap()
                    ->limit(40),

                Tables\Columns\TextColumn::make('nomenclature.code')
                    ->label('Nomenclature')
                    ->searchable()
                    ->badge()
                    ->color('warning')
                    ->placeholder('—')
                    ->tooltip(
                        fn($record) =>
                        $record->nomenclature
                            ? $record->nomenclature->libelle
                            : ($record->niveau === 'tache' ? 'Non applicable (tâche principale)' : null)
                    ),

                Tables\Columns\TextColumn::make('service.nom')
                    ->label('Service')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('ae')
                    ->label('AE')
                    ->formatStateUsing(
                        fn($record) =>
                        number_format($record->getTotalAe(), 0, ',', ' ') . ' FCFA'
                    )
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Summarizer::make()
                            ->label('Total AE')
                            ->using(function ($query) {
                                // Récupérer les IDs des tâches principales depuis la query
                                $tacheIds = (clone $query)
                                    ->where('niveau', 'tache')
                                    ->pluck('id');

                                // Charger les modèles complets avec Eloquent
                                $taches = \App\Models\Tache::whereIn('id', $tacheIds)->get();

                                // Calculer le total
                                $total = $taches->sum(function ($tache) {
                                    return $tache->getTotalAe();
                                });

                                return number_format($total, 0, ',', ' ') . ' FCFA';
                            }),
                    ])
                    ->tooltip(
                        fn($record) =>
                        $record->estTachePrincipale()
                            ? 'Calculé : somme des ' . $record->sousTaches->count() . ' sous-tâche(s)'
                            : 'Montant saisi'
                    ),

                Tables\Columns\TextColumn::make('cp')
                    ->label('CP')
                    ->formatStateUsing(
                        fn($record) =>
                        number_format($record->getTotalCp(), 0, ',', ' ') . ' FCFA'
                    )
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Summarizer::make()
                            ->label('Total CP')
                            ->using(function ($query) {
                                // Récupérer les IDs des tâches principales depuis la query
                                $tacheIds = (clone $query)
                                    ->where('niveau', 'tache')
                                    ->pluck('id');

                                // Charger les modèles complets avec Eloquent
                                $taches = \App\Models\Tache::whereIn('id', $tacheIds)->get();

                                // Calculer le total
                                $total = $taches->sum(function ($tache) {
                                    return $tache->getTotalCp();
                                });

                                return number_format($total, 0, ',', ' ') . ' FCFA';
                            }),
                    ])
                    ->tooltip(
                        fn($record) =>
                        $record->estTachePrincipale()
                            ? 'Calculé : somme des ' . $record->sousTaches->count() . ' sous-tâche(s)'
                            : 'Montant saisi'
                    ),
                Tables\Columns\TextColumn::make('sousTaches_count')
                    ->label('Sous-tâches')
                    ->counts('sousTaches')
                    ->badge()
                    ->color('success')
                    ->visible(fn($record) => $record && $record->niveau === 'tache') // ← Ajout de $record &&
                    ->toggleable(),

                Tables\Columns\IconColumn::make('actif')
                    ->label('Actif')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('exercice_id')
                    ->label('Exercice')
                    ->relationship('exercice', 'annee')
                    ->searchable()
                    ->preload()
                    ->placeholder('Tous les exercices')
                    ->default(fn() => Exercice::getActif()?->id),

                Tables\Filters\SelectFilter::make('niveau')
                    ->label('Niveau')
                    ->options([
                        'tache' => 'Tâche principale',
                        'sous_tache' => 'Sous-tâche',
                    ])
                    ->placeholder('Tous les niveaux'),

                Tables\Filters\SelectFilter::make('parent_id')
                    ->label('Tâche parent')
                    ->relationship('parent', 'libelle')
                    ->searchable()
                    ->preload()
                    ->placeholder('Toutes les tâches parentes')
                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->code} - {$record->libelle}"),

                Tables\Filters\SelectFilter::make('activite_id')
                    ->label('Activité')
                    ->relationship('activite', 'libelle')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('nomenclature_id')
                    ->label('Nomenclature')
                    ->relationship('nomenclature', 'libelle')
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
                Tables\Actions\Action::make('voir_sous_taches')
                    ->label('Sous-tâches')
                    ->icon('heroicon-o-list-bullet')
                    ->color('info')
                    ->visible(fn($record) => $record->niveau === 'tache')
                    ->url(fn($record) => static::getUrl('index', ['tableFilters' => ['parent_id' => ['value' => $record->id]]]))
                    ->tooltip('Voir les sous-tâches'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('code', 'asc')
            ->groups([
                Tables\Grouping\Group::make('niveau')
                    ->label('Niveau')
                    ->collapsible(),
                Tables\Grouping\Group::make('parent.libelle')
                    ->label('Tâche parent')
                    ->collapsible(),
                Tables\Grouping\Group::make('activite.action.programme.libelle')
                    ->label('Programme')
                    ->collapsible(),
            ]);
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
            'index' => Pages\ListTaches::route('/'),
            'create' => Pages\CreateTache::route('/create'),
            'edit' => Pages\EditTache::route('/{record}/edit'),
        ];
    }
}
