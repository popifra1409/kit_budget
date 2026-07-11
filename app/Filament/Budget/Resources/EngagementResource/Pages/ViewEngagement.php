<?php

namespace App\Filament\Budget\Resources\EngagementResource\Pages;

use App\Filament\Budget\Resources\EngagementResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use App\Models\EtatConfig;
use App\Models\Avenant;
use Filament\Forms;
use Filament\Forms\Get;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ViewEngagement extends ViewRecord
{
    protected static string $resource = EngagementResource::class;

    protected function getHeaderActions(): array
    {
        return [

            // ── Modifier (manuel provisoire) ──────────────────────
            Actions\EditAction::make()
                ->visible(fn($record) => $record->statut === 'provisoire' && !$record->engageable_id),

            // ── Passer définitif ──────────────────────────────────
            Actions\Action::make('valider')
                ->label('Passer définitif')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn($record) => $record->statut === 'provisoire')
                ->requiresConfirmation()
                ->modalHeading('Passer l\'engagement en définitif')
                ->modalDescription(
                    fn($record) =>
                    "Voulez-vous passer l'engagement {$record->numero} en statut définitif ?\n"
                        . "Montant : " . number_format($record->montant_engage, 0, ',', ' ') . " FCFA\n\n"
                        . "Cette action est irréversible."
                )
                ->action(function ($record) {
                    try {
                        $record->passerDefinitif(auth()->user());
                        Notification::make()->title('✅ Engagement passé en définitif')->success()
                            ->body("L'engagement {$record->numero} est maintenant définitif.")->send();
                        return redirect()->route('filament.budget.resources.engagements.view', ['record' => $record]);
                    } catch (\Exception $e) {
                        Notification::make()->title('❌ Erreur')->danger()->body($e->getMessage())->send();
                    }
                }),

            // ── Créer les ordonnances ─────────────────────────────
            Actions\Action::make('creer_ordonnances')
                ->label('Créer les OP')
                ->icon('heroicon-o-document-currency-dollar')
                ->color('primary')
                ->visible(fn($record) => $record->statut === 'definitif' && !$record->hasOrdonnancesPaiement())
                ->requiresConfirmation()
                ->modalHeading('Créer les ordonnances de paiement')
                ->modalDescription(
                    fn($record) =>
                    "Créer les ordonnances pour l'engagement {$record->numero} ?\n\n"
                        . "Montant : " . number_format($record->montant_engage, 0, ',', ' ') . " FCFA"
                )
                ->modalContent(function ($record) {
                    $record->load(['engageable', 'beneficiaire']);
                    $donnees = $record->extraireDonneesDocument();
                    return view('filament.modals.recap-ordonnances', [
                        'engagement' => $record,
                        'donnees'    => $donnees,
                    ]);
                })
                ->modalWidth('3xl')
                ->action(function ($record) {
                    try {
                        $ordonnances = $record->creerOrdonnancesPaiement();
                        $message = "✅ Ordonnances créées :\n\n";
                        if (isset($ordonnances['standard']))
                            $message .= "• OP Standard : {$ordonnances['standard']->numero} — "
                                . number_format($ordonnances['standard']->montant_net, 0, ',', ' ') . " FCFA\n";
                        if (isset($ordonnances['impot']))
                            $message .= "• OP Impôt : {$ordonnances['impot']->numero} — "
                                . number_format($ordonnances['impot']->montant_net, 0, ',', ' ') . " FCFA";
                        Notification::make()->title('Ordonnances créées')->success()
                            ->body($message)->duration(10000)->send();
                        return redirect()->route('filament.budget.resources.engagements.view', ['record' => $record]);
                    } catch (\Exception $e) {
                        Notification::make()->title('❌ Erreur')->danger()
                            ->body($e->getMessage())->persistent()->send();
                    }
                }),

            // ── Avenant / Rectification ───────────────────────────
            Actions\Action::make('creer_avenant')
                ->label('Avenant / Rectification')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->visible(
                    fn($record) =>
                    $record->statut === 'definitif'
                        && $record->hasOrdonnancesPaiement()
                        && auth()->user()?->can('create_avenant_engagement')
                        // ✅ Masqué si au moins une OP est marquée payée (irréversible)
                        && !$record->ordonnancesPaiement()->where('statut', 'payee')->exists()
                )
                ->form([
                    Forms\Components\Placeholder::make('info')
                        ->label('')
                        ->content(new \Illuminate\Support\HtmlString(
                            '<div style="background:#fef9c3;border:1px solid #ca8a04;border-radius:.5rem;padding:.75rem;">
⚠️ <strong>Avenant</strong> — Rectification d\'un engagement définitif.<br>
Les champs sont pré-remplis avec les valeurs actuelles — modifiez uniquement ce qui doit être corrigé.
</div>'
                        ))
                        ->columnSpanFull(),

                    Forms\Components\Select::make('type_correction')
                        ->label('Type de correction')
                        ->options([
                            'montant'      => '💰 Montant uniquement',
                            'nomenclature' => '📋 Ligne budgétaire uniquement',
                            'mixte'        => '🔄 Montant + Ligne budgétaire',
                            'taxes'        => '🧾 Taxes/Impôts uniquement',
                            'complet'      => '✏️ Montant + Taxes + Ligne budgétaire',
                            'objet'        => '📝 Objet seulement (sans impact budget)',
                        ])
                        ->required()->live()
                        ->helperText('Choisissez ce qui doit être corrigé'),

                    Forms\Components\Textarea::make('nouvel_objet')
                        ->label('Nouvel objet')->rows(2)
                        ->placeholder(fn() => $this->record->objet ?? '—')
                        ->helperText('📋 Avant avenant : ' . ($this->record->objet ?? '—') . ' — laissez vide pour conserver')
                        ->visible(fn(Get $get) => in_array($get('type_correction'), ['objet', 'complet']))
                        ->columnSpanFull(),

                    Forms\Components\Section::make('Nouveaux montants du document')
                        ->schema([
                            Forms\Components\TextInput::make('montant_brut')
                                ->label('Montant brut (FCFA)')->numeric()->prefix('FCFA')
                                ->default(fn() => $this->record->engageable?->montant_brut ?? $this->record->montant_engage)
                                ->helperText(fn() => '📋 Avant avenant : ' . number_format($this->record->engageable?->montant_brut ?? $this->record->montant_engage, 0, ',', ' ') . ' FCFA')
                                ->visible(fn(Get $get) => in_array($get('type_correction'), ['montant', 'mixte', 'complet']) && $this->record->estDecision()),

                            Forms\Components\TextInput::make('montant_cnps')
                                ->label('CNPS (FCFA)')->numeric()->prefix('FCFA')
                                ->default(fn() => $this->record->engageable?->montant_cnps ?? 0)
                                ->helperText(fn() => '📋 Avant avenant : ' . number_format($this->record->engageable?->montant_cnps ?? 0, 0, ',', ' ') . ' FCFA')
                                ->visible(fn(Get $get) => in_array($get('type_correction'), ['taxes', 'mixte', 'complet']) && $this->record->estDecision()),

                            Forms\Components\TextInput::make('montant_ir')
                                ->label('IR (FCFA)')->numeric()->prefix('FCFA')
                                ->default(fn() => $this->record->engageable?->montant_ir ?? 0)
                                ->helperText(fn() => '📋 Avant avenant : ' . number_format($this->record->engageable?->montant_ir ?? 0, 0, ',', ' ') . ' FCFA')
                                ->visible(fn(Get $get) => in_array($get('type_correction'), ['taxes', 'mixte', 'complet']) && ($this->record->estDecision() || $this->record->estBonCommande())),

                            Forms\Components\TextInput::make('montant_irnc')
                                ->label('IRNC (FCFA)')->numeric()->prefix('FCFA')
                                ->default(fn() => $this->record->engageable?->montant_irnc ?? 0)
                                ->helperText(fn() => '📋 Avant avenant : ' . number_format($this->record->engageable?->montant_irnc ?? 0, 0, ',', ' ') . ' FCFA')
                                ->visible(fn(Get $get) => in_array($get('type_correction'), ['taxes', 'mixte', 'complet']) && $this->record->estDecision()),

                            Forms\Components\TextInput::make('montant_tva')
                                ->label('TVA (FCFA)')->numeric()->prefix('FCFA')
                                ->default(fn() => $this->record->engageable?->montant_tva ?? 0)
                                ->helperText(fn() => '📋 Avant avenant : ' . number_format($this->record->engageable?->montant_tva ?? 0, 0, ',', ' ') . ' FCFA')
                                ->visible(fn(Get $get) => in_array($get('type_correction'), ['taxes', 'mixte', 'complet'])),

                            Forms\Components\TextInput::make('autres_retenues')
                                ->label('Autres retenues (FCFA)')->numeric()->prefix('FCFA')
                                ->default(fn() => $this->record->engageable?->autres_retenues ?? 0)
                                ->helperText(fn() => '📋 Avant avenant : ' . number_format($this->record->engageable?->autres_retenues ?? 0, 0, ',', ' ') . ' FCFA')
                                ->visible(fn(Get $get) => in_array($get('type_correction'), ['taxes', 'mixte', 'complet']) && $this->record->estDecision()),

                            Forms\Components\TextInput::make('montant_ht')
                                ->label('Montant HT (FCFA)')->numeric()->prefix('FCFA')
                                ->default(fn() => $this->record->engageable?->montant_ht ?? 0)
                                ->helperText(fn() => '📋 Avant avenant : ' . number_format($this->record->engageable?->montant_ht ?? 0, 0, ',', ' ') . ' FCFA')
                                ->visible(fn(Get $get) => in_array($get('type_correction'), ['montant', 'mixte', 'complet']) && $this->record->estBonCommande()),

                            Forms\Components\TextInput::make('montant_ttc')
                                ->label('Montant TTC (FCFA)')->numeric()->prefix('FCFA')
                                ->default(fn() => $this->record->engageable?->montant_ttc ?? $this->record->montant_engage)
                                ->helperText(fn() => '📋 Avant avenant : ' . number_format($this->record->engageable?->montant_ttc ?? $this->record->montant_engage, 0, ',', ' ') . ' FCFA')
                                ->visible(fn(Get $get) => in_array($get('type_correction'), ['montant', 'mixte', 'complet']) && $this->record->estBonCommande()),

                            Forms\Components\TextInput::make('montant_tsr')
                                ->label('TSR (FCFA)')->numeric()->prefix('FCFA')
                                ->default(fn() => $this->record->engageable?->montant_tsr ?? 0)
                                ->helperText(fn() => '📋 Avant avenant : ' . number_format($this->record->engageable?->montant_tsr ?? 0, 0, ',', ' ') . ' FCFA')
                                ->visible(fn(Get $get) => in_array($get('type_correction'), ['taxes', 'mixte', 'complet']) && $this->record->estBonCommande()),
                        ])
                        ->columns(2)
                        ->visible(fn(Get $get) => in_array($get('type_correction'), ['montant', 'mixte', 'taxes', 'complet'])),

                    Forms\Components\Select::make('nomenclature_corrigee_id')
                        ->label('Nouvelle ligne budgétaire')
                        ->options(function () {
                            return \App\Models\LigneBudgetaire::where('budget_id', $this->record->budget_id)
                                ->with('nomenclature')->get()
                                ->filter(fn($lb) => $lb->nomenclature)
                                ->mapWithKeys(fn($lb) => [
                                    $lb->nomenclature_id =>
                                    "{$lb->nomenclature->code} - {$lb->nomenclature->libelle} "
                                        . "(Dispo: " . number_format($lb->disponible_engagement, 0, ',', ' ') . " FCFA)"
                                ])->toArray();
                        })
                        ->searchable()
                        ->required(fn(Get $get) => in_array($get('type_correction'), ['nomenclature', 'mixte', 'complet']))
                        ->visible(fn(Get $get) => in_array($get('type_correction'), ['nomenclature', 'mixte', 'complet']))
                        ->helperText(fn() => '📋 Avant avenant : ' . ($this->record->nomenclaturePrincipale?->code ?? '—')),

                    Forms\Components\Section::make('Ordonnance impôt existante')
                        ->schema([
                            Forms\Components\Placeholder::make('op_impot_info')
                                ->label('')
                                ->content(function () {
                                    $op = $this->record->ordonnancesPaiement()
                                        ->where('type_ordonnance', 'impot')->first();
                                    if (!$op) return new \Illuminate\Support\HtmlString(
                                        '<span style="color:#6b7280;">Aucune OP impôt — sera créée si des taxes sont renseignées.</span>'
                                    );
                                    return new \Illuminate\Support\HtmlString(
                                        "<div style='background:#f0fdf4;border:1px solid #86efac;border-radius:.375rem;padding:.5rem .75rem;'>"
                                            . "OP Impôt : <strong>{$op->numero}</strong> — "
                                            . number_format($op->montant_net, 0, ',', ' ') . " FCFA</div>"
                                    );
                                })
                                ->columnSpanFull(),
                        ])
                        ->visible(fn(Get $get) => in_array($get('type_correction'), ['taxes', 'complet']))
                        ->collapsed(false),

                    Forms\Components\Toggle::make('corriger_ordonnances')
                        ->label('Mettre à jour les ordonnances de paiement existantes')
                        ->default(true)
                        ->helperText('Recalcule les montants des OP Standard et OP Impôt (ou crée l\'OPT si absente)')
                        ->visible(fn() => $this->record->ordonnancesPaiement()->exists())
                        ->inline(false),

                    Forms\Components\Textarea::make('motif')
                        ->label('Motif de la rectification')
                        ->required()->rows(3)
                        ->placeholder('Ex: Erreur de saisie, correction des taxes...'),
                ])
                ->modalHeading('Créer un avenant de rectification')
                ->modalWidth('2xl')
                ->action(function (array $data) {

                    $optResultat       = null;
                    $optNumeroCorrige  = false;
                    $optNumeroOriginal = null;
                    $deltaFinal        = 0;

                    try {
                        DB::transaction(function () use (
                            $data,
                            &$optResultat,
                            &$optNumeroCorrige,
                            &$optNumeroOriginal,
                            &$deltaFinal
                        ) {
                            $engagement      = $this->record;
                            $typeCorrection  = $data['type_correction'];
                            $montantOriginal = (float) $engagement->montant_engage;

                            // ── Montant corrigé ───────────────────────────────────
                            if ($engagement->estDecision()) {
                                $montantCorrige = isset($data['montant_brut'])
                                    ? (float) $data['montant_brut'] : $montantOriginal;
                            } elseif ($engagement->estBonCommande()) {
                                $montantCorrige = isset($data['montant_ttc'])
                                    ? (float) $data['montant_ttc'] : $montantOriginal;
                            } else {
                                $montantCorrige = $montantOriginal;
                            }

                            if (in_array($typeCorrection, ['taxes', 'objet'])) {
                                $montantCorrige = $montantOriginal;
                            }

                            $nomenclatureOriginaleId = $engagement->nomenclature_principale_id;
                            $nomenclatureCorrigeeId  = $data['nomenclature_corrigee_id']
                                ?? $nomenclatureOriginaleId;
                            $delta      = $montantCorrige - $montantOriginal;
                            $deltaFinal = $delta;
                            $corrigerOp = (bool) ($data['corriger_ordonnances'] ?? true);

                            $donneesCorrection = array_filter([
                                'montant_brut'    => $data['montant_brut']    ?? null,
                                'montant_cnps'    => $data['montant_cnps']    ?? null,
                                'montant_irnc'    => $data['montant_irnc']    ?? null,
                                'montant_tva'     => $data['montant_tva']     ?? null,
                                'autres_retenues' => $data['autres_retenues'] ?? null,
                                'montant_ht'      => $data['montant_ht']      ?? null,
                                'montant_ttc'     => $data['montant_ttc']     ?? null,
                                'montant_tsr'     => $data['montant_tsr']     ?? null,
                                'montant_ir'      => $data['montant_ir']      ?? null,
                                'objet'           => !empty($data['nouvel_objet'])
                                    ? trim($data['nouvel_objet']) : null,
                            ], fn($v) => $v !== null && $v !== '');

                            // ── Créer l'avenant ───────────────────────────────────
                            $avenant = Avenant::create([
                                'document_source_type'      => $engagement->engageable_type,
                                'document_source_id'        => $engagement->engageable_id,
                                'document_corrige_type'     => $engagement->engageable_type,
                                'document_corrige_id'       => $engagement->engageable_id,
                                'engagement_original_id'    => $engagement->id,
                                'numero_avenant'            => Avenant::prochainNumero($engagement->id),
                                'motif'                     => $data['motif'],
                                'type_correction'           => $typeCorrection,
                                'montant_original'          => $montantOriginal,
                                'montant_corrige'           => $montantCorrige,
                                'delta_montant'             => $delta,
                                'nomenclature_originale_id' => $nomenclatureOriginaleId,
                                'nomenclature_corrigee_id'  => $nomenclatureCorrigeeId,
                                'statut'                    => 'brouillon',
                                'created_by'                => auth()->id(),
                                'donnees_correction'        => !empty($donneesCorrection)
                                    ? $donneesCorrection : null,
                            ]);

                            $avenant->appliquer();

                            // ── Mettre à jour le document source ──────────────────
                            if (!empty($donneesCorrection) && $engagement->engageable_id) {
                                $doc = $engagement->engageable;

                                \Illuminate\Database\Eloquent\Model::withoutEvents(
                                    function () use ($doc, $donneesCorrection) {
                                        if ($doc instanceof \App\Models\DecisionAdministrative) {
                                            $montantBrut    = (float)($donneesCorrection['montant_brut']    ?? $doc->montant_brut    ?? 0);
                                            $montantCnps    = (float)($donneesCorrection['montant_cnps']    ?? $doc->montant_cnps    ?? 0);
                                            $montantIr      = (float)($donneesCorrection['montant_ir']      ?? $doc->montant_ir      ?? 0);
                                            $montantIrnc    = (float)($donneesCorrection['montant_irnc']    ?? $doc->montant_irnc    ?? 0);
                                            $montantTva     = (float)($donneesCorrection['montant_tva']     ?? $doc->montant_tva     ?? 0);
                                            $autresRetenues = (float)($donneesCorrection['autres_retenues'] ?? $doc->autres_retenues ?? 0);
                                            $totalTaxes     = $montantCnps + $montantIr + $montantIrnc + $montantTva + $autresRetenues;

                                            $doc->updateQuietly([
                                                'montant_brut'    => $montantBrut,
                                                'montant_ir'      => $montantIr,
                                                'montant_cnps'    => $montantCnps,
                                                'montant_irnc'    => $montantIrnc,
                                                'montant_tva'     => $montantTva,
                                                'autres_retenues' => $autresRetenues,
                                                'total_taxes'     => $totalTaxes,
                                                'montant_net'     => $montantBrut - $totalTaxes,
                                                'mode_saisie'     => 'forfait',
                                            ]);

                                            if (!empty($donneesCorrection['objet'])) {
                                                $doc->updateQuietly(['objet' => trim($donneesCorrection['objet'])]);
                                            }
                                        } elseif ($doc instanceof \App\Models\BonCommande) {
                                            $montantTtc = (float)($donneesCorrection['montant_ttc'] ?? $doc->montant_ttc);
                                            $montantIr  = (float)($donneesCorrection['montant_ir']  ?? $doc->montant_ir);
                                            $montantTva = (float)($donneesCorrection['montant_tva'] ?? $doc->montant_tva);
                                            $montantTsr = (float)($donneesCorrection['montant_tsr'] ?? $doc->montant_tsr);

                                            $updateData = array_merge(
                                                array_diff_key($donneesCorrection, ['objet' => null]),
                                                ['net_a_percevoir' => $montantTtc - ($montantIr + $montantTva + $montantTsr)]
                                            );
                                            if (!empty($donneesCorrection['objet'])) {
                                                $updateData['objet'] = $donneesCorrection['objet'];
                                            }
                                            $doc->updateQuietly($updateData);
                                        }

                                        Log::info('Document source mis à jour par avenant', [
                                            'type'        => get_class($doc),
                                            'id'          => $doc->id,
                                            'corrections' => $donneesCorrection,
                                        ]);
                                    }
                                );
                            }

                            // ✅ Objet sur l'engagement lui-même
                            if (!empty($donneesCorrection['objet'])) {
                                $engagement->updateQuietly(['objet' => $donneesCorrection['objet']]);
                            }

                            // ═══════════════════════════════════════════════════════
                            // ✅ FIX — TRANSFERT DE NOMENCLATURE BUDGÉTAIRE
                            //    Déclenché pour : 'nomenclature', 'mixte', 'complet'
                            //    Quand la ligne budgétaire change :
                            //      1. Libérer crédits ancienne ligne
                            //      2. Créditer nouvelle ligne
                            //      3. Mettre à jour engagement.nomenclature_principale_id
                            //      4. Mettre à jour lignes_engagement
                            //      5. Recalculer les deux LigneBudgetaire
                            // ═══════════════════════════════════════════════════════
                            if (
                                in_array($typeCorrection, ['nomenclature', 'mixte', 'complet'])
                                && !empty($data['nomenclature_corrigee_id'])
                                && (int) $data['nomenclature_corrigee_id'] !== (int) $nomenclatureOriginaleId
                            ) {
                                // Montant à transférer = montant corrigé (après avenant)
                                $montantTransfere = $montantCorrige;

                                // 1. Libérer les crédits sur l'ancienne ligne
                                $ancienneLigne = \App\Models\LigneBudgetaire::where('budget_id', $engagement->budget_id)
                                    ->where('nomenclature_id', $nomenclatureOriginaleId)
                                    ->first();

                                if ($ancienneLigne) {
                                    $ancienneLigne->engage = max(0, (float) $ancienneLigne->engage - $montantTransfere);
                                    $ancienneLigne->disponible_engagement =
                                        (float) $ancienneLigne->budget_rectifie - $ancienneLigne->engage;
                                    $ancienneLigne->saveQuietly();

                                    Log::info('Avenant nomenclature — crédits libérés ancienne ligne', [
                                        'engagement'       => $engagement->numero,
                                        'nomenclature_old' => $nomenclatureOriginaleId,
                                        'montant_libere'   => $montantTransfere,
                                        'engage_restant'   => $ancienneLigne->engage,
                                    ]);
                                }

                                // 2. Créditer la nouvelle ligne
                                $nouvelleLigne = \App\Models\LigneBudgetaire::where('budget_id', $engagement->budget_id)
                                    ->where('nomenclature_id', $nomenclatureCorrigeeId)
                                    ->first();

                                if (!$nouvelleLigne) {
                                    throw new \Exception(
                                        "Ligne budgétaire introuvable pour la nomenclature ID {$nomenclatureCorrigeeId}."
                                    );
                                }

                                $nouvelleLigne->engage = (float) $nouvelleLigne->engage + $montantTransfere;
                                $nouvelleLigne->disponible_engagement =
                                    (float) $nouvelleLigne->budget_rectifie - $nouvelleLigne->engage;
                                $nouvelleLigne->saveQuietly();

                                Log::info('Avenant nomenclature — crédits ajoutés nouvelle ligne', [
                                    'engagement'       => $engagement->numero,
                                    'nomenclature_new' => $nomenclatureCorrigeeId,
                                    'montant_ajoute'   => $montantTransfere,
                                    'engage_nouveau'   => $nouvelleLigne->engage,
                                ]);

                                // 3. Mettre à jour engagement.nomenclature_principale_id
                                $engagement->updateQuietly([
                                    'nomenclature_principale_id' => $nomenclatureCorrigeeId,
                                ]);

                                // 4. Mettre à jour les lignes_engagement
                                $engagement->lignes()
                                    ->where('nomenclature_id', $nomenclatureOriginaleId)
                                    ->update(['nomenclature_id' => $nomenclatureCorrigeeId]);

                                Log::info('Avenant nomenclature appliqué', [
                                    'engagement'        => $engagement->numero,
                                    'nomenclature_old'  => $nomenclatureOriginaleId,
                                    'nomenclature_new'  => $nomenclatureCorrigeeId,
                                    'montant_transfere' => $montantTransfere,
                                ]);

                                // 5. Recalculer les deux lignes budgétaires
                                $ancienneLigne?->recalculerDepuisEngagements();
                                $nouvelleLigne->recalculerDepuisEngagements();
                            }
                            // ═══════════════════════════════════════════════════════
                            // FIN FIX — suite du code original
                            // ═══════════════════════════════════════════════════════

                            // ── Rafraîchir depuis la DB ───────────────────────────
                            $engagement->refresh();
                            $engagement->load('engageable');
                            $doc = $engagement->engageable;

                            // ── Calculer les nouvelles taxes ──────────────────────
                            $nouvellesTaxes = 0;
                            if ($engagement->estDecision()) {
                                $nouvellesTaxes =
                                    (float)($donneesCorrection['montant_cnps']    ?? $doc?->montant_cnps    ?? 0)
                                    + (float)($donneesCorrection['montant_ir']      ?? $doc?->montant_ir      ?? 0)
                                    + (float)($donneesCorrection['montant_irnc']    ?? $doc?->montant_irnc    ?? 0)
                                    + (float)($donneesCorrection['montant_tva']     ?? $doc?->montant_tva     ?? 0)
                                    + (float)($donneesCorrection['autres_retenues'] ?? $doc?->autres_retenues ?? 0);
                            } elseif ($engagement->estBonCommande()) {
                                $nouvellesTaxes =
                                    (float)($donneesCorrection['montant_ir']  ?? $doc?->montant_ir  ?? 0)
                                    + (float)($donneesCorrection['montant_tva'] ?? $doc?->montant_tva ?? 0)
                                    + (float)($donneesCorrection['montant_tsr'] ?? $doc?->montant_tsr ?? 0);
                            }

                            $opImpotExiste    = $engagement->ordonnancesPaiement()->where('type_ordonnance', 'impot')->exists();
                            $opStandardExiste = $engagement->ordonnancesPaiement()->where('type_ordonnance', 'standard')->exists();

                            // ════ CAS 1 : OPT absente + taxes > 0 → créer/mettre à jour ══
                            if (!$opImpotExiste && $nouvellesTaxes > 0 && in_array($typeCorrection, ['taxes', 'mixte', 'complet'])) {
                                $exerciceId = $engagement->exercice_id;
                                $annee      = $engagement->exercice?->annee ?? now()->year;

                                $numeroCandidat = null;
                                if ($opStandardExiste) {
                                    $opStdPourNumero = $engagement->ordonnancesPaiement()->where('type_ordonnance', 'standard')->first();
                                    if ($opStdPourNumero?->numero) {
                                        $numeroCandidat = preg_replace('/^OP-/', 'OPT-', $opStdPourNumero->numero);
                                    }
                                }
                                if (!$numeroCandidat) {
                                    $seq            = \App\Models\OrdonnancePaiement::whereYear('created_at', $annee)->where('type_ordonnance', 'impot')->withTrashed()->count() + 1;
                                    $numeroCandidat = 'OPT-' . $annee . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
                                }

                                $optExistanteTrashed = \App\Models\OrdonnancePaiement::withTrashed()
                                    ->where('engagement_id', $engagement->id)->where('type_ordonnance', 'impot')->first();

                                $numeroOpt        = $numeroCandidat;
                                $numeroFutCorrige = false;

                                if (!$optExistanteTrashed) {
                                    $collision = \App\Models\OrdonnancePaiement::withTrashed()
                                        ->where('numero', $numeroCandidat)->where('engagement_id', '!=', $engagement->id)->exists();
                                    if ($collision) {
                                        $suffixe = 1;
                                        do {
                                            $tentative = $suffixe === 1 ? $numeroCandidat . '-BIS' : $numeroCandidat . '-BIS' . $suffixe;
                                            $existe    = \App\Models\OrdonnancePaiement::withTrashed()->where('numero', $tentative)->exists();
                                            $suffixe++;
                                        } while ($existe && $suffixe < 20);
                                        $numeroOpt        = $tentative;
                                        $numeroFutCorrige = true;
                                        Log::warning('Collision numéro OPT — numéro alternatif généré', [
                                            'engagement_actuel' => $engagement->numero,
                                            'numero_candidat'   => $numeroCandidat,
                                            'numero_attribue'   => $numeroOpt,
                                        ]);
                                    }
                                }

                                $donneesOpt = [
                                    'numero'          => $numeroOpt,
                                    'engagement_id'   => $engagement->id,
                                    'exercice_id'     => $exerciceId,
                                    'type_ordonnance' => 'impot',
                                    'date_emission'   => now(),
                                    'mois_emission'   => now()->month,
                                    'statut'          => 'emise',
                                    'montant_brut'    => $nouvellesTaxes,
                                    'montant_impot'   => $nouvellesTaxes,
                                    'montant_net'     => $nouvellesTaxes,
                                    'objet'           => 'Reversement impôts et taxes — ' . ($engagement->objet ?? ''),
                                    'created_by'      => auth()->id(),
                                    'montant_tva' => 0,
                                    'montant_ir' => 0,
                                    'montant_tsr' => 0,
                                    'montant_cnps' => 0,
                                    'montant_irnc' => 0,
                                    'montant_autres_taxes' => 0,
                                ];

                                if ($engagement->estDecision()) {
                                    $donneesOpt = array_merge($donneesOpt, [
                                        'montant_cnps'         => (float)($donneesCorrection['montant_cnps']    ?? $doc?->montant_cnps    ?? 0),
                                        'montant_ir'           => (float)($donneesCorrection['montant_ir']      ?? $doc?->montant_ir      ?? 0),
                                        'montant_irnc'         => (float)($donneesCorrection['montant_irnc']    ?? $doc?->montant_irnc    ?? 0),
                                        'montant_tva'          => (float)($donneesCorrection['montant_tva']     ?? $doc?->montant_tva     ?? 0),
                                        'montant_autres_taxes' => (float)($donneesCorrection['autres_retenues'] ?? $doc?->autres_retenues ?? 0),
                                    ]);
                                }
                                if ($engagement->estBonCommande()) {
                                    $donneesOpt = array_merge($donneesOpt, [
                                        'montant_ir'  => (float)($donneesCorrection['montant_ir']  ?? $doc?->montant_ir  ?? 0),
                                        'montant_tva' => (float)($donneesCorrection['montant_tva'] ?? $doc?->montant_tva ?? 0),
                                        'montant_tsr' => (float)($donneesCorrection['montant_tsr'] ?? $doc?->montant_tsr ?? 0),
                                    ]);
                                }

                                if ($optExistanteTrashed) {
                                    if ($optExistanteTrashed->trashed()) {
                                        $optExistanteTrashed->restore();
                                        Log::info('OPT restaurée (était soft-deleted)', ['engagement' => $engagement->numero, 'opt' => $optExistanteTrashed->numero]);
                                    }
                                    unset($donneesOpt['numero']);
                                    $optExistanteTrashed->update($donneesOpt);
                                    $opt = $optExistanteTrashed;
                                } else {
                                    $opt = \App\Models\OrdonnancePaiement::create($donneesOpt);
                                }

                                $optResultat       = $opt;
                                $optNumeroCorrige  = $numeroFutCorrige;
                                $optNumeroOriginal = $numeroCandidat;

                                if ($opStandardExiste) {
                                    $opStd = $engagement->ordonnancesPaiement()->where('type_ordonnance', 'standard')->first();
                                    if ($opStd && $engagement->estDecision()) {
                                        $mb = (float)($donneesCorrection['montant_brut'] ?? $doc?->montant_brut ?? 0);
                                        $tc = (float)($donneesCorrection['montant_cnps']    ?? $doc?->montant_cnps    ?? 0)
                                            + (float)($donneesCorrection['montant_ir']      ?? $doc?->montant_ir      ?? 0)
                                            + (float)($donneesCorrection['montant_irnc']    ?? $doc?->montant_irnc    ?? 0)
                                            + (float)($donneesCorrection['montant_tva']     ?? $doc?->montant_tva     ?? 0)
                                            + (float)($donneesCorrection['autres_retenues'] ?? $doc?->autres_retenues ?? 0);
                                        $opStd->updateQuietly([
                                            'montant_net' => $mb - $tc,
                                            'montant_brut' => $mb,
                                            'montant_cnps' => (float)($donneesCorrection['montant_cnps'] ?? $doc?->montant_cnps ?? 0),
                                            'montant_ir'   => (float)($donneesCorrection['montant_ir']   ?? $doc?->montant_ir   ?? 0),
                                            'montant_irnc' => (float)($donneesCorrection['montant_irnc'] ?? $doc?->montant_irnc ?? 0),
                                            'montant_tva'  => (float)($donneesCorrection['montant_tva']  ?? $doc?->montant_tva  ?? 0),
                                            'montant_autres_taxes' => (float)($donneesCorrection['autres_retenues'] ?? $doc?->autres_retenues ?? 0),
                                        ]);
                                    }
                                    if ($opStd && $engagement->estBonCommande()) {
                                        $ttc = (float)($donneesCorrection['montant_ttc'] ?? $doc?->montant_ttc ?? 0);
                                        $ir  = (float)($donneesCorrection['montant_ir']  ?? $doc?->montant_ir  ?? 0);
                                        $tva = (float)($donneesCorrection['montant_tva'] ?? $doc?->montant_tva ?? 0);
                                        $tsr = (float)($donneesCorrection['montant_tsr'] ?? $doc?->montant_tsr ?? 0);
                                        $opStd->updateQuietly([
                                            'montant_net'  => $ttc - ($ir + $tva + $tsr),
                                            'montant_brut' => $ttc,
                                            'montant_ir'   => $ir,
                                            'montant_tva' => $tva,
                                            'montant_tsr' => $tsr,
                                        ]);
                                    }
                                }

                                Log::info('OPT créée/restaurée via avenant', ['engagement' => $engagement->numero, 'opt' => $opt->numero, 'taxes' => $nouvellesTaxes]);

                                // ════ CAS 2 : OPT existante → mettre à jour ══════════
                            } elseif ($opImpotExiste && $corrigerOp && !empty($donneesCorrection)) {
                                foreach ($engagement->ordonnancesPaiement()->get() as $op) {
                                    if ($op->type_ordonnance === 'standard') {
                                        if ($doc instanceof \App\Models\DecisionAdministrative) {
                                            $montantBrut    = (float)($donneesCorrection['montant_brut']    ?? $doc->montant_brut);
                                            $montantCnps    = (float)($donneesCorrection['montant_cnps']    ?? $doc->montant_cnps);
                                            $montantIr      = (float)($donneesCorrection['montant_ir']      ?? $doc->montant_ir);
                                            $montantIrnc    = (float)($donneesCorrection['montant_irnc']    ?? $doc->montant_irnc);
                                            $montantTva     = (float)($donneesCorrection['montant_tva']     ?? $doc->montant_tva);
                                            $autresRetenues = (float)($donneesCorrection['autres_retenues'] ?? $doc->autres_retenues);
                                            $totalTaxes     = $montantCnps + $montantIr + $montantIrnc + $montantTva + $autresRetenues;
                                            $op->updateQuietly([
                                                'montant_net' => $montantBrut - $totalTaxes,
                                                'montant_brut' => $montantBrut,
                                                'montant_cnps' => $montantCnps,
                                                'montant_ir' => $montantIr,
                                                'montant_irnc' => $montantIrnc,
                                                'montant_tva' => $montantTva,
                                                'montant_autres_taxes' => $autresRetenues,
                                            ]);
                                        } elseif ($doc instanceof \App\Models\BonCommande) {
                                            $montantTtc = (float)($donneesCorrection['montant_ttc'] ?? $doc->montant_ttc);
                                            $montantIr  = (float)($donneesCorrection['montant_ir']  ?? $doc->montant_ir);
                                            $montantTva = (float)($donneesCorrection['montant_tva'] ?? $doc->montant_tva);
                                            $montantTsr = (float)($donneesCorrection['montant_tsr'] ?? $doc->montant_tsr);
                                            $op->updateQuietly([
                                                'montant_net'  => $montantTtc - ($montantIr + $montantTva + $montantTsr),
                                                'montant_brut' => $montantTtc,
                                                'montant_tva'  => $montantTva,
                                                'montant_ir' => $montantIr,
                                                'montant_tsr' => $montantTsr,
                                            ]);
                                        }
                                    } elseif ($op->type_ordonnance === 'impot') {
                                        if ($doc instanceof \App\Models\DecisionAdministrative) {
                                            $montantCnps    = (float)($donneesCorrection['montant_cnps']    ?? $doc->montant_cnps);
                                            $montantIr      = (float)($donneesCorrection['montant_ir']      ?? $doc->montant_ir);
                                            $montantIrnc    = (float)($donneesCorrection['montant_irnc']    ?? $doc->montant_irnc);
                                            $montantTva     = (float)($donneesCorrection['montant_tva']     ?? $doc->montant_tva);
                                            $autresRetenues = (float)($donneesCorrection['autres_retenues'] ?? $doc->autres_retenues);
                                            $total          = $montantCnps + $montantIr + $montantIrnc + $montantTva + $autresRetenues;
                                            $op->updateQuietly([
                                                'montant_net' => $total,
                                                'montant_impot' => $total,
                                                'montant_brut' => $total,
                                                'montant_cnps' => $montantCnps,
                                                'montant_ir' => $montantIr,
                                                'montant_irnc' => $montantIrnc,
                                                'montant_tva' => $montantTva,
                                                'montant_autres_taxes' => $autresRetenues,
                                            ]);
                                        } elseif ($doc instanceof \App\Models\BonCommande) {
                                            $montantIr  = (float)($donneesCorrection['montant_ir']  ?? $doc->montant_ir);
                                            $montantTva = (float)($donneesCorrection['montant_tva'] ?? $doc->montant_tva);
                                            $montantTsr = (float)($donneesCorrection['montant_tsr'] ?? $doc->montant_tsr);
                                            $total      = $montantIr + $montantTva + $montantTsr;
                                            $op->updateQuietly([
                                                'montant_net' => $total,
                                                'montant_impot' => $total,
                                                'montant_brut' => $total,
                                                'montant_ir' => $montantIr,
                                                'montant_tva' => $montantTva,
                                                'montant_tsr' => $montantTsr,
                                            ]);
                                        }
                                    }
                                    Log::info('OP mise à jour par avenant', ['op_numero' => $op->numero, 'type' => $op->type_ordonnance, 'nouveau_montant_net' => $op->fresh()->montant_net]);
                                }

                                $opImpot = $engagement->ordonnancesPaiement()->where('type_ordonnance', 'impot')->first();
                                if ($opImpot && $nouvellesTaxes <= 0) {
                                    $opImpot->delete();
                                }
                            }

                            // ── Répercussion objet sur les OP (en dernier) ────────
                            if (!empty($donneesCorrection['objet'])) {
                                $objetFinal     = $engagement->objet;
                                $nouvelObjetOpt = 'Reversement impôts et taxes — ' . $objetFinal;
                                $opsAMettreAJour = $engagement->ordonnancesPaiement()->withTrashed()->get();
                                foreach ($opsAMettreAJour as $op) {
                                    $nouvelObjet = $op->type_ordonnance === 'impot' ? $nouvelObjetOpt : $objetFinal;
                                    if ($op->objet !== $nouvelObjet) $op->updateQuietly(['objet' => $nouvelObjet]);
                                }
                                Log::info('Objet répercuté sur les ordonnances', ['engagement' => $engagement->numero, 'nb_op' => $opsAMettreAJour->count()]);
                            }
                        }); // fin DB::transaction

                        // ── Message résumé ────────────────────────────────────
                        $engagement     = $this->record->fresh();
                        $typeCorrection = $data['type_correction'];
                        $msgParts       = ['✅ Avenant appliqué avec succès.'];

                        $opImpot = $engagement->ordonnancesPaiement()->where('type_ordonnance', 'impot')->first();

                        if ($optResultat) {
                            if ($optNumeroCorrige) {
                                $msgParts[] = "⚠️ ATTENTION : Le numéro attendu ({$optNumeroOriginal}) était déjà utilisé.\n"
                                    . "OPT créée avec le numéro alternatif : {$optResultat->numero}";
                            } else {
                                $msgParts[] = "OPT : {$optResultat->numero} — " . number_format($optResultat->montant_net, 0, ',', ' ') . " FCFA";
                            }
                        } elseif ($opImpot) {
                            $msgParts[] = "OPT : {$opImpot->numero} — " . number_format($opImpot->montant_net, 0, ',', ' ') . " FCFA";
                        }

                        // ✅ Message spécifique au changement de nomenclature
                        if (
                            in_array($typeCorrection, ['nomenclature', 'mixte', 'complet'])
                            && !empty($data['nomenclature_corrigee_id'])
                            && (int) $data['nomenclature_corrigee_id'] !== (int) ($engagement->fresh()->nomenclature_principale_id ?? 0)
                        ) {
                            $ancienCode  = \App\Models\NomenclatureBudgetaire::find($engagement->nomenclature_principale_id)?->code ?? '—';
                            $nouveauCode = \App\Models\NomenclatureBudgetaire::find($data['nomenclature_corrigee_id'])?->code ?? '—';
                            $msgParts[]  = "📋 Ligne budgétaire transférée : {$ancienCode} → {$nouveauCode}";
                        }

                        if (!empty($data['nouvel_objet'])) {
                            $msgParts[] = "📝 Objet mis à jour.";
                        }

                        if ($typeCorrection !== 'objet') {
                            if ($deltaFinal > 0)       $msgParts[] = "Augmentation : +" . number_format($deltaFinal, 0, ',', ' ') . " FCFA.";
                            elseif ($deltaFinal < 0)   $msgParts[] = "Réduction : "     . number_format(abs($deltaFinal), 0, ',', ' ') . " FCFA libérés.";
                        }

                        Notification::make()
                            ->title($optNumeroCorrige ? '⚠️ Avenant appliqué — anomalie détectée' : '✅ Avenant appliqué')
                            ->color($optNumeroCorrige ? 'warning' : 'success')
                            ->body(implode("\n\n", $msgParts))
                            ->duration($optNumeroCorrige ? null : 8000)
                            ->persistent($optNumeroCorrige)
                            ->send();

                        return redirect()->route('filament.budget.resources.engagements.view', ['record' => $this->record]);
                    } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                        Log::error('Avenant — contrainte unique violée', ['engagement' => $this->record->numero, 'error' => $e->getMessage()]);
                        Notification::make()->title('❌ Erreur — Numéro OPT en conflit')->danger()
                            ->body("Le numéro d'OPT est déjà utilisé. Aucune modification enregistrée.")->persistent()->send();
                    } catch (\Throwable $e) {
                        Log::error('Erreur avenant', ['engagement' => $this->record->numero, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                        Notification::make()->title('❌ Erreur avenant')->danger()
                            ->body("Une erreur est survenue : " . $e->getMessage() . "\n\nAucune modification enregistrée.")->persistent()->send();
                    }
                }),

            // ── Voir les ordonnances ──────────────────────────────
            Actions\Action::make('voir_ordonnances')
                ->label('Voir les OP')->icon('heroicon-o-eye')->color('info')
                ->visible(fn($record) => $record->hasOrdonnancesPaiement())
                ->modalHeading(fn($record) => "Ordonnances — {$record->numero}")
                ->modalContent(fn($record) => view('filament.modals.ordonnances-list', [
                    'ordonnances' => $record->ordonnancesPaiement()->with('beneficiaire')->get(),
                    'engagement'  => $record,
                ]))
                ->modalWidth('5xl')->modalSubmitAction(false)->modalCancelActionLabel('Fermer'),

            // ── Annuler ───────────────────────────────────────────
            Actions\Action::make('annuler')
                ->label('Annuler l\'engagement')
                ->icon('heroicon-o-x-circle')->color('danger')
                ->visible(
                    fn($record) =>
                    in_array($record->statut, ['provisoire', 'definitif'])
                        && $record->peutEtreAnnule()
                        && auth()->user()?->can('annuler_engagement')
                )
                ->requiresConfirmation()
                ->modalHeading('Annuler l\'engagement')
                ->modalDescription(fn($record) => new \Illuminate\Support\HtmlString(
                    "<div style='color:#dc2626;font-weight:600;font-size:.9rem;line-height:1.8;'>"
                        . "L'engagement <strong>{$record->numero}</strong> sera annulé.<br>"
                        . "Les crédits seront libérés sur la ligne budgétaire.<br>"
                        . ($record->engageable
                            ? "<span style='color:#92400e;'>Le document source ("
                            . ($record->estBonCommande() ? 'Bon de Commande' : 'Décision Administrative')
                            . " <strong>{$record->engageable->numero}</strong>) "
                            . "reviendra à l'état <strong>Validé</strong>.</span>"
                            : "")
                        . "</div>"
                ))
                ->form([
                    Forms\Components\Textarea::make('motif')->label('Motif d\'annulation')
                        ->required()->rows(3)->placeholder('Précisez le motif de l\'annulation...'),
                ])
                ->action(function ($record, array $data) {
                    if ($record->ordonnancesPaiement()->exists()) {
                        Notification::make()->title('❌ Annulation impossible')->danger()->persistent()
                            ->body("Des ordonnances de paiement sont liées.\nAnnulez-les d'abord.")->send();
                        return;
                    }
                    try {
                        DB::transaction(function () use ($record, $data) {
                            $record->annuler(force: true);
                            if ($record->engageable) {
                                if ($record->estBonCommande()) {
                                    $record->engageable->updateQuietly(['statut' => 'valide', 'engage' => false]);
                                } elseif ($record->estDecision()) {
                                    $record->engageable->updateQuietly(['statut' => 'validee']);
                                }
                            }
                        });
                        $msgSource = $record->engageable
                            ? " | " . ($record->estBonCommande() ? 'BC' : 'DA') . " {$record->engageable->numero} → Validé"
                            : "";
                        Notification::make()->title('✅ Engagement annulé')->warning()
                            ->body("Crédits libérés{$msgSource}")->send();
                        return redirect()->route('filament.budget.resources.engagements.index');
                    } catch (\Exception $e) {
                        Notification::make()->title('❌ Erreur')->danger()->body($e->getMessage())->persistent()->send();
                    }
                }),

            // ── PDF : Certificat ──────────────────────────────────
            Actions\Action::make('telecharger_ce')
                ->label('Télécharger CE')->icon('heroicon-o-arrow-down-tray')->color('success')
                ->visible(fn($record) => $record->statut === 'definitif')
                ->form([
                    Forms\Components\Select::make('variante')->label('Modèle d\'état')
                        ->options(fn() => EtatConfig::variantesPour('certificat_engagement'))
                        ->default(fn() => EtatConfig::defautPour('certificat_engagement')?->code)
                        ->required()->helperText('⭐ = modèle par défaut'),
                ])
                ->action(function (array $data) {
                    $this->dispatch('open-url-new-tab', url: route('pdf.telecharger', ['etat' => $data['variante'], 'id' => $this->record->id]));
                }),

            Actions\Action::make('apercu_ce')
                ->label('Aperçu CE')->icon('heroicon-o-eye')->color('info')
                ->visible(fn($record) => $record->statut === 'definitif')
                ->form([
                    Forms\Components\Select::make('variante')->label('Modèle d\'état')
                        ->options(fn() => EtatConfig::variantesPour('certificat_engagement'))
                        ->default(fn() => EtatConfig::defautPour('certificat_engagement')?->code)
                        ->required()->helperText('⭐ = modèle par défaut'),
                ])
                ->action(function (array $data) {
                    $this->dispatch('open-url-new-tab', url: route('pdf.afficher', ['etat' => $data['variante'], 'id' => $this->record->id]));
                }),

            // ── PDF : Autorisation ────────────────────────────────
            Actions\Action::make('telecharger_ae')
                ->label('Télécharger AE')->icon('heroicon-o-arrow-down-tray')->color('primary')
                ->visible(fn($record) => $record->statut === 'definitif')
                ->form([
                    Forms\Components\Select::make('variante')->label('Modèle d\'état')
                        ->options(fn() => EtatConfig::variantesPour('autorisation_engagement'))
                        ->default(fn() => EtatConfig::defautPour('autorisation_engagement')?->code)
                        ->required()->helperText('⭐ = modèle par défaut'),
                ])
                ->action(function (array $data) {
                    $this->dispatch('open-url-new-tab', url: route('pdf.telecharger', ['etat' => $data['variante'], 'id' => $this->record->id]));
                }),

            Actions\Action::make('apercu_ae')
                ->label('Aperçu AE')->icon('heroicon-o-eye')->color('gray')
                ->visible(fn($record) => $record->statut === 'definitif')
                ->form([
                    Forms\Components\Select::make('variante')->label('Modèle d\'état')
                        ->options(fn() => EtatConfig::variantesPour('autorisation_engagement'))
                        ->default(fn() => EtatConfig::defautPour('autorisation_engagement')?->code)
                        ->required()->helperText('⭐ = modèle par défaut'),
                ])
                ->action(function (array $data) {
                    $this->dispatch('open-url-new-tab', url: route('pdf.afficher', ['etat' => $data['variante'], 'id' => $this->record->id]));
                }),
        ];
    }

    // =========================================================
    // INFOLIST — inchangé
    // =========================================================
    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([

            Infolists\Components\Section::make('Informations générales')
                ->schema([
                    Infolists\Components\TextEntry::make('numero')->label('Numéro')->weight('bold')->copyable(),
                    Infolists\Components\TextEntry::make('reference_document')->label('Référence document')->placeholder('Aucune'),
                    Infolists\Components\TextEntry::make('type_engagement')->label('Type')->badge(),
                    Infolists\Components\TextEntry::make('date_engagement')->label('Date engagement')->date('d/m/Y'),
                    Infolists\Components\TextEntry::make('montant_engage')->label('Montant engagé')->money('XAF')->weight('bold')->color('success'),
                    Infolists\Components\TextEntry::make('statut')->label('Statut')->badge()
                        ->color(fn(string $state): string => match ($state) {
                            'provisoire' => 'warning',
                            'definitif' => 'success',
                            'annule' => 'danger',
                            default => 'gray',
                        })
                        ->formatStateUsing(fn(string $state): string => match ($state) {
                            'provisoire' => 'Provisoire',
                            'definitif' => 'Définitif',
                            'annule' => 'Annulé',
                            default => $state,
                        }),
                ])->columns(3),

            Infolists\Components\Section::make('Budget et nomenclature')
                ->schema([
                    Infolists\Components\TextEntry::make('budget.libelle')->label('Budget'),
                    Infolists\Components\TextEntry::make('exercice.annee')->label('Exercice')->badge(),
                    Infolists\Components\TextEntry::make('nomenclaturePrincipale.code')->label('Code nomenclature')->badge()->color('warning'),
                    Infolists\Components\TextEntry::make('nomenclaturePrincipale.libelle')->label('Libellé nomenclature')->columnSpanFull(),
                ])->columns(3),

            Infolists\Components\Section::make('Bénéficiaire')
                ->schema([
                    Infolists\Components\TextEntry::make('beneficiaire_type')
                        ->label('Type de bénéficiaire')
                        ->formatStateUsing(fn($state) => match ($state) {
                            'App\Models\Fournisseur' => 'Fournisseur',
                            'App\Models\Personnel'   => 'Personnel',
                            'App\Models\User'        => 'Utilisateur',
                            default                  => class_basename($state ?? ''),
                        }),
                    Infolists\Components\TextEntry::make('beneficiaire_nom')
                        ->label('Nom')
                        ->getStateUsing(fn($record) => $record->getNomBeneficiaire() ?? 'Non défini'),
                ])
                ->columns(2),

            Infolists\Components\Section::make('Document source')
                ->schema([
                    Infolists\Components\TextEntry::make('engageable_type')
                        ->label('Type de document')
                        ->formatStateUsing(fn($state) => match (class_basename($state ?? '')) {
                            'BonCommande'            => 'Bon de commande',
                            'DecisionAdministrative' => 'Décision administrative',
                            default                  => $state ? class_basename($state) : 'Manuel',
                        })
                        ->badge(),
                    Infolists\Components\TextEntry::make('engageable.numero')
                        ->label('Numéro document')->placeholder('N/A'),
                    Infolists\Components\TextEntry::make('objet')
                        ->label('Objet')->columnSpanFull(),
                ])
                ->columns(2),

            Infolists\Components\Section::make('Détails des montants')
                ->schema([
                    Infolists\Components\TextEntry::make('montant_ttc')
                        ->label(fn($record) => $record->estBonCommande() ? 'Montant TTC' : 'Montant brut')
                        ->money('XAF')->weight('bold')->color('primary')
                        ->getStateUsing(function ($record) {
                            $record->load('engageable');
                            $d = $record->extraireDonneesDocument();
                            return $d['montant_ttc'] ?? $d['montant_brut'] ?? 0;
                        }),

                    Infolists\Components\Grid::make(3)->schema([
                        Infolists\Components\TextEntry::make('retenue_ir')
                            ->label('IR')->money('XAF')->color('danger')
                            ->getStateUsing(fn($record) => $record->extraireDonneesDocument()['montant_ir'] ?? 0),
                        Infolists\Components\TextEntry::make('retenue_tva')
                            ->label('TVA')->money('XAF')->color('danger')
                            ->getStateUsing(fn($record) => $record->extraireDonneesDocument()['montant_tva'] ?? 0),
                        Infolists\Components\TextEntry::make('retenue_tsr')
                            ->label('TSR')->money('XAF')->color('danger')
                            ->getStateUsing(fn($record) => $record->extraireDonneesDocument()['montant_tsr'] ?? 0),
                    ])->visible(fn($record) => $record->estBonCommande()),

                    Infolists\Components\Grid::make(4)->schema([
                        Infolists\Components\TextEntry::make('retenue_cnps')
                            ->label('CNPS')->money('XAF')->color('danger')
                            ->getStateUsing(fn($record) => $record->extraireDonneesDocument()['montant_cnps'] ?? 0)
                            ->visible(fn($record) => ($record->extraireDonneesDocument()['montant_cnps'] ?? 0) > 0),
                        Infolists\Components\TextEntry::make('retenue_irnc')
                            ->label('IRNC')->money('XAF')->color('danger')
                            ->getStateUsing(fn($record) => $record->extraireDonneesDocument()['montant_irnc'] ?? 0)
                            ->visible(fn($record) => ($record->extraireDonneesDocument()['montant_irnc'] ?? 0) > 0),
                        Infolists\Components\TextEntry::make('retenue_tva_da')
                            ->label('TVA')->money('XAF')->color('danger')
                            ->getStateUsing(fn($record) => $record->extraireDonneesDocument()['montant_tva'] ?? 0)
                            ->visible(fn($record) => ($record->extraireDonneesDocument()['montant_tva'] ?? 0) > 0),
                        Infolists\Components\TextEntry::make('autres_retenues')
                            ->label('Autres retenues')->money('XAF')->color('danger')
                            ->getStateUsing(fn($record) => $record->extraireDonneesDocument()['autres_retenues'] ?? 0)
                            ->visible(fn($record) => ($record->extraireDonneesDocument()['autres_retenues'] ?? 0) > 0),
                    ])->visible(fn($record) => $record->estDecision()),

                    Infolists\Components\TextEntry::make('total_retenues')
                        ->label('Total retenues')->money('XAF')->weight('bold')->color('warning')
                        ->getStateUsing(function ($record) {
                            $d = $record->extraireDonneesDocument();
                            if ($record->estBonCommande()) {
                                return ($d['montant_ir'] ?? 0) + ($d['montant_tva'] ?? 0) + ($d['montant_tsr'] ?? 0);
                            }
                            return ($d['montant_cnps'] ?? 0) + ($d['montant_irnc'] ?? 0)
                                + ($d['montant_tva'] ?? 0) + ($d['montant_redevance'] ?? 0)
                                + ($d['montant_feicom'] ?? 0) + ($d['autres_retenues'] ?? 0);
                        }),

                    Infolists\Components\TextEntry::make('montant_net_final')
                        ->label('💰 Montant Net à payer')
                        ->money('XAF')->weight('bold')->color('success')
                        ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                        ->getStateUsing(fn($record) => $record->extraireDonneesDocument()['montant_net'] ?? 0),
                ])
                ->columns(2)
                ->visible(fn($record) => $record->engageable_id !== null)
                ->collapsible(),

            Infolists\Components\Section::make('Lignes d\'engagement')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('lignes')
                        ->label('Détail')
                        ->schema([
                            Infolists\Components\TextEntry::make('numero_ligne')->label('N°'),
                            Infolists\Components\TextEntry::make('nomenclature.code')->label('Code'),
                            Infolists\Components\TextEntry::make('nomenclature.libelle')->label('Libellé'),
                            Infolists\Components\TextEntry::make('montant')->label('Montant')->money('XAF'),
                        ])
                        ->columns(4),
                ])
                ->collapsible()
                ->visible(fn($record) => $record->lignes()->exists()),

            Infolists\Components\Section::make('Ordonnances de paiement')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('ordonnancesPaiement')
                        ->label('Liste')
                        ->schema([
                            Infolists\Components\TextEntry::make('numero')->label('Numéro')->weight('bold'),
                            Infolists\Components\TextEntry::make('type_ordonnance')->label('Type')->badge()
                                ->formatStateUsing(fn($state) => match ($state) {
                                    'standard' => 'Standard',
                                    'impot' => 'Impôt',
                                    default => $state,
                                }),
                            Infolists\Components\TextEntry::make('montant_net')->label('Montant')->money('XAF'),
                            Infolists\Components\TextEntry::make('date_emission')->label('Date')->date('d/m/Y'),
                            Infolists\Components\TextEntry::make('statut')->label('Statut')->badge()
                                ->color(fn(string $state): string => match ($state) {
                                    'emise' => 'success',
                                    'visee' => 'info',
                                    'payee' => 'success',
                                    'annulee' => 'danger',
                                    default => 'gray',
                                })
                                ->formatStateUsing(fn(string $state): string => match ($state) {
                                    'emise' => 'Émise',
                                    'visee' => 'Visée',
                                    'payee' => 'Payée',
                                    'annulee' => 'Annulée',
                                    default => $state,
                                }),
                        ])
                        ->columns(5),
                ])
                ->collapsible()
                ->visible(fn($record) => $record->ordonnancesPaiement()->exists()),

            Infolists\Components\Section::make('Historique')
                ->schema([
                    Infolists\Components\TextEntry::make('created_at')
                        ->label('Créé le')->dateTime('d/m/Y H:i'),
                    Infolists\Components\TextEntry::make('updated_at')
                        ->label('Modifié le')->dateTime('d/m/Y H:i'),
                    Infolists\Components\TextEntry::make('date_validation')
                        ->label('Validé le')->dateTime('d/m/Y H:i')
                        ->visible(fn($record) => $record->date_validation),
                    Infolists\Components\TextEntry::make('engagePar.name')
                        ->label('Validé par')
                        ->visible(fn($record) => $record->engage_par),
                ])
                ->columns(2)->collapsible(),
        ]);
    }
}
