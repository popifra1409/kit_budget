<?php

namespace App\Filament\Planification\Resources;

use App\Filament\Planification\Resources\ActionResource\Pages;
use App\Filament\Planification\Resources\Concerns\CreationActionSousProgramme;
use App\Models\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Enums\ActionsPosition;
use App\Filament\Forms\Components\ExerciceSelect;
use App\Models\Exercice;
use App\Models\SousProgrammeEp;
use Filament\Notifications\Notification;

class ActionResource extends Resource
{
    protected static ?string $model = Action::class;
    protected static ?string $navigationIcon = 'heroicon-o-bolt';
    protected static ?string $navigationLabel = 'Actions';
    protected static ?string $modelLabel = 'Action';
    protected static ?string $pluralModelLabel = 'Actions';
    protected static ?string $navigationGroup = 'Plans Stratégiques EP';
    // Le menu lit la hiérarchie : plan → programmes de la tutelle → sous-programmes de l'EP
    // (B1b, rang 2) → actions.
    protected static ?int $navigationSort = 3;

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
        return auth()->user()?->can('view_any_action') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->can('view_action') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_action') ?? false;
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();
        if (!$user?->can('update_action')) return false;

        if (!$record->estModifiable()) {
            if (request()->routeIs('filament.*')) {
                Notification::make()->title('Exercice verrouillé')->warning()
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
        if (!$user?->can('delete_action')) return false;
        return $record->estModifiable();
    }

    public static function canEditRecord($record): bool
    {
        $canEdit = static::canEdit($record);
        if (!$canEdit && $record->estLectureSeule()) {
            Notification::make()->title('Édition impossible')->warning()
                ->body("L'exercice {$record->exercice->annee} est {$record->exercice->statut}. Seul un super admin peut modifier.")
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
                Forms\Components\Section::make('Informations de l\'Action')
                    ->schema([
                        Forms\Components\Section::make('Exercice')
                            ->description('Exercice budgétaire de rattachement')
                            ->schema([ExerciceSelect::make()])
                            ->collapsible()
                            ->collapsed(fn($record) => $record !== null),

                        // B2c — on range une action sous un sous-programme de L'HÔPITAL, plus sous un
                        // programme de la tutelle : `actions.programme_id` découle du rattachement choisi
                        // sur ce sous-programme (règle validée en B), et le saisissant ne choisit plus
                        // la classification budgétaire lui-même. La valeur dérivée se lit sous le menu,
                        // en direct, et s'écrit dans `CreationActionSousProgramme::ranger()`.
                        Forms\Components\Select::make('sous_programme_ep_id')
                            ->label('Sous-programme de l\'EP')
                            ->required()->searchable()->preload()->live()
                            ->options(function (?Action $record): array {
                                $liste = SousProgrammeEp::query()
                                    ->whereNotNull('programme_budgetaire_id')
                                    ->orderBy('code')
                                    ->get()
                                    ->mapWithKeys(fn (SousProgrammeEp $sp): array => [
                                        $sp->id => "{$sp->code} — {$sp->libelle}",
                                    ])
                                    ->all();

                                // Le sous-programme de la ligne en édition peut avoir été retiré, ou
                                // avoir perdu son rattachement : absent des options, un champ requis se
                                // rendrait vide alors qu'il est rempli en base — et l'enregistrement
                                // suivant effacerait le lien. Il reste proposé, nommé pour ce qu'il est,
                                // et la règle dessous le refuse si on le garde.
                                $propre = $record?->sousProgrammeEp;

                                if ($propre !== null && ! isset($liste[$propre->id])) {
                                    $liste[$propre->id] = "{$propre->code} — {$propre->libelle}"
                                        . ($propre->trashed() ? ' (sous-programme retiré)' : ' (sans programme de rattachement)');
                                }

                                return $liste;
                            })
                            ->helperText(function (callable $get): ?string {
                                $sp = SousProgrammeEp::find((int) ($get('sous_programme_ep_id') ?? 0));

                                if ($sp === null) {
                                    return null;
                                }

                                try {
                                    $programme = CreationActionSousProgramme::programmeDe($sp);
                                } catch (\RuntimeException) {
                                    // Le sous-programme n'a rien où ranger l'action : le refus se
                                    // lit sous le menu par la règle, pas dans une promesse vide.
                                    return null;
                                }

                                return "Programme portant les actions de ce sous-programme : "
                                    . "« {$programme->code} — {$programme->libelle} » (exercice {$programme->annee}).";
                            })
                            ->rule(function (callable $get): \Closure {
                                return function (string $attribute, $value, \Closure $fail) use ($get) {
                                    // `required()` fait le champ vide ; ici la valeur existe, reste à
                                    // savoir si elle désigne encore quelque chose qui porte des actions.
                                    if ($value === null || $value === '') {
                                        return;
                                    }

                                    $sp = SousProgrammeEp::withTrashed()->find((int) $value);

                                    if ($sp === null) {
                                        $fail('Ce sous-programme de l\'EP n\'existe plus — choisissez-en un autre.');

                                        return;
                                    }

                                    // Une seule règle pour les deux écrans, un seul message : ce que
                                    // le modal des sous-programmes refuse à l'appel de `creer()`, la
                                    // fiche le refuse ici, sous le champ.
                                    try {
                                        $programme = CreationActionSousProgramme::programmeDe($sp);
                                    } catch (\RuntimeException $e) {
                                        $fail($e->getMessage());

                                        return;
                                    }

                                    // Le programme ne se choisit plus, l'exercice si : sans ce contrôle,
                                    // l'écran écrirait une action rangée sur un exercice que son
                                    // programme ne connaît pas. Refuser plutôt que réécrire l'exercice
                                    // choisi — la valeur saisie disparaîtrait sans que rien le dise.
                                    $exercice = (int) ($get('exercice_id') ?? 0);

                                    if ($exercice > 0 && $exercice !== (int) $programme->exercice_id) {
                                        $fail("« {$sp->code} » range ses actions sous le programme « {$programme->code} » de l'exercice "
                                            . "{$programme->annee} : l'action ne peut pas être rangée sur l'exercice "
                                            . Exercice::find($exercice)?->annee
                                            . ". Reprenez l'exercice " . $programme->annee
                                            . ", ou rattachez ce sous-programme à un programme de l'exercice visé.");
                                    }
                                };
                            }),

                        // ✅ Code généré automatiquement à l'enregistrement
                        Forms\Components\TextInput::make('code')
                            ->label('Code')
                            ->disabled()->dehydrated(false)
                            ->placeholder('Généré automatiquement à l\'enregistrement')
                            ->helperText(fn($record) => $record ? null : 'A + numéro suivant de l\'exercice (A1, A2…).'),

                        Forms\Components\TextInput::make('libelle')
                            ->label('Libellé')->required()->maxLength(255)->columnSpanFull()
                            ->placeholder('Ex: Prise en charge des maladies chroniques'),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')->rows(3)->columnSpanFull(),

                        \App\Filament\Planification\Components\ResponsableSelect::make(),

                        Forms\Components\TextInput::make('ordre')
                            ->label('Ordre d\'affichage')->numeric()->default(0),

                        Forms\Components\Toggle::make('actif')->label('Actif')->default(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Objectif Spécifique')
                    ->description('Définissez l\'objectif spécifique de cette action')
                    ->schema([
                        Forms\Components\Repeater::make('objectifsSpecifiques')
                            ->relationship('objectifsSpecifiques')->label('')
                            ->schema([
                                Forms\Components\Textarea::make('libelle')
                                    ->label('Objectif spécifique')->required()->rows(2)
                                    ->placeholder('Ex: Améliorer la prise en charge des cas chroniques...'),
                                Forms\Components\TextInput::make('ordre')
                                    ->label('Ordre')->numeric()->default(0),
                            ])
                            ->columnSpanFull()->defaultItems(1)
                            ->addActionLabel('Ajouter un objectif spécifique')
                            ->collapsible()
                            ->itemLabel(fn(array $state): ?string => \Str::limit($state['libelle'] ?? 'Nouvel objectif', 50)),
                    ])
                    ->collapsible()
                    ->collapsed(fn($record) => $record !== null),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'exercice',
                'sousProgrammeEp',
                'responsable',
                'activites.taches' => function ($query) {
                    $query->where('niveau', 'tache')->with('sousTaches');
                }
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

                Tables\Columns\TextColumn::make('sousProgrammeEp.code')
                    ->label('Sous-programme de l\'EP')->searchable()->sortable()->badge()->color('gray')
                    ->tooltip(fn(Action $record) => $record->sousProgrammeEp?->libelle)
                    ->placeholder('—'),

                // Lecture seule : la colonne est ce que le rattachement du sous-programme implique,
                // plus une valeur saisie ici.
                Tables\Columns\TextColumn::make('programme.code')
                    ->label('Programme')->searchable()->sortable()->badge()->color('primary'),

                Tables\Columns\TextColumn::make('code')
                    ->label('Code')->searchable()->sortable()->weight('bold'),

                Tables\Columns\TextColumn::make('libelle')
                    ->label('Libellé')->searchable()->sortable()->wrap()->limit(50),

                Tables\Columns\TextColumn::make('responsable.nom_complet')
                    ->label('Responsable de mise en œuvre')->placeholder('—'),

                Tables\Columns\TextColumn::make('activites_count')
                    ->label('Activités')->counts('activites')->badge()->color('success'),

                Tables\Columns\TextColumn::make('total_ae')
                    ->label('Total AE')
                    ->formatStateUsing(
                        fn($record) =>
                        number_format($record->getTotalAe(), 0, ',', ' ') . ' FCFA'
                    )
                    ->color('warning')
                    ->tooltip('Autorisations d\'Engagement - Somme de toutes les activités')
                    ->toggleable()->sortable(false),

                Tables\Columns\TextColumn::make('total_cp')
                    ->label('Total CP')
                    ->formatStateUsing(
                        fn($record) =>
                        number_format($record->getTotalCp(), 0, ',', ' ') . ' FCFA'
                    )
                    ->color('info')
                    ->tooltip('Crédits de Paiement - Somme de toutes les activités')
                    ->toggleable()->sortable(false),

                Tables\Columns\IconColumn::make('actif')->label('Actif')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('exercice_id')
                    ->label('Exercice')
                    ->relationship('exercice', 'annee')
                    ->searchable()->preload()
                    ->placeholder('Tous les exercices')
                    ->default(fn() => Exercice::getActif()?->id),

                Tables\Filters\SelectFilter::make('sous_programme_ep_id')
                    ->label('Sous-programme de l\'EP')
                    ->options(fn(): array => SousProgrammeEp::withTrashed()
                        ->orderBy('code')->get()
                        ->mapWithKeys(fn(SousProgrammeEp $sp): array => [$sp->id => "{$sp->code} — {$sp->libelle}"])
                        ->all())
                    ->searchable()->preload(),

                Tables\Filters\SelectFilter::make('programme_id')
                    ->label('Programme')
                    ->relationship('programme', 'libelle')->searchable()->preload(),

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
            ->defaultSort('code', 'asc');
    }

    public static function getRelations(): array
    {
        return [
            // Le niveau suivant de la hiérarchie d'abord : une action se décline en activités,
            // et c'est de là que la chaîne repart vers les tâches.
            \App\Filament\Planification\Resources\ActionResource\RelationManagers\ActivitesRelationManager::class,
            \App\Filament\Planification\Resources\Concerns\IndicateursRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListActions::route('/'),
            'create' => Pages\CreateAction::route('/create'),
            'edit'   => Pages\EditAction::route('/{record}/edit'),
        ];
    }
}
