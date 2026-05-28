<?php

namespace App\Filament\Budget\Resources\RegieAvanceResource\RelationManagers;

use App\Models\DecisionAdministrative;
use App\Models\LigneBudgetaire;
use App\Models\LigneRegieAvance;
use App\Models\MenuDepenseDecision;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DecisionSourceRavRelationManager extends RelationManager
{
    protected static string $relationship = 'decisionsSource';
    protected static ?string $title       = 'Décision source (DA engagée)';

    public function form(Forms\Form $form): Forms\Form
    {
        $regie = $this->getOwnerRecord();

        return $form->schema([

            Forms\Components\Select::make('decision_administrative_id')
                ->label('Décision Administrative engagée')
                ->options(function () use ($regie) {
                    $daDejaLiee = MenuDepenseDecision::where('regie_avance_id', $regie->id)
                        ->pluck('decision_administrative_id')
                        ->toArray();

                    return DecisionAdministrative::where('statut', 'engagee')
                        ->whereNotIn('id', $daDejaLiee)
                        ->get()
                        ->mapWithKeys(fn($da) => [
                            $da->id =>
                            "{$da->numero} — {$da->objet} "
                                . "(" . number_format($da->montant_net, 0, ',', ' ')
                                . " FCFA)"
                        ]);
                })
                ->required()
                ->searchable()
                ->live()
                ->afterStateUpdated(function ($state, Set $set) {
                    if (!$state) {
                        $set('montant_da', 0);
                        $set('nomenclature_id', null);
                        $set('ligne_budgetaire_id', null);
                        return;
                    }

                    $da = DecisionAdministrative::find($state);
                    if (!$da) return;

                    // ✅ Toujours depuis la DA
                    $set('montant_da', $da->montant_net);

                    // ── Résoudre nomenclature via engagement ──────
                    $engagement = \App\Models\Engagement::where('engageable_id', $state)
                        ->where(function ($q) {
                            $q->where('engageable_type', 'App\\Models\\DecisionAdministrative')
                                ->orWhere('engageable_type', 'decision_administrative');
                        })->first();

                    $nomId = $engagement?->nomenclature_principale_id;
                    if (!$nomId && $engagement) {
                        $nomId = \App\Models\LigneEngagement::where('engagement_id', $engagement->id)
                            ->value('nomenclature_id');
                    }

                    $set('nomenclature_id', $nomId);

                    if ($nomId && $da->budget_id) {
                        $lb = LigneBudgetaire::where('budget_id', $da->budget_id)
                            ->where('nomenclature_id', $nomId)
                            ->first();
                        $set('ligne_budgetaire_id', $lb?->id);
                    }
                })
                ->helperText('Une seule DA source pour une RAV.')
                ->columnSpanFull(),

            Forms\Components\Hidden::make('nomenclature_id'),
            Forms\Components\Hidden::make('ligne_budgetaire_id'),

            Forms\Components\TextInput::make('montant_da')
                ->label('Montant net à décaisser (FCFA)')
                ->numeric()->required()->prefix('FCFA')
                ->helperText('Pré-rempli depuis le montant net de la DA — modifiable.'),

            // ── Aperçu automatique ────────────────────────────
            Forms\Components\Placeholder::make('apercu')
                ->label('📋 Récapitulatif')
                ->content(function (Get $get) {
                    $daId    = $get('decision_administrative_id');
                    $nomId   = $get('nomenclature_id');
                    $lbId    = $get('ligne_budgetaire_id');
                    $montant = (float) ($get('montant_da') ?? 0);

                    if (!$daId) return '← Sélectionnez une Décision Administrative';

                    $da  = DecisionAdministrative::find($daId);
                    $nom = $nomId ? \App\Models\NomenclatureBudgetaire::find($nomId) : null;
                    $lb  = $lbId  ? LigneBudgetaire::find($lbId) : null;

                    if (!$nom || !$lb) {
                        return new \Illuminate\Support\HtmlString(
                            '<div class="rounded p-3 text-sm '
                                . 'bg-red-50 dark:bg-red-900/30 '
                                . 'text-red-700 dark:text-red-300 '
                                . 'border border-red-200 dark:border-red-700">'
                                . '⚠️ Nomenclature ou ligne budgétaire non trouvée. '
                                . 'Vérifiez que la DA est bien engagée avec une ligne budgétaire.'
                                . '</div>'
                        );
                    }

                    $restant      = $lb->disponible_engagement - $montant;
                    $colorRestant = $restant < 0
                        ? 'text-red-600 dark:text-red-400'
                        : 'text-green-600 dark:text-green-400';

                    return new \Illuminate\Support\HtmlString(
                        '<div class="rounded-lg p-3 text-sm leading-loose '
                            . 'bg-slate-100 dark:bg-slate-800 '
                            . 'text-slate-800 dark:text-slate-200">'
                            . '<table class="w-full">'
                            . '<tr><td class="font-semibold w-44 py-1">Décision :</td>'
                            . '<td class="py-1">'
                            . '<span class="px-2 py-0.5 rounded text-xs font-bold '
                            . 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">'
                            . $da->numero . '</span>'
                            . '</td></tr>'
                            . '<tr><td class="font-semibold py-1">Nomenclature :</td>'
                            . '<td class="py-1">'
                            . '<span class="px-2 py-0.5 rounded text-xs '
                            . 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">'
                            . $nom->code . '</span> ' . $nom->libelle
                            . '</td></tr>'
                            . '<tr><td class="font-semibold py-1">Disponible ligne :</td>'
                            . '<td class="py-1 text-blue-600 dark:text-blue-400">'
                            . number_format($lb->disponible_engagement, 0, ',', ' ') . ' FCFA'
                            . '</td></tr>'
                            . '<tr><td class="font-semibold py-1">Montant net DA :</td>'
                            . '<td class="py-1 text-green-600 dark:text-green-400 font-semibold">'
                            . number_format($da->montant_net, 0, ',', ' ') . ' FCFA'
                            . '</td></tr>'
                            . ($montant > 0
                                ? '<tr><td class="font-semibold py-1">Après allocation :</td>'
                                . '<td class="py-1 font-bold ' . $colorRestant . '">'
                                . number_format($restant, 0, ',', ' ') . ' FCFA'
                                . '</td></tr>'
                                : ''
                            )
                            . '</table></div>'
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
                    ->weight('bold')->badge()->color('primary'),

                Tables\Columns\TextColumn::make('decisionAdministrative.objet')
                    ->label('Objet DA')
                    ->limit(40),

                Tables\Columns\TextColumn::make('nomenclature.code')
                    ->label('Nomenclature')
                    ->badge()->color('gray'),

                Tables\Columns\TextColumn::make('nomenclature.libelle')
                    ->label('Libellé')
                    ->limit(35),

                Tables\Columns\TextColumn::make('montant_da')
                    ->label('Montant alloué')
                    ->money('XAF')
                    ->weight('bold')->color('success'),

                Tables\Columns\TextColumn::make('decisionAdministrative.statut')
                    ->label('Statut DA')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'engagee' => 'success',
                        'validee' => 'warning',
                        default   => 'gray',
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('➕ Associer la DA source')
                    ->visible(
                        fn() =>
                        $this->getOwnerRecord()?->statut === 'actif'
                            && MenuDepenseDecision::where(
                                'regie_avance_id',
                                $this->getOwnerRecord()?->id
                            )->count() === 0
                            && auth()->user()?->can('update_regie_avance')
                    )
                    ->using(function (array $data): MenuDepenseDecision {
                        $regie = $this->getOwnerRecord();
                        $daId  = $data['decision_administrative_id'];

                        $da = DecisionAdministrative::find($daId);
                        if (!$da) throw new \Exception("DA introuvable (id={$daId}).");

                        // ✅ Lire le montant depuis la DA — priorité sur le formulaire
                        $montant = (float) ($da->montant_net ?? $data['montant_da'] ?? 0);
                        if ($montant <= 0) {
                            $montant = (float) ($data['montant_da'] ?? 0);
                        }

                        // ── Résoudre nomenclature ─────────────────
                        $engagement = \App\Models\Engagement::where('engageable_id', $daId)
                            ->where(function ($q) {
                                $q->where('engageable_type', 'App\\Models\\DecisionAdministrative')
                                    ->orWhere('engageable_type', 'decision_administrative');
                            })->first();

                        $nomId = $data['nomenclature_id']
                            ?? $engagement?->nomenclature_principale_id;

                        if (!$nomId && $engagement) {
                            $nomId = \App\Models\LigneEngagement::where('engagement_id', $engagement->id)
                                ->value('nomenclature_id');
                        }

                        $lbId = $data['ligne_budgetaire_id'] ?? null;
                        if (!$lbId && $nomId && $da->budget_id) {
                            $lb   = LigneBudgetaire::where('budget_id', $da->budget_id)
                                ->where('nomenclature_id', $nomId)->first();
                            $lbId = $lb?->id;
                        }

                        // ── 1. Créer la liaison ───────────────────
                        $liaison = MenuDepenseDecision::create([
                            'regie_avance_id'            => $regie->id,
                            'decision_administrative_id' => $daId,
                            'nomenclature_id'            => $nomId,
                            'ligne_budgetaire_id'        => $lbId,
                            'montant_da'                 => $montant,
                        ]);

                        // ── 2. Créer la ligne mini-budget ─────────
                        if ($nomId && $montant > 0) {
                            LigneRegieAvance::firstOrCreate(
                                [
                                    'regie_avance_id' => $regie->id,
                                    'nomenclature_id' => $nomId,
                                ],
                                [
                                    'ligne_budgetaire_id' => $lbId,
                                    'montant_alloue'      => $montant,
                                    'montant_consomme'    => 0,
                                    'montant_disponible'  => $montant,
                                ]
                            );
                        }

                        // ── 3. ✅ Mettre à jour la régie via DB::table
                        // pour contourner tout observer/global scope
                        DB::table('regies_avances')
                            ->where('id', $regie->id)
                            ->update([
                                'decision_administrative_id' => $daId,
                                'montant_alloue'             => $montant,
                                'montant_disponible'         => $montant,
                                // Sync encaisse_annuelle si pas encore renseignée
                                'encaisse_annuelle'          => $regie->encaisse_annuelle > 0
                                    ? $regie->encaisse_annuelle
                                    : $montant,
                                'updated_at'                 => now(),
                            ]);

                        Log::info('✅ DA source associée à la RAV', [
                            'regie_id'    => $regie->id,
                            'regie_num'   => $regie->numero,
                            'da_id'       => $daId,
                            'da_num'      => $da->numero,
                            'montant'     => $montant,
                            'nomenclature_id' => $nomId,
                            'lb_id'       => $lbId,
                        ]);

                        return $liaison;
                    })
                    ->successNotification(
                        Notification::make()
                            ->title('✅ DA source associée')
                            ->success()
                            ->body('La ligne de mini-budget et les montants ont été mis à jour.')
                    ),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->visible(
                        fn() =>
                        $this->getOwnerRecord()?->statut === 'actif'
                            && auth()->user()?->can('update_regie_avance')
                    )
                    ->using(function (MenuDepenseDecision $record): void {
                        $regie = $this->getOwnerRecord();

                        // ── Supprimer la ligne mini-budget ────────
                        LigneRegieAvance::where([
                            'regie_avance_id' => $regie->id,
                            'nomenclature_id' => $record->nomenclature_id,
                        ])->where('montant_consomme', 0)->delete();

                        $record->delete();

                        // ── Réinitialiser la régie via DB::table ──
                        DB::table('regies_avances')
                            ->where('id', $regie->id)
                            ->update([
                                'decision_administrative_id' => null,
                                'montant_alloue'             => 0,
                                'montant_disponible'         => 0,
                                'updated_at'                 => now(),
                            ]);

                        Log::info('DA source dissociée de la RAV', [
                            'regie_id' => $regie->id,
                            'da_id'    => $record->decision_administrative_id,
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
