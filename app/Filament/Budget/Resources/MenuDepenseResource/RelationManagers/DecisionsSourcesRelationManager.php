<?php

namespace App\Filament\Budget\Resources\MenuDepenseResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Tables;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Notifications\Notification;
use App\Models\MenuDepenseDecision;
use App\Models\LigneRegieAvance;
use App\Models\LigneBudgetaire;
use App\Models\NomenclatureBudgetaire;
use App\Models\DecisionAdministrative;

class DecisionsSourcesRelationManager extends RelationManager
{
    protected static string $relationship = 'decisionsSource';
    protected static ?string $title       = 'Décisions sources (DA engagées)';

    public function form(Forms\Form $form): Forms\Form
    {
        $md = $this->getOwnerRecord();

        return $form->schema([

            // ── DA source ──────────────────────────────────────────
            Forms\Components\Select::make('decision_administrative_id')
                ->label('Décision Administrative engagée')
                ->options(function () use ($md) {
                    $daDejaLiees = MenuDepenseDecision::where('regie_avance_id', $md->id)
                        ->pluck('decision_administrative_id')->toArray();

                    return DecisionAdministrative::where('statut', 'engagee')
                        ->whereNotIn('id', $daDejaLiees)
                        ->get()
                        ->mapWithKeys(fn($da) => [
                            $da->id =>
                            "{$da->numero} — {$da->objet} "
                                . "(" . number_format($da->montant_net, 0, ',', ' ') . " FCFA)"
                        ]);
                })
                ->required()
                ->searchable()
                ->live()
                ->afterStateUpdated(function ($state, Set $set) {
                    if (!$state) {
                        $set('montant_da', 0);
                        return;
                    }

                    $da = DecisionAdministrative::find($state);
                    if (!$da) return;

                    // Pré-remplir le montant net
                    $set('montant_da', $da->montant_net);

                    // ✅ Chercher l'engagement directement
                    $engagement = \App\Models\Engagement::where('engageable_id', $state)
                        ->where(function ($q) {
                            $q->where('engageable_type', 'App\\Models\\DecisionAdministrative')
                                ->orWhere('engageable_type', 'decision_administrative');
                        })
                        ->first();

                    $nomId = $engagement?->nomenclature_principale_id;
                    if (!$nomId && $engagement) {
                        $nomId = \App\Models\LigneEngagement::where('engagement_id', $engagement->id)
                            ->value('nomenclature_id');
                    }

                    // Stocker dans les Hidden pour l'aperçu
                    $set('nomenclature_id',    $nomId);
                    $set(
                        'ligne_budgetaire_id',
                        $nomId && $da->budget_id
                            ? LigneBudgetaire::where('budget_id', $da->budget_id)
                            ->where('nomenclature_id', $nomId)
                            ->value('id')
                            : null
                    );
                })
                ->helperText('Nomenclature et ligne budgétaire récupérées automatiquement depuis l\'engagement.')
                ->columnSpanFull(),

            // ✅ Champs cachés — remplis automatiquement
            Forms\Components\Hidden::make('nomenclature_id'),
            Forms\Components\Hidden::make('ligne_budgetaire_id'),

            // ✅ Champs helper pour l'aperçu (non persistés)
            Forms\Components\Hidden::make('_apercu_code'),
            Forms\Components\Hidden::make('_apercu_libelle'),
            Forms\Components\Hidden::make('_apercu_disponible'),
            Forms\Components\Hidden::make('_apercu_montant_net_da'),

            // ── Montant (pré-rempli, modifiable si besoin) ─────────
            Forms\Components\TextInput::make('montant_da')
                ->label('Montant alloué depuis cette DA (FCFA)')
                ->numeric()
                ->required()
                ->prefix('FCFA')
                ->helperText(
                    'Pré-rempli depuis le montant net de la DA. '
                        . 'Modifiable si vous n\'allouez qu\'une partie.'
                )
                ->live(onBlur: true),

            // ── Aperçu automatique ─────────────────────────────────
            Forms\Components\Placeholder::make('apercu_da')
                ->label('📋 Récapitulatif automatique')
                ->content(function (Get $get) {
                    $daId    = $get('decision_administrative_id');
                    $nomId   = $get('nomenclature_id');
                    $lbId    = $get('ligne_budgetaire_id');
                    $montant = (float) ($get('montant_da') ?? 0);

                    if (!$daId) {
                        return '← Sélectionnez une Décision Administrative';
                    }

                    $da  = DecisionAdministrative::find($daId);
                    $nom = $nomId ? NomenclatureBudgetaire::find($nomId) : null;
                    $lb  = $lbId  ? LigneBudgetaire::find($lbId)         : null;

                    if (!$da) return '—';

                    // Alerte si nomenclature non trouvée
                    if (!$nom || !$lb) {
                        return new \Illuminate\Support\HtmlString(
                            '<div class="rounded-lg p-3 text-sm '
                                . 'bg-red-50 dark:bg-red-900/30 '
                                . 'text-red-700 dark:text-red-300 '
                                . 'border border-red-200 dark:border-red-700">'
                                . '⚠️ Aucune nomenclature trouvée pour cette DA. '
                                . 'Vérifiez que la DA est bien engagée avec une ligne budgétaire.'
                                . '</div>'
                        );
                    }

                    $restant      = $lb->disponible_engagement - $montant;
                    $colorRestant = $restant < 0
                        ? 'text-red-600 dark:text-red-400'
                        : 'text-green-600 dark:text-green-400';

                    $alertRestant = $restant < 0
                        ? '<div class="mt-2 rounded p-2 text-xs font-semibold '
                        . 'bg-red-100 text-red-700 '
                        . 'dark:bg-red-900/40 dark:text-red-300">'
                        . '❌ Montant insuffisant sur la ligne budgétaire !'
                        . '</div>'
                        : '';

                    return new \Illuminate\Support\HtmlString(
                        '<div class="rounded-lg p-3 text-sm leading-loose '
                            . 'bg-slate-100 dark:bg-slate-800 '
                            . 'text-slate-800 dark:text-slate-200">'
                            . '<table class="w-full">'

                            // DA
                            . '<tr class="border-b border-slate-200 dark:border-slate-700">'
                            . '<td class="font-semibold pr-4 w-48 py-1">Décision :</td>'
                            . '<td class="py-1">'
                            . '<span class="px-2 py-0.5 rounded text-xs font-bold '
                            . 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">'
                            . $da->numero . '</span>'
                            . '</td></tr>'

                            // Nomenclature
                            . '<tr class="border-b border-slate-200 dark:border-slate-700">'
                            . '<td class="font-semibold pr-4 py-1">Nomenclature :</td>'
                            . '<td class="py-1">'
                            . '<span class="px-2 py-0.5 rounded text-xs '
                            . 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">'
                            . $nom->code . '</span>'
                            . ' ' . $nom->libelle
                            . '</td></tr>'

                            // Budget disponible
                            . '<tr class="border-b border-slate-200 dark:border-slate-700">'
                            . '<td class="font-semibold pr-4 py-1">Disponible ligne :</td>'
                            . '<td class="py-1 text-blue-600 dark:text-blue-400">'
                            . number_format($lb->disponible_engagement, 0, ',', ' ') . ' FCFA'
                            . '</td></tr>'

                            // Montant net DA
                            . '<tr class="border-b border-slate-200 dark:border-slate-700">'
                            . '<td class="font-semibold pr-4 py-1">Montant net DA :</td>'
                            . '<td class="py-1 text-green-600 dark:text-green-400 font-semibold">'
                            . number_format($da->montant_net, 0, ',', ' ') . ' FCFA'
                            . '</td></tr>'

                            // Montant alloué saisi
                            . ($montant > 0
                                ? '<tr class="border-b border-slate-200 dark:border-slate-700">'
                                . '<td class="font-semibold pr-4 py-1">Montant alloué :</td>'
                                . '<td class="py-1 font-bold">'
                                . number_format($montant, 0, ',', ' ') . ' FCFA'
                                . '</td></tr>'

                                // Disponible après
                                . '<tr>'
                                . '<td class="font-semibold pr-4 py-1">Disponible après :</td>'
                                . '<td class="py-1 font-bold ' . $colorRestant . '">'
                                . number_format($restant, 0, ',', ' ') . ' FCFA'
                                . '</td></tr>'
                                : ''
                            )

                            . '</table>'
                            . $alertRestant
                            . '</div>'
                    );
                })
                ->columnSpanFull(),
        ]);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->recordTitleAttribute('montant_da')
            ->columns([
                Tables\Columns\TextColumn::make('decisionAdministrative.numero')
                    ->label('N° DA')
                    ->weight('bold')
                    ->badge()
                    ->color('primary')
                    ->copyable(),

                Tables\Columns\TextColumn::make('decisionAdministrative.objet')
                    ->label('Objet DA')
                    ->limit(35)
                    ->tooltip(fn($record) => $record->decisionAdministrative?->objet),

                Tables\Columns\TextColumn::make('nomenclature.code')
                    ->label('Code nomenclature')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('nomenclature.libelle')
                    ->label('Libellé nomenclature')
                    ->limit(35),

                Tables\Columns\TextColumn::make('montant_da')
                    ->label('Montant alloué')
                    ->money('XAF')
                    ->weight('bold')
                    ->color('success')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('XAF')
                            ->label('Total'),
                    ]),

                Tables\Columns\TextColumn::make('decisionAdministrative.statut')
                    ->label('Statut DA')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'engagee' => 'success',
                        'validee' => 'warning',
                        default   => 'gray',
                    })
                    ->formatStateUsing(fn($state) => match ($state) {
                        'engagee' => 'Engagée',
                        'validee' => 'Validée',
                        default   => $state,
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('➕ Ajouter une DA source')
                    ->visible(
                        fn() =>
                        $this->getOwnerRecord()?->statut === 'actif'
                            && auth()->user()?->can('update_menu_depense')
                    )
                    ->using(function (array $data): MenuDepenseDecision {
                        $md    = $this->getOwnerRecord();
                        $daId  = $data['decision_administrative_id'];
                        $montant = (float) ($data['montant_da'] ?? 0);

                        $da = DecisionAdministrative::find($daId);

                        // ✅ Chercher l'engagement DIRECTEMENT sans passer par la relation morphOne
                        $engagement = \App\Models\Engagement::where('engageable_id', $daId)
                            ->where(function ($q) {
                                $q->where('engageable_type', 'App\\Models\\DecisionAdministrative')
                                    ->orWhere('engageable_type', 'decision_administrative');
                            })
                            ->first();

                        // ✅ Résoudre la nomenclature depuis l'engagement
                        $nomId = $engagement?->nomenclature_principale_id;

                        // Fallback lignes d'engagement
                        if (!$nomId && $engagement) {
                            $nomId = \App\Models\LigneEngagement::where('engagement_id', $engagement->id)
                                ->value('nomenclature_id');
                        }

                        // ✅ Trouver la ligne budgétaire
                        $lbId = null;
                        if ($nomId && $da?->budget_id) {
                            $lb   = LigneBudgetaire::where('budget_id', $da->budget_id)
                                ->where('nomenclature_id', $nomId)
                                ->first();
                            $lbId = $lb?->id;
                        }

                        \Log::info('MenuDepenseDecision::using', [
                            'da_id'          => $daId,
                            'engagement_id'  => $engagement?->id,
                            'nomId'          => $nomId,
                            'lbId'           => $lbId,
                            'montant'        => $montant,
                        ]);

                        // ── 1. Créer la liaison ───────────────────────────────
                        $liaison = MenuDepenseDecision::create([
                            'regie_avance_id'           => $md->id,
                            'decision_administrative_id' => $daId,
                            'nomenclature_id'            => $nomId,
                            'ligne_budgetaire_id'        => $lbId,
                            'montant_da'                 => $montant,
                        ]);

                        // ── 2. Créer/cumuler la ligne mini-budget ─────────────
                        if ($nomId && $montant > 0) {
                            $ligne = LigneRegieAvance::where([
                                'regie_avance_id' => $md->id,
                                'nomenclature_id' => $nomId,
                            ])->first();

                            if ($ligne) {
                                $ligne->updateQuietly([
                                    'montant_alloue'     => $ligne->montant_alloue + $montant,
                                    'montant_disponible' => $ligne->montant_disponible + $montant,
                                ]);
                            } else {
                                LigneRegieAvance::create([
                                    'regie_avance_id'    => $md->id,
                                    'nomenclature_id'    => $nomId,
                                    'ligne_budgetaire_id' => $lbId,
                                    'montant_alloue'     => $montant,
                                    'montant_consomme'   => 0,
                                    'montant_disponible' => $montant,
                                ]);
                            }
                        }

                        // ── 3. Recalculer total du Menu Dépense ───────────────
                        $total = MenuDepenseDecision::where('regie_avance_id', $md->id)
                            ->sum('montant_da');

                        $md->updateQuietly([
                            'montant_alloue'     => $total,
                            'montant_disponible' => $total,
                        ]);

                        return $liaison;
                    })
                    ->successNotification(
                        Notification::make()
                            ->title('✅ DA source ajoutée')
                            ->success()
                            ->body('La ligne de mini-budget a été créée automatiquement.')
                    ),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->visible(
                        fn() =>
                        $this->getOwnerRecord()?->statut === 'actif'
                            && auth()->user()?->can('update_menu_depense')
                    )
                    ->using(function (MenuDepenseDecision $record): void {
                        $md      = $this->getOwnerRecord();
                        $montant = (float) $record->montant_da;
                        $nomId   = $record->nomenclature_id;

                        // ── 1. Soustraire de la ligne mini-budget ──
                        $ligne = LigneRegieAvance::where([
                            'regie_avance_id' => $md->id,
                            'nomenclature_id' => $nomId,
                        ])->first();

                        if ($ligne) {
                            $nouveauAlloue = max(0, $ligne->montant_alloue - $montant);
                            if ($nouveauAlloue <= 0 && $ligne->montant_consomme <= 0) {
                                $ligne->delete();
                            } else {
                                $ligne->updateQuietly([
                                    'montant_alloue'     => $nouveauAlloue,
                                    'montant_disponible' => max(0, $ligne->montant_disponible - $montant),
                                ]);
                            }
                        }

                        // ── 2. Supprimer la liaison ────────────────
                        $record->delete();

                        // ── 3. Recalculer total ────────────────────
                        $total = MenuDepenseDecision::where('regie_avance_id', $md->id)
                            ->sum('montant_da');

                        $md->updateQuietly([
                            'montant_alloue'     => $total,
                            'montant_disponible' => $total,
                        ]);
                    })
                    ->successNotification(
                        Notification::make()
                            ->title('DA source supprimée')
                            ->warning()
                    ),
            ]);
    }
}
