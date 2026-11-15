<?php

namespace App\Filament\Planification\Resources;

use App\Filament\Planification\Resources\SousProgrammeEpResource\Pages;
use App\Filament\Planification\Resources\SousProgrammeEpResource\RelationManagers\ActionsRelationManager;
use App\Models\Exercice;
use App\Models\PlanStrategiqueEp;
use App\Models\Programme;
use App\Models\SousProgrammeEp;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Table;

class SousProgrammeEpResource extends Resource
{
    protected static ?string $model = SousProgrammeEp::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Plans Stratégiques EP';

    // B1b — les sous-programmes de l'hôpital ont leur propre entrée : ce sont eux qui portent les
    // actions, et chacun se rattache à un programme budgétaire du MINSANTE (menu précédent).
    protected static ?string $navigationLabel = "Sous-programmes de l'EP";
    protected static ?string $modelLabel = "Sous-programme de l'EP";
    protected static ?string $pluralModelLabel = "Sous-programmes de l'EP";
    protected static ?int $navigationSort = 2;

    // ========================================
    // PERMISSIONS — même catalogue que l'onglet du plan stratégique, pour que les deux entrées
    // disent la même chose. Sans ces surcharges, `Resource::can*()` vaut « true » faute de
    // policy : la page de création serait ouverte à tout compte connecté au panneau.
    // ========================================

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_sous_programme_ep') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->can('view_sous_programme_ep') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_sous_programme_ep') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('update_sous_programme_ep') ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('delete_sous_programme_ep') ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->can('delete_sous_programme_ep') ?? false;
    }

    /**
     * Programmes budgetaires de l'exercice actif, sans le scope global 'exercice'
     * (evite une liste vide si l'exercice de session differe).
     */

    // Recherche globale (champ du bandeau), comme dans le panel Budget
    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'libelle'];
    }

    protected static function programmesExerciceActif()
    {
        return Programme::withoutGlobalScope('exercice')
            ->where('exercice_id', Exercice::getActif()?->id)
            ->orderBy('code')
            ->get();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identification')
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->required()->unique(ignoreRecord: true)->maxLength(50),
                    Forms\Components\TextInput::make('libelle')
                        ->required()->maxLength(255),
                    \App\Filament\Planification\Components\ResponsableSelect::make()
                        ->label('Responsable (agent)')
                        ->required()
                        ->helperText('Agent de la table des personnels : la revue des libellés et la matrice d\'arrimage ne lui montrent que ses sous-programmes. Sans agent désigné — ou sans fiche reliée à un login — le sous-programme n\'est visible que des comptes habilités « Tous les libellés ».'),
                    Forms\Components\Textarea::make('description')->columnSpanFull(),
                ])
                ->columns(2),

            // B1b — depuis le menu « Sous-programmes de l'EP », c'est ici que la ligne choisit le
            // plan qu'elle décompose. L'onglet du plan stratégique n'a pas ce champ : il tient
            // déjà le plan par son enregistrement parent.
            Forms\Components\Section::make("Plan stratégique décomposé")
                ->schema([
                    Forms\Components\Select::make('plan_strategique_ep_id')
                        ->label('Plan stratégique (PSP)')
                        ->options(fn() => PlanStrategiqueEp::orderBy('code')->pluck('libelle', 'id')->all())
                        ->searchable()
                        ->required()
                        ->helperText('Instruction du 22 janvier 2026 : quatre sous-programmes maximum par plan (3 opérationnels + 1 support).')
                        ->columnSpanFull(),
                ])
                ->columns(1),

            // Le sous-programme est une décomposition de l'hôpital ; il n'en existe qu'un par
            // programme de la tutelle, et le code qui range les actions en découle.
            Forms\Components\Section::make('Rattachement à la tutelle')
                ->description("Le sous-programme décompose l'action de l'établissement public. Il est rattaché à un programme budgétaire du MINSANTE — celui-ci découle des objectifs de développement du pays (SND30) et de la stratégie sectorielle de santé —, et c'est sous ce programme que les actions, activités, tâches et lignes budgétaires sont rangées.")
                ->schema([
                    Forms\Components\Select::make('programme_budgetaire_id')
                        ->label("Programme budgétaire du MINSANTE")
                        ->helperText('Ex : 410 Prévention de la maladie en milieu hospitalier. Un seul sous-programme de l\'EP par programme de la tutelle.')
                        ->options(fn() => static::programmesExerciceActif()
                            ->where('niveau', 'programme')
                            ->mapWithKeys(fn($p) => [$p->id => "{$p->code} - {$p->libelle}"]))
                        ->searchable()
                        ->required()
                        // `rules()` et non `rule()` : Filament évalue la fermeture au montage, où
                        // `$attribute`/`$value`/`$fail` n'existent pas encore — `rule()` fait
                        // mourir la page. La fermeture reçoit donc `$get` et RETOURNE la règle.
                        ->rules(function ($get) {
                            return [function (string $attribute, $value, \Closure $fail) use ($get) {
                                // Le refus se lit sous le champ : le modèle garde la même règle,
                                // mais son exception arriverait en page d'erreur. Exclusion par
                                // `code` (unique) et non par `$record` : la ligne peut être en
                                // création, et dans un RelationManager `$record` est le plan
                                // stratégique.
                                $conflit = SousProgrammeEp::autreSousProgrammeDuMemeProgramme(
                                    $value ? (int) $value : null,
                                    saufCode: $get('code'),
                                );

                                if ($conflit) {
                                    $fail("Ce programme du MINSANTE a déjà son sous-programme de l'EP :"
                                        . " « {$conflit->code} » ({$conflit->libelle}).");
                                }
                            }];
                        }),
                ])
                ->columns(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')->label('N°'),
                Tables\Columns\TextColumn::make('code'),
                Tables\Columns\TextColumn::make('libelle')->searchable(),

                // Le sous-programme ne tient qu'à un seul programme de la tutelle : une colonne
                // suffit. L'ancienne « Programme EP » répétait ce code, dont il découle.
                Tables\Columns\TextColumn::make('programmeBudgetaire.code')
                    ->label('Programme du MINSANTE')
                    ->badge()
                    ->color(fn($state) => $state ? 'success' : 'danger')
                    ->placeholder('Non rattaché'),

                Tables\Columns\TextColumn::make('responsable.nom_complet')
                    ->label('Responsable')
                    ->placeholder('Non désigné'),

                Tables\Columns\TextColumn::make('planStrategiqueEp.libelle')->label('PSP'),
                Tables\Columns\BadgeColumn::make('statut')->colors([
                    'gray' => 'brouillon',
                    'warning' => 'en_transmission',
                    'success' => ['valide', 'en_vigueur'],
                    'danger' => 'cloture',
                ]),
            ])

            // Étape 4 — le filtre que le lien « Sous-programmes » de la fiche d'un programme
            // ouvre déjà appliqué. Sans ce SelectFilter, le paramètre `tableFilters[…]` de
            // l'URL est ignoré en silence et la liste entière s'affiche : le lien paraît
            // fonctionner alors qu'il ne filtre rien.
            // Options prises SANS le scope d'exercice, contrairement au champ du formulaire
            // (`programmesExerciceActif()`) : un filtre doit savoir nommer la valeur que porte
            // la ligne, y compris le programme d'un exercice clôturé consultable après coup.
            ->filters([
                Tables\Filters\SelectFilter::make('programme_budgetaire_id')
                    ->label('Programme du MINSANTE')
                    ->options(fn() => Programme::withoutGlobalScope('exercice')
                        ->orderBy('code')->get()
                        ->mapWithKeys(fn(Programme $p) => [$p->getKey() => "{$p->code} - {$p->libelle}"]))
                    ->searchable()
                    ->placeholder('Tous les programmes'),
            ])

            // Les six gestes d'une ligne tiennent dans un menu, aligné à gauche comme dans la
            // liste des BCR : les colonnes « Code / Libellé / Statut » ne se font plus décaler.
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),

                    // Étape 5 — le fil d'Ariane : le menu « Programmes » mène ici par l'action
                    // « Sous-programmes » (liste déjà filtrée). L'action suivante se créait en
                    // ouvrant la fiche du sous-programme puis son onglet « Actions » ; elle se
                    // crée maintenant sur la ligne, sans quitter la liste.
                    Tables\Actions\Action::make('ajouterAction')
                        ->label('Ajouter une action')
                        ->icon('heroicon-o-plus-circle')
                        ->color('gray')
                        ->visible(fn() => auth()->user()->can('create_action'))
                        ->form(\App\Filament\Planification\Resources\Concerns\CreationActionSousProgramme::champs())
                        ->modalHeading(fn(SousProgrammeEp $record) => "Nouvelle action sous « {$record->code} »")
                        ->modalDescription("Elle sera rangée sous le programme du MINSANTE auquel ce sous-programme est rattaché, sur l'exercice de ce programme — ces deux valeurs ne se choisissent pas ici.")
                        ->action(function (SousProgrammeEp $record, array $data) {
                            // Le vendor coupe déjà l'appel d'une action cachée (`isDisabled()` inclut
                            // `isHidden()`) : ce garde n'est pas le premier rempart mais le repli, si
                            // le bouton redevient appelable (droit retiré en cours de route, refonte
                            // d'écran). Le refus s'imprime au lieu de fermer le modal sans rien dire.
                            if (!auth()->user()?->can('create_action')) {
                                Notification::make()
                                    ->title('Action non autorisée')
                                    ->body('Votre rôle ne donne pas le droit de créer une action.')
                                    ->danger()->persistent()
                                    ->send();

                                return;
                            }

                            try {
                                $action = \App\Filament\Planification\Resources\Concerns\CreationActionSousProgramme::creer(
                                    $record, $data);

                                Notification::make()
                                    ->title("« {$action->code} » est créée sous « {$record->code} »")
                                    ->body('La suite de la chaîne — les activités — se saisit dans sa fiche.')
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('Action refusée')
                                    ->body($e->getMessage())
                                    ->danger()->persistent()
                                    ->send();
                            }
                        }),

                    // Même règle que dans le sous-onglet du plan stratégique : le cycle
                    // ne se pilote pas depuis un seul écran.
                    Tables\Actions\Action::make('valider')
                        ->label('Valider')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn() => auth()->user()->can('valider_sous_programme_ep'))
                        ->requiresConfirmation()
                        ->modalHeading(fn(SousProgrammeEp $record) => "Valider — {$record->code}")
                        ->modalDescription('Le sous-programme passe à « Validé ». Transmettre reste le chemin tracé entre deux agents, ce n\'est plus une condition de la validation.')
                        ->action(function (SousProgrammeEp $record) {
                            if (!auth()->user()?->can('valider_sous_programme_ep')) {
                                Notification::make()
                                    ->title('Action non autorisée')
                                    ->danger()
                                    ->body('Votre rôle ne donne pas le droit de valider un sous-programme.')
                                    ->persistent()
                                    ->send();
                                return;
                            }

                            try {
                                $record->valider();
                                Notification::make()
                                    ->title("« {$record->code} » est validé")
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('Validation refusée')
                                    ->danger()
                                    ->body($e->getMessage())
                                    ->persistent()
                                    ->send();
                            }
                        }),

                    Tables\Actions\Action::make('mettreEnVigueur')
                        ->label('Mettre en vigueur')
                        ->icon('heroicon-o-play-circle')
                        ->color('success')
                        ->visible(fn() => auth()->user()->can('mettre_en_vigueur_sous_programme_ep'))
                        ->requiresConfirmation()
                        ->modalHeading(fn(SousProgrammeEp $record) => "Mettre en vigueur — {$record->code}")
                        ->modalDescription('Le sous-programme passe de « Validé » à « En vigueur » : son contenu devient la référence de l\'exercice.')
                        ->action(function (SousProgrammeEp $record) {
                            // « visible » ne protège pas l'appel : le droit est revérifié ici.
                            if (!auth()->user()?->can('mettre_en_vigueur_sous_programme_ep')) {
                                Notification::make()
                                    ->title('Action non autorisée')
                                    ->danger()
                                    ->body('Votre rôle ne donne pas le droit de mettre un sous-programme en vigueur.')
                                    ->persistent()
                                    ->send();
                                return;
                            }

                            try {
                                $record->mettreEnVigueur();
                                Notification::make()
                                    ->title("« {$record->code} » est en vigueur")
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('Mise en vigueur refusée')
                                    ->danger()
                                    ->body($e->getMessage())
                                    ->persistent()
                                    ->send();
                            }
                        }),

                    Tables\Actions\Action::make('matrice')
                        ->label("Matrice d'arrimage")
                        ->icon('heroicon-o-squares-2x2')
                        ->visible(fn() => auth()->user()->can('view_matrice_arrimage'))
                        ->url(fn(SousProgrammeEp $record) => \App\Filament\SuiviEvaluation\Pages\MatriceArrimage::getUrl(
                            ['psp' => $record->plan_strategique_ep_id, 'sp' => $record->id],
                            panel: 'suivi-evaluation'
                        ))
                        ->openUrlInNewTab(),

                    Tables\Actions\Action::make('libelles')
                        ->label('Tableau des libellés')
                        ->icon('heroicon-o-list-bullet')
                        ->visible(fn() => auth()->user()->can('view_arborescence_libelles'))
                        ->url(fn(SousProgrammeEp $record) => \App\Filament\Planification\Pages\ArborescenceLibelles::getUrl([
                            'psp' => $record->plan_strategique_ep_id,
                            'sp'  => $record->id,
                        ]))
                        ->openUrlInNewTab(),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')
                    ->button()->size('sm'),
            ], position: ActionsPosition::BeforeColumns);
    }

    public static function getRelations(): array
    {
        return [
            ActionsRelationManager::class,
            \App\Filament\Planification\Resources\Concerns\IndicateursRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSousProgrammeEps::route('/'),
            'create' => Pages\CreateSousProgrammeEp::route('/create'),
            'edit' => Pages\EditSousProgrammeEp::route('/{record}/edit'),
        ];
    }
}
