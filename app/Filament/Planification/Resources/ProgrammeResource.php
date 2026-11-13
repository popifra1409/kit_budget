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

                // Étape 4 — « Hiérarchie » n'est plus un choix : depuis cet écran, un
                // programme est toujours de niveau « programme ». L'ancienne option
                // « sous_programme » fabriquait une deuxième famille de sous-programmes,
                // sans actions ni budget, introuvable depuis les états — le seul
                // propriétaire est désormais « Sous-programmes » (SousProgrammeEp), qui
                // rattache ici par `programme_budgetaire_id`.
                Forms\Components\Section::make('Sous-programmes')
                    ->description('Un programme est toujours de niveau « programme » ; ses sous-programmes se gèrent dans leur propre écran.')
                    ->schema([
                        // `Hidden`, pas d'absence du champ : sans lui, l'instance créée ne
                        // porterait `niveau` qu'après relecture (la valeur vient du défaut de
                        // colonne), et `estProgrammePrincipal()` la nierait sur l'objet frais.
                        Forms\Components\Hidden::make('niveau')->default('programme'),

                        Forms\Components\Placeholder::make('sous_programmes_rattaches')
                            ->label('Sous-programmes rattachés')
                            ->content(fn (?Programme $record): string => $record === null
                                ? "Le rattachement se pose côté sous-programme : Planification → Sous-programmes, champ « Programme de rattachement (ministériel) »."
                                : $record->sousProgrammesEp()->count()
                                    . ' sous-programme(s) de l\'EP rattaché(s) à « ' . $record->code . ' ».'),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Informations du Programme')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Code')->required()->unique(ignoreRecord: true)
                            // Plus de « P413-01 » : l'exemple à suffixe était le gabarit des
                            // sous-programmes de gestion interne, qui ne se créent plus ici.
                            ->maxLength(50)->placeholder('Ex: 413'),

                        Forms\Components\TextInput::make('libelle')
                            ->label('Libellé')->required()->maxLength(255)->columnSpanFull()
                            ->placeholder('Ex: PRISE EN CHARGE DES CAS'),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')->rows(3)->columnSpanFull(),

                        // La règle — les agents en poste, plus celui déjà posé même s'il est
                        // retiré — est la même sur les quatre niveaux de la hiérarchie : elle
                        // vit dans ResponsableSelect, pas ici.
                        \App\Filament\Planification\Components\ResponsableSelect::make()
                            ->label('Responsable (agent)'),

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

            // Étape 4 — le bloc affichait `niveau` et « Programme parent », deux valeurs qui
            // n'existeront plus jamais ici. Il liste les sous-programmes de l'EP rattachés au
            // programme et mène à leur écran : c'est la seule hiérarchie qui reste.
            Infolists\Components\Section::make('Sous-programmes')
                ->description('Le sous-programme de gestion interne se crée et se pilote dans l\'écran « Sous-programmes » ; son champ « Programme de rattachement (ministériel) » est ce programme.')
                ->schema([
                    // Entrée multi-valeurs plutôt que `RepeatableEntry` : seule une entrée
                    // connaît son `placeholder()`, donc seule elle sait dire le vide —
                    // l'infolist n'a pas de `description()` sur ses entrées.
                    Infolists\Components\TextEntry::make('sousProgrammesEp.code')
                        ->label('Sous-programmes rattachés')
                        ->badge()->color('primary')
                        ->placeholder('Aucun sous-programme de l\'EP n\'a ce programme pour rattachement')
                        ->helperText('Le libellé, le responsable et le statut de chaque sous-programme se lisent dans l\'écran « Sous-programmes ».'),

                    Infolists\Components\Actions::make([
                        Infolists\Components\Actions\Action::make('voir_sous_programmes')
                            ->label('Ouvrir les sous-programmes de ce programme')
                            ->icon('heroicon-o-square-2-stack')
                            // Le lien n'est pas conditionné au droit : un bouton caché se
                            // lirait comme « ce programme n'a pas de sous-programme ». C'est
                            // l'écran cible qui répond, et il répond visiblement.
                            ->url(fn (Programme $record): string => static::urlSousProgrammesRattaches($record)),
                    ]),
                    // Pas de `key()` ici : vendor `HasAction::isLivewireClickHandlerEnabled()`
                    // coupe le gestionnaire d'événement dès qu'une URL est posée, et c'est
                    // uniquement ce gestionnaire qui réclame une clé. Le contrôle par mutation l'a
                    // vérifié en retirant la ligne : la fiche se rendait sans changement. La
                    // première action de modal ajoutée dans ce bloc devra sa `key()`.
                ]),

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
    // LIEN VERS LES SOUS-PROGRAMMES (Étape 4)
    // ========================================

    /**
     * La liste des sous-programmes de l'EP rattachés à ce programme, ouverte déjà filtrée.
     *
     * Le paramètre `tableFilters[…]` n'a d'effet que parce que
     * `SousProgrammeEpResource::table()` déclare un `SelectFilter` de ce nom : renommer la
     * clé d'un côté sans l'autre ne lève aucune erreur à l'exécution, cela rend seulement le
     * lien muet — la liste entière s'affiche. C'est ce que contrôle le harnais.
     */
    public static function urlSousProgrammesRattaches(Programme $record): string
    {
        return SousProgrammeEpResource::getUrl('index', [
            'tableFilters[programme_budgetaire_id][value]' => $record->getKey(),
        ]);
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

                // Étape 4 : les colonnes « Niveau » et « Programme parent » rendaient une
                // valeur constante (« Programme ») et un tiret pour toutes les lignes. Elles
                // disparaissent avec l'option du formulaire ; la hiérarchie qui reste tient
                // dans l'action « Sous-programmes » ci-dessous.

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

                // Filtres « Niveau » et « Programme parent » retirés avec leurs colonnes :
                // ils n'offraient que des valeurs que ce formulaire ne produit plus.
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

                    // Étape 4 — le programme ne porte plus ses sous-programmes, il y mène.
                    // L'action reste visible même sans droit : le compte refusé tombe sur le
                    // 403 de l'écran cible, ce qui se voit, plutôt que sur un bouton absent
                    // qui ferait croire que le programme n'a pas de sous-programme.
                    Tables\Actions\Action::make('sous_programmes')
                        ->label('Sous-programmes')
                        ->icon('heroicon-o-square-2-stack')
                        ->url(fn (Programme $record): string => static::urlSousProgrammesRattaches($record)),
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
