<?php

namespace App\Filament\Budget\Resources\MemoireDepenseResource\Pages;

use App\Filament\Budget\Resources\MemoireDepenseResource;
use App\Filament\Budget\Resources\DecisionAdministrativeResource;
use App\Models\DecisionAdministrative;
use App\Models\TypeDecision;
use App\Models\User;
use App\Models\Transmission;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;

class ViewMemoireDepense extends ViewRecord
{
    protected static string $resource = MemoireDepenseResource::class;

    // =========================================================
    // INFOLIST — affichage des données avec mode saisie correct
    // =========================================================
    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([

            Infolists\Components\Section::make('Informations générales')
                ->schema([
                    Infolists\Components\Grid::make(4)->schema([

                        Infolists\Components\TextEntry::make('numero')
                            ->label('Numéro')
                            ->weight('bold')->copyable(),

                        Infolists\Components\TextEntry::make('date_memoire')
                            ->label('Date')->date('d/m/Y'),

                        Infolists\Components\TextEntry::make('exercice')
                            ->label('Exercice')->badge()->color('info'),

                        Infolists\Components\TextEntry::make('statut')
                            ->label('Statut')->badge()
                            ->color(fn($state) => match ($state) {
                                'brouillon'  => 'gray',
                                'valide'     => 'success',
                                'transmis'   => 'info',
                                'transforme' => 'warning',
                                'annule'     => 'danger',
                                default      => 'gray',
                            })
                            ->formatStateUsing(fn($state) => match ($state) {
                                'brouillon'  => '✏️ Brouillon',
                                'valide'     => '✅ Validé',
                                'transmis'   => '📤 Transmis',
                                'transforme' => '🔄 Transformé en DA',
                                'annule'     => '❌ Annulé',
                                default      => $state,
                            }),
                    ]),

                    Infolists\Components\TextEntry::make('objet')
                        ->label('Objet')->columnSpanFull(),
                ])
                ->columns(1),

            // ✅ Mode de saisie persisté
            Infolists\Components\Section::make('Paramètres de saisie')
                ->schema([
                    Infolists\Components\Grid::make(3)->schema([

                        Infolists\Components\TextEntry::make('mode_saisie')
                            ->label('Mode de saisie utilisé')
                            ->badge()
                            ->color(fn($state) => match ($state) {
                                'montant_nap'   => 'success',
                                'prix_unitaire' => 'info',
                                default         => 'gray',
                            })
                            ->formatStateUsing(fn($state) => match ($state) {
                                'montant_nap'   => '📊 Montant NAP (Net à Payer)',
                                'prix_unitaire' => '💰 Prix Unitaire HT',
                                default         => '—',
                            }),

                        Infolists\Components\TextEntry::make('taux_tva_affiche')
                            ->label('Taux TVA')
                            ->getStateUsing(
                                fn($record) => ($record->lignes->first()?->taux_tva ?? 19.25) . ' %'
                            )
                            ->badge()->color('warning'),

                        Infolists\Components\TextEntry::make('taux_ir_affiche')
                            ->label('Taux IR')
                            ->getStateUsing(
                                fn($record) => ($record->lignes->first()?->taux_ir ?? 5.5) . ' %'
                            )
                            ->badge()->color('danger'),
                    ]),
                ])
                ->collapsible(),

            // ── Références ─────────────────────────────────────────
            Infolists\Components\Section::make('Références')
                ->schema([
                    Infolists\Components\Grid::make(4)->schema([
                        Infolists\Components\TextEntry::make('numero_decision')
                            ->label('N° Décision')->placeholder('—'),
                        Infolists\Components\TextEntry::make('date_decision')
                            ->label('Date décision')->date('d/m/Y')->placeholder('—'),
                        Infolists\Components\TextEntry::make('numero_ce')
                            ->label('N° CE')->placeholder('—'),
                        Infolists\Components\TextEntry::make('date_ce')
                            ->label('Date CE')->date('d/m/Y')->placeholder('—'),
                    ]),
                ])
                ->collapsible()->collapsed(),

            // ── Récapitulatif financier ─────────────────────────────
            Infolists\Components\Section::make('Récapitulatif financier')
                ->schema([
                    Infolists\Components\Grid::make(5)->schema([

                        // ✅ Totaux calculés avec la même règle que les blades :
                        //    Σ valeurs arrondies individuellement → cohérence colonnes/totaux
                        //    TTC = Σ(round(HT)) + Σ(round(TVA)) → pas Σ(round(TTC))

                        Infolists\Components\TextEntry::make('total_ht')
                            ->label('Montant HT')
                            ->getStateUsing(
                                fn($record) =>
                                number_format(
                                    $record->lignes->reduce(
                                        fn($carry, $l) => $carry + (int) round((float)($l->montant_ht ?? 0)),
                                        0
                                    ),
                                    0,
                                    ',',
                                    ' '
                                ) . ' FCFA'
                            ),

                        Infolists\Components\TextEntry::make('total_tva')
                            ->label('TVA')
                            ->getStateUsing(
                                fn($record) =>
                                number_format(
                                    $record->lignes->reduce(
                                        fn($carry, $l) => $carry + (int) round((float)($l->montant_tva ?? 0)),
                                        0
                                    ),
                                    0,
                                    ',',
                                    ' '
                                ) . ' FCFA'
                            )
                            ->color('warning'),

                        Infolists\Components\TextEntry::make('total_ttc')
                            ->label('Montant TTC')
                            ->getStateUsing(fn($record) => number_format(
                                // ✅ TTC = Σ round(HT) + Σ round(TVA) — cohérent avec blades
                                $record->lignes->reduce(fn($c, $l) => $c + (int) round((float)($l->montant_ht ?? 0)), 0)
                                    + $record->lignes->reduce(fn($c, $l) => $c + (int) round((float)($l->montant_tva ?? 0)), 0),
                                0,
                                ',',
                                ' '
                            ) . ' FCFA')
                            ->weight('bold')->color('primary'),

                        Infolists\Components\TextEntry::make('total_ir')
                            ->label('IR retenu')
                            ->getStateUsing(
                                fn($record) =>
                                number_format(
                                    $record->lignes->reduce(
                                        fn($carry, $l) => $carry + (int) round((float)($l->montant_ir ?? 0)),
                                        0
                                    ),
                                    0,
                                    ',',
                                    ' '
                                ) . ' FCFA'
                            )
                            ->color('danger'),

                        Infolists\Components\TextEntry::make('total_nap')
                            ->label('💰 Net à Payer')
                            ->getStateUsing(
                                fn($record) =>
                                number_format(
                                    $record->lignes->reduce(function ($carry, $l) {
                                        $nap = (float)($l->montant_net ?? $l->net_a_payer ?? 0);
                                        if ($nap <= 0 && ($l->montant_ht ?? 0) > 0) {
                                            $nap = (float)$l->montant_ht - (float)($l->montant_ir ?? 0);
                                        }
                                        return $carry + (int) round($nap);
                                    }, 0),
                                    0,
                                    ',',
                                    ' '
                                ) . ' FCFA'
                            )
                            ->weight('bold')->color('success'),
                    ]),
                ]),

            // ── Lignes de dépenses ──────────────────────────────────
            Infolists\Components\Section::make('Lignes de dépenses')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('lignes')
                        ->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make('numero_ligne')
                                ->label('#')
                                ->columnSpan(1),

                            Infolists\Components\TextEntry::make('nature_depense')
                                ->label('Nature')
                                ->columnSpan(3),

                            Infolists\Components\TextEntry::make('quantite')
                                ->label('Qté')
                                ->columnSpan(1),

                            // ✅ P.U NET = NAP unitaire (valeur saisie)
                            Infolists\Components\TextEntry::make('pu_net')
                                ->label('P.U NET')
                                ->getStateUsing(
                                    fn($record) =>
                                    number_format(
                                        ($record->montant_net ?? $record->net_a_payer ?? 0)
                                            / max(1, (float) $record->quantite),
                                        0,
                                        ',',
                                        ' '
                                    ) . ' F'
                                )
                                ->color('success')
                                ->columnSpan(1),

                            // ✅ NAP Total juste après P.U NET
                            Infolists\Components\TextEntry::make('nap_total_affiche')
                                ->label('NAP Total')
                                ->getStateUsing(fn($record) => number_format(
                                    (float) ($record->net_a_payer
                                        ?? $record->montant_net
                                        ?? 0),
                                    0,
                                    ',',
                                    ' '
                                ) . ' F')
                                ->color('success')
                                ->columnSpan(1),

                            Infolists\Components\TextEntry::make('montant_ht')
                                ->label('MHT')
                                ->formatStateUsing(
                                    fn($state) =>
                                    number_format((float) $state, 0, ',', ' ') . ' F'
                                )
                                ->columnSpan(1),

                            Infolists\Components\TextEntry::make('montant_tva')
                                ->label('TVA')
                                ->formatStateUsing(
                                    fn($state) =>
                                    number_format((float) $state, 0, ',', ' ') . ' F'
                                )
                                ->color('warning')
                                ->columnSpan(1),

                            Infolists\Components\TextEntry::make('montant_ir')
                                ->label('IR')
                                ->formatStateUsing(
                                    fn($state) =>
                                    number_format((float) $state, 0, ',', ' ') . ' F'
                                )
                                ->color('danger')
                                ->columnSpan(1),

                            Infolists\Components\TextEntry::make('montant_ttc')
                                ->label('TTC')
                                ->formatStateUsing(
                                    fn($state) =>
                                    number_format((float) $state, 0, ',', ' ') . ' F'
                                )
                                ->weight('bold')
                                ->color('primary')
                                ->columnSpan(1),
                        ])
                        ->columns(11)
                        ->columnSpanFull(),
                ]),

            // ── DA liée ─────────────────────────────────────────────
            Infolists\Components\Section::make('Décision Administrative liée')
                ->schema([
                    Infolists\Components\Grid::make(3)->schema([
                        Infolists\Components\TextEntry::make('decisionAdministrative.numero')
                            ->label('N° DA')->badge()->color('primary')->copyable(),
                        Infolists\Components\TextEntry::make('decisionAdministrative.statut')
                            ->label('Statut DA')->badge(),
                        Infolists\Components\TextEntry::make('decisionAdministrative.date_decision')
                            ->label('Date DA')->date('d/m/Y'),
                    ]),
                ])
                ->visible(fn($record) => $record->decision_administrative_id !== null)
                ->collapsible(),

            // ── Signature ───────────────────────────────────────────
            Infolists\Components\Section::make('Signature')
                ->schema([
                    Infolists\Components\Grid::make(3)->schema([
                        Infolists\Components\TextEntry::make('signataire_nom')
                            ->label('Signataire')->placeholder('—'),
                        Infolists\Components\TextEntry::make('signataire_fonction')
                            ->label('Fonction')->placeholder('—'),
                        Infolists\Components\TextEntry::make('lieu_signature')
                            ->label('Lieu')->placeholder('—'),
                    ]),
                ])
                ->collapsible()->collapsed(),
        ]);
    }

    // =========================================================
    // ACTIONS — identiques à votre code original
    // =========================================================
    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn() => $this->record->statut === 'brouillon'),

            Action::make('apercu_memoire')
                ->label('Aperçu')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->outlined()
                ->modalHeading(fn() => 'Aperçu — ' . $this->record->numero)
                ->modalWidth('7xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fermer')
                ->modalContent(function () {
                    $this->record->load('lignes');
                    return view('filament.modals.apercu-memoire-depense', [
                        'memoire' => $this->record,
                    ]);
                }),

            Action::make('generer_pdf')
                ->label('Télécharger PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->url(fn() => route('memoire-depense.pdf', $this->record))
                ->openUrlInNewTab(),

            Action::make('valider')
                ->label('Valider')
                ->icon('heroicon-o-check-circle')
                ->color('warning')
                ->visible(
                    fn() =>
                    $this->record->statut === 'brouillon'
                        && static::getResource()::canValider($this->record)
                )
                ->requiresConfirmation()
                ->modalHeading('Valider le mémoire')
                ->modalDescription(fn() => "Valider le mémoire {$this->record->numero} ?")
                ->action(function () {
                    // ✅ Recalculer et persister les totaux depuis les lignes
                    $lignes = $this->record->lignes;
                    $this->record->update([
                        'statut'      => 'valide',
                        'montant_ht'  => $lignes->sum('montant_ht'),
                        'montant_tva' => $lignes->sum('montant_tva'),
                        'montant_ttc' => $lignes->sum('montant_ttc'),
                        'montant_ir'  => $lignes->sum('montant_ir'),
                        'montant_net' => $lignes->sum('montant_net') ?: $lignes->sum('net_a_payer'),
                    ]);
                    Notification::make()->title('✅ Mémoire validé')->success()->send();
                    $this->refreshFormData(['statut']);
                }),

            Action::make('transmettre')
                ->label('Transmettre')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->visible(
                    fn() =>
                    in_array($this->record->statut, ['brouillon', 'valide'])
                        && !$this->record->estEnCoursDeTransmission()
                )
                ->form([
                    Forms\Components\Select::make('destinataire_id')
                        ->label('Transmettre à')
                        ->options(fn() => User::where('id', '!=', auth()->id())
                            ->orderBy('name')->pluck('name', 'id'))
                        ->required()->searchable()->preload()->live(),
                    Forms\Components\Select::make('action_attendue')
                        ->label('Action attendue')
                        ->options([
                            'validation'   => 'Validation',
                            'verification' => 'Vérification',
                            'signature'    => 'Signature',
                            'information'  => 'Pour information',
                        ])
                        ->required()->default('validation'),
                    Forms\Components\Textarea::make('commentaire')
                        ->label('Commentaire')->rows(3),
                    Forms\Components\Select::make('priorite')
                        ->label('Priorité')
                        ->options([
                            'basse'   => 'Basse',
                            'normale' => 'Normale',
                            'haute'   => 'Haute',
                            'urgente' => 'Urgente',
                        ])
                        ->required()->default('normale'),
                    Forms\Components\DatePicker::make('date_limite')
                        ->label('Date limite (optionnel)')->minDate(now()),
                ])
                ->action(function (array $data) {
                    $destinataire = User::findOrFail($data['destinataire_id']);
                    $this->record->transmettreA(
                        $destinataire,
                        $data['action_attendue'],
                        $data['commentaire'] ?? null,
                        ['priorite' => $data['priorite'], 'date_limite' => $data['date_limite'] ?? null]
                    );
                    Notification::make()
                        ->title('📤 Mémoire transmis')->success()
                        ->body("Transmis à {$destinataire->name}")->send();
                }),

            Action::make('retourner')
                ->label('Retourner')
                ->icon('heroicon-o-arrow-uturn-left')->color('warning')
                ->visible(
                    fn() =>
                    $this->record->estDestinataireActuel()
                        && $this->record->transmissionEnCours()
                )
                ->form([
                    Forms\Components\Textarea::make('motif')
                        ->label('Motif du retour')->required()->rows(3),
                ])
                ->requiresConfirmation()
                ->modalHeading('Retourner pour correction')
                ->action(function (array $data) {
                    Transmission::where('document_type', get_class($this->record))
                        ->where('document_id', $this->record->id)
                        ->where('destinataire_id', auth()->id())
                        ->where('statut', 'en_attente')
                        ->first()
                        ?->rejeter($data['motif']);
                    $this->record->update(['statut' => 'brouillon']);
                    Notification::make()->title('↩ Mémoire retourné')->warning()->send();
                }),

            Action::make('cloturer_transmission')
                ->label('Clôturer')
                ->icon('heroicon-o-check-circle')->color('success')
                ->visible(
                    fn() =>
                    $this->record->estDestinataireActuel()
                        && $this->record->transmissionEnCours()
                )
                ->form([
                    Forms\Components\Textarea::make('reponse')
                        ->label('Réponse / Commentaire')->rows(3),
                ])
                ->requiresConfirmation()
                ->action(function (array $data) {
                    $this->record->cloturerTransmission($data['reponse'] ?? null);
                    Notification::make()->title('✅ Transmission clôturée')->success()->send();
                }),

            Action::make('historique_transmissions')
                ->label('Historique')
                ->icon('heroicon-o-clock')->color('gray')
                ->visible(fn() => $this->record->aEteTransmis())
                ->modalHeading(fn() => 'Historique — ' . $this->record->numero)
                ->modalContent(fn() => view('filament.modals.historique-transmissions', [
                    'transmissions' => $this->record->historiqueTransmissions(),
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fermer'),

            Action::make('transformer_en_da')
                ->label('Transformer en DA')
                ->icon('heroicon-o-arrow-right-circle')->color('primary')
                ->visible(
                    fn() =>
                    $this->record->statut === 'valide'
                        && !$this->record->decision_administrative_id
                        && static::getResource()::canTransformerEnDa($this->record)
                )
                ->modalHeading('Transformer en Décision Administrative')
                ->modalDescription('Le mémoire sera converti en DA. Complétez les informations manquantes.')
                ->modalWidth('2xl')
                ->form([
                    Forms\Components\Placeholder::make('info_memoire')
                        ->label('Mémoire source')
                        ->content(new \Illuminate\Support\HtmlString(
                            '<div class="rounded-lg p-3 text-sm leading-loose '
                                . 'bg-slate-100 dark:bg-slate-800 '
                                . 'text-slate-800 dark:text-slate-200">'
                                . '<strong>N° :</strong> ' . $this->record->numero . '<br>'
                                . '<strong>Objet :</strong> ' . ($this->record->objet ?? '—') . '<br>'
                                . '<strong>Mode saisie :</strong> '
                                . ($this->record->mode_saisie === 'montant_nap'
                                    ? '📊 Montant NAP' : '💰 Prix Unitaire') . '<br>'
                                . '<strong>Montant TTC :</strong> '
                                . number_format((float) $this->record->montant_ttc, 0, ',', ' ')
                                . ' FCFA<br>'
                                . '<strong>Montant NAP :</strong> '
                                . number_format((float) $this->record->montant_net, 0, ',', ' ')
                                . ' FCFA'
                                . '</div>'
                        ))
                        ->columnSpanFull(),

                    Forms\Components\Select::make('type_decision_id')
                        ->label('Type de décision')
                        ->options(fn() => TypeDecision::actif()->ordonne()->pluck('libelle', 'id'))
                        ->required()->searchable()->preload()
                        ->helperText('Champ absent dans le mémoire — à préciser obligatoirement'),

                    Forms\Components\Select::make('type_beneficiaire')
                        ->label('Type de bénéficiaire')
                        ->options([
                            'personnel'   => 'Personnel',
                            'fournisseur' => 'Fournisseur',
                        ])
                        ->required()->live()->default('personnel'),

                    Forms\Components\Select::make('personnel_id')
                        ->label('Personnel')
                        ->options(fn() => \App\Models\Personnel::where('actif', true)
                            ->orderBy('nom')->get()
                            ->mapWithKeys(fn($p) => [
                                $p->id => "{$p->matricule} — {$p->nom} {$p->prenoms}"
                            ]))
                        ->searchable()->preload()
                        ->required(fn(Get $get) => $get('type_beneficiaire') === 'personnel')
                        ->visible(fn(Get $get)   => $get('type_beneficiaire') === 'personnel'),

                    Forms\Components\Select::make('fournisseur_id')
                        ->label('Fournisseur')
                        ->options(fn() => \App\Models\Fournisseur::orderBy('raison_sociale')
                            ->pluck('raison_sociale', 'id'))
                        ->searchable()
                        ->required(fn(Get $get) => $get('type_beneficiaire') === 'fournisseur')
                        ->visible(fn(Get $get)   => $get('type_beneficiaire') === 'fournisseur'),

                    Forms\Components\Select::make('budget_id')
                        ->label('Budget')
                        ->options(\App\Models\Budget::where('actif', true)->pluck('libelle', 'id'))
                        ->required()->searchable()->preload(),

                    Forms\Components\DatePicker::make('date_decision')
                        ->label('Date de décision')->default(now())->required(),

                    Forms\Components\Textarea::make('observations')
                        ->label('Observations')->rows(2),
                ])
                ->action(function (array $data) {
                    try {
                        $exercice = \App\Models\Exercice::getActif();
                        if (!$exercice) throw new \Exception('Aucun exercice actif trouvé.');

                        $lignes     = $this->record->lignes;
                        $montantTtc = $lignes->sum('montant_ttc') ?: (float) $this->record->montant_ttc;
                        $montantHt  = $lignes->sum('montant_ht')  ?: (float) $this->record->montant_ht;
                        $montantTva = $lignes->sum('montant_tva') ?: (float) $this->record->montant_tva;
                        $montantIr  = $lignes->sum('montant_ir')  ?: (float) $this->record->montant_ir;
                        $montantNet = $lignes->sum('montant_net') ?: (float) $this->record->montant_net;
                        $totalTaxes = $montantTva + $montantIr;

                        $premiereLigne = $lignes->first();
                        $tauxIr  = (float) ($premiereLigne?->taux_ir  ?? 5.5);
                        $tauxTva = (float) ($premiereLigne?->taux_tva ?? 19.25);

                        $numeroDA = DecisionAdministrative::genererNumero($exercice->id);

                        $da = DecisionAdministrative::withoutEvents(function () use (
                            $data,
                            $exercice,
                            $numeroDA,
                            $montantTtc,
                            $montantHt,
                            $montantTva,
                            $montantIr,
                            $montantNet,
                            $totalTaxes,
                            $tauxIr,
                            $tauxTva
                        ) {
                            return DecisionAdministrative::create([
                                'numero'           => $numeroDA,
                                'exercice_id'      => $exercice->id,
                                'budget_id'        => $data['budget_id'],
                                'type_decision_id' => $data['type_decision_id'],
                                'type_beneficiaire' => $data['type_beneficiaire'],
                                'personnel_id'     => $data['type_beneficiaire'] === 'personnel'
                                    ? $data['personnel_id'] : null,
                                'fournisseur_id'   => $data['type_beneficiaire'] === 'fournisseur'
                                    ? $data['fournisseur_id'] : null,
                                'date_decision'    => $data['date_decision'],
                                'objet'            => $this->record->objet,
                                'reference_decision' => $this->record->numero,
                                'signataire'       => $this->record->signataire_nom,
                                'mode_saisie'      => 'forfait',
                                'montant_brut'     => $montantTtc,
                                'montant_ht'       => $montantHt,
                                'montant_tva'      => $montantTva,
                                'montant_cnps'     => 0,
                                'montant_irnc'     => 0,
                                'montant_ir'       => $montantIr,
                                'autres_retenues'  => 0,
                                'total_taxes'      => $totalTaxes,
                                'montant_net'      => $montantNet,
                                'taux_cnps'        => 0,
                                'taux_irnc'        => 0,
                                'taux_ir'          => $tauxIr,
                                'taux_tva'         => $tauxTva,
                                'type_tva'                        => 'forfait',
                                'type_redevance_audiovisuelle'    => 'forfait',
                                'montant_redevance_audiovisuelle' => 0,
                                'type_feicom'                     => 'forfait',
                                'montant_feicom'                  => 0,
                                'statut'           => 'brouillon',
                                'observations'     => "📋 Créée depuis le Mémoire N° {$this->record->numero}\n"
                                    . "Mode saisie : " . ($this->record->mode_saisie ?? 'montant_nap') . "\n"
                                    . "Date mémoire : " . ($this->record->date_memoire?->format('d/m/Y') ?? '—')
                                    . ($data['observations'] ? "\n" . $data['observations'] : ''),
                                'created_by'       => auth()->id(),
                            ]);
                        });

                        $this->record->update([
                            'decision_administrative_id' => $da->id,
                            'numero_decision'            => $da->numero,
                            'date_decision'              => $da->date_decision,
                            'statut'                     => 'transforme',
                        ]);

                        Notification::make()
                            ->title('✅ DA créée avec succès')->success()
                            ->body("La décision {$da->numero} a été créée.")
                            ->send();

                        $this->redirect(
                            DecisionAdministrativeResource::getUrl('view', ['record' => $da->id])
                        );
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('❌ Erreur')->danger()
                            ->body($e->getMessage())->persistent()->send();
                    }
                }),

            Action::make('annuler')
                ->label('Annuler')
                ->icon('heroicon-o-x-circle')->color('danger')
                ->visible(fn() => !in_array($this->record->statut, ['annule', 'transforme']))
                ->requiresConfirmation()
                ->modalHeading('Annuler le mémoire')
                ->form([
                    Forms\Components\Textarea::make('motif')
                        ->label("Motif d'annulation")->rows(2),
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'statut'       => 'annule',
                        'observations' => ($this->record->observations ?? '') .
                            "\n\n--- ANNULÉ LE " . now()->format('d/m/Y H:i') . " ---\n" .
                            "Motif : " . ($data['motif'] ?? 'Non précisé') . "\n" .
                            "Par : " . auth()->user()->name,
                    ]);
                    Notification::make()->title('⚠️ Mémoire annulé')->warning()->send();
                }),

            Action::make('recuperer')
                ->label('Récupérer')
                ->icon('heroicon-o-arrow-uturn-left')->color('warning')
                ->visible(fn() => $this->record->statut === 'annule')
                ->requiresConfirmation()
                ->modalHeading('Récupérer le mémoire')
                ->form([
                    Forms\Components\Textarea::make('motif')
                        ->label('Motif de récupération')->rows(2),
                ])
                ->action(function (array $data) {
                    \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
                        $this->record->transmissions()
                            ->where('statut', 'en_attente')
                            ->update([
                                'statut'          => 'annule',
                                'date_traitement' => now(),
                                'reponse'         => 'Annulée — récupéré le ' . now()->format('d/m/Y H:i'),
                            ]);
                        $this->record->update([
                            'statut'         => 'brouillon',
                            'date_signature' => null,
                            'fichier_pdf'    => null,
                            'observations'   => ($this->record->observations ?? '') .
                                "\n\n--- RÉCUPÉRÉ LE " . now()->format('d/m/Y H:i') . " ---\n" .
                                "Motif : " . ($data['motif'] ?? 'Récupéré pour modification') . "\n" .
                                "Par : " . auth()->user()->name,
                        ]);
                    });

                    Notification::make()
                        ->title('✅ Mémoire récupéré')->success()
                        ->body("Le mémoire {$this->record->numero} est en brouillon.")->send();

                    $this->redirect(
                        MemoireDepenseResource::getUrl('edit', ['record' => $this->record->id])
                    );
                }),
        ];
    }
}
