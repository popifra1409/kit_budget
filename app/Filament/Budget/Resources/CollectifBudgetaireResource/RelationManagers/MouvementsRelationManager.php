<?php

namespace App\Filament\Budget\Resources\CollectifBudgetaireResource\RelationManagers;

use App\Models\LigneBudgetaire;
use App\Models\LignePrevisionRecette;
use App\Models\NomenclatureBudgetaire;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MouvementsRelationManager extends RelationManager
{
    protected static string $relationship = 'mouvements';
    protected static ?string $recordTitleAttribute = 'id';

    /**
     * Niveau hiérarchiques OHADA et parents admissibles pour chacun.
     * Doit rester aligné sur les règles du saving() de NomenclatureBudgetaire.
     */
    private const PARENTS_AUTORISES = [
        'classe'      => ['chapitre'],
        'chapitre'    => [],
        'article'     => ['chapitre'],
        'paragraphe'  => ['article', 'chapitre'],
        'compte'      => ['chapitre', 'classe'],
        'sous_compte' => ['classe', 'compte'],
        'ligne'       => ['compte', 'sous_compte', 'paragraphe'],
    ];

    private const NIVEAUX = [
        'chapitre'    => 'Chapitre',
        'article'     => 'Article',
        'paragraphe'  => 'Paragraphe',
        'classe'      => 'Classe',
        'compte'      => 'Compte',
        'sous_compte' => 'Sous-compte',
        'ligne'       => 'Ligne',
    ];

    /**
     * Toutes les nomenclatures d'un type doivent être atteignables : Choices.js
     * tronque l'affichage à optionsLimit (50 par défaut), ce qui masquait le reste.
     */
    private const OPTIONS_LIMIT = 500;

    /**
     * Classe comptable déduite du code saisi (1-8), sinon la classe par défaut du type.
     * Une dépense n'est pas limitée à la classe 6 : immobilisations (2), stocks (3)...
     */
    private function devinerClasse(?string $code, ?string $type): string
    {
        $premiere = $code !== null && $code !== '' ? substr($code, 0, 1) : '';

        if (in_array($premiere, ['1', '2', '3', '4', '5', '6', '7', '8'], true)) {
            return $premiere;
        }

        return $type === 'recette' ? '7' : '6';
    }

    /**
     * Libellé de classe OHADA, pour l'aide à la saisie du code.
     */
    private function libelleClasse(string $classe): string
    {
        return match ($classe) {
            '1'     => 'Classe 1 — Comptes de capitaux (recette ou dépense selon le contexte)',
            '2'     => 'Classe 2 — Actif immobilisé (dépenses d\'investissement)',
            '3'     => 'Classe 3 — Comptes de stocks',
            '4'     => 'Classe 4 — Comptes de tiers',
            '5'     => 'Classe 5 — Comptes de trésorerie',
            '6'     => 'Classe 6 — Comptes de charges (dépenses)',
            '7'     => 'Classe 7 — Comptes de produits (recettes)',
            '8'     => 'Classe 8 — Comptes de résultats',
            default => 'Classe non reconnue — vérifiez le code saisi',
        };
    }

    /**
     * Lignes déjà existantes pour le type choisi, dans le budget / la prévision
     * actif de l'exercice du collectif courant.
     */
    private function nomenclaturesDejaInscrites(?string $type): array
    {
        $exercice = $this->getOwnerRecord()->exercice;

        if ($type === 'recette') {
            $previsionId = $exercice?->previsionRecettes()->where('actif', true)->value('id');

            return $previsionId
                ? LignePrevisionRecette::where('prevision_recette_id', $previsionId)
                    ->pluck('nomenclature_id')->all()
                : [];
        }

        $budgetId = $exercice?->budgets()->where('actif', true)->value('id');

        return $budgetId
            ? LigneBudgetaire::where('budget_id', $budgetId)
                ->pluck('nomenclature_id')->all()
            : [];
    }

    /**
     * Budget / prévision actif de l'exercice du collectif, cible des nouvelles lignes.
     */
    private function cible(?string $type): ?object
    {
        $exercice = $this->getOwnerRecord()->exercice;

        return $type === 'recette'
            ? $exercice?->previsionRecettes()->where('actif', true)->first()
            : $exercice?->budgets()->where('actif', true)->first();
    }

    /**
     * Nomenclature encore en vie portant ce code dans l'exercice du collectif.
     *
     * Le code est la clé métier : deux lignes « 731207 » sont un doublon même si
     * elles pointent sur deux enregistrements de nomenclature différents.
     */
    private function nomenclatureAvecCode(?string $code, ?string $type): ?NomenclatureBudgetaire
    {
        return NomenclatureBudgetaire::doublonDeCode(
            $this->getOwnerRecord()->exercice_id ? (int) $this->getOwnerRecord()->exercice_id : null,
            $code ? trim((string) $code) : null,
            $type
        );
    }

    /**
     * Ligne déjà inscrite pour ce code dans le budget / la prévision de l'exercice.
     */
    private function ligneAvecCode(?string $code, ?string $type): ?object
    {
        $cible = $this->cible($type);
        $code  = $code ? trim((string) $code) : null;
        if (!$cible || !$code) return null;

        return $type === 'recette'
            ? LignePrevisionRecette::doublonDeCode((int) $cible->id, $code)
            : LigneBudgetaire::doublonDeCode((int) $cible->id, $code);
    }

    /**
     * Message de refus de doublon, avec l'endroit où retrouver la ligne existante.
     */
    private function messageDoublon(?string $code, ?string $type, ?object $ligne): string
    {
        $libelle = $type === 'recette' ? 'prévision de recettes' : 'budget';

        if (!$ligne) {
            return "Le code « {$code} » existe déjà dans la nomenclature de cet exercice"
                . " — choisissez l'action « 📋 Ajouter une nomenclature existante sans ligne budgétaire »"
                . " plutôt que d'en créer une seconde fois.";
        }

        return "Le code « {$code} » a déjà une ligne dans ce {$libelle}"
            . ' (ligne n°' . $ligne->id . ', montant '
            . number_format((float) ($type === 'recette' ? $ligne->montant_rectifie : $ligne->budget_rectifie), 0, ',', ' ')
            . " FCFA). Pour le modifier, utilisez l'action « ✏️ Modifier une ligne existante »"
            . ' — deux lignes au même code fausseraient le budget.';
    }

    /**
     * Contrôles anti-doublon exécutés avant la moindre écriture du formulaire.
     */
    private function verifierAbsenceDeDoublon(array $data): void
    {
        $type = $data['type'] ?? null;
        if (!in_array($type, ['depense', 'recette'])) return;

        $mode = $data['mode_action'] ?? 'modifier';

        if ($mode === 'creer_nouvelle') {
            $code = trim((string) ($data['nouveau_code'] ?? ''));
            if ($code === '') return;

            $existante = $this->nomenclatureAvecCode($code, $type);
            if ($existante) {
                throw new \Exception(
                    $this->messageDoublon($code, $type, $this->ligneAvecCode($code, $type))
                    . ' Nomenclature existante : n°' . $existante->id . ' — ' . $existante->libelle . '.'
                );
            }
        }

        if ($mode === 'ajouter_existante') {
            $nomenclature = NomenclatureBudgetaire::find($data['nomenclature_existante_id'] ?? null);
            if (!$nomenclature) return;

            $ligne = $this->ligneAvecCode($nomenclature->code, $type);
            // La ligne portée par cette même nomenclature est filtrée par le sélecteur ;
            // on ne refuse que si c'est un ENREGISTREMENT différent au même code.
            if ($ligne && (int) $ligne->nomenclature_id !== (int) $nomenclature->id) {
                throw new \Exception($this->messageDoublon($nomenclature->code, $type, $ligne));
            }
        }
    }

    /**
     * ✅ Par défaut, Filament passe cette RelationManager en lecture seule
     *    dès que CollectifBudgetaireResource::canEdit() renvoie false pour
     *    le collectif parent (ce qui est le cas dès qu'il est 'adopte').
     *    On désactive ce comportement automatique : c'est chaque action
     *    individuelle (Ajouter, Annuler ce mouvement, Corriger...) qui gère
     *    finement sa propre visibilité selon le statut du collectif.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form->schema([

            // ── 1. Type de mouvement ─────────────────────────────
            Forms\Components\Select::make('type')
                ->label('Type de mouvement')
                ->options([
                    'depense'  => '💰 Dépense',
                    'recette'  => '📥 Recette',
                    'virement' => '↔️ Virement budgétaire',
                ])
                ->required()
                ->live()
                ->afterStateUpdated(function ($set) {
                    $set('mode_action', null);
                    $set('ligne_depense_id', null);
                    $set('ligne_recette_id', null);
                    $set('ligne_source_id', null);
                    $set('ligne_destination_id', null);
                    $set('nomenclature_existante_id', null);
                    $set('nouveau_code', null);
                    $set('nouveau_libelle', null);
                    $set('nouveau_parent_id', null);
                    $set('montant_modification', null);
                }),

            // ── 2. Mode d'action (dépense/recette uniquement) ────
            Forms\Components\Select::make('mode_action')
                ->label('Action souhaitée')
                ->options([
                    'modifier'         => '✏️ Modifier une ligne existante (augmentation / réduction)',
                    'ajouter_existante' => '📋 Ajouter une nomenclature existante sans ligne budgétaire',
                    'creer_nouvelle'   => '🆕 Créer une nouvelle nomenclature et la provisionner',
                ])
                ->required()
                ->live()
                ->visible(fn($get) => in_array($get('type'), ['depense', 'recette']))
                ->afterStateUpdated(function ($set) {
                    $set('ligne_depense_id', null);
                    $set('ligne_recette_id', null);
                    $set('nomenclature_existante_id', null);
                    $set('nouveau_code', null);
                    $set('nouveau_libelle', null);
                    $set('montant_modification', null);
                }),

            // ════════════════════════════════════════════════════
            // CAS 1 : Modifier ligne existante
            // ════════════════════════════════════════════════════
            Forms\Components\Section::make('Ligne à modifier')
                ->schema([
                    Forms\Components\Select::make('ligne_depense_id')
                        ->label('Ligne de dépense')
                        ->options(fn() => LigneBudgetaire::with('nomenclature')
                            ->get()
                            ->mapWithKeys(fn($l) => [
                                $l->id => ($l->nomenclature?->code ?? '?')
                                    . ' — ' . ($l->nomenclature?->libelle ?? '?')
                                    . ' | Disponible : '
                                    . number_format($l->disponible_engagement ?? 0, 0, ',', ' ')
                                    . ' FCFA'
                            ]))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->visible(fn($get) => $get('type') === 'depense'),

                    Forms\Components\Select::make('ligne_recette_id')
                        ->label('Ligne de recette')
                        ->options(fn() => LignePrevisionRecette::with('nomenclature')
                            ->get()
                            ->mapWithKeys(fn($l) => [
                                $l->id => ($l->nomenclature?->code ?? '?')
                                    . ' — ' . ($l->nomenclature?->libelle ?? '?')
                            ]))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->visible(fn($get) => $get('type') === 'recette'),

                    Forms\Components\TextInput::make('montant_modification')
                        ->label('Montant de la modification (FCFA)')
                        ->numeric()
                        ->prefix('FCFA')
                        ->required()
                        ->helperText('✅ Positif = augmentation | ❌ Négatif = réduction (ex: -500000)'),
                ])
                ->compact()
                ->visible(fn($get) => $get('mode_action') === 'modifier'
                    && in_array($get('type'), ['depense', 'recette'])),

            // ════════════════════════════════════════════════════
            // CAS 2 : Ajouter nomenclature existante sans ligne
            // ════════════════════════════════════════════════════
            Forms\Components\Section::make('Nomenclature à ajouter au budget')
                ->schema([
                    Forms\Components\Select::make('nomenclature_existante_id')
                        ->label('Nomenclature budgétaire')
                        ->options(function ($get) {
                            // Le type budgétaire (depense/recette) est la vraie clé de tri :
                            // une dépense peut porter une autre classe que 6 (2, 3, 5...).
                            return NomenclatureBudgetaire::query()
                                ->type($get('type'))
                                ->actives()
                                ->whereNotIn('id', $this->nomenclaturesDejaInscrites($get('type')))
                                ->orderBy('code')
                                ->get()
                                ->mapWithKeys(fn($n) => [
                                    $n->id => "{$n->code} — {$n->libelle}"
                                ]);
                        })
                        ->searchable()
                        ->preload()
                        ->optionsLimit(self::OPTIONS_LIMIT)
                        ->required()
                        ->helperText('Nomenclatures de ce type déjà créées mais sans ligne dans le budget actuel'),

                    Forms\Components\TextInput::make('montant_modification')
                        ->label('Montant à provisionner (FCFA)')
                        ->numeric()
                        ->prefix('FCFA')
                        ->minValue(0)
                        ->required()
                        ->helperText('Montant initial inscrit au budget rectifié'),
                ])
                ->compact()
                ->visible(fn($get) => $get('mode_action') === 'ajouter_existante'
                    && in_array($get('type'), ['depense', 'recette'])),

            // ════════════════════════════════════════════════════
            // CAS 3 : Créer nouvelle nomenclature + ligne
            // ════════════════════════════════════════════════════
            Forms\Components\Section::make('Nouvelle nomenclature budgétaire')
                ->description('Cette nomenclature sera créée et provisionnée dans le budget rectifié')
                ->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('nouveau_code')
                            ->label('Code OHADA')
                            ->required()
                            ->maxLength(20)
                            ->live(onBlur: true)
                            ->placeholder(fn($get) => $get('type') === 'recette' ? 'Ex: 712001' : 'Ex: 621101')
                            ->helperText(fn($get) => $this->libelleClasse(
                                $this->devinerClasse($get('nouveau_code'), $get('type'))
                            )),

                        Forms\Components\Select::make('nouveau_niveau')
                            ->label('Niveau')
                            ->options(self::NIVEAUX)
                            ->default('paragraphe')
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn($set) => $set('nouveau_parent_id', null)),

                        Forms\Components\Select::make('nouveau_parent_id')
                            ->label('Rattacher à (parent)')
                            ->placeholder(fn($get) => $get('nouveau_niveau') === 'chapitre'
                                ? 'Un chapitre ne peut pas avoir de parent'
                                : 'Aucun — niveau chapitre')
                            ->options(function ($get) {
                                $niveau = $get('nouveau_niveau') ?: 'paragraphe';
                                $niveauxParents = self::PARENTS_AUTORISES[$niveau] ?? array_keys(self::NIVEAUX);

                                if ($niveauxParents === []) {
                                    return [];
                                }

                                return NomenclatureBudgetaire::query()
                                    ->type($get('type'))
                                    ->actives()
                                    ->whereIn('niveau', $niveauxParents)
                                    ->orderBy('code')
                                    ->get()
                                    ->mapWithKeys(fn($n) => [
                                        $n->id => "{$n->code} — {$n->libelle} ({$n->niveau})"
                                    ]);
                            })
                            ->searchable()
                            ->preload()
                            ->optionsLimit(self::OPTIONS_LIMIT)
                            ->disabled(fn($get) => $get('nouveau_niveau') === 'chapitre'),
                    ]),

                    Forms\Components\TextInput::make('nouveau_libelle')
                        ->label('Libellé de la nomenclature')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull()
                        ->placeholder('Ex: Indemnités de déplacement sur le terrain'),

                    Forms\Components\TextInput::make('montant_modification')
                        ->label('Montant à provisionner dans le budget rectifié (FCFA)')
                        ->numeric()
                        ->prefix('FCFA')
                        ->minValue(0)
                        ->required(),
                ])
                ->compact()
                ->visible(fn($get) => $get('mode_action') === 'creer_nouvelle'
                    && in_array($get('type'), ['depense', 'recette'])),

            // ════════════════════════════════════════════════════
            // VIREMENT budgétaire (source → destination)
            // ════════════════════════════════════════════════════
            Forms\Components\Section::make('Virement budgétaire')
                ->description('Le montant est transféré de la ligne source vers la ligne destination')
                ->schema([
                    Forms\Components\Select::make('ligne_source_id')
                        ->label('Ligne source (débite)')
                        ->options(fn() => LigneBudgetaire::with('nomenclature')
                            ->get()
                            ->mapWithKeys(fn($l) => [
                                $l->id => ($l->nomenclature?->code ?? '?')
                                    . ' — ' . ($l->nomenclature?->libelle ?? '?')
                                    . ' | Dispo: '
                                    . number_format($l->disponible_engagement ?? 0, 0, ',', ' ')
                                    . ' FCFA'
                            ]))
                        ->searchable()
                        ->required(),

                    Forms\Components\Select::make('ligne_destination_id')
                        ->label('Ligne destination (créditée)')
                        ->options(fn() => LigneBudgetaire::with('nomenclature')
                            ->get()
                            ->mapWithKeys(fn($l) => [
                                $l->id => ($l->nomenclature?->code ?? '?')
                                    . ' — ' . ($l->nomenclature?->libelle ?? '?')
                            ]))
                        ->searchable()
                        ->required(),

                    Forms\Components\TextInput::make('montant_modification')
                        ->label('Montant du virement (FCFA)')
                        ->numeric()
                        ->prefix('FCFA')
                        ->minValue(1)
                        ->required(),
                ])
                ->compact()
                ->visible(fn($get) => $get('type') === 'virement'),

            // ── Motif (toujours visible) ─────────────────────────
            Forms\Components\Textarea::make('motif')
                ->label('Motif / Justification')
                ->required()
                ->rows(2)
                ->maxLength(500)
                ->placeholder('Justification budgétaire de ce mouvement...')
                ->columnSpanFull()
                ->visible(fn($get) => $get('type') !== null),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut mvt.')
                    ->colors([
                        'success' => 'actif',
                        'danger'  => 'annule',
                    ])
                    ->formatStateUsing(fn($state) => $state === 'annule' ? '❌ Annulé' : '✅ Actif')
                    ->tooltip(fn($record) => $record->statut === 'annule'
                        ? "Annulé le " . $record->date_annulation?->format('d/m/Y H:i') . " par " . ($record->annulateur?->name ?? '—')
                        : null),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Type')
                    ->colors([
                        'warning' => 'depense',
                        'info'    => 'recette',
                        'primary' => 'virement',
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'depense'  => '💰 Dépense',
                        'recette'  => '📥 Recette',
                        'virement' => '↔️ Virement',
                        default    => $state,
                    }),

                Tables\Columns\TextColumn::make('ligne_concernee')
                    ->label('Ligne budgétaire')
                    ->getStateUsing(function ($record) {
                        if (!$record) return '—';
                        return match ($record->type) {
                            'depense' => $record->nouvelleLigneDepense?->nomenclature?->code
                                ? '🆕 ' . $record->nouvelleLigneDepense->nomenclature->code
                                . ' — ' . $record->nouvelleLigneDepense->nomenclature->libelle
                                : ($record->ligneDepense?->nomenclature?->code
                                    . ' — ' . ($record->ligneDepense?->nomenclature?->libelle ?? '—')),
                            'recette' => $record->nouvelleLigneRecette?->nomenclature?->code
                                ? '🆕 ' . $record->nouvelleLigneRecette->nomenclature->code
                                . ' — ' . $record->nouvelleLigneRecette->nomenclature->libelle
                                : ($record->ligneRecette?->nomenclature?->code
                                    . ' — ' . ($record->ligneRecette?->nomenclature?->libelle ?? '—')),
                            'virement' => (function () use ($record) {
                                $source = $record->ligneSource?->nomenclature?->code;
                                $dest   = $record->ligneDestination?->nomenclature?->code;
                                if ((!$source || !$dest) && $record->virement_budgetaire_id) {
                                    $v = \App\Models\VirementBudgetaire::with([
                                        'ligneSource.nomenclature',
                                        'ligneDestination.nomenclature'
                                    ])->find($record->virement_budgetaire_id);
                                    $source = $source ?? ($v?->ligneSource?->nomenclature?->code ?? '?');
                                    $dest   = $dest   ?? ($v?->ligneDestination?->nomenclature?->code ?? '?');
                                }
                                return '↓ ' . ($source ?? '?') . ' → ' . ($dest ?? '?');
                            })(),
                            default => '—',
                        };
                    })
                    ->wrap()
                    ->searchable(false),

                Tables\Columns\TextColumn::make('montant_modification')
                    ->label('Montant')
                    ->money('XOF', true)
                    ->color(fn($record) => $record && $record->montant_modification < 0 ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('motif')
                    ->label('Motif')
                    ->limit(50)
                    ->wrap(),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Ajouter un mouvement')
                    ->mutateFormDataUsing(function (array $data): array {
                        $collectif = $this->getOwnerRecord();
                        $exercice  = $collectif->exercice;
                        $type      = $data['type'];
                        $mode      = $data['mode_action'] ?? 'modifier';

                        // ✅ Anti-doublon AVANT toute écriture : ni nomenclature ni ligne
                        //    ne doivent être créées quand le code existe déjà dans l'exercice.
                        $this->verifierAbsenceDeDoublon($data);

                        // ── CAS 2 : Nomenclature existante sans ligne ──────
                        if ($mode === 'ajouter_existante' && in_array($type, ['depense', 'recette'])) {
                            if ($type === 'depense') {
                                $budget = $exercice->budgets()->where('actif', true)->first();
                                if (!$budget) throw new \Exception('Aucun budget actif trouvé.');

                                $ligne = LigneBudgetaire::create([
                                    'budget_id'              => $budget->id,
                                    'nomenclature_id'        => $data['nomenclature_existante_id'],
                                    'budget_initial'         => $data['montant_modification'] ?? 0,
                                    'budget_rectifie'        => $data['montant_modification'] ?? 0,
                                    'est_issue_collectif'    => true,
                                    'collectif_creation_id'  => $collectif->id,
                                ]);
                                $data['nouvelle_ligne_depense_id'] = $ligne->id;
                                $data['ligne_depense_id']          = null;
                            } else {
                                $prevision = $exercice->previsionRecettes()->where('actif', true)->first();
                                if (!$prevision) throw new \Exception('Aucune prévision de recettes active.');

                                $ligne = LignePrevisionRecette::create([
                                    'prevision_recette_id'   => $prevision->id,
                                    'nomenclature_id'        => $data['nomenclature_existante_id'],
                                    'montant_prevu_initial'  => $data['montant_modification'] ?? 0,
                                    'montant_rectifie'       => $data['montant_modification'] ?? 0,
                                    'est_issue_collectif'    => true,
                                    'collectif_creation_id'  => $collectif->id,
                                ]);
                                $data['nouvelle_ligne_recette_id'] = $ligne->id;
                                $data['ligne_recette_id']          = null;
                            }
                        }

                        // ── CAS 3 : Nouvelle nomenclature + ligne ──────────
                        elseif ($mode === 'creer_nouvelle' && in_array($type, ['depense', 'recette'])) {
                            // Créer la nomenclature
                            $nomenclature = NomenclatureBudgetaire::create([
                                'code'      => $data['nouveau_code'],
                                'libelle'   => $data['nouveau_libelle'],
                                'classe'    => $this->devinerClasse($data['nouveau_code'] ?? null, $type),
                                'type'      => $type,
                                'niveau'    => $data['nouveau_niveau'] ?? 'paragraphe',
                                'parent_id' => $data['nouveau_parent_id'] ?? null,
                                'actif'     => true,
                            ]);

                            // ✅ Trace du lien : sans elle, la nomenclature survivait seule
                            //    au mouvement supprimé et son code réapparaissait en doublon.
                            $data['nomenclature_creee_id'] = $nomenclature->id;

                            if ($type === 'depense') {
                                $budget = $exercice->budgets()->where('actif', true)->first();
                                if (!$budget) throw new \Exception('Aucun budget actif trouvé.');

                                $ligne = LigneBudgetaire::create([
                                    'budget_id'              => $budget->id,
                                    'nomenclature_id'        => $nomenclature->id,
                                    'budget_initial'         => $data['montant_modification'] ?? 0,
                                    'budget_rectifie'        => $data['montant_modification'] ?? 0,
                                    'est_issue_collectif'    => true,
                                    'collectif_creation_id'  => $collectif->id,
                                ]);
                                $data['nouvelle_ligne_depense_id'] = $ligne->id;
                            } else {
                                $prevision = $exercice->previsionRecettes()->where('actif', true)->first();
                                if (!$prevision) throw new \Exception('Aucune prévision de recettes active.');

                                $ligne = LignePrevisionRecette::create([
                                    'prevision_recette_id'   => $prevision->id,
                                    'nomenclature_id'        => $nomenclature->id,
                                    'montant_prevu_initial'  => $data['montant_modification'] ?? 0,
                                    'montant_rectifie'       => $data['montant_modification'] ?? 0,
                                    'est_issue_collectif'    => true,
                                    'collectif_creation_id'  => $collectif->id,
                                ]);
                                $data['nouvelle_ligne_recette_id'] = $ligne->id;
                            }
                        }

                        // ✅ Créer VirementBudgetaire en statut en_attente si type virement
                        if (
                            $data['type'] === 'virement'
                            && !empty($data['ligne_source_id'])
                            && !empty($data['ligne_destination_id'])
                        ) {
                            $ligneSource = LigneBudgetaire::find($data['ligne_source_id']);
                            $virement = \App\Models\VirementBudgetaire::create([
                                'exercice_id'          => $collectif->exercice_id,
                                'budget_id'            => $ligneSource?->budget_id,
                                'ligne_source_id'      => $data['ligne_source_id'],
                                'ligne_destination_id' => $data['ligne_destination_id'],
                                'montant'              => $data['montant_modification'],
                                'date_virement'        => now(),
                                'motif'                => '[Collectif ' . $collectif->numero . '] ' . ($data['motif'] ?? ''),
                                'reference_decision'   => $collectif->numero,
                                'statut'               => 'en_attente',
                                // Sans cette marque, le virement passait pour un
                                // mouvement de gestion : actions manuelles possibles
                                // et comptabilisé dans le plafond des virements.
                                // Symétrique de VirementBudgetaire::creerDepuisCollectif().
                                'origine'              => 'collectif',
                            ]);
                            $data['virement_budgetaire_id'] = $virement->id;
                        }

                        // Nettoyer les champs temporaires
                        unset(
                            $data['mode_action'],
                            $data['nomenclature_existante_id'],
                            $data['nouveau_code'],
                            $data['nouveau_libelle'],
                            $data['nouveau_niveau'],
                            $data['nouveau_parent_id'],
                        );

                        return $data;
                    })
                    ->visible(fn() => $this->getOwnerRecord()->statut !== 'annule')
                    ->after(function ($record) {
                        $collectif = $this->getOwnerRecord();

                        // ✅ Un collectif 'projet' sera appliqué globalement à son
                        //    adoption (comportement inchangé). Mais s'il est déjà
                        //    'adopte', ce nouveau mouvement doit être appliqué
                        //    immédiatement — sinon il resterait sans effet réel
                        //    sur le budget tant que personne ne le déclenche.
                        if ($collectif->statut === 'adopte') {
                            try {
                                $record->appliquer(auth()->user());

                                Notification::make()
                                    ->title('✅ Mouvement ajouté et appliqué immédiatement')
                                    ->body('Ce collectif étant déjà adopté, le mouvement a été appliqué directement sur le budget.')
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                // ✅ L'application a échoué (ex: disponible insuffisant) —
                                //    marquer le mouvement comme non-effectif plutôt que de
                                //    le laisser 'actif' par défaut sans effet réel.
                                $record->updateQuietly([
                                    'statut'          => 'annule',
                                    'date_annulation' => now(),
                                    'annule_par'      => auth()->id(),
                                ]);

                                Notification::make()
                                    ->title('⚠️ Mouvement créé mais NON appliqué')
                                    ->body('Erreur : ' . $e->getMessage()
                                        . ' — Le mouvement existe mais n\'a aucun effet sur le budget. '
                                        . 'Utilisez "Corriger" pour resaisir un montant valide et l\'appliquer.')
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }
                        }
                    }),
            ])
            ->actions([
                // ✅ Action Voir détail mouvement
                Tables\Actions\Action::make('voir')
                    ->label('Détail')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn($record) => 'Détail du mouvement — ' . ucfirst($record->type))
                    ->modalContent(function ($record) {
                        $ligneSource      = null;
                        $ligneDestination = null;
                        $virement         = null;

                        if ($record->type === 'virement' && $record->virement_budgetaire_id) {
                            $virement = \App\Models\VirementBudgetaire::with([
                                'ligneSource.nomenclature',
                                'ligneDestination.nomenclature',
                            ])->find($record->virement_budgetaire_id);
                            $ligneSource      = $virement?->ligneSource;
                            $ligneDestination = $virement?->ligneDestination;
                        }

                        $ligneDepense = $record->ligneDepense?->load('nomenclature')
                            ?? $record->nouvelleLigneDepense?->load('nomenclature');
                        $ligneRecette = $record->ligneRecette?->load('nomenclature')
                            ?? $record->nouvelleLigneRecette?->load('nomenclature');

                        return view('filament.modals.detail-mouvement-collectif', compact(
                            'record',
                            'virement',
                            'ligneSource',
                            'ligneDestination',
                            'ligneDepense',
                            'ligneRecette'
                        ));
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer'),

                Tables\Actions\EditAction::make()
                    ->visible(fn() => $this->getOwnerRecord()->statut === 'projet')
                    ->mutateRecordDataUsing(function (array $data, $record): array {
                        // Le type 'virement' ne stocke pas ligne_source_id / ligne_destination_id
                        // sur le modèle Mouvement — ces infos vivent dans VirementBudgetaire.
                        // On les recharge ici pour que le formulaire d'édition soit pré-rempli.
                        if ($record->type === 'virement' && $record->virement_budgetaire_id) {
                            $virement = \App\Models\VirementBudgetaire::find($record->virement_budgetaire_id);

                            if ($virement) {
                                $data['ligne_source_id']      = $virement->ligne_source_id;
                                $data['ligne_destination_id'] = $virement->ligne_destination_id;
                                $data['montant_modification']  = $virement->montant;
                                // Le motif stocké côté VirementBudgetaire est préfixé par
                                // "[Collectif NUMERO] " — on l'enlève pour l'édition
                                $data['motif'] = preg_replace(
                                    '/^\[Collectif [^\]]+\]\s*/',
                                    '',
                                    $virement->motif ?? ($data['motif'] ?? '')
                                );
                            }
                        }

                        // Idem pour dépense/recette créées via 'ajouter_existante' ou 'creer_nouvelle' :
                        // le formulaire attend mode_action + ligne_depense_id/ligne_recette_id,
                        // mais seuls nouvelle_ligne_depense_id / nouvelle_ligne_recette_id sont stockés.
                        if ($record->type === 'depense' && $record->nouvelle_ligne_depense_id) {
                            $data['mode_action']     = 'modifier';
                            $data['ligne_depense_id'] = $record->nouvelle_ligne_depense_id;
                        }

                        if ($record->type === 'recette' && $record->nouvelle_ligne_recette_id) {
                            $data['mode_action']     = 'modifier';
                            $data['ligne_recette_id'] = $record->nouvelle_ligne_recette_id;
                        }

                        return $data;
                    }),

                // ✅ Annuler CE mouvement précis (le collectif reste adopté,
                //    les autres mouvements ne sont pas affectés)
                Tables\Actions\Action::make('annulerMouvement')
                    ->label('Annuler ce mouvement')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(
                        fn($record) =>
                        $record->statut !== 'annule'
                            && $record->collectif?->statut === 'adopte'
                            && auth()->user()?->can('update_collectif_budgetaire')
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Annuler ce mouvement')
                    ->modalDescription('Seul ce mouvement sera annulé — son effet sur le budget sera inversé. Les autres mouvements de ce collectif ne sont pas affectés. Impossible si des engagements ont déjà été pris sur la ligne concernée.')
                    ->action(function ($record) {
                        try {
                            $record->annuler(auth()->user());
                            Notification::make()
                                ->title('✅ Mouvement annulé')
                                ->body('Vous pouvez maintenant le corriger via le bouton "Corriger".')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('❌ Annulation impossible')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),

                // ✅ Corriger un mouvement déjà annulé — resaisir le montant
                //    puis le réappliquer automatiquement.
                Tables\Actions\Action::make('corrigerMouvement')
                    ->label('Corriger')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->visible(
                        fn($record) =>
                        $record->statut === 'annule'
                            && $record->collectif?->statut === 'adopte'
                            && auth()->user()?->can('update_collectif_budgetaire')
                    )
                    ->form([
                        Forms\Components\TextInput::make('nouveau_montant')
                            ->label('Nouveau montant')
                            ->numeric()
                            ->minValue(0.01)
                            ->required()
                            ->default(fn($record) => $record->montant_modification),

                        Forms\Components\Textarea::make('motif_correction')
                            ->label('Motif de la correction')
                            ->rows(2)
                            ->placeholder('Ex : erreur de saisie initiale, montant corrigé suite à vérification...'),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Corriger ce mouvement')
                    ->modalDescription('Le nouveau montant sera appliqué immédiatement sur la ligne concernée.')
                    ->action(function ($record, array $data) {
                        try {
                            $record->corriger(
                                (float) $data['nouveau_montant'],
                                auth()->user(),
                                $data['motif_correction'] ?? null
                            );
                            Notification::make()
                                ->title('✅ Mouvement corrigé et réappliqué')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('❌ Correction impossible')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn() => $this->getOwnerRecord()->statut === 'projet')
                    ->requiresConfirmation()
                    ->modalHeading('Supprimer ce mouvement')
                    ->modalDescription(fn($record) => $record->resumeDeCeQuIlCreait()
                        ? 'Seront retirés avec lui : ' . $record->resumeDeCeQuIlCreait()
                            . '. Impossible si ces éléments portent déjà des opérations.'
                        : 'Ce mouvement n\'a créé aucun élément en base.')
                    ->action(function ($record) {
                        try {
                            $avant  = $record->resumeDeCeQuIlCreait();
                            $record->delete();

                            Notification::make()
                                ->success()
                                ->title('Mouvement supprimé')
                                ->body($avant
                                    ? 'Retiré avec : ' . $avant . '. Vous pouvez le recréer, plus aucun doublon.'
                                    : 'Aucune ligne créée par ce mouvement.')
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->danger()
                                ->persistent()
                                ->title('❌ Suppression impossible')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn() => $this->getOwnerRecord()->statut === 'projet')
                        ->requiresConfirmation()
                        ->action(function (\Illuminate\Support\Collection $records) {
                            $part         = 0;
                            $protegees    = [];

                            // ✅ Un mouvement verrouillé ne doit pas bloquer les autres.
                            foreach ($records as $record) {
                                try {
                                    $record->delete();
                                    $part++;
                                } catch (\Throwable $e) {
                                    $protegees[] = 'Mouvement n°' . $record->id . ' : ' . $e->getMessage();
                                }
                            }

                            if ($part > 0) {
                                Notification::make()
                                    ->success()
                                    ->title($part . ' mouvement(s) supprimé(s)')
                                    ->body('Les lignes, nomenclatures et virements en attente créés par ces mouvements ont été retirés.')
                                    ->send();
                            }

                            if ($protegees) {
                                Notification::make()
                                    ->danger()
                                    ->persistent()
                                    ->title(count($protegees) . ' mouvement(s) non supprimé(s)')
                                    ->body(implode("\n", $protegees))
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }
}
