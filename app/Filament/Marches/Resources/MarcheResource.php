<?php

namespace App\Filament\Marches\Resources;

use App\Filament\Actions\WorkflowActions;
use App\Filament\Concerns\AutorisationParPermissions;
use App\Filament\Marches\Resources\MarcheResource\Pages;
use App\Models\CategorieMarche;
use App\Models\Commission;
use App\Models\Exercice;
use App\Models\ExpressionBesoin;
use App\Models\FichePresence;
use App\Models\Marche;
use App\Models\NomenclatureBudgetaire;
use App\Models\Offre;
use App\Models\OrdreService;
use App\Models\Service;
use App\Models\TypeContrat;
use App\Models\TypeOrdreService;
use App\Models\TypeProcedure;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Marchés publics — fiche du marché et conduite de son cycle de vie.
 *
 * Le cycle est mené par des actions, jamais par l'écriture directe du statut :
 * la machine à états est dans le modèle (config/marches.php), l'écran ne fait que
 * présenter les transitions permises à l'utilisateur connecté.
 *
 * Le circuit hiérarchique (transmettre, rappeler, retourner, clôturer, historique)
 * est fourni par WorkflowActions, le même jeu que les bons de commande.
 */
class MarcheResource extends Resource
{
    use AutorisationParPermissions;

    protected static ?string $model = Marche::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Appels d\'Offres';
    protected static ?string $navigationLabel = 'Marchés';
    protected static ?string $modelLabel = 'marché';
    protected static ?string $pluralModelLabel = 'marchés';
    protected static ?string $recordTitleAttribute = 'numero';
    protected static ?int $navigationSort = 1;

    protected static function prefixePermission(): string
    {
        return 'marche';
    }

    // =========================================================
    // AUTORISATION SPÉCIFIQUE DU CYCLE
    // =========================================================

    /**
     * Un marché enregistré ne se modifie plus : soit il est encore brouillon, soit
     * il faut force_update_marche (réservé à l'administration, pas au service
     * des marchés, qui ne corrige ses validations qu'en les retirant).
     */
    public static function canEdit($record): bool
    {
        if ($record->estEnCoursDeTransmission()) {
            return auth()->user()?->can('force_update_marche') ?? false;
        }

        return auth()->user()?->can('force_update_marche')
            || (auth()->user()?->can('update_marche') && $record->statut === 'brouillon');
    }

    /** Suppression réservée au brouillon : un marché lancé reste dans le dossier. */
    public static function canDelete($record): bool
    {
        return $record->statut === 'brouillon' && (auth()->user()?->can('delete_marche') ?? false);
    }

    public static function canValider(Marche $record): bool
    {
        return auth()->user()?->can('valider_marche') ?? false;
    }

    public static function canRetrait(Marche $record): bool
    {
        return auth()->user()?->can('valider_marche') ?? false;
    }

    public static function canPublier(Marche $record): bool
    {
        return auth()->user()?->can('publier_dao_marche') ?? false;
    }

    public static function canOuvrirPlis(Marche $record): bool
    {
        return auth()->user()?->can('ouvrir_plis_marche') ?? false;
    }

    public static function canAnnuler(Marche $record): bool
    {
        return auth()->user()?->can('annuler_marche') ?? false;
    }

    /**
     * Attribution (étape D) : deux permissions, pas une. « attribuer_marche » ouvre
     * la décision, « lever_attribution_marche » la défait — les séparer, c'est dire
     * au journal qui a tranché et qui est revenu sur la décision.
     *
     * Pas de force_update_attribution : la voie de correction d'une attribution est
     * sa levée, devant l'auteur de la décision et sa ligne au journal, jamais une
     * réécriture silencieuse des colonnes.
     */
    public static function canAttribuer(): bool
    {
        return auth()->user()?->can('attribuer_marche') ?? false;
    }

    public static function canLeverAttribution(): bool
    {
        return auth()->user()?->can('lever_attribution_marche') ?? false;
    }

    /**
     * Homologation (étape E) : la même symétrie que l'attribution, une permission pour
     * la décision et une pour sa levée. Pas de `force_update_homologation` — la voie de
     * correction d'une homologation est sa levée, tant qu'aucun ordre de service n'a
     * été émis.
     */
    public static function canHomologuer(): bool
    {
        return auth()->user()?->can('homologuer_marche') ?? false;
    }

    public static function canLeverHomologation(): bool
    {
        return auth()->user()?->can('lever_homologation_marche') ?? false;
    }

    /**
     * Émettre un ordre de service depuis la fiche du marché se juge sur
     * `create_ordre_service` : c'est la permission de la ligne que l'action écrit, et
     * l'émission n'a pas d'autre chemin (l'écran « Ordres de service » n'offre pas de
     * création). Une permission `emettre_ordre_service` serait la même main déclarée
     * deux fois.
     */
    public static function canEmettreOrdreService(): bool
    {
        return auth()->user()?->can('create_ordre_service') ?? false;
    }

    /**
     * Dresser la fiche de présence se juge sur `create_fiche_presence`, pour la même
     * raison que l'ordre de service : c'est la permission de la ligne que l'action
     * écrit, et l'écran « Fiches de présence » n'offre pas de création.
     */
    public static function canDresserFichePresence(): bool
    {
        return auth()->user()?->can('create_fiche_presence') ?? false;
    }

    // =========================================================
    // FORMULAIRE
    // =========================================================

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Information du marché')->schema([
                Forms\Components\TextInput::make('numero')->label('Référence')
                    ->disabled()->dehydrated(false)
                    ->helperText('Générée à l\'enregistrement'),
                Forms\Components\TextInput::make('titre')->label('Titre ou intitulé')
                    ->required()->maxLength(255)
                    ->placeholder('Fourniture de matériel de bureau au service des marchés'),
                Forms\Components\Textarea::make('objet')->label('Objet du marché')
                    ->rows(2)->columnSpanFull(),
                Forms\Components\Select::make('exercice_id')->label('Exercice')
                    ->options(fn () => Exercice::orderByDesc('annee')->pluck('libelle', 'id'))
                    ->default(fn () => Exercice::getActif()?->id)
                    ->required()->searchable()->preload(),
                Forms\Components\Select::make('expression_besoin_id')->label('Expression de besoin d\'origine')
                    ->options(fn () => ExpressionBesoin::orderByDesc('numero')->pluck('numero', 'id'))
                    ->searchable()->preload()->placeholder('Aucune')
                    ->helperText('Le « Besoin → DAO » : rattache le marché à la demande du service'),
                Forms\Components\Select::make('service_demandeur_id')->label('Service demandeur')
                    ->options(fn () => Service::where('actif', true)->orderBy('nom')->pluck('nom', 'id'))
                    ->searchable()->preload()->placeholder('Aucun'),
                Forms\Components\Select::make('responsable_id')->label('Responsable du marché')
                    ->options(fn () => User::orderBy('name')->pluck('name', 'id'))
                    ->default(fn () => auth()->id())
                    ->required()->searchable()->preload(),
            ])->columns(2),

            Forms\Components\Section::make('Caractéristiques de la passation')->schema([
                Forms\Components\Select::make('categorie_id')->label('Catégorie de marché')
                    ->options(fn () => CategorieMarche::actives()->pluck('libelle', 'id'))
                    ->native()
                    ->placeholder('—')
                    ->live()
                    ->afterStateUpdated(function (?int $state, callable $set, callable $get) {
                        $categorie = CategorieMarche::find($state);
                        if ($categorie && $categorie->duree_mois && !$get('duree_execution_mois')) {
                            $set('duree_execution_mois', $categorie->duree_mois);
                        }
                    })
                    ->helperText('pluriannuel, annuel ou mensuel — obligatoire pour sortir du brouillon ; le pluriannuel prépare l\'engagement par tranches'),
                Forms\Components\Select::make('type_procedure_id')->label('Type de procédure')
                    ->options(function () {
                        return TypeProcedure::actives()->get()
                            ->mapWithKeys(fn (TypeProcedure $t) => [
                                $t->id => config('marches.familles_procedure')[$t->famille] . ' — ' . $t->libelle,
                            ]);
                    })
                    ->placeholder('—')
                    ->searchable()->preload()
                    ->helperText('Obligatoire pour sortir du brouillon'),
                Forms\Components\TextInput::make('duree_execution_mois')->label('Délai d\'exécution')
                    ->numeric()->integer()->minValue(1)->suffix('mois')
                    ->helperText('Repris de la catégorie, modifiable'),
                Forms\Components\Select::make('commission_id')->label('Commission')
                    ->options(fn () => Commission::actives()->orderBy('libelle')->pluck('libelle', 'id'))
                    ->searchable()->preload()->placeholder('Aucune')
                    ->helperText("Épinglée pour le PV d'évaluation et la fiche de présence (étape E)"),
                Forms\Components\TextInput::make('montant_estime')->label('Montant estimatif')
                    ->numeric()->required()->default(0)->suffix('FCFA')->live()
                    ->afterStateUpdated(function (?string $state, callable $get, callable $set) {
                        // Le seuil tient la main tant que personne ne l'a contredite.
                        if ($get('classement_manuel')) {
                            return;
                        }
                        if ($calcule = TypeContrat::pourMontant((float) $state)) {
                            $set('type_contrat_id', $calcule->id);
                        }
                    }),
                Forms\Components\Select::make('nomenclature_id')->label('Ligne budgétaire principale')
                    ->options(fn () => NomenclatureBudgetaire::where('type', 'depense')
                        ->orderBy('code')->get()
                        ->mapWithKeys(fn (NomenclatureBudgetaire $n) => [
                            $n->id => $n->code . ' — ' . $n->libelle,
                        ]))
                    ->searchable()->preload()->placeholder('Aucune')
                    ->helperText('Reprise à l\'engagement budgétaire'),
            ])->columns(2),

            Forms\Components\Section::make('Type de contrat — règle de seuil')->schema([
                Forms\Components\Placeholder::make('seuil_applicable')
                    ->label('Classement donné par le seuil')
                    ->content(function (callable $get): string {
                        $montant = (float) $get('montant_estime');
                        if ($montant <= 0) {
                            return 'Saisir le montant estimatif pour que la règle de seuil classe le marché.';
                        }
                        $calcule = TypeContrat::pourMontant($montant);

                        return $calcule
                            ? number_format($montant, 0, ',', ' ') . ' FCFA → ' . $calcule->libelle
                            : 'Aucun seuil actif ne couvre ce montant — vérifier le paramétrage des types de contrat.';
                    }),
                Forms\Components\Select::make('type_contrat_id')->label('Type de contrat retenu')
                    ->options(fn () => TypeContrat::actives()->orderBy('ordre')->pluck('libelle', 'id'))
                    ->native()->live()
                    // Coupé tant que le seuil fait foi : la valeur vient du calcul,
                    // donc rien de saisi n'est masqué ici. Obligatoire seulement quand
                    // l'humain prend la main — sinon une fiche en brouillon à 0 FCFA
                    // serait refusée sur un champ déjà fermé.
                    ->required(fn (callable $get): bool => (bool) $get('classement_manuel'))
                    ->disabled(fn (callable $get) => ! (bool) $get('classement_manuel'))
                    ->dehydrated(fn (callable $get) => (bool) $get('classement_manuel')),
                Forms\Components\Checkbox::make('classement_manuel')
                    ->label('Classer contre le seuil')
                    ->live()
                    ->helperText('À n\'utiliser qu\'avec un justificatif : le choix est tracé avec son auteur et sa date.'),
                Forms\Components\Textarea::make('classement_justificatif')
                    ->label('Justificatif du classement manuel')
                    ->rows(2)->columnSpanFull()
                    ->visible(fn (callable $get) => (bool) $get('classement_manuel'))
                    ->required(fn (callable $get) => (bool) $get('classement_manuel')),
                Forms\Components\Placeholder::make('contestation_en_cours')
                    ->label('')
                    ->columnSpanFull()
                    ->visible(fn ($record) => $record instanceof Marche && $record->classementContredit())
                    ->content(fn () => new \Illuminate\Support\HtmlString(
                        '<div class="rounded-lg p-3 text-sm bg-amber-50 dark:bg-amber-900/30 '
                            . 'text-amber-800 dark:text-amber-200 border border-amber-300 dark:border-amber-700">'
                            . '⚠️ Le seuil ne classe pas ce marché ainsi. Enregistrez le justificatif, '
                            . 'ou décochez « Classer contre le seuil » pour revenir au calcul.'
                            . '</div>'
                    )),
            ])->columns(2),

            Forms\Components\Section::make('Dates et suivi du cycle')
                ->schema([
                    Forms\Components\Placeholder::make('statut_suivi')
                        ->label('Statut')
                        ->content(fn ($record) => $record
                            ? Marche::statutLibelle($record->statut)
                            : 'Brouillon — le statut se règle par les actions du cycle.'),
                    Forms\Components\DatePicker::make('date_limite_depot')
                        ->label('Date limite de dépôt des offres')
                        ->native(),
                    Forms\Components\Placeholder::make('date_publication_dao_lu')
                        ->label('Publication du DAO')
                        ->content(fn ($record) => $record?->date_publication_dao?->format('d/m/Y') ?? '—'),
                    Forms\Components\Placeholder::make('date_ouverture_plis_lu')
                        ->label('Ouverture des plis')
                        ->content(fn ($record) => $record?->date_ouverture_plis?->format('d/m/Y') ?? '—'),
                    Forms\Components\Placeholder::make('validation_lue')
                        ->label('Validation')
                        ->content(fn ($record) => $record?->date_validation
                            ? $record->date_validation->format('d/m/Y') . ' — ' . ($record->valideur?->name ?? '')
                            : '—'),
                    Forms\Components\Textarea::make('observations')->rows(2)->columnSpanFull(),
                ])
                ->columns(2)
                ->description('Ces dates se renseignent par les actions du cycle ; la date limite de dépôt '
                    . 'peut être corrigée tant que le marché n\'est pas ouvert.')
                ->visible(fn ($record) => (bool) $record),

            Forms\Components\Section::make('Évaluation et attribution')
                ->schema([
                    Forms\Components\Placeholder::make('classement')
                        ->label('Classement sorti des notes')
                        ->columnSpanFull()
                        ->content(fn (Marche $record) => static::classementHtml($record)),

                    Forms\Components\Placeholder::make('offre_retenue_lue')
                        ->label('Offre retenue')
                        ->content(fn (Marche $record) => $record->offreAttribuee
                            ? $record->offreAttribuee->numero . ' — '
                                . ($record->offreAttribuee->fournisseur?->raison_sociale ?? 'soumissionnaire inconnu')
                            : '— l\'attribution se prononce par l\'action « Attribuer » du tableau'),
                    Forms\Components\Placeholder::make('montant_attribue_lu')
                        ->label('Montant attribué')
                        ->content(fn (Marche $record) => $record->montant_attribue !== null
                            ? number_format((float) $record->montant_attribue, 0, ',', ' ') . ' FCFA'
                            : '—'),
                    Forms\Components\Placeholder::make('attribution_lue')
                        ->label('Décision')
                        ->content(fn (Marche $record) => $record->date_attribution
                            ? $record->date_attribution->format('d/m/Y H:i') . ' — '
                                . ($record->attribueur?->name ?? '')
                            : '—'),
                    Forms\Components\Placeholder::make('attribution_justificatif_lu')
                        ->label('Justificatif hors classement')
                        ->columnSpanFull()
                        ->visible(fn (Marche $record) => filled($record->attribution_justificatif))
                        ->content(fn (Marche $record) => new HtmlString(
                            nl2br(e($record->attribution_justificatif))
                        )),
                ])
                ->columns(2)
                ->description('Les notes se portent à l\'écran « Grille d\'évaluation » ; le classement ci-dessous '
                    . 'sort de ces notes, il ne se saisit pas. L\'attribution est provisoire : elle se lève '
                    . 'tant que rien n\'est homologué.')
                ->visible(fn ($record) => (bool) $record),

            Forms\Components\Section::make('Homologation et exécution')
                ->schema([
                    Forms\Components\Placeholder::make('homologation_lue')
                        ->label('Homologation')
                        ->content(fn (Marche $record) => $record->date_homologation
                            ? $record->date_homologation->format('d/m/Y H:i') . ' — '
                                . ($record->homologueur?->name ?? '')
                            : '— rien n\'est homologué. L\'homologation se prononce par l\'action '
                                . '« Homologuer », une fois l\'attribution provisoire arrêtée.'),
                    Forms\Components\Placeholder::make('demarrage_lu')
                        ->label('Ordre de service de démarrage')
                        ->content(function (Marche $record) {
                            $os = $record->ordresService()->demarrage()->first();

                            return $os
                                ? $os->numero . ' — ' . ($os->date_emission?->format('d/m/Y') ?? '—')
                                : '— le service n\'est pas encore commandé';
                        }),
                    Forms\Components\Placeholder::make('ordres_service_lu')
                        ->label('Chronologie des ordres de service')
                        ->columnSpanFull()
                        ->content(fn (Marche $record) => static::ordonnancementHtml($record)),
                ])
                ->columns(2)
                ->description('L\'homologation rend l\'attribution définitive. Le service se commande ensuite par '
                    . 'écrit : l\'ordre de service de démarrage est ce qui met le marché en cours d\'exécution, '
                    . 'les arrêts et les reprises s\'ajoutent à sa suite.')
                ->visible(fn ($record) => (bool) $record),

            Forms\Components\Section::make('Annulation')
                ->schema([
                    Forms\Components\Placeholder::make('annulation_lue')
                        ->label('')
                        ->columnSpanFull()
                        ->content(fn (Marche $record) => new \Illuminate\Support\HtmlString(
                            '<strong>' . ($record->annuleur?->name ?? '—') . '</strong> — '
                                . $record->date_annulation?->format('d/m/Y H:i') . '<br>'
                                . nl2br(e($record->motif_annulation))
                        )),
                ])
                ->visible(fn ($record) => $record instanceof Marche && $record->statut === 'annule'),
        ]);
    }

    // =========================================================
    // TABLE
    // =========================================================

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('numero', 'desc')
            ->persistFiltersInSession()
            ->columns([
                Tables\Columns\TextColumn::make('numero')->label('Référence')
                    ->fontFamily('mono')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('titre')->label('Marché')
                    ->weight('bold')->searchable()->limit(60),
                Tables\Columns\TextColumn::make('statut')->label('Statut')->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'brouillon'      => 'gray',
                        'valide'         => 'warning',
                        'publie'         => 'info',
                        'en_evaluation'  => 'primary',
                        'attribue'       => 'primary',
                        'homologue'      => 'success',
                        'en_execution'   => 'success',
                        'termine'        => 'success',
                        default          => 'danger',
                    })
                    ->formatStateUsing(fn (?string $state) => Marche::statutLibelle($state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('montant_estime')->label('Montant estimatif')
                    ->money('XAF')->sortable(),
                Tables\Columns\TextColumn::make('typeContrat.libelle')->label('Contrat'),
                Tables\Columns\TextColumn::make('categorie.libelle')->label('Catégorie')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('typeProcedure.libelle')->label('Procédure')
                    ->placeholder('—'),
                Tables\Columns\IconColumn::make('classement_manuel')->label('Classé à la main')
                    ->boolean()
                    ->tooltip('Le seuil a été contredit — le justificatif est dans la fiche'),
                Tables\Columns\TextColumn::make('date_limite_depot')->label('Dépôt des offres')
                    ->date('d/m/Y')->placeholder('—')->sortable(),
                // Le guichet de réception se voit depuis la liste des marchés : un DAO
                // publié sans aucune offre n'est pas sans suite, il est peut-être juste
                // mal saisi. Compte simple, pas de lien — la liste des offres a déjà son
                // filtre « Marché », et un deep-link irait se heurter aux filtres que
                // cette table persiste en session.
                Tables\Columns\TextColumn::make('offres_count')->label('Offres')
                    ->counts('offres')->badge()->color('gray')->sortable(),
                // La commande du service se voit comme son dépôt : un marché homologué
                // sans ordre de service n'est pas sans suite, il attend que l'écrit
                // parte chez le titulaire. Compte des lignes vivantes — un ordre retiré
                // du dossier ne commande plus rien.
                Tables\Columns\TextColumn::make('ordres_service_count')->label('Ordres de service')
                    ->counts('ordresService')->badge()->color('gray')->sortable(),
                // L'attribution (étape D) se lit dans la liste : ce que la décision a
                // retenu, à quel montant, et si la main a contredit le classement.
                Tables\Columns\TextColumn::make('offreAttribuee.fournisseur.raison_sociale')
                    ->label('Attribué à')->placeholder('—')->limit(35)
                    ->description(fn (Marche $record) => $record->offreAttribuee?->numero),
                Tables\Columns\TextColumn::make('montant_attribue')->label('Montant attribué')
                    ->money('XAF')->placeholder('—')->sortable(),
                Tables\Columns\IconColumn::make('attribution_manuelle')
                    ->label('Attribué hors classement')->boolean()
                    ->tooltip('Le justificatif est dans la fiche, avec son auteur'),
                Tables\Columns\TextColumn::make('responsable.name')->label('Responsable')->placeholder('—'),
            ])
            // Le paramètre doit s'appeler $query : Filament résout les closures par
            // nom, un autre nom lui fait injecter un Builder vide de modèle.
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'categorie', 'typeProcedure', 'typeContrat', 'responsable',
                'offreAttribuee.fournisseur',
            ]))
            ->filters([
                Tables\Filters\SelectFilter::make('statut')->label('Statut')
                    ->options(fn () => Marche::statuts())
                    ->multiple(),
                Tables\Filters\SelectFilter::make('categorie_id')->label('Catégorie')
                    ->options(fn () => CategorieMarche::actives()->pluck('libelle', 'id')),
                Tables\Filters\SelectFilter::make('type_procedure_id')->label('Type de procédure')
                    ->options(fn () => TypeProcedure::actives()->pluck('libelle', 'id')),
                Tables\Filters\SelectFilter::make('commission_id')->label('Commission')
                    ->options(fn () => Commission::actives()->pluck('libelle', 'id')),
            ])
            ->actions(self::actionsCycle())
            // Pas d'action groupée de suppression : un marché déjà lancé ne se
            // supprime pas, et la liste ne doit pas offrir ce que le modèle refuse.
            ->bulkActions([]);
    }

    // =========================================================
    // TRANSITIONS DU MARCHÉ
    // =========================================================

    /**
     * Le jeu générique du dépôt (modifier, transmettre, rappeler, retourner,
     * clôturer, historique, annuler, supprimer) sert aux marchés comme aux bons de
     * commande. Son « Valider » est écarté : il appelle la transition sans
     * try/catch, un marché incomplet serait donc refusé par une page d'erreur au
     * lieu d'un message à l'écran. Il est remplacé par la version ci-dessous.
     *
     * Les actions du cycle ne se cachent pas derrière le circuit de transmission :
     * « Valider » doit rester visible au destinataire à qui le marché est transmis
     * pour validation, et le modèle refuse avec son message 🔒 quand l'action n'est
     * pas la bonne. Une action du cycle est donc calée sur le seul statut.
     */
    public static function actionsCycle(): array
    {
        $generiques = array_values(array_filter(
            WorkflowActions::make(),
            fn ($action) => $action->getName() !== 'valider',
        ));

        return array_merge($generiques, [
            self::actionValider(),
            self::actionRetraitValidation(),
            self::actionPublierDao(),
            self::actionOuvrirPlis(),
            self::actionAttribuer(),
            self::actionLeverAttribution(),
            self::actionHomologuer(),
            self::actionLeverHomologation(),
            self::actionEmettreOrdreService(),
            self::actionDresserFichePresence(),
        ]);
    }

    public static function actionValider(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('valider')
            ->label('Valider')
            ->icon('heroicon-o-check-circle')->color('warning')
            // pas peutEtreValide() : un marché incomplet doit montrer le bouton et
            // dire ce qui manque, pas disparaître sans explication
            ->visible(fn (Marche $record) => $record->statut === 'brouillon'
                && static::canValider($record))
            ->requiresConfirmation()
            ->modalHeading(fn (Marche $record) => "Valider le marché — {$record->numero}")
            ->modalDescription('Après validation, la fiche n\'est plus modifiable : une correction passe par « Retirer la validation » tant que rien n\'est publié.')
            ->action(function (Marche $record) {
                try {
                    $record->valider(auth()->user());
                    Notification::make()->title('✅ Marché validé')
                        ->body($record->numero . ' — ' . Marche::statutLibelle($record->statut))
                        ->success()->send();
                } catch (\Exception $e) {
                    Notification::make()->title('❌ Validation refusée')
                        ->danger()->body($e->getMessage())->persistent()->send();
                }
            });
    }

    /**
     * Le service des marchés peut défaire sa propre validation tant que rien n'a
     * été publié. Passé la publication, il n'y a pas de retour en arrière.
     */
    public static function actionRetraitValidation(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('retrait_validation')
            ->label('Retirer la validation')
            ->icon('heroicon-o-arrow-uturn-left')->color('warning')
            ->visible(fn (Marche $record) => $record->statut === 'valide'
                && static::canRetrait($record))
            ->requiresConfirmation()
            ->modalHeading(fn (Marche $record) => "Retirer la validation — {$record->numero}")
            ->modalDescription('Le marché revient en brouillon et redevient modifiable. Le retrait est tracé.')
            ->action(function (Marche $record) {
                try {
                    $record->retirerValidation(auth()->user());
                    Notification::make()->title('↩ Validation retirée')
                        ->body("{$record->numero} est de nouveau en brouillon")
                        ->warning()->send();
                } catch (\Exception $e) {
                    Notification::make()->title('❌ Retrait refusé')
                        ->danger()->body($e->getMessage())->persistent()->send();
                }
            });
    }

    public static function actionPublierDao(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('publier_dao')
            ->label('Publier le DAO')
            ->icon('heroicon-o-megaphone')->color('info')
            ->visible(fn (Marche $record) => $record->peutEtrePublie()
                && static::canPublier($record))
            ->modalHeading(fn (Marche $record) => "Publication du DAO — {$record->numero}")
            ->modalDescription("L'avis de lancement fixe la date limite de dépôt des offres.")
            ->form([
                Forms\Components\DatePicker::make('date_limite_depot')
                    ->label('Date limite de dépôt des offres')
                    ->required()->minDate(now())->native(),
            ])
            ->action(function (Marche $record, array $data) {
                try {
                    $record->publierDao(auth()->user(), $data['date_limite_depot']);
                    Notification::make()->title('📢 DAO publié')
                        ->body("{$record->numero} — dépôt des offres jusqu'au "
                            . $record->date_limite_depot->format('d/m/Y'))
                        ->success()->send();
                } catch (\Exception $e) {
                    Notification::make()->title('❌ Publication refusée')
                        ->danger()->body($e->getMessage())->persistent()->send();
                }
            });
    }

    public static function actionOuvrirPlis(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('ouvrir_plis')
            ->label('Ouvrir les plis')
            ->icon('heroicon-o-envelope-open')->color('primary')
            ->visible(fn (Marche $record) => $record->peutEtreEvalue()
                && static::canOuvrirPlis($record))
            ->requiresConfirmation()
            ->modalHeading(fn (Marche $record) => "Ouverture des plis — {$record->numero}")
            ->modalDescription('Le marché passe en évaluation : les offres se déposent à l\'écran « Offres reçues », '
                . 'qui les accueille dès la publication du DAO. C\'est là aussi qu\'elles se décident, une par une.')
            ->action(function (Marche $record) {
                try {
                    $record->ouvrirPlis(auth()->user());
                    Notification::make()->title('📂 Plis ouverts')
                        ->body("{$record->numero} — évaluation en cours")
                        ->success()->send();
                } catch (\Exception $e) {
                    Notification::make()->title('❌ Ouverture refusée')
                        ->danger()->body($e->getMessage())->persistent()->send();
                }
            });
    }

    // =========================================================
    // CLASSEMENT ET ATTRIBUTION (étape D)
    // =========================================================

    /**
     * Le classement tel que les notes le donnent, avec la ligne de l'offre retenue
     * marquée. Il se relit à l'écran parce que c'est là que le service décide : une
     * attribution prise sans voir le classement serait une signature à l'aveugle.
     *
     * Les offres non notées y figurent avec ce qui leur manque — c'est la liste que
     * le modèle oppose à l'attribution, affichée avant le refus et non après.
     */
    public static function classementHtml(Marche $record): HtmlString
    {
        $boite = function (string $classes, string $contenu): HtmlString {
            return new HtmlString('<div class="rounded-lg p-3 text-sm border ' . $classes . '">' . $contenu . '</div>');
        };

        if (in_array($record->statut, ['brouillon', 'valide', 'publie'], true)) {
            return $boite(
                'bg-gray-50 dark:bg-gray-900/30 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-700',
                'La notation s\'ouvre à l\'ouverture des plis : le marché est « '
                    . e(Marche::statutLibelle($record->statut)) . '".'
            );
        }

        $classement = $record->classementOffres();

        if ($classement->isEmpty()) {
            return $boite(
                'bg-amber-50 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200 border-amber-300 dark:border-amber-700',
                'Aucune offre recevable à classer. Le classement ne retient que les offres déclarées '
                    . 'recevables — ' . $record->offres()->count() . ' offre(s) au dépôt, elles se décident '
                    . 'à l\'écran « Offres reçues ».'
            );
        }

        $lignes = $classement->map(function (Offre $o, int $i) use ($record): string {
            $retenue = (int) $record->offre_attribuee_id === (int) $o->id;

            return '<tr' . ($retenue ? ' style="font-weight:600"' : '') . '>'
                . '<td>' . ($i + 1) . '</td>'
                . '<td>' . e($o->numero) . ($retenue ? ' 🏆' : '') . '</td>'
                . '<td>' . e($o->fournisseur?->raison_sociale ?? 'soumissionnaire inconnu') . '</td>'
                . '<td>' . (float) $o->totalEvalue() . ' / ' . (float) $o->baremeTotal() . '</td>'
                . '<td>' . number_format((float) $o->montant_offert, 0, ',', ' ') . '</td>'
                . '<td>' . ($o->estEvaluee()
                    ? 'complète'
                    : '<span style="color:#b45309">manque : ' . e(implode(', ', $o->etapesManquantes())) . '</span>')
                . '</td></tr>';
        })->all();

        $tableau = '<table class="w-full text-left text-sm"><thead><tr>'
            . '<th>Rang</th><th>Offre</th><th>Soumissionnaire</th><th>Note</th>'
            . '<th>Montant (FCFA)</th><th>Grille</th>'
            . '</tr></thead><tbody>' . implode('', $lignes) . '</tbody></table>';

        $avertissement = '';
        if ($record->offresNonEvaluees() !== []) {
            $avertissement = '<div class="mt-2 text-amber-700">⛔ Attribution refusée tant qu\'une offre '
                . 'recevable attend ses notes — voir « Grille d\'évaluation ».</div>';
        }
        if ($record->attributionContredite()) {
            $avertissement .= '<div class="mt-2 text-amber-700">✋ L\'offre retenue n\'est pas la mieux notée : '
                . 'le justificatif enregistré est lu avec la décision.</div>';
        }

        return new HtmlString($tableau . $avertissement);
    }

    public static function actionAttribuer(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('attribuer')
            ->label('Attribuer')
            ->icon('heroicon-o-trophy')->color('success')
            // le seul statut, comme pour les autres actions du cycle : « toutes les
            // offres sont-elles notées ? » est exactement ce que l'écran existe à
            // vérifier — le bouton reste, et le refus nomme les offres qui attendent
            ->visible(fn (Marche $record) => $record->statut === 'en_evaluation'
                && static::canAttribuer())
            ->modalHeading(fn (Marche $record) => "Attribution provisoire — {$record->numero}")
            ->modalDescription('Le classement sort des notes portées sur les offres recevables. '
                . 'L\'attribution est provisoire : elle se lève tant que rien n\'est homologué, '
                . 'et elle fige le montant retenu.')
            ->form(function (Marche $record): array {
                $classement = $record->classementOffres();
                $mieuxNotee = $classement->first();
                $incomplete = $record->offresNonEvaluees();

                return [
                    Forms\Components\Placeholder::make('rappel_classement')
                        ->label('')
                        ->columnSpanFull()
                        ->visible($incomplete !== [])
                        ->content(new HtmlString(
                            '<div class="rounded-lg p-3 text-sm bg-amber-50 dark:bg-amber-900/30 '
                                . 'text-amber-800 dark:text-amber-200 border border-amber-300 '
                                . 'dark:border-amber-700">⛔ Classement incomplet — '
                                . nl2br(e(implode("\n", $incomplete)))
                                . '<br>Le marché ne s\'attribue pas tant qu\'une offre recevable attend ses notes.</div>'
                        )),

                    Forms\Components\Select::make('offre_attribuee_id')
                        ->label('Offre retenue')
                        ->columnSpanFull()
                        ->options($classement->mapWithKeys(fn (Offre $o, int $i) => [
                            $o->id => ($i + 1) . ' · ' . $o->numero . ' — '
                                . ($o->fournisseur?->raison_sociale ?? 'soumissionnaire inconnu')
                                . ' · ' . (float) $o->totalEvalue() . '/' . (float) $o->baremeTotal()
                                . ' · ' . number_format((float) $o->montant_offert, 0, ',', ' ') . ' FCFA'
                                . ($o->estEvaluee() ? '' : ' · ⚠ grille incomplète'),
                        ])->all())
                        ->default($mieuxNotee?->id)
                        ->required()->searchable()->live()
                        ->helperText($classement->isEmpty()
                            ? 'Aucune offre recevable : le classement ne retient que les offres déclarées recevables.'
                            : 'La tête du classement est proposée ; retenir une autre offre exige un justificatif.'),

                    Forms\Components\Textarea::make('attribution_justificatif')
                        ->label('Justificatif de l\'attribution hors classement')
                        ->columnSpanFull()
                        ->rows(3)
                        ->visible(fn (callable $get): bool => $mieuxNotee !== null
                            && (int) $get('offre_attribuee_id') !== (int) $mieuxNotee->id)
                        ->required(fn (callable $get): bool => $mieuxNotee !== null
                            && (int) $get('offre_attribuee_id') !== (int) $mieuxNotee->id)
                        ->placeholder('Ex. : la mieux notée propose 18 mois pour un besoin de 12 ; l\'offre retenue, '
                            . 'notée 48/60, tient le délai et reste sous le montant estimatif.'),
                ];
            })
            ->action(function (Marche $record, array $data) {
                try {
                    $offre = Offre::find((int) ($data['offre_attribuee_id'] ?? 0));
                    if (!$offre) {
                        throw new \Exception("Choisissez l'offre retenue.");
                    }

                    $record->attribuer(auth()->user(), $offre, $data['attribution_justificatif'] ?? null);

                    Notification::make()->title('🏆 Marché attribué')
                        ->body($record->numero . ' — ' . ($offre->fournisseur?->raison_sociale ?? $offre->numero)
                            . ' · ' . number_format((float) $record->montant_attribue, 0, ',', ' ') . ' FCFA')
                        ->success()->send();
                } catch (\Exception $e) {
                    Notification::make()->title('❌ Attribution refusée')
                        ->danger()->body($e->getMessage())->persistent()->send();
                }
            });
    }

    /**
     * Le symétrique : le service défait sa propre attribution, les colonnes se vident
     * et le marché rouvre sa fenêtre de notation. L'offre elle-même n'est pas touchée —
     * ses décisions (étape C) restent, seule la décision du marché se retire.
     */
    public static function actionLeverAttribution(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('lever_attribution')
            ->label('Lever l\'attribution')
            ->icon('heroicon-o-arrow-uturn-left')->color('warning')
            ->visible(fn (Marche $record) => $record->statut === 'attribue'
                && static::canLeverAttribution())
            ->requiresConfirmation()
            ->modalHeading(fn (Marche $record) => "Lever l'attribution — {$record->numero}")
            ->modalDescription('Le marché revient en évaluation : l\'offre retenue, son montant, son auteur et sa '
                . 'date sont effacés des colonnes, la ligne reste au journal. Les notes d\'évaluation redeviennent '
                . 'corrigeables.')
            ->action(function (Marche $record) {
                try {
                    $record->leverAttribution(auth()->user());

                    Notification::make()->title('↩ Attribution levée')
                        ->body($record->numero . ' — évaluation reprise')
                        ->warning()->send();
                } catch (\Exception $e) {
                    Notification::make()->title('❌ Levée refusée')
                        ->danger()->body($e->getMessage())->persistent()->send();
                }
            });
    }

    // =========================================================
    // HOMOLOGATION ET ORDRES DE SERVICE (étape E)
    // =========================================================

    /**
     * Homologué → attribué : la symétrie de la levée d'attribution. Le bouton reste
     * visible sur le statut, même quand des ordres de service ont déjà été émis :
     * c'est à l'écran que le service voit que la décision n'est plus défaisable, pas
     * à un bouton qui a disparu. Le modèle nomme les ordres qui tiennent.
     */
    public static function actionHomologuer(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('homologuer')
            ->label('Homologuer')
            ->icon('heroicon-o-shield-check')->color('success')
            ->visible(fn (Marche $record) => $record->peutEtreHomologue()
                && static::canHomologuer())
            ->requiresConfirmation()
            ->modalHeading(fn (Marche $record) => "Homologuer le marché — {$record->numero}")
            ->modalDescription('L\'homologation rend l\'attribution provisoire définitive : elle n\'ajoute rien '
                . 'au dossier, elle approuve ce qui y est. Elle ne commande pas le service — c\'est l\'ordre '
                . 'de service de démarrage qui le met en exécution, après elle.')
            ->action(function (Marche $record) {
                try {
                    $record->homologuer(auth()->user());

                    Notification::make()->title('🛡️ Marché homologué')
                        ->body($record->numero . ' — ' . Marche::statutLibelle($record->statut))
                        ->success()->send();
                } catch (\Exception $e) {
                    Notification::make()->title('❌ Homologation refusée')
                        ->danger()->body($e->getMessage())->persistent()->send();
                }
            });
    }

    public static function actionLeverHomologation(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('lever_homologation')
            ->label('Lever l\'homologation')
            ->icon('heroicon-o-arrow-uturn-left')->color('warning')
            ->visible(fn (Marche $record) => $record->statut === 'homologue'
                && static::canLeverHomologation())
            ->requiresConfirmation()
            ->modalHeading(fn (Marche $record) => "Lever l'homologation — {$record->numero}")
            ->modalDescription('Le marché revient en attribution provisoire : auteur et date de l\'homologation '
                . 'sont effacés des colonnes, la ligne reste au journal. Possible tant qu\'aucun ordre de '
                . 'service n\'a été émis — passé la commande du service, ce ne sont plus les colonnes du '
                . 'marché qui se discutent.')
            ->action(function (Marche $record) {
                try {
                    $record->leverHomologation(auth()->user());

                    Notification::make()->title('↩ Homologation levée')
                        ->body($record->numero . ' — attribution provisoire de nouveau en cause')
                        ->warning()->send();
                } catch (\Exception $e) {
                    Notification::make()->title('❌ Levée refusée')
                        ->danger()->body($e->getMessage())->persistent()->send();
                }
            });
    }

    /**
     * Émettre un ordre de service — la seule entrée de l'écran vers `ordres_service`.
     *
     * L'action est sur la fiche du marché, pas sur une page de création : un ordre de
     * service n'existe pas sans le marché qu'il commande, et l'émission dépend de son
     * statut à lui. L'écran « Ordres de service » reste en lecture et correction.
     *
     * Le type choisi se lit avant de valider : c'est lui qui décide si le marché avance.
     */
    public static function actionEmettreOrdreService(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('emettre_ordre_service')
            ->label('Émettre un ordre de service')
            ->icon('heroicon-o-document-arrow-up')->color('info')
            ->visible(fn (Marche $record) => $record->peutRecevoirDesOrdres()
                && static::canEmettreOrdreService())
            ->modalHeading(fn (Marche $record) => "Émettre un ordre de service — {$record->numero}")
            ->modalDescription('L\'ordre de service est l\'acte écrit par lequel le service est commandé au '
                . 'titulaire : daté, motivé, versé au dossier. Le marché suit — un ordre de démarrage le '
                . 'passe en cours d\'exécution.')
            ->form(function (Marche $record): array {
                $types = TypeOrdreService::grille();

                return [
                    Forms\Components\Select::make('type_ordre_service_id')
                        ->label('Type d\'ordre de service')
                        ->columnSpanFull()
                        ->options($types->pluck('libelle', 'id')->all())
                        ->default($types->first()?->id)
                        ->required()->live()->searchable()
                        ->helperText('Le référentiel dit, pour chaque type, s\'il met le marché en '
                            . 'exécution — c\'est une règle de l\'EP, pas du code.'),

                    // L'effet sur le marché se lit au moment du choix, pas après un refus :
                    // c'est la consequence de la case `demarre_lexecution`, et l'agent ne
                    // la voit pas dans le paramétrage depuis cet écran.
                    Forms\Components\Placeholder::make('regard')
                        ->label('')
                        ->columnSpanFull()
                        ->content(function (callable $get) use ($record): HtmlString {
                            $type = TypeOrdreService::find((int) $get('type_ordre_service_id'));
                            if (!$type) {
                                return static::boite('bg-gray-50 dark:bg-gray-900/30 text-gray-700 dark:text-gray-300 '
                                    . 'border-gray-300 dark:border-gray-700',
                                    'Choisissez le type : c\'est lui qui décide de l\'effet sur le marché.');
                            }
                            if (!$type->demarre_lexecution) {
                                return static::boite('bg-gray-50 dark:bg-gray-900/30 text-gray-700 dark:text-gray-300 '
                                    . 'border-gray-300 dark:border-gray-700',
                                    '« ' . e($type->libelle) . ' » ne fait pas avancer le marché : il s\'ajoute '
                                        . 'à la chronologie du service.');
                            }
                            if ($record->statut !== 'homologue') {
                                return static::boite('bg-amber-50 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200 '
                                    . 'border-amber-300 dark:border-amber-700',
                                    '⛔ « ' . e($type->libelle) . ' » serait refusé : le marché est déjà « '
                                        . e(Marche::statutLibelle($record->statut)) . '", son service a déjà été '
                                        . 'commandé. Un marché ne reçoit qu\'un seul ordre de démarrage.');
                            }

                            return static::boite('bg-emerald-50 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-200 '
                                . 'border-emerald-300 dark:border-emerald-700',
                                '▶️ Émettre cet ordre met le marché en « ' . e(Marche::statutLibelle('en_execution'))
                                    . '" et ouvre le délai d\'exécution à sa date d\'émission.');
                        }),

                    Forms\Components\DatePicker::make('date_emission')
                        ->label('Date d\'émission')
                        ->required()->default(now()->toDateString())
                        ->maxDate(now())->native()
                        ->helperText('C\'est elle qui ouvre le délai d\'exécution ; elle ne peut pas '
                            . 'précéder l\'homologation.'),

                    Forms\Components\Textarea::make('objet')
                        ->label('Service commandé')
                        ->columnSpanFull()
                        ->required()->rows(2)
                        ->placeholder('Ex. : démarrer les prestations le 15/11/2026, pour un délai de 12 mois '
                            . 'compté de cette date.'),

                    Forms\Components\FileUpload::make('fichier_os')
                        ->label('Ordre de service signé (scan ou photo)')
                        ->columnSpanFull()
                        ->disk('public')->directory('marches/ordres-service')
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                        ->maxSize(10240)->downloadable()->openable(),

                    Forms\Components\Textarea::make('observations')->rows(2)->columnSpanFull(),
                ];
            })
            ->action(function (Marche $record, array $data) {
                $avant = $record->statut;

                try {
                    $type = TypeOrdreService::find((int) ($data['type_ordre_service_id'] ?? 0));
                    if (!$type) {
                        throw new \Exception("Choisissez le type d'ordre de service.");
                    }

                    $os = $record->emettreOrdreService(auth()->user(), $type, [
                        'date_emission' => $data['date_emission'] ?? null,
                        'objet'         => $data['objet'] ?? null,
                        'fichier_os'    => $data['fichier_os'] ?? null,
                        'observations'  => $data['observations'] ?? null,
                    ]);

                    Notification::make()->title('📄 Ordre de service émis')
                        ->body($os->numero . ' — ' . $type->libelle . ' du '
                            . $os->date_emission->format('d/m/Y')
                            . ($record->statut !== $avant
                                ? ' · ' . $record->numero . ' : ' . Marche::statutLibelle($record->statut)
                                : ''))
                        ->success()->send();
                } catch (\Exception $e) {
                    Notification::make()->title('❌ Émission refusée')
                        ->danger()->body($e->getMessage())->persistent()->send();
                }
            });
    }

    /**
     * Dresser la fiche de présence de la séance — la seule entrée vers
     * `fiches_presence`, pour la même raison que l'ordre de service : la fiche
     * constate une séance d'un marché, et c'est le marché qui dit si cette séance a
     * une matière. L'écran « Fiches de présence » reste en lecture et en émargement.
     *
     * Deux choses se lisent avant de valider, parce que c'est là qu'on les cherche :
     * qui va être recopié (la commission épinglée, membre par membre), et si une
     * fiche existe déjà pour le jour demandé — une séance d'un même jour ne se
     * dresse pas deux fois.
     */
    public static function actionDresserFichePresence(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('dresser_fiche_presence')
            ->label('Fiche de présence')
            ->icon('heroicon-o-clipboard-document-check')->color('info')
            // Visible sur toute la fenêtre de séance, y compris quand la commission
            // n'est pas épinglée : l'agent lit alors ce qui manque, la fenêtre ne se
            // referme pas sur un bouton absent.
            ->visible(fn (Marche $record) => $record->peutRecevoirUneFichePresence()
                && static::canDresserFichePresence())
            ->modalHeading(fn (Marche $record) => "Dresser la fiche de présence — {$record->numero}")
            ->modalDescription('La fiche constate la séance de la commission : date, objet, et les membres '
                . 'recopiés du paramétrage. Chacun visera la fiche en signant ; rien n\'est visé à sa place.')
            ->form(function (Marche $record): array {
                $commission = $record->commission;
                $membres    = $commission?->membres ?? collect();

                return [
                    Forms\Components\Placeholder::make('commission_lue')
                        ->label('Commission réunie')
                        ->columnSpanFull()
                        ->content(function () use ($record, $commission, $membres): HtmlString {
                            if (!$commission) {
                                return static::boite('bg-amber-50 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200 '
                                    . 'border-amber-300 dark:border-amber-700',
                                    '⛔ Aucune commission épinglée au marché : la fiche ne peut pas dire qui '
                                        . 'siégeait. Choisissez la commission sur la fiche du marché.');
                            }
                            if ($membres->isEmpty()) {
                                return static::boite('bg-amber-50 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200 '
                                    . 'border-amber-300 dark:border-amber-700',
                                    '⛔ La commission « ' . e($commission->libelle) . ' » n\'a aucun membre au '
                                        . 'paramétrage : sa composition se remplit d\'abord.');
                            }

                            $lignes = $membres->map(fn ($m) => e($m->nom) . ' — '
                                . e(config('marches.titres_membre')[$m->titre] ?? $m->titre)
                                . ($m->telephone ? ' · ' . e($m->telephone) : ''))->implode('<br>');

                            return static::boite('bg-gray-50 dark:bg-gray-900/30 text-gray-700 dark:text-gray-300 '
                                . 'border-gray-300 dark:border-gray-700',
                                '<strong>' . e($commission->libelle) . '</strong> (' . count($membres)
                                    . ' membre(s) recopié(s) sur la fiche)<br>' . $lignes);
                        }),

                    Forms\Components\DatePicker::make('date_seance')
                        ->label('Jour de la séance')
                        ->required()->native()->live()
                        ->maxDate(now())
                        // Le jour de l'ouverture des plis est la séance la plus
                        // fréquente : le proposer n'invente rien, la date est déjà au
                        // dossier. Le code, lui, ne date jamais une réunion.
                        ->default($record->date_ouverture_plis?->toDateString() ?? now()->toDateString())
                        ->helperText('Le jour où la commission s\'est rassemblée — pas celui de la saisie.'),

                    Forms\Components\TextInput::make('lieu')->label('Lieu')
                        ->maxLength(255)->placeholder('Salle des délibérations'),

                    Forms\Components\Textarea::make('objet')
                        ->label('Objet de la séance')->columnSpanFull()
                        ->required()->rows(2)
                        ->placeholder('Ex. : ouverture des plis et jugement des offres du marché '
                            . $record->numero . '.'),

                    // La collision se lit avant la submission : le même jour pour le
                    // même marché est déjà pris, et la fiche qui l'occupe est nommée.
                    Forms\Components\Placeholder::make('regard')
                        ->label('')
                        ->columnSpanFull()
                        ->content(function (callable $get) use ($record): HtmlString {
                            $boitees = [];
                            $date    = $get('date_seance');

                            $fiches = $record->fichesPresence()->withCount([
                                'membres as presents_count' => fn ($q) => $q->where('present', true),
                                'membres as visas_count'    => fn ($q) => $q->where('visa', true),
                            ])->get();

                            if ($date) {
                                $deja = $fiches->first(
                                    fn (FichePresence $f) => $f->date_seance?->toDateString() === \Illuminate\Support\Carbon::parse($date)->toDateString()
                                );
                                if ($deja) {
                                    $boitees[] = ['bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-200 '
                                        . 'border-red-300 dark:border-red-700',
                                        '⛔ La fiche ' . e($deja->numero) . ' constate déjà la séance du '
                                            . \Illuminate\Support\Carbon::parse($date)->format('d/m/Y')
                                            . '. Corrigez-la, ou choisissez un autre jour.'];
                                }
                            }

                            if ($fiches->isEmpty()) {
                                $boitees[] = ['bg-gray-50 dark:bg-gray-900/30 text-gray-700 dark:text-gray-300 '
                                    . 'border-gray-300 dark:border-gray-700',
                                    'Aucune fiche de présence au dossier de ce marché : celle-ci sera la '
                                        . 'première séance constatée.'];
                            } else {
                                foreach ($fiches as $fiche) {
                                    $boitees[] = ['bg-gray-50 dark:bg-gray-900/30 text-gray-700 dark:text-gray-300 '
                                        . 'border-gray-300 dark:border-gray-700',
                                        '📋 ' . e($fiche->numero) . ' du ' . $fiche->date_seance->format('d/m/Y')
                                            . ' — ' . (int) $fiche->presents_count . ' présent(s), '
                                            . (int) $fiche->visas_count . ' visa(s) posé(s) : '
                                            . e($fiche->objet)];
                                }
                            }

                            $contenu = array_map(
                                fn ($b) => static::boite($b[0], $b[1]),
                                $boitees
                            );

                            return new HtmlString(implode('', $contenu));
                        }),

                    Forms\Components\Textarea::make('observations')->label('Observations')
                        ->rows(2)->columnSpanFull(),
                ];
            })
            ->action(function (Marche $record, array $data) {
                try {
                    $fiche = $record->dresserFichePresence(auth()->user(), [
                        'date_seance'  => $data['date_seance'] ?? null,
                        'lieu'         => $data['lieu'] ?? null,
                        'objet'        => $data['objet'] ?? null,
                        'observations' => $data['observations'] ?? null,
                    ]);

                    Notification::make()->title('📋 Fiche de présence dressée')
                        ->body($fiche->numero . ' — séance du ' . $fiche->date_seance->format('d/m/Y')
                            . ', ' . $fiche->membres()->count() . ' membre(s) à émarger')
                        ->success()->send();
                } catch (\Exception $e) {
                    Notification::make()->title('❌ Dressage refusé')
                        ->danger()->body($e->getMessage())->persistent()->send();
                }
            });
    }

    /**
     * La chronologie d'exécution du marché : ce que le service a commandé, dans l'ordre
     * des dates. Elle se relit depuis la fiche parce que c'est là que l'homologation et
     * l'émission se décident — et qu'un marché « en cours d'exécution » sans ordre de
     * service au dossier est une anomalie que seul ce regard montre.
     */
    public static function ordonnancementHtml(Marche $record): HtmlString
    {
        $gris = 'bg-gray-50 dark:bg-gray-900/30 text-gray-700 dark:text-gray-300 '
            . 'border-gray-300 dark:border-gray-700';
        $attente = 'bg-amber-50 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200 '
            . 'border-amber-300 dark:border-amber-700';

        if (in_array($record->statut, ['brouillon', 'valide', 'publie', 'en_evaluation'], true)) {
            return static::boite($gris, 'Le service ne se commande qu\'après l\'attribution et son '
                . 'homologation : le marché est « ' . e(Marche::statutLibelle($record->statut)) . '".');
        }

        if ($record->statut === 'attribue') {
            return static::boite($attente, 'Attribution provisoire, rien n\'est encore commandé. '
                . 'L\'homologation rend la décision définitive, puis l\'ordre de service de démarrage '
                . 'met le service en exécution.');
        }

        $ordres = $record->ordresService;

        if ($ordres->isEmpty()) {
            if ($record->statut === 'en_execution') {
                return static::boite($attente, '⚠️ Le marché est « en cours d\'exécution » sans aucun ordre '
                    . 'de service au dossier : l\'acte qui a ouvert le délai manque. Émettez-le — le '
                    . 'marché n\'a pas à être remis en arrière pour autant.');
            }

            if ($record->statut === 'homologue') {
                return static::boite($attente, 'Aucun ordre de service au dossier. Le service se commande '
                    . 'par écrit : émettez l\'ordre de service de démarrage (action « Émettre un ordre de '
                    . 'service ») pour ouvrir le délai d\'exécution.');
            }

            return static::boite($gris, 'Aucun ordre de service au dossier : ce marché est clos sans '
                . 'avoir été commandé par écrit ici.');
        }

        $lignes = $ordres->map(function (OrdreService $os): string {
            return '<tr' . ($os->demarreLexecution() ? ' style="font-weight:600"' : '') . '>'
                . '<td>' . e($os->date_emission?->format('d/m/Y') ?? '—') . '</td>'
                . '<td>' . e($os->numero) . ($os->demarreLexecution() ? ' ▶️' : '') . '</td>'
                . '<td>' . e($os->type?->libelle ?? 'type retiré du référentiel') . '</td>'
                . '<td>' . e(Str::limit($os->objet, 80)) . '</td>'
                . '<td>' . e($os->emetteur?->name ?? '—') . '</td>'
                . '<td>' . (filled($os->fichier_os) ? '📎' : '') . '</td>'
                . '</tr>';
        })->all();

        $tableau = '<table class="w-full text-left text-sm"><thead><tr>'
            . '<th>Date</th><th>Référence</th><th>Type</th><th>Service commandé</th>'
            . '<th>Émis par</th><th>Pièce</th>'
            . '</tr></thead><tbody>' . implode('', $lignes) . '</tbody></table>';

        // Un marché homologué qui a des lignes mais pas de démarrage : ce n'est pas
        // cet écran qui l'a mis là (l'émission fait avancer le marché), mais la fiche
        // doit le dire plutôt que de laisser croire le service commandé.
        $avertissement = $record->statut === 'homologue'
            ? '<div class="mt-2 text-amber-700">▶️ Rien n\'a ouvert l\'exécution : le marché reste '
                . '« homologué » jusqu\'à l\'ordre de service de démarrage.</div>'
            : '';

        return new HtmlString($tableau . $avertissement);
    }

    /** Une boîte de lecture commune aux regards du cycle (classement, ordonnancement). */
    private static function boite(string $classes, string $contenu): HtmlString
    {
        return new HtmlString('<div class="rounded-lg p-3 text-sm border ' . $classes . '">' . $contenu . '</div>');
    }

    // =========================================================
    // NAVIGATION
    // =========================================================

    public static function getNavigationBadge(): ?string
    {
        return static::canViewAny()
            ? (string) Marche::query()->enCours()->count()
            : null;
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMarches::route('/'),
            'create' => Pages\CreateMarche::route('/create'),
            'edit'   => Pages\EditMarche::route('/{record}/edit'),
        ];
    }
}
