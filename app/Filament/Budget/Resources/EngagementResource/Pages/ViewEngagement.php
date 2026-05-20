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
                    "Voulez-vous passer l'engagement {$record->numero} en statut définitif ?\n" .
                        "Montant : " . number_format($record->montant_engage, 0, ',', ' ') . " FCFA\n\n" .
                        "Cette action est irréversible."
                )
                ->action(function ($record) {
                    try {
                        $record->passerDefinitif(auth()->user());
                        Notification::make()
                            ->title('✅ Engagement passé en définitif')->success()
                            ->body("L'engagement {$record->numero} est maintenant définitif.")
                            ->send();
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
                ->visible(
                    fn($record) =>
                    $record->statut === 'definitif' && !$record->hasOrdonnancesPaiement()
                )
                ->requiresConfirmation()
                ->modalHeading('Créer les ordonnances de paiement')
                ->modalDescription(
                    fn($record) =>
                    "Créer les ordonnances pour l'engagement {$record->numero} ?\n\n" .
                        "Montant : " . number_format($record->montant_engage, 0, ',', ' ') . " FCFA"
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
                            $message .= "• OP Standard : {$ordonnances['standard']->numero} — " .
                                number_format($ordonnances['standard']->montant_net, 0, ',', ' ') . " FCFA\n";
                        if (isset($ordonnances['impot']))
                            $message .= "• OP Impôt : {$ordonnances['impot']->numero} — " .
                                number_format($ordonnances['impot']->montant_net, 0, ',', ' ') . " FCFA";
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
                        && auth()->user()?->can('create_avenant_engagement')
                )
                ->form([
                    Forms\Components\Placeholder::make('info')
                        ->label('')
                        ->content(new \Illuminate\Support\HtmlString(
                            '<div style="background:#fef9c3;border:1px solid #ca8a04;border-radius:.5rem;padding:.75rem;">
                            ⚠️ <strong>Avenant</strong> — Rectification d\'un engagement définitif.<br>
                            La ligne budgétaire et les ordonnances seront mises à jour selon vos corrections.
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

                    // ── Nouveaux montants ─────────────────────────
                    Forms\Components\Section::make('Nouveaux montants du document')
                        ->schema([
                            // DA — montant brut
                            Forms\Components\TextInput::make('montant_brut')
                                ->label('Montant brut (FCFA)')->numeric()->prefix('FCFA')
                                ->helperText(
                                    fn() =>
                                    'Actuel : ' . number_format(
                                        $this->record->engageable?->montant_brut ?? $this->record->montant_engage,
                                        0,
                                        ',',
                                        ' '
                                    ) . ' FCFA'
                                )
                                ->visible(
                                    fn(Get $get) =>
                                    in_array($get('type_correction'), ['montant', 'mixte', 'complet'])
                                        && $this->record->estDecision()
                                ),

                            // DA — taxes
                            Forms\Components\TextInput::make('montant_cnps')
                                ->label('CNPS (FCFA)')->numeric()->prefix('FCFA')->default(0)
                                ->helperText(
                                    fn() =>
                                    'Actuel : ' . number_format($this->record->engageable?->montant_cnps ?? 0, 0, ',', ' ') . ' FCFA'
                                )
                                ->visible(
                                    fn(Get $get) =>
                                    in_array($get('type_correction'), ['taxes', 'mixte', 'complet'])
                                        && $this->record->estDecision()
                                ),

                            // ✅ FIX 1 — montant_ir visible pour DA ET BC
                            Forms\Components\TextInput::make('montant_ir')
                                ->label('IR (FCFA)')->numeric()->prefix('FCFA')->default(0)
                                ->helperText(
                                    fn() =>
                                    'Actuel : ' . number_format($this->record->engageable?->montant_ir ?? 0, 0, ',', ' ') . ' FCFA'
                                )
                                ->visible(
                                    fn(Get $get) =>
                                    in_array($get('type_correction'), ['taxes', 'mixte', 'complet'])
                                        && ($this->record->estDecision() || $this->record->estBonCommande())
                                ),

                            Forms\Components\TextInput::make('montant_irnc')
                                ->label('IRNC (FCFA)')->numeric()->prefix('FCFA')->default(0)
                                ->helperText(
                                    fn() =>
                                    'Actuel : ' . number_format($this->record->engageable?->montant_irnc ?? 0, 0, ',', ' ') . ' FCFA'
                                )
                                ->visible(
                                    fn(Get $get) =>
                                    in_array($get('type_correction'), ['taxes', 'mixte', 'complet'])
                                        && $this->record->estDecision()
                                ),

                            Forms\Components\TextInput::make('montant_tva')
                                ->label('TVA (FCFA)')->numeric()->prefix('FCFA')->default(0)
                                ->helperText(
                                    fn() =>
                                    'Actuel : ' . number_format($this->record->engageable?->montant_tva ?? 0, 0, ',', ' ') . ' FCFA'
                                )
                                ->visible(
                                    fn(Get $get) =>
                                    in_array($get('type_correction'), ['taxes', 'mixte', 'complet'])
                                ),

                            Forms\Components\TextInput::make('autres_retenues')
                                ->label('Autres retenues (FCFA)')->numeric()->prefix('FCFA')->default(0)
                                ->helperText(
                                    fn() =>
                                    'Actuel : ' . number_format($this->record->engageable?->autres_retenues ?? 0, 0, ',', ' ') . ' FCFA'
                                )
                                ->visible(
                                    fn(Get $get) =>
                                    in_array($get('type_correction'), ['taxes', 'mixte', 'complet'])
                                        && $this->record->estDecision()
                                ),

                            // BC — montants
                            Forms\Components\TextInput::make('montant_ht')
                                ->label('Montant HT (FCFA)')->numeric()->prefix('FCFA')
                                ->helperText(
                                    fn() =>
                                    'Actuel : ' . number_format($this->record->engageable?->montant_ht ?? 0, 0, ',', ' ') . ' FCFA'
                                )
                                ->visible(
                                    fn(Get $get) =>
                                    in_array($get('type_correction'), ['montant', 'mixte', 'complet'])
                                        && $this->record->estBonCommande()
                                ),

                            Forms\Components\TextInput::make('montant_ttc')
                                ->label('Montant TTC (FCFA)')->numeric()->prefix('FCFA')
                                ->helperText(
                                    fn() =>
                                    'Actuel : ' . number_format($this->record->engageable?->montant_ttc ?? 0, 0, ',', ' ') . ' FCFA'
                                )
                                ->visible(
                                    fn(Get $get) =>
                                    in_array($get('type_correction'), ['montant', 'mixte', 'complet'])
                                        && $this->record->estBonCommande()
                                ),

                            Forms\Components\TextInput::make('montant_tsr')
                                ->label('TSR (FCFA)')->numeric()->prefix('FCFA')->default(0)
                                ->helperText(
                                    fn() =>
                                    'Actuel : ' . number_format($this->record->engageable?->montant_tsr ?? 0, 0, ',', ' ') . ' FCFA'
                                )
                                ->visible(
                                    fn(Get $get) =>
                                    in_array($get('type_correction'), ['taxes', 'mixte', 'complet'])
                                        && $this->record->estBonCommande()
                                ),
                        ])
                        ->columns(2)
                        ->visible(
                            fn(Get $get) =>
                            in_array($get('type_correction'), ['montant', 'mixte', 'taxes', 'complet'])
                        ),

                    // ── Nouvelle ligne budgétaire ──────────────────
                    Forms\Components\Select::make('nomenclature_corrigee_id')
                        ->label('Nouvelle ligne budgétaire')
                        ->options(function () {
                            return \App\Models\LigneBudgetaire::where('budget_id', $this->record->budget_id)
                                ->with('nomenclature')->get()
                                ->filter(fn($lb) => $lb->nomenclature)
                                ->mapWithKeys(fn($lb) => [
                                    $lb->nomenclature_id =>
                                    "{$lb->nomenclature->code} - {$lb->nomenclature->libelle} " .
                                        "(Dispo: " . number_format($lb->disponible_engagement, 0, ',', ' ') . " FCFA)"
                                ])->toArray();
                        })
                        ->searchable()
                        ->required(
                            fn(Get $get) =>
                            in_array($get('type_correction'), ['nomenclature', 'mixte', 'complet'])
                        )
                        ->visible(
                            fn(Get $get) =>
                            in_array($get('type_correction'), ['nomenclature', 'mixte', 'complet'])
                        )
                        ->helperText(
                            fn() =>
                            'Actuelle : ' . ($this->record->nomenclaturePrincipale?->code ?? '—')
                        ),

                    // ── OP Impôt existante ─────────────────────────
                    Forms\Components\Section::make('Ordonnance impôt existante')
                        ->schema([
                            Forms\Components\Placeholder::make('op_impot_info')
                                ->label('')
                                ->content(function () {
                                    $op = $this->record->ordonnancesPaiement()
                                        ->where('type_ordonnance', 'impot')->first();
                                    if (!$op) return new \Illuminate\Support\HtmlString(
                                        '<span style="color:#6b7280;">Aucune OP impôt associée.</span>'
                                    );
                                    return new \Illuminate\Support\HtmlString(
                                        "<div style='background:#f0fdf4;border:1px solid #86efac;border-radius:.375rem;padding:.5rem .75rem;'>
                                        OP Impôt : <strong>{$op->numero}</strong> — " .
                                            number_format($op->montant_net, 0, ',', ' ') . " FCFA
                                        </div>"
                                    );
                                })
                                ->columnSpanFull(),
                        ])
                        ->visible(
                            fn(Get $get) =>
                            in_array($get('type_correction'), ['taxes', 'complet'])
                                && $this->record->ordonnancesPaiement()
                                ->where('type_ordonnance', 'impot')->exists()
                        )
                        ->collapsed(false),

                    // ── Corriger les OP ────────────────────────────
                    Forms\Components\Toggle::make('corriger_ordonnances')
                        ->label('Mettre à jour les ordonnances de paiement existantes')
                        ->default(true)
                        ->helperText('Recalcule les montants des OP Standard et OP Impôt')
                        ->visible(fn() => $this->record->ordonnancesPaiement()->exists())
                        ->inline(false),

                    // ── Motif ──────────────────────────────────────
                    Forms\Components\Textarea::make('motif')
                        ->label('Motif de la rectification')
                        ->required()->rows(3)
                        ->placeholder('Ex: Erreur de saisie, correction des taxes...'),
                ])
                ->modalHeading('Créer un avenant de rectification')
                ->modalWidth('2xl')
                ->action(function (array $data) {
                    try {
                        $engagement     = $this->record;
                        $typeCorrection = $data['type_correction'];

                        $montantOriginal = (float) $engagement->montant_engage;

                        // ── Déterminer le nouveau montant principal ────
                        if ($engagement->estDecision()) {
                            $montantCorrige = isset($data['montant_brut'])
                                ? (float) $data['montant_brut']
                                : $montantOriginal;
                        } elseif ($engagement->estBonCommande()) {
                            $montantCorrige = isset($data['montant_ttc'])
                                ? (float) $data['montant_ttc']
                                : $montantOriginal;
                        } else {
                            $montantCorrige = $montantOriginal;
                        }

                        // Pour taxes uniquement → montant engagement inchangé
                        if (in_array($typeCorrection, ['taxes', 'objet'])) {
                            $montantCorrige = $montantOriginal;
                        }

                        $nomenclatureOriginaleId = $engagement->nomenclature_principale_id;
                        $nomenclatureCorrigeeId  = $data['nomenclature_corrigee_id']
                            ?? $nomenclatureOriginaleId;

                        $delta      = $montantCorrige - $montantOriginal;
                        $corrigerOp = (bool) ($data['corriger_ordonnances'] ?? false);

                        // ── Collecter les corrections du document ──────
                        $donneesCorrection = array_filter([
                            // DA
                            'montant_brut'    => $data['montant_brut']    ?? null,
                            'montant_cnps'    => $data['montant_cnps']    ?? null,
                            'montant_irnc'    => $data['montant_irnc']    ?? null,
                            'montant_tva'     => $data['montant_tva']     ?? null,
                            'autres_retenues' => $data['autres_retenues'] ?? null,
                            // BC
                            'montant_ht'      => $data['montant_ht']      ?? null,
                            'montant_ttc'     => $data['montant_ttc']     ?? null,
                            'montant_tsr'     => $data['montant_tsr']     ?? null,
                            // DA + BC
                            'montant_ir'      => $data['montant_ir']      ?? null,
                        ], fn($v) => $v !== null && $v !== '');

                        // ── Créer l'avenant ────────────────────────────
                        $avenant = Avenant::create([
                            'document_source_type'     => $engagement->engageable_type,
                            'document_source_id'       => $engagement->engageable_id,
                            'document_corrige_type'    => $engagement->engageable_type,
                            'document_corrige_id'      => $engagement->engageable_id,
                            'engagement_original_id'   => $engagement->id,
                            'numero_avenant'           => Avenant::prochainNumero($engagement->id),
                            'motif'                    => $data['motif'],
                            'type_correction'          => $typeCorrection,
                            'montant_original'         => $montantOriginal,
                            'montant_corrige'          => $montantCorrige,
                            'delta_montant'            => $delta,
                            'nomenclature_originale_id' => $nomenclatureOriginaleId,
                            'nomenclature_corrigee_id' => $nomenclatureCorrigeeId,
                            'statut'                   => 'brouillon',
                            'created_by'               => auth()->id(),
                            'donnees_correction'       => !empty($donneesCorrection)
                                ? $donneesCorrection : null,
                        ]);

                        // ── Appliquer l'avenant (LB + engagement) ─────
                        $avenant->appliquer();

                        // ── Mettre à jour le document source ──────────
                        if (!empty($donneesCorrection) && $engagement->engageable_id) {
                            $doc = $engagement->engageable;

                            \Illuminate\Database\Eloquent\Model::withoutEvents(function () use ($doc, $donneesCorrection) {
                                if ($doc instanceof \App\Models\DecisionAdministrative) {
                                    $montantBrut    = (float)($donneesCorrection['montant_brut']    ?? $doc->montant_brut);
                                    $montantCnps    = (float)($donneesCorrection['montant_cnps']    ?? $doc->montant_cnps);
                                    $montantIr      = (float)($donneesCorrection['montant_ir']      ?? $doc->montant_ir);
                                    $montantIrnc    = (float)($donneesCorrection['montant_irnc']    ?? $doc->montant_irnc);
                                    $montantTva     = (float)($donneesCorrection['montant_tva']     ?? $doc->montant_tva);
                                    $autresRetenues = (float)($donneesCorrection['autres_retenues'] ?? $doc->autres_retenues);

                                    $totalTaxes = $montantCnps + $montantIr + $montantIrnc + $montantTva + $autresRetenues;
                                    $montantNet = $montantBrut - $totalTaxes;

                                    $doc->updateQuietly(array_merge($donneesCorrection, [
                                        'montant_ir'  => $montantIr,
                                        'total_taxes' => $totalTaxes,
                                        'montant_net' => $montantNet,
                                        'mode_saisie' => 'forfait',
                                    ]));
                                } elseif ($doc instanceof \App\Models\BonCommande) {
                                    $montantTtc = (float)($donneesCorrection['montant_ttc'] ?? $doc->montant_ttc);
                                    $montantIr  = (float)($donneesCorrection['montant_ir']  ?? $doc->montant_ir);
                                    $montantTva = (float)($donneesCorrection['montant_tva'] ?? $doc->montant_tva);
                                    $montantTsr = (float)($donneesCorrection['montant_tsr'] ?? $doc->montant_tsr);

                                    $doc->updateQuietly(array_merge($donneesCorrection, [
                                        'net_a_percevoir' => $montantTtc - ($montantIr + $montantTva + $montantTsr),
                                    ]));
                                }

                                \Log::info("Document source mis à jour par avenant", [
                                    'type'        => get_class($doc),
                                    'id'          => $doc->id,
                                    'corrections' => $donneesCorrection,
                                ]);
                            });
                        }

                        // ── Mettre à jour les OP si demandé ───────────
                        if (
                            $corrigerOp && $engagement->ordonnancesPaiement()->exists()
                            && !empty($donneesCorrection)
                        ) {

                            $doc = $engagement->engageable;

                            foreach ($engagement->ordonnancesPaiement()->get() as $op) {

                                if ($op->type_ordonnance === 'standard') {
                                    if ($doc instanceof \App\Models\DecisionAdministrative) {
                                        $montantBrut    = (float)($donneesCorrection['montant_brut']    ?? $doc->montant_brut);
                                        $montantCnps    = (float)($donneesCorrection['montant_cnps']    ?? $doc->montant_cnps);
                                        $montantIr      = (float)($donneesCorrection['montant_ir']      ?? $doc->montant_ir);
                                        $montantIrnc    = (float)($donneesCorrection['montant_irnc']    ?? $doc->montant_irnc);
                                        $montantTva     = (float)($donneesCorrection['montant_tva']     ?? $doc->montant_tva);
                                        $autresRetenues = (float)($donneesCorrection['autres_retenues'] ?? $doc->autres_retenues);
                                        // ✅ FIX 2 — montant_ir inclus dans totalTaxes DA
                                        $totalTaxes     = $montantCnps + $montantIr + $montantIrnc + $montantTva + $autresRetenues;

                                        $op->updateQuietly([
                                            'montant_net'          => $montantBrut - $totalTaxes,
                                            'montant_brut'         => $montantBrut,
                                            'montant_cnps'         => $montantCnps,
                                            'montant_ir'           => $montantIr,
                                            'montant_irnc'         => $montantIrnc,
                                            'montant_tva'          => $montantTva,
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
                                            'montant_ir'   => $montantIr,
                                            'montant_tsr'  => $montantTsr,
                                        ]);
                                    }
                                } elseif ($op->type_ordonnance === 'impot') {
                                    if ($doc instanceof \App\Models\DecisionAdministrative) {
                                        $montantCnps    = (float)($donneesCorrection['montant_cnps']    ?? $doc->montant_cnps);
                                        // ✅ FIX 3 — montant_ir inclus dans OPT DA
                                        $montantIr      = (float)($donneesCorrection['montant_ir']      ?? $doc->montant_ir);
                                        $montantIrnc    = (float)($donneesCorrection['montant_irnc']    ?? $doc->montant_irnc);
                                        $montantTva     = (float)($donneesCorrection['montant_tva']     ?? $doc->montant_tva);
                                        $autresRetenues = (float)($donneesCorrection['autres_retenues'] ?? $doc->autres_retenues);

                                        $op->updateQuietly([
                                            'montant_net'          => $montantCnps + $montantIr + $montantIrnc + $montantTva + $autresRetenues,
                                            'montant_cnps'         => $montantCnps,
                                            'montant_ir'           => $montantIr,
                                            'montant_irnc'         => $montantIrnc,
                                            'montant_tva'          => $montantTva,
                                            'montant_autres_taxes' => $autresRetenues,
                                        ]);
                                    } elseif ($doc instanceof \App\Models\BonCommande) {
                                        $montantIr  = (float)($donneesCorrection['montant_ir']  ?? $doc->montant_ir);
                                        $montantTva = (float)($donneesCorrection['montant_tva'] ?? $doc->montant_tva);
                                        $montantTsr = (float)($donneesCorrection['montant_tsr'] ?? $doc->montant_tsr);

                                        $op->updateQuietly([
                                            'montant_net' => $montantIr + $montantTva + $montantTsr,
                                            'montant_ir'  => $montantIr,
                                            'montant_tva' => $montantTva,
                                            'montant_tsr' => $montantTsr,
                                        ]);
                                    }
                                }

                                \Log::info("OP mise à jour par avenant", [
                                    'op_numero'          => $op->numero,
                                    'type'               => $op->type_ordonnance,
                                    'nouveau_montant_net' => $op->fresh()->montant_net,
                                ]);
                            }
                        }

                        // ── Supprimer l'OP Impôt si toutes les taxes sont nulles ──
                        if ($corrigerOp) {
                            $opImpot = $engagement->ordonnancesPaiement()
                                ->where('type_ordonnance', 'impot')
                                ->first();

                            if ($opImpot) {
                                $totalTaxesCorrige = 0;

                                if ($engagement->estDecision()) {
                                    $doc = $engagement->engageable;
                                    // ✅ FIX 4 — montant_ir inclus dans vérification suppression OPT
                                    $totalTaxesCorrige =
                                        (float)($donneesCorrection['montant_cnps']    ?? $doc->montant_cnps    ?? 0)
                                        + (float)($donneesCorrection['montant_ir']      ?? $doc->montant_ir      ?? 0)
                                        + (float)($donneesCorrection['montant_irnc']    ?? $doc->montant_irnc    ?? 0)
                                        + (float)($donneesCorrection['montant_tva']     ?? $doc->montant_tva     ?? 0)
                                        + (float)($donneesCorrection['autres_retenues'] ?? $doc->autres_retenues ?? 0);
                                } elseif ($engagement->estBonCommande()) {
                                    $doc = $engagement->engageable;
                                    $totalTaxesCorrige =
                                        (float)($donneesCorrection['montant_ir']  ?? $doc->montant_ir  ?? 0)
                                        + (float)($donneesCorrection['montant_tva'] ?? $doc->montant_tva ?? 0)
                                        + (float)($donneesCorrection['montant_tsr'] ?? $doc->montant_tsr ?? 0);
                                }

                                if ($totalTaxesCorrige <= 0) {
                                    $opImpot->delete();
                                    \Log::info("OPT supprimée car taxes = 0 après avenant", [
                                        'op_numero'  => $opImpot->numero,
                                        'engagement' => $engagement->numero,
                                    ]);
                                    $msg .= "\n🗑️ OP Impôt supprimée (taxes nulles).";
                                }
                            }
                        }

                        // ── Message résumé ─────────────────────────────
                        $msg = "Avenant #{$avenant->numero_avenant} appliqué.\n";
                        if ($typeCorrection === 'objet') {
                            $msg .= "Aucun impact budgétaire.";
                        } elseif ($delta > 0) {
                            $msg .= "Augmentation : +" . number_format($delta, 0, ',', ' ') . " FCFA engagés.";
                        } elseif ($delta < 0) {
                            $msg .= "Réduction : " . number_format(abs($delta), 0, ',', ' ') . " FCFA libérés.";
                        }
                        if ($corrigerOp && $engagement->ordonnancesPaiement()->exists()) {
                            $msg .= "\n✅ Ordonnances mises à jour.";
                        }

                        Notification::make()->title('✅ Avenant appliqué')
                            ->success()->body($msg)->duration(8000)->send();

                        return redirect()->route(
                            'filament.budget.resources.engagements.view',
                            ['record' => $this->record]
                        );
                    } catch (\Exception $e) {
                        Notification::make()->title('❌ Erreur avenant')
                            ->danger()->body($e->getMessage())->persistent()->send();
                    }
                }),

            // ── Voir les ordonnances ──────────────────────────────
            Actions\Action::make('voir_ordonnances')
                ->label('Voir les OP')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->visible(fn($record) => $record->hasOrdonnancesPaiement())
                ->modalHeading(fn($record) => "Ordonnances — {$record->numero}")
                ->modalContent(function ($record) {
                    return view('filament.modals.ordonnances-list', [
                        'ordonnances' => $record->ordonnancesPaiement()->with('beneficiaire')->get(),
                        'engagement'  => $record,
                    ]);
                })
                ->modalWidth('5xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fermer'),

            // ── Annuler ───────────────────────────────────────────
            Actions\Action::make('annuler')
                ->label('Annuler')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn($record) => $record->statut === 'provisoire' && $record->peutEtreAnnule())
                ->requiresConfirmation()
                ->modalHeading('Annuler l\'engagement')
                ->modalDescription('Confirmer l\'annulation ? Le budget sera libéré.')
                ->action(function ($record) {
                    try {
                        $record->annuler();
                        Notification::make()->title('✅ Engagement annulé')->success()
                            ->body("L'engagement {$record->numero} a été annulé.")->send();
                        return redirect()->route('filament.budget.resources.engagements.index');
                    } catch (\Exception $e) {
                        Notification::make()->title('❌ Erreur')->danger()
                            ->body($e->getMessage())->persistent()->send();
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
                    $this->dispatch('open-url-new-tab', url: route('pdf.telecharger', [
                        'etat' => $data['variante'],
                        'id' => $this->record->id,
                    ]));
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
                    $this->dispatch('open-url-new-tab', url: route('pdf.afficher', [
                        'etat' => $data['variante'],
                        'id' => $this->record->id,
                    ]));
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
                    $this->dispatch('open-url-new-tab', url: route('pdf.telecharger', [
                        'etat' => $data['variante'],
                        'id' => $this->record->id,
                    ]));
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
                    $this->dispatch('open-url-new-tab', url: route('pdf.afficher', [
                        'etat' => $data['variante'],
                        'id' => $this->record->id,
                    ]));
                }),
        ];
    }

    // =========================================================
    // INFOLIST (inchangé)
    // =========================================================
    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([

            Infolists\Components\Section::make('Informations générales')
                ->schema([
                    Infolists\Components\TextEntry::make('numero')
                        ->label('Numéro')->weight('bold')->copyable(),
                    Infolists\Components\TextEntry::make('reference_document')
                        ->label('Référence document')->placeholder('Aucune'),
                    Infolists\Components\TextEntry::make('type_engagement')
                        ->label('Type')->badge(),
                    Infolists\Components\TextEntry::make('date_engagement')
                        ->label('Date engagement')->date('d/m/Y'),
                    Infolists\Components\TextEntry::make('montant_engage')
                        ->label('Montant engagé')->money('XAF')->weight('bold')->color('success'),
                    Infolists\Components\TextEntry::make('statut')
                        ->label('Statut')->badge()
                        ->color(fn(string $state): string => match ($state) {
                            'provisoire' => 'warning',
                            'definitif'  => 'success',
                            'annule'     => 'danger',
                            default      => 'gray',
                        })
                        ->formatStateUsing(fn(string $state): string => match ($state) {
                            'provisoire' => 'Provisoire',
                            'definitif'  => 'Définitif',
                            'annule'     => 'Annulé',
                            default      => $state,
                        }),
                ])
                ->columns(3),

            Infolists\Components\Section::make('Budget et nomenclature')
                ->schema([
                    Infolists\Components\TextEntry::make('budget.libelle')->label('Budget'),
                    Infolists\Components\TextEntry::make('exercice.annee')->label('Exercice')->badge(),
                    Infolists\Components\TextEntry::make('nomenclaturePrincipale.code')
                        ->label('Code nomenclature')->badge()->color('warning'),
                    Infolists\Components\TextEntry::make('nomenclaturePrincipale.libelle')
                        ->label('Libellé nomenclature')->columnSpanFull(),
                ])
                ->columns(3),

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

            // ── Détails des montants ──────────────────────────────
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

                    // Retenues BC
                    Infolists\Components\Grid::make(3)
                        ->schema([
                            Infolists\Components\TextEntry::make('retenue_ir')
                                ->label('IR')->money('XAF')->color('danger')
                                ->getStateUsing(fn($record) => $record->extraireDonneesDocument()['montant_ir'] ?? 0),
                            Infolists\Components\TextEntry::make('retenue_tva')
                                ->label('TVA')->money('XAF')->color('danger')
                                ->getStateUsing(fn($record) => $record->extraireDonneesDocument()['montant_tva'] ?? 0),
                            Infolists\Components\TextEntry::make('retenue_tsr')
                                ->label('TSR')->money('XAF')->color('danger')
                                ->getStateUsing(fn($record) => $record->extraireDonneesDocument()['montant_tsr'] ?? 0),
                        ])
                        ->visible(fn($record) => $record->estBonCommande()),

                    // Retenues DA
                    Infolists\Components\Grid::make(4)
                        ->schema([
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
                            Infolists\Components\TextEntry::make('retenue_redevance')
                                ->label('Redevance AV')->money('XAF')->color('danger')
                                ->getStateUsing(fn($record) => $record->extraireDonneesDocument()['montant_redevance'] ?? 0)
                                ->visible(fn($record) => ($record->extraireDonneesDocument()['montant_redevance'] ?? 0) > 0),
                            Infolists\Components\TextEntry::make('retenue_feicom')
                                ->label('FEICOM')->money('XAF')->color('danger')
                                ->getStateUsing(fn($record) => $record->extraireDonneesDocument()['montant_feicom'] ?? 0)
                                ->visible(fn($record) => ($record->extraireDonneesDocument()['montant_feicom'] ?? 0) > 0),
                            Infolists\Components\TextEntry::make('autres_retenues')
                                ->label('Autres retenues')->money('XAF')->color('danger')
                                ->getStateUsing(fn($record) => $record->extraireDonneesDocument()['autres_retenues'] ?? 0)
                                ->visible(fn($record) => ($record->extraireDonneesDocument()['autres_retenues'] ?? 0) > 0),
                        ])
                        ->visible(fn($record) => $record->estDecision()),

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
                    Infolists\Components\TextEntry::make('created_at')->label('Créé le')->dateTime('d/m/Y H:i'),
                    Infolists\Components\TextEntry::make('updated_at')->label('Modifié le')->dateTime('d/m/Y H:i'),
                    Infolists\Components\TextEntry::make('date_validation')->label('Validé le')
                        ->dateTime('d/m/Y H:i')->visible(fn($record) => $record->date_validation),
                    Infolists\Components\TextEntry::make('engagePar.name')->label('Validé par')
                        ->visible(fn($record) => $record->engage_par),
                ])
                ->columns(2)->collapsible(),
        ]);
    }
}
