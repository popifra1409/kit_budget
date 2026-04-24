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
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;

class ViewMemoireDepense extends ViewRecord
{
    protected static string $resource = MemoireDepenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // ── Modifier ─────────────────────────────────────────
            Actions\EditAction::make()
                ->visible(fn() => $this->record->statut === 'brouillon'),

            // ── Aperçu ───────────────────────────────────────────
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

            // ── PDF ──────────────────────────────────────────────
            Action::make('generer_pdf')
                ->label('Télécharger PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->url(fn() => route('memoire-depense.pdf', $this->record))
                ->openUrlInNewTab(),

            // ── Valider ──────────────────────────────────────────
            Action::make('valider')
                ->label('Valider')
                ->icon('heroicon-o-check-circle')
                ->color('warning')
                ->visible(
                    fn() => $this->record->statut === 'brouillon'
                        && static::getResource()::canValider($this->record)
                )
                ->requiresConfirmation()
                ->modalHeading('Valider le mémoire')
                ->modalDescription(fn() => "Valider le mémoire {$this->record->numero} ?")
                ->action(function () {
                    $this->record->update(['statut' => 'valide']);
                    Notification::make()->title('✅ Mémoire validé')->success()->send();
                    $this->refreshFormData(['statut']);
                }),

            // ── Transmettre ──────────────────────────────────────
            Action::make('transmettre')
                ->label('Transmettre')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->visible(
                    fn() => in_array($this->record->statut, ['brouillon', 'valide'])
                        && !$this->record->estEnCoursDeTransmission()
                )
                ->form([
                    Forms\Components\Select::make('destinataire_id')
                        ->label('Transmettre à')
                        ->options(
                            fn() => User::where('id', '!=', auth()->id())
                                ->orderBy('name')
                                ->pluck('name', 'id')
                        )
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
                        ->label('Date limite (optionnel)')
                        ->minDate(now()),
                ])
                ->action(function (array $data) {
                    $destinataire = User::findOrFail($data['destinataire_id']);
                    $this->record->transmettreA(
                        $destinataire,
                        $data['action_attendue'],
                        $data['commentaire'] ?? null,
                        [
                            'priorite'    => $data['priorite'],
                            'date_limite' => $data['date_limite'] ?? null,
                        ]
                    );
                    Notification::make()
                        ->title('📤 Mémoire transmis')
                        ->success()
                        ->body("Transmis à {$destinataire->name}")
                        ->send();
                }),

            // ── Retourner ────────────────────────────────────────
            Action::make('retourner')
                ->label('Retourner')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(
                    fn() => $this->record->estDestinataireActuel()
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

                    Notification::make()
                        ->title('↩ Mémoire retourné')->warning()->send();
                }),

            // ── Clôturer transmission ────────────────────────────
            Action::make('cloturer_transmission')
                ->label('Clôturer')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(
                    fn() => $this->record->estDestinataireActuel()
                        && $this->record->transmissionEnCours()
                )
                ->form([
                    Forms\Components\Textarea::make('reponse')
                        ->label('Réponse / Commentaire')->rows(3),
                ])
                ->requiresConfirmation()
                ->action(function (array $data) {
                    $this->record->cloturerTransmission($data['reponse'] ?? null);
                    Notification::make()
                        ->title('✅ Transmission clôturée')->success()->send();
                }),

            // ── Historique transmissions ─────────────────────────
            Action::make('historique_transmissions')
                ->label('Historique')
                ->icon('heroicon-o-clock')
                ->color('gray')
                ->visible(fn() => $this->record->aEteTransmis())
                ->modalHeading(fn() => 'Historique — ' . $this->record->numero)
                ->modalContent(fn() => view('filament.modals.historique-transmissions', [
                    'transmissions' => $this->record->historiqueTransmissions(),
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fermer'),

            // =========================================================
            // ── Transformer en Décision Administrative ───────────────
            // =========================================================
            Action::make('transformer_en_da')
                ->label('Transformer en DA')
                ->icon('heroicon-o-arrow-right-circle')
                ->color('primary')
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
                    // ── Info recap mémoire ────────────────────────
                    Forms\Components\Placeholder::make('info_memoire')
                        ->label('Mémoire source')
                        ->content(new \Illuminate\Support\HtmlString(
                            '<div style="background:#f1f5f9;padding:.75rem 1rem;border-radius:.5rem;font-size:.82rem;line-height:1.8;">'
                                . '<strong>N° :</strong> ' . $this->record->numero . '<br>'
                                . '<strong>Objet :</strong> ' . ($this->record->objet ?? '—') . '<br>'
                                . '<strong>Montant TTC :</strong> ' . number_format((float)$this->record->montant_ttc, 0, ',', ' ') . ' FCFA<br>'
                                . '<strong>Montant NAP :</strong> ' . number_format((float)$this->record->montant_net, 0, ',', ' ') . ' FCFA'
                                . '</div>'
                        ))
                        ->columnSpanFull(),

                    // ── Type de décision (obligatoire) ────────────
                    Forms\Components\Select::make('type_decision_id')
                        ->label('Type de décision')
                        ->options(fn() => TypeDecision::actif()->ordonne()->pluck('libelle', 'id'))
                        ->required()
                        ->searchable()
                        ->preload()
                        ->helperText('Champ absent dans le mémoire — à préciser obligatoirement'),

                    // ── Bénéficiaire ──────────────────────────────
                    Forms\Components\Select::make('type_beneficiaire')
                        ->label('Type de bénéficiaire')
                        ->options([
                            'personnel'   => 'Personnel',
                            'fournisseur' => 'Fournisseur',
                        ])
                        ->required()
                        ->live()
                        ->default('personnel'),

                    Forms\Components\Select::make('personnel_id')
                        ->label('Personnel')
                        ->options(
                            fn() => \App\Models\Personnel::where('actif', true)
                                ->orderBy('nom')
                                ->get()
                                ->mapWithKeys(fn($p) => [
                                    $p->id => "{$p->matricule} — {$p->nom} {$p->prenoms}"
                                ])
                        )
                        ->searchable()->preload()
                        ->required(fn(Get $get) => $get('type_beneficiaire') === 'personnel')
                        ->visible(fn(Get $get) => $get('type_beneficiaire') === 'personnel'),

                    Forms\Components\Select::make('fournisseur_id')
                        ->label('Fournisseur')
                        ->options(
                            fn() => \App\Models\Fournisseur::orderBy('raison_sociale')
                                ->pluck('raison_sociale', 'id')
                        )
                        ->searchable()
                        ->required(fn(Get $get) => $get('type_beneficiaire') === 'fournisseur')
                        ->visible(fn(Get $get) => $get('type_beneficiaire') === 'fournisseur'),

                    // ── Budget et nomenclature ────────────────────
                    Forms\Components\Select::make('budget_id')
                        ->label('Budget')
                        ->options(\App\Models\Budget::where('actif', true)->pluck('libelle', 'id'))
                        ->required()->searchable()->preload(),

                    Forms\Components\DatePicker::make('date_decision')
                        ->label('Date de décision')
                        ->default(now())->required(),

                    Forms\Components\Textarea::make('observations')
                        ->label('Observations')->rows(2),
                ])
                ->action(function (array $data) {
                    try {
                        $exercice = \App\Models\Exercice::getActif();
                        if (!$exercice) {
                            throw new \Exception('Aucun exercice actif trouvé.');
                        }

                        // ✅ Variables calculées depuis le mémoire
                        $montantTtc = (float) $this->record->montant_ttc;
                        $montantHt  = (float) $this->record->montant_ht;
                        $montantTva = (float) $this->record->montant_tva;
                        $montantIr  = (float) $this->record->montant_ir;   // IR du mémoire
                        $montantNet = (float) $this->record->montant_net;
                        $totalTaxes = $montantTva + $montantIr;            // TVA + IR

                        $numeroDA = DecisionAdministrative::genererNumero($exercice->id);

                        // ── Créer la DA depuis le mémoire ─────────
                        $da = DecisionAdministrative::withoutEvents(function () use (
                            $data,
                            $exercice,
                            $numeroDA,
                            $montantTtc,
                            $montantHt,
                            $montantTva,
                            $montantIr,
                            $montantNet,
                            $totalTaxes
                        ) {
                            return DecisionAdministrative::create([
                                'numero'            => $numeroDA,
                                'exercice_id'       => $exercice->id,
                                'budget_id'         => $data['budget_id'],
                                'type_decision_id'  => $data['type_decision_id'],
                                'type_beneficiaire' => $data['type_beneficiaire'],
                                'personnel_id'      => $data['type_beneficiaire'] === 'personnel'
                                    ? $data['personnel_id'] : null,
                                'fournisseur_id'    => $data['type_beneficiaire'] === 'fournisseur'
                                    ? $data['fournisseur_id'] : null,
                                'date_decision'     => $data['date_decision'],
                                'objet'             => $this->record->objet,
                                'reference_decision' => $this->record->numero,
                                'signataire'        => $this->record->signataire_nom,

                                // ✅ Mode forfait — stoppe tout recalcul
                                'mode_saisie'       => 'forfait',

                                // ✅ Mapping exact mémoire → DA
                                'montant_brut'      => $montantTtc,  // TTC du mémoire
                                'montant_ht'        => $montantHt,   // HT du mémoire
                                'montant_tva'       => $montantTva,  // TVA du mémoire
                                'montant_cnps'      => 0,
                                'montant_irnc'      => $montantIr,   // ✅ IR du mémoire → IRNC de la DA
                                'autres_retenues'   => 0,
                                'total_taxes'       => $totalTaxes,  // ✅ TVA + IR
                                'montant_net'       => $montantNet,  // Net du mémoire

                                // Taux neutralisés
                                'taux_cnps'         => 0,
                                'taux_irnc'         => 0,
                                'taux_tva'          => 0,
                                'type_tva'                        => 'forfait',
                                'type_redevance_audiovisuelle'    => 'forfait',
                                'montant_redevance_audiovisuelle' => 0,
                                'type_feicom'                     => 'forfait',
                                'montant_feicom'                  => 0,

                                'statut'            => 'brouillon',
                                'observations' => "📋 Créée par transformation du Mémoire de Dépense N° {$this->record->numero}\n"
                                    . "Date mémoire : " . ($this->record->date_memoire?->format('d/m/Y') ?? '—') . "\n"
                                    . ($data['observations'] ? "\n" . $data['observations'] : ''),
                                'created_by'        => auth()->id(),
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
                            ->body("La décision {$da->numero} a été créée depuis le mémoire {$this->record->numero}.")
                            ->send();

                        $this->redirect(
                            DecisionAdministrativeResource::getUrl('view', ['record' => $da->id])
                        );
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('❌ Erreur lors de la transformation')
                            ->danger()->body($e->getMessage())->persistent()->send();
                    }
                }),

            // ── Annuler ─────────────────────────────────────────────
            Action::make('annuler')
                ->label('Annuler')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn() => !in_array($this->record->statut, ['annule', 'transforme']))
                ->requiresConfirmation()
                ->modalHeading('Annuler le mémoire')
                ->modalDescription('Le mémoire sera annulé. Vous pourrez le récupérer ultérieurement.')
                ->form([
                    Forms\Components\Textarea::make('motif')
                        ->label('Motif d\'annulation')
                        ->rows(2)
                        ->placeholder('Précisez le motif...'),
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'statut'       => 'annule',
                        'observations' => ($this->record->observations ?? '') .
                            "\n\n--- ANNULÉ LE " . now()->format('d/m/Y H:i') . " ---\n" .
                            "Motif : " . ($data['motif'] ?? 'Non précisé') . "\n" .
                            "Par : " . auth()->user()->name,
                    ]);
                    Notification::make()
                        ->title('⚠️ Mémoire annulé')
                        ->warning()
                        ->body('Vous pouvez le récupérer via le bouton "Récupérer".')
                        ->send();
                }),

            Action::make('recuperer')
                ->label('Récupérer')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn() => $this->record->statut === 'annule')
                ->requiresConfirmation()
                ->modalHeading('Récupérer le mémoire')
                ->modalDescription('Le mémoire sera remis en brouillon et pourra être modifié.')
                ->form([
                    Forms\Components\Textarea::make('motif')
                        ->label('Motif de récupération')
                        ->rows(2)
                        ->placeholder('Précisez le motif...'),
                ])
                ->action(function (array $data) {
                    \Illuminate\Support\Facades\DB::transaction(function () use ($data) {

                        // ✅ 1. Annuler toutes les transmissions en attente
                        $this->record->transmissions()
                            ->where('statut', 'en_attente')
                            ->update([
                                'statut'          => 'annule',
                                'date_traitement' => now(),
                                'reponse'         => 'Annulée — mémoire récupéré le ' . now()->format('d/m/Y H:i'),
                            ]);

                        // ✅ 2. Remettre en brouillon + réinitialiser les champs de validation
                        $this->record->update([
                            'statut'             => 'brouillon',    // ← état modifiable
                            'date_signature'     => null,           // ← réinitialiser signature
                            'fichier_pdf'        => null,           // ← réinitialiser PDF généré
                            'observations'       => ($this->record->observations ?? '') .
                                "\n\n--- RÉCUPÉRÉ LE " . now()->format('d/m/Y H:i') . " ---\n" .
                                "Motif : " . ($data['motif'] ?? 'Document récupéré pour modification') . "\n" .
                                "Par : " . auth()->user()->name,
                        ]);
                    });

                    Notification::make()
                        ->title('✅ Mémoire récupéré et modifiable')
                        ->success()
                        ->body("Le mémoire {$this->record->numero} est en brouillon — vous pouvez le modifier.")
                        ->send();

                    // ✅ Rediriger vers l'édition directement
                    $this->redirect(
                        \App\Filament\Budget\Resources\MemoireDepenseResource::getUrl('edit', [
                            'record' => $this->record->id,
                        ])
                    );
                }),
        ];
    }
}
