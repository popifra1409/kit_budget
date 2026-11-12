<?php

namespace App\Filament\Planification\Resources;

use App\Filament\Planification\Resources\ProgrammeResource\Pages;
use App\Models\Programme;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Enums\ActionsPosition;
use App\Filament\Forms\Components\ExerciceSelect;
use App\Models\Exercice;

class ProgrammeResource extends Resource
{
    protected static ?string $model = Programme::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Programmes';
    protected static ?string $modelLabel = 'Programme';
    protected static ?string $pluralModelLabel = 'Programmes';
    protected static ?string $navigationGroup = 'Plans Stratégiques EP';
    protected static ?int $navigationSort = 1;

    // ========================================
    // PERMISSIONS
    // ========================================

    // Recherche globale (champ du bandeau), comme dans le panel Budget
    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'libelle'];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_programme') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->can('view_programme') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_programme') ?? false;
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();
        if (!$user) return false;

        if ($user->can('update_programme')) {
            if (!$record->estModifiable() && request()->routeIs('filament.*')) {
                \Filament\Notifications\Notification::make()
                    ->title('Exercice verrouillé')->warning()
                    ->body("L'exercice {$record->exercice->annee} est {$record->exercice->getBadgeStatut()}. Modifications impossibles.")
                    ->send();
            }
            return $record->estModifiable();
        }

        return false;
    }

    public static function canDelete($record): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        return $user->can('delete_programme') && $record->estModifiable();
    }

    public static function canEditRecord($record): bool
    {
        $canEdit = static::canEdit($record);
        if (!$canEdit && $record->estLectureSeule()) {
            \Filament\Notifications\Notification::make()
                ->title('Édition impossible')->warning()
                ->body("L'exercice {$record->exercice->annee} est {$record->exercice->statut}. Seul un utilisateur avec permission peut modifier.")
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
                Forms\Components\Section::make('Exercice')
                    ->description('Exercice budgétaire de rattachement')
                    ->schema([ExerciceSelect::make()])
                    ->collapsible()
                    ->collapsed(fn($record) => $record !== null),

                Forms\Components\Section::make('Hiérarchie')
                    ->description('Définir si c\'est un programme principal ou un sous-programme')
                    ->schema([
                        Forms\Components\Select::make('niveau')
                            ->label('Niveau')
                            ->options([
                                'programme'     => 'Programme (rattachement tutelle)',
                                'sous_programme' => 'Sous-programme (subdivision de gestion interne)',
                            ])
                            ->required()->default('programme')->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state === 'programme') $set('parent_id', null);
                            })
                            ->helperText('Un sous-programme doit être rattaché à un programme principal'),

                        Forms\Components\Select::make('parent_id')
                            ->label('Programme parent')
                            ->searchable()->preload()
                            ->placeholder('Aucun (Programme principal)')
                            ->disabled(fn(callable $get) => $get('niveau') === 'programme')
                            ->required(fn(callable $get) => $get('niveau') === 'sous_programme')
                            ->helperText('Sélectionner le programme principal pour ce sous-programme')
                            ->getSearchResultsUsing(function (string $search) {
                                return \App\Models\Programme::where('niveau', 'programme')
                                    ->where(function ($q) use ($search) {
                                        $q->where('libelle', 'like', "%{$search}%")
                                            ->orWhere('code', 'like', "%{$search}%");
                                    })
                                    ->limit(50)->get()
                                    ->mapWithKeys(fn($item) => [$item->id => "{$item->code} - {$item->libelle}"]);
                            })
                            ->getOptionLabelUsing(
                                fn($value): ?string =>
                                \App\Models\Programme::find($value)?->code . ' - ' .
                                    \App\Models\Programme::find($value)?->libelle
                            ),
                    ])
                    ->columns(2)->collapsible()
                    ->collapsed(fn($record) => $record !== null && $record->niveau === 'programme'),

                Forms\Components\Section::make('Informations du Programme')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Code')->required()->unique(ignoreRecord: true)
                            ->maxLength(50)->placeholder('Ex: P413 ou P413-01'),

                        Forms\Components\TextInput::make('libelle')
                            ->label('Libellé')->required()->maxLength(255)->columnSpanFull()
                            ->placeholder('Ex: PRISE EN CHARGE DES CAS'),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')->rows(3)->columnSpanFull(),

                        Forms\Components\Select::make('responsable_personnel_id')
                            ->label('Responsable (agent)')
                            ->searchable()->preload()
                            ->placeholder('Aucun')
                            // La liste vient des agents en poste. Un agent déjà posé sur ce
                            // programme mais retiré entre-temps reste dans la liste, sinon le
                            // champ afficherait un identifiant nu et un enregistrement sans
                            // clic pourrait effacer le responsable. Le suffixe le signale :
                            // ce n'est pas un choix à faire pour la première fois.
                            ->options(fn ($record) => \App\Models\Personnel::withTrashed()
                                ->where(fn ($q) => $q->whereNull('deleted_at')
                                    ->orWhere('id', $record?->responsable_personnel_id))
                                ->orderBy('nom')->orderBy('prenoms')->get()
                                ->mapWithKeys(fn ($p) => [
                                    $p->id => $p->nom_complet.' ('.($p->matricule ?: '—').')'
                                        .($p->trashed() ? ' — agent retiré' : ''),
                                ]))
                            ->helperText('Table des personnels, et non des comptes : un agent sans accès peut être responsable. C\'est lui qu\'imprimera le cadre logique étendu.'),

                        Forms\Components\TextInput::make('annee')
                            ->label('Année budgétaire')->required()->numeric()
                            ->default(now()->year)->minValue(2020)->maxValue(2050),

                        Forms\Components\Toggle::make('actif')->label('Actif')->default(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Objectif Principal')
                    ->description('Définissez l\'objectif principal de ce programme')
                    ->schema([
                        Forms\Components\Repeater::make('objectifsPrincipaux')
                            ->relationship('objectifsPrincipaux')->label('')
                            ->schema([
                                Forms\Components\Textarea::make('libelle')
                                    ->label('Objectif principal')->required()->rows(2)
                                    ->placeholder('Ex: Assurer une prise en charge curative et préventive...'),
                                Forms\Components\TextInput::make('ordre')
                                    ->label('Ordre')->numeric()->default(0),
                            ])
                            ->columnSpanFull()->defaultItems(1)
                            ->addActionLabel('Ajouter un objectif principal')
                            ->collapsible()
                            ->itemLabel(fn(array $state): ?string => \Str::limit($state['libelle'] ?? 'Nouvel objectif', 50)),
                    ])
                    ->collapsible()
                    ->collapsed(fn($record) => $record !== null),
            ]);
    }

    // ========================================
    // INFOLIST — lecture seule (page « Voir » et ses futures impressions)
    // ========================================

    /**
     * Définie sur le resource et non sur la page : `ViewProgramme` ne surcharge pas
     * `infolist()`, donc il délègue ici. Une page qui redéclarerait son propre schéma
     * ferait disparaître ce bloc de l'écran sans qu'aucune vérification ne le signale.
     *
     * Aucun garde d'édition ici : « Voir » s'applique aussi aux exercices clôturés, qui
     * sont précisément ceux dont on consulte le responsable après coup.
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Exercice')
                ->schema([
                    Infolists\Components\TextEntry::make('exercice.annee')
                        ->label('Exercice')->placeholder('Aucun exercice rattaché'),

                    Infolists\Components\TextEntry::make('exercice.statut')
                        ->label('Statut')->badge()
                        ->formatStateUsing(fn (?string $state): ?string => $state === null ? null : ucfirst($state))
                        ->color(fn (?string $state): string => match ($state) {
                            'actif' => 'success',
                            'cloture' => 'warning',
                            'archive' => 'danger',
                            default => 'gray',
                        }),

                    Infolists\Components\TextEntry::make('annee')->label('Année budgétaire'),

                    Infolists\Components\IconEntry::make('actif')->label('Actif')->boolean(),
                ])
                ->columns(4),

            Infolists\Components\Section::make('Hiérarchie')
                ->schema([
                    Infolists\Components\TextEntry::make('niveau')
                        ->label('Niveau')->badge()
                        ->formatStateUsing(fn (?string $state): string => match ($state) {
                            'programme' => 'Programme',
                            'sous_programme' => 'Sous-programme',
                            default => ucfirst((string) $state),
                        })
                        ->color(fn (?string $state): string => $state === 'sous_programme' ? 'success' : 'primary'),

                    Infolists\Components\TextEntry::make('parent')
                        ->label('Programme parent')
                        ->placeholder('Aucun (programme principal)')
                        ->formatStateUsing(fn (?Programme $state): ?string => $state === null
                            ? null
                            : "{$state->code} — {$state->libelle}"),
                ])
                ->columns(2),

            Infolists\Components\Section::make('Informations du Programme')
                ->schema([
                    Infolists\Components\TextEntry::make('code')->label('Code')->weight('bold')->copyable(),

                    Infolists\Components\TextEntry::make('libelle')->label('Libellé')->weight('bold'),

                    Infolists\Components\TextEntry::make('description')
                        ->label('Description')->placeholder('Aucune description')
                        ->columnSpanFull(),

                    // L'agent, pas le compte : la BelongsTo est `withTrashed()`, donc un
                    // agent retiré affiche encore son nom — signalé en rouge plutôt que
                    // remplacé par « Aucun », qui ferait passer un retrait pour un oubli.
                    // `helperText()` : v3 n'a pas de `description()` sur une entry, et le
                    // harnais l'a découvert à l'exécution, `php -l` n'y voyant rien.
                    Infolists\Components\TextEntry::make('responsablePersonnel.nom_complet')
                        ->label('Responsable (agent)')
                        ->placeholder('Aucun agent désigné')
                        ->helperText(fn (?Programme $record): ?string => static::responsableResume($record))
                        ->color(fn (?Programme $record): ?string => $record->responsablePersonnel?->trashed()
                            ? 'danger' : null),
                ])
                ->columns(2),

            Infolists\Components\Section::make('Objectif Principal')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('objectifsPrincipaux')
                        ->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make('libelle')->label('Objectif principal'),
                            Infolists\Components\TextEntry::make('ordre')->label('Ordre'),
                        ])
                        ->columns(2),
                ])
                ->description('Aucun objectif principal enregistré')
                ->collapsible(),
        ]);
    }

    /**
     * Détail du champ « Responsable » (helperText de l'entry, seconde ligne du « Voir ») :
     * matricule, service, fonction, et l'avertissement de retrait. Séparé du schéma parce
     * que le cadre logique étendu imprimera la même information et ne devra pas la
     * réécrire.
     */
    public static function responsableResume(?Programme $record): ?string
    {
        $agent = $record?->responsablePersonnel;

        if (!$agent) {
            return null;
        }

        $resume = collect([
            $agent->matricule ? 'Matricule '.$agent->matricule : null,
            $agent->service?->nom,
            $agent->fonction,
        ])->filter()->implode(' · ');

        if ($agent->trashed()) {
            $resume = ($resume === '' ? '' : $resume.' · ').
                'agent retiré — à remplacer';
        }

        return $resume === '' ? null : $resume;
    }

    // ========================================
    // REQUÊTE
    // ========================================

    // `exercice` sert aux gardes `estModifiable()` des actions de la table.
    // `responsablePersonnel` est redondant pour la liste — Filament ajoute de lui-même la
    // relation de toute colonne `a.b` à la requête de table (`getEagerLoads()` la contient
    // même sans ce `with()`, vérifié par mutation) — mais le cadre logique étendu, lui,
    // lira des Programmes hors table et comptera sur cet eager-load.
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['exercice', 'responsablePersonnel']);
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
                    ->label('Code')->searchable()->sortable()->copyable(),

                Tables\Columns\TextColumn::make('libelle')
                    ->label('Libellé')->searchable()->sortable()->weight('bold')->wrap(),

                Tables\Columns\BadgeColumn::make('niveau')
                    ->label('Niveau')
                    ->colors(['primary' => 'programme', 'success' => 'sous_programme'])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'programme'      => 'Programme',
                        'sous_programme' => 'Sous-programme',
                        default          => ucfirst($state),
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('parent.code')
                    ->label('Programme parent')->searchable()->sortable()->toggleable()
                    ->placeholder('—')
                    ->formatStateUsing(
                        fn($record) =>
                        $record->parent
                            ? "{$record->parent->code} - " . \Str::limit($record->parent->libelle, 30)
                            : '—'
                    ),

                // `nom_complet` est un accesseur, pas une colonne : ni sortable ni
                // searchable ici, le SQL n'a rien à trier. Le tri par responsable se fera
                // depuis la table des personnels.
                Tables\Columns\TextColumn::make('responsablePersonnel.nom_complet')
                    ->label('Responsable')->toggleable(isToggledHiddenByDefault: false)
                    ->placeholder('—')
                    ->tooltip(fn ($record) => $record->responsablePersonnel
                        ? 'Matricule '.($record->responsablePersonnel->matricule ?: '—')
                            .($record->responsablePersonnel->trashed() ? ' — agent retiré' : '')
                        : null),

                Tables\Columns\TextColumn::make('annee')->label('Année')->sortable(),

                Tables\Columns\IconColumn::make('actif')->label('Actif')->boolean()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('exercice_id')
                    ->label('Exercice')
                    ->relationship('exercice', 'annee')
                    ->searchable()->preload()
                    ->placeholder('Tous les exercices')
                    ->default(fn() => Exercice::getActif()?->id),

                Tables\Filters\SelectFilter::make('niveau')
                    ->label('Niveau')
                    ->options([
                        'programme'      => 'Programme principal',
                        'sous_programme' => 'Sous-programme',
                    ]),

                Tables\Filters\SelectFilter::make('parent_id')
                    ->label('Programme parent')
                    ->relationship('parent', 'libelle')->searchable()->preload(),

                Tables\Filters\SelectFilter::make('annee')
                    ->label('Année')
                    ->options(fn() => range(2020, 2050)),

                Tables\Filters\TernaryFilter::make('actif')->label('Actif'),
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
            ->defaultSort('code');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'                 => Pages\ListProgrammes::route('/'),
            'create'                => Pages\CreateProgramme::route('/create'),
            'view'                  => Pages\ViewProgramme::route('/{record}'),
            'edit'                  => Pages\EditProgramme::route('/{record}/edit'),
            'generer-cadre-logique' => Pages\GenerationCadreLogique::route('/generer-cadre-logique'),
        ];
    }
}
