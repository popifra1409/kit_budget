<?php

namespace App\Filament\Budget\Resources\CollectifBudgetaireResource\RelationManagers;

use App\Models\LigneBudgetaire;
use App\Models\LignePrevisionRecette;
use App\Models\NomenclatureBudgetaire;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MouvementsRelationManager extends RelationManager
{
    protected static string $relationship = 'mouvements';
    protected static ?string $recordTitleAttribute = 'id';

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
                            $type = $get('type');
                            $classe = $type === 'depense' ? '6' : '7';
                            // Nomenclatures existantes SANS ligne dans le budget courant
                            $dejaDansLeBudget = LigneBudgetaire::pluck('nomenclature_id')->toArray();
                            return NomenclatureBudgetaire::where('classe', $classe)
                                ->whereNotIn('id', $dejaDansLeBudget)
                                ->orderBy('code')
                                ->get()
                                ->mapWithKeys(fn($n) => [
                                    $n->id => "{$n->code} — {$n->libelle}"
                                ]);
                        })
                        ->searchable()
                        ->preload()
                        ->required()
                        ->helperText('Nomenclatures déjà créées mais sans ligne dans le budget actuel'),

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
                            ->placeholder(fn($get) => $get('type') === 'depense' ? 'Ex: 621101' : 'Ex: 712001')
                            ->helperText(fn($get) => $get('type') === 'depense'
                                ? 'Classe 6 — Comptes de charges'
                                : 'Classe 7 — Comptes de produits'),

                        Forms\Components\Select::make('nouveau_niveau')
                            ->label('Niveau')
                            ->options([
                                'chapitre'   => 'Chapitre (ex: 62)',
                                'article'    => 'Article (ex: 621)',
                                'paragraphe' => 'Paragraphe (ex: 621101)',
                                'compte'     => 'Compte',
                                'ligne'      => 'Ligne détaillée',
                            ])
                            ->default('paragraphe')
                            ->required(),

                        Forms\Components\Select::make('nouveau_parent_id')
                            ->label('Rattacher à (parent)')
                            ->placeholder('Aucun — niveau chapitre')
                            ->options(function ($get) {
                                $type = $get('type');
                                $classe = $type === 'depense' ? '6' : '7';
                                return NomenclatureBudgetaire::where('classe', $classe)
                                    ->orderBy('code')
                                    ->get()
                                    ->mapWithKeys(fn($n) => [
                                        $n->id => "{$n->code} — {$n->libelle} ({$n->niveau})"
                                    ]);
                            })
                            ->searchable(),
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
                                    'montant_initial'        => $data['montant_modification'] ?? 0,
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
                                'classe'    => $type === 'depense' ? '6' : '7',
                                'type'      => $type,
                                'niveau'    => $data['nouveau_niveau'] ?? 'paragraphe',
                                'parent_id' => $data['nouveau_parent_id'] ?? null,
                                'actif'     => true,
                            ]);

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
                                    'montant_initial'        => $data['montant_modification'] ?? 0,
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
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
