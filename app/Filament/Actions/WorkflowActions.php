<?php

namespace App\Filament\Actions;

use Filament\Tables;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use App\Models\Transmission;
use App\Models\User;

class WorkflowActions
{
    /**
     * Génère les actions standard du workflow administratif
     *
     * @param bool $avecEngagement  Active l'action "Engager"
     * @param string|null $pdfServiceClass  Classe du service PDF (ex: BonCommandePdfService::class)
     * @param string|null $pdfRouteName  Nom de la route pour l'aperçu PDF
     * @param bool $avecModalEngagement  Active le modal de vérification budgétaire (uniquement pour BonCommande)
     */

    /**
     * Actions PDF uniquement
     */
    public static function pdfActionsOnly(string $serviceClass, string $routeName): Tables\Actions\ActionGroup
    {
        return self::pdfActions($serviceClass, $routeName);
    }

    public static function make(
        bool $avecEngagement = false,
        ?string $pdfServiceClass = null,
        ?string $pdfRouteName = null,
        bool $avecModalEngagement = true
    ): array {
        $actions = [
            Tables\Actions\EditAction::make()
                ->visible(function ($record) {
                    // Règle 1 : transmission en cours non clôturée → non modifiable
                    // (peu importe qui est l'émetteur ou le destinataire)
                    $transmissionActive = Transmission::where('document_type', get_class($record))
                        ->where('document_id', $record->id)
                        ->where('statut', 'en_attente')
                        ->exists();

                    if ($transmissionActive) {
                        return false;
                    }

                    // Règle 2 : méthode dédiée sur le model si elle existe
                    if (method_exists($record, 'peutEtreModifiePar')) {
                        return $record->peutEtreModifiePar();
                    }

                    // Règle 3 : fallback — modifiable seulement si brouillon
                    return $record->estModifiable();
                })
                ->tooltip(function ($record) {
                    $transmissionActive = Transmission::where('document_type', get_class($record))
                        ->where('document_id', $record->id)
                        ->where('statut', 'en_attente')
                        ->exists();

                    return $transmissionActive
                        ? 'Document en cours de transmission — modification bloquée'
                        : null;
                }),
        ];

        // Actions PDF (si service fourni)
        if ($pdfServiceClass && $pdfRouteName) {
            $actions[] = self::pdfActions($pdfServiceClass, $pdfRouteName);
        }

        $actions = array_merge($actions, [
            self::valider(),
        ]);

        if ($avecEngagement) {
            // ✅ Choisir entre modal avancé ou simple selon le paramètre
            $actions[] = $avecModalEngagement
                ? self::engagerAvecModal()
                : self::engagerSimple();
        }

        $actions = array_merge($actions, [
            self::transmettre(),
            self::retourner(),
            self::cloturer(),
            self::historique(),
        ]);

        return $actions;
    }

    /* =========================
     | ACTIONS : PDF
     ========================= */
    /**
     * Actions PDF avec choix du type d'état
     */
    private static function pdfActions(string $serviceClass, string $routeName): Tables\Actions\ActionGroup
    {
        return Tables\Actions\ActionGroup::make([
            // === BON DE COMMANDE SIMPLE (standard avec en-tête) ===
            Tables\Actions\Action::make('apercu_pdf_simple')
                ->label('Aperçu BC')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->url(fn($record) => route('bons-commande.pdf.preview.simple', ['bonCommande' => $record->id]))
                ->openUrlInNewTab(),

            Tables\Actions\Action::make('telecharger_pdf_simple')
                ->label('Télécharger BC')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->url(fn($record) => route('bons-commande.pdf.download.simple', ['bonCommande' => $record->id])),

            // === BON DE COMMANDE SIMPLE PRÉIMPRIMÉ (sans en-tête/footer) ===
            // Tables\Actions\Action::make('apercu_pdf_simple_preimprime')
            //     ->label('Aperçu BC Préimprimé')
            //     ->icon('heroicon-o-eye')
            //     ->color('gray')
            //     ->url(fn($record) => route('bons-commande.pdf.preview.simple-preimprime', ['bonCommande' => $record->id]))
            //     ->openUrlInNewTab(),

            // Tables\Actions\Action::make('telecharger_pdf_simple_preimprime')
            //     ->label('Télécharger BC Préimprimé')
            //     ->icon('heroicon-o-arrow-down-on-square')
            //     ->color('gray')
            //     ->url(fn($record) => route('bons-commande.pdf.download.simple-preimprime', ['bonCommande' => $record->id])),

            // === SÉPARATEUR ===
            Tables\Actions\Action::make('separator_1')
                ->label('─────────────────')
                ->disabled()
                ->color('gray'),

            // === BON DE COMMANDE AVEC ANNEXES (complet) ===
            Tables\Actions\Action::make('apercu_pdf_complet')
                ->label('Aperçu BCA')
                ->icon('heroicon-o-eye')
                ->color('warning')
                ->url(fn($record) => route('bons-commande.pdf.preview.complet', ['bonCommande' => $record->id]))
                ->openUrlInNewTab(),

            Tables\Actions\Action::make('telecharger_pdf_complet')
                ->label('Télécharger BCA')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('danger')
                ->url(fn($record) => route('bons-commande.pdf.download.complet', ['bonCommande' => $record->id])),
        ])
            ->label('Télécharger')
            ->icon('heroicon-o-document')
            ->size('sm')
            ->color('success')
            ->button()
            ->visible(fn($record) => !in_array($record->statut, ['brouillon']));
    }

    /* =========================
     | ACTION : VALIDER
     ========================= */
    private static function valider(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('valider')
            ->label('Valider')
            ->icon('heroicon-o-check-circle')
            ->color('warning')
            ->visible(function ($record) {
                // Doit avoir la permission
                if (!auth()->user()?->can('valider_bon_commande')) {
                    return false;
                }

                // Doit être en brouillon
                if ($record->statut !== 'brouillon') {
                    return false;
                }

                // CAS 1 : Je suis le destinataire et c'est transmis à moi pour validation
                if (method_exists($record, 'estDestinataireActuel') && $record->estDestinataireActuel()) {
                    $transmission = $record->transmissionEnCours();
                    if ($transmission && $transmission->action_attendue === 'validation') {
                        return true;
                    }
                }

                // CAS 2 : Je suis le créateur et le document n'est PAS transmis ailleurs
                if ($record->created_by === auth()->id()) {
                    if (method_exists($record, 'estEnCoursDeTransmissionPourAutrui')) {
                        // Si transmis à quelqu'un d'autre, je ne peux PAS valider
                        return !$record->estEnCoursDeTransmissionPourAutrui();
                    }
                    // Fallback : utiliser l'ancienne méthode
                    return !$record->estEnCoursDeTransmission();
                }

                return false;
            })
            ->requiresConfirmation()
            ->action(function ($record) {
                $record->valider(auth()->user());

                // ✅ Traiter la transmission si elle existe
                Transmission::where('document_type', get_class($record))
                    ->where('document_id', $record->id)
                    ->where('destinataire_id', auth()->id())
                    ->where('statut', 'en_attente')
                    ->first()
                    ?->traiter('Document validé avec succès');

                Notification::make()
                    ->title('Document validé')
                    ->success()
                    ->send();
            });
    }

    /* =========================
     | ACTION : ENGAGER AVEC MODAL DE VÉRIFICATION
     ========================= */

    private static function engagerAvecModal(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('engager')
            ->label('Engager')
            ->icon('heroicon-o-currency-dollar')
            ->color('success')
            ->visible(function ($record) {
                if (!($record instanceof \App\Models\BonCommande)) {
                    return false;
                }

                // Doit avoir la permission
                if (!auth()->user()?->can('engager_bon_commande')) {
                    return false;
                }

                // Doit être validé et pas encore engagé
                if ($record->statut !== 'valide' || $record->engagement_id) {
                    return false;
                }

                // CAS 1 : Je suis le destinataire et c'est transmis à moi pour engagement
                if (method_exists($record, 'estDestinataireActuel') && $record->estDestinataireActuel()) {
                    $transmission = $record->transmissionEnCours();
                    if ($transmission && $transmission->action_attendue === 'engagement') {
                        return true;
                    }
                }

                // CAS 2 : Je suis le créateur et le document n'est PAS transmis ailleurs
                if ($record->created_by === auth()->id()) {
                    if (method_exists($record, 'estEnCoursDeTransmissionPourAutrui')) {
                        return !$record->estEnCoursDeTransmissionPourAutrui();
                    }
                    return !$record->estEnCoursDeTransmission();
                }

                return false;
            })
            ->modalHeading(fn($record) => "Engagement budgétaire - BC N° {$record->numero}")
            ->modalDescription('Vérification de la disponibilité budgétaire')
            ->modalWidth('5xl')
            ->modalContent(function ($record) {
                $verifications = $record->verifierDisponibiliteBudgetaire();

                return view('filament.modals.engagement-budget-verification', [
                    'bonCommande' => $record,
                    'verifications' => $verifications,
                ]);
            })
            ->modalSubmitActionLabel(function ($record) {
                $verifications = $record->verifierDisponibiliteBudgetaire();

                return $verifications['peut_engager']
                    ? '✅ Confirmer l\'engagement'
                    : '❌ Crédit insuffisant';
            })
            ->modalCancelActionLabel('Annuler')
            ->disabled(function ($record) {
                $verifications = $record->verifierDisponibiliteBudgetaire();
                return !$verifications['peut_engager'];
            })
            ->action(function ($record) {
                try {
                    $verifications = $record->verifierDisponibiliteBudgetaire();

                    if (!$verifications['peut_engager']) {
                        $details = [];
                        foreach ($verifications['lignes_budgetaires'] as $ligne) {
                            if (!$ligne['suffisant']) {
                                $details[] = "• {$ligne['nomenclature']->code} : manque " .
                                    number_format($ligne['manque'], 0, ',', ' ') . " FCFA";
                            }
                        }

                        Notification::make()
                            ->title('❌ Crédit budgétaire insuffisant')
                            ->danger()
                            ->body(
                                "L'engagement ne peut pas être créé :\n\n" .
                                    implode("\n", $details) .
                                    "\n\n💡 Actions possibles :\n" .
                                    "• Réduire le montant du bon de commande\n" .
                                    "• Effectuer un virement budgétaire\n" .
                                    "• Utiliser une autre nomenclature"
                            )
                            ->persistent()
                            ->send();

                        return;
                    }

                    $engagement = $record->engagerBudget($verifications);

                    // ✅ Traiter la transmission après engagement réussi
                    Transmission::where('document_type', get_class($record))
                        ->where('document_id', $record->id)
                        ->where('destinataire_id', auth()->id())
                        ->where('action_attendue', 'engagement')
                        ->where('statut', 'en_attente')
                        ->first()
                        ?->traiter("Budget engagé - Engagement N° {$engagement->numero}");

                    Notification::make()
                        ->title('✅ Budget engagé avec succès')
                        ->success()
                        ->body("Le bon de commande {$record->numero} a été engagé. Engagement créé : {$engagement->numero}")
                        ->duration(5000)
                        ->send();
                } catch (\Exception $e) {
                    \Log::error("Erreur engagement BC {$record->numero} : " . $e->getMessage());

                    Notification::make()
                        ->title('❌ Erreur lors de l\'engagement')
                        ->danger()
                        ->body($e->getMessage())
                        ->persistent()
                        ->send();
                }
            });
    }

    /* =========================
     | ACTION : ENGAGER SIMPLE (ANCIEN MODE)
     ========================= */
    private static function engagerSimple(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('engager')
            ->label('Engager')
            ->icon('heroicon-o-banknotes')
            ->color('primary')
            ->visible(function ($record) {

                // Doit être validé/validee et pas encore engagé
                if (!in_array($record->statut, ['valide', 'validee'])) {
                    return false;
                }

                if ($record->engage ?? $record->engagee ?? false) {
                    return false;
                }

                // CAS 1 : Je suis le destinataire pour engagement
                if (method_exists($record, 'estDestinataireActuel') && $record->estDestinataireActuel()) {
                    $transmission = $record->transmissionEnCours();
                    if ($transmission && $transmission->action_attendue === 'engagement') {
                        return true;
                    }
                }

                // CAS 2 : Je suis le créateur et pas transmis ailleurs
                if ($record->created_by === auth()->id()) {
                    if (method_exists($record, 'estEnCoursDeTransmissionPourAutrui')) {
                        return !$record->estEnCoursDeTransmissionPourAutrui();
                    }
                }

                return true;
            })
            ->requiresConfirmation()
            ->modalHeading('Engager le budget')
            ->modalDescription(fn($record) => "Créer un engagement budgétaire pour " . ($record->numero ?? 'ce document'))
            ->form(function ($record) {
                if ($record instanceof \App\Models\DecisionAdministrative) {
                    return [
                        Forms\Components\Select::make('nomenclature_id')
                            ->label('Nomenclature budgétaire')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->options(function () use ($record) {
                                return \App\Models\LigneBudgetaire::where('budget_id', $record->budget_id)
                                    ->with('nomenclature')
                                    ->get()
                                    ->filter(fn($ligne) => $ligne->nomenclature)
                                    ->mapWithKeys(function ($ligne) {
                                        $nomenclature = $ligne->nomenclature;
                                        $label = $nomenclature->code . ' - ' . $nomenclature->libelle;
                                        $disponible = $ligne->disponible_engagement ?? 0;
                                        $label .= ' (Dispo: ' . number_format($disponible, 0, ',', ' ') . ' FCFA)';
                                        return [$nomenclature->id => $label];
                                    })
                                    ->toArray();
                            })
                            ->helperText('Sélectionnez la ligne budgétaire à engager')
                            ->live()
                            ->afterStateUpdated(function ($state, $set) use ($record) {
                                if ($state) {
                                    $ligne = \App\Models\LigneBudgetaire::where('budget_id', $record->budget_id)
                                        ->where('nomenclature_id', $state)
                                        ->with('nomenclature')
                                        ->first();

                                    if ($ligne) {
                                        $set('ligne_details', [
                                            'montant_vote' => $ligne->montant_vote,
                                            'engage' => $ligne->engage,
                                            'disponible' => $ligne->disponible_engagement,
                                        ]);
                                    }
                                }
                            }),

                        Forms\Components\Placeholder::make('montant_info')
                            ->label('Montants de la décision')
                            ->content(fn($record) => new \Illuminate\Support\HtmlString(
                                '<div style="font-family: monospace; line-height: 1.8;">' .
                                    '<strong>Montant brut :</strong> ' .
                                    number_format($record->montant_brut ?? 0, 0, ',', ' ') . ' FCFA<br>' .

                                    '<strong>CNPS (' . number_format($record->taux_cnps ?? 0, 2) . '%) :</strong> ' .
                                    number_format($record->montant_cnps ?? 0, 0, ',', ' ') . ' FCFA<br>' .

                                    '<strong>IRNC (' . number_format($record->taux_irnc ?? 0, 2) . '%) :</strong> ' .
                                    number_format($record->montant_irnc ?? 0, 0, ',', ' ') . ' FCFA<br>' .

                                    '<strong>Autres retenues :</strong> ' .
                                    number_format($record->autres_retenues ?? 0, 0, ',', ' ') . ' FCFA<br>' .

                                    '<strong>Total taxes :</strong> ' .
                                    number_format($record->total_taxes ?? 0, 0, ',', ' ') . ' FCFA<br>' .

                                    '<strong style="color: green;">Net à engager :</strong> ' .
                                    '<strong>' . number_format($record->montant_net ?? 0, 0, ',', ' ') . ' FCFA</strong>' .
                                    '</div>'
                            )),

                        Forms\Components\Placeholder::make('ligne_info')
                            ->label('Crédit disponible')
                            ->content(function ($get) use ($record) {
                                $nomenclatureId = $get('nomenclature_id');

                                if (!$nomenclatureId) {
                                    return 'Sélectionnez une nomenclature pour voir le crédit disponible';
                                }

                                $ligne = \App\Models\LigneBudgetaire::where('budget_id', $record->budget_id)
                                    ->where('nomenclature_id', $nomenclatureId)
                                    ->first();

                                if (!$ligne) {
                                    return 'Ligne budgétaire introuvable';
                                }

                                $peutEngager = $ligne->peutEngager($record->montant_net);
                                $color = $peutEngager ? 'green' : 'red';
                                $icon = $peutEngager ? '✅' : '❌';

                                return new \Illuminate\Support\HtmlString(
                                    '<div style="font-family: monospace; line-height: 1.8;">' .
                                        '<strong>Montant voté :</strong> ' . number_format($ligne->montant_vote, 0, ',', ' ') . ' FCFA<br>' .
                                        '<strong>Déjà engagé :</strong> ' . number_format($ligne->engage, 0, ',', ' ') . ' FCFA<br>' .
                                        '<strong style="color: ' . $color . ';">' . $icon . ' Disponible :</strong> <strong>' . number_format($ligne->disponible_engagement, 0, ',', ' ') . ' FCFA</strong><br>' .
                                        ($peutEngager
                                            ? '<span style="color: green;">✅ Crédit suffisant</span>'
                                            : '<span style="color: red;">❌ Crédit insuffisant (manque ' . number_format($record->montant_net - $ligne->disponible_engagement, 0, ',', ' ') . ' FCFA)</span>'
                                        ) .
                                        '</div>'
                                );
                            })
                            ->hidden(fn($get) => !$get('nomenclature_id')),
                    ];
                } else {
                    // Pour BonCommande ou autres (mode simple)
                    return [
                        Forms\Components\TextInput::make('montant_engage')
                            ->label('Montant à engager')
                            ->numeric()
                            ->required()
                            ->default(fn($record) => $record->montant_total ?? $record->montant_ht ?? 0)
                            ->suffix('FCFA')
                            ->helperText('Montant qui sera engagé sur le budget'),
                    ];
                }
            })
            ->action(function ($record, array $data) {
                try {
                    if (method_exists($record, 'engagerBudget')) {
                        if ($record instanceof \App\Models\DecisionAdministrative) {
                            if (empty($data['nomenclature_id'])) {
                                throw new \Exception('Veuillez sélectionner une nomenclature budgétaire');
                            }

                            $record->engagerBudget($data['nomenclature_id']);
                        } else {
                            $montantEngage = $data['montant_engage'] ?? ($record->montant_total ?? $record->montant_ht ?? 0);

                            try {
                                $engagement = $record->engagerBudget($montantEngage);
                            } catch (\ArgumentCountError $e) {
                                $engagement = $record->engagerBudget();
                            }
                        }

                        $record->refresh();

                        $numeroEngagement = $record->engagement?->numero ?? $record->engagement?->reference_document ?? 'N/A';

                        Notification::make()
                            ->title('Budget engagé avec succès')
                            ->success()
                            ->body("Engagement créé : {$numeroEngagement}")
                            ->send();
                    } else {
                        throw new \Exception('La méthode engagerBudget() n\'existe pas');
                    }
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Erreur lors de l\'engagement')
                        ->danger()
                        ->body($e->getMessage())
                        ->persistent()
                        ->send();

                    throw $e;
                }
            });
    }

    /* =========================
     | ACTION : TRANSMETTRE
     ========================= */
    private static function transmettre(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('transmettre')
            ->label('Transmettre')
            ->icon('heroicon-o-paper-airplane')
            ->color('info')
            ->visible(
                fn($record) =>
                method_exists($record, 'peutEtreTransmis')
                    && $record->peutEtreTransmis()
                    && in_array($record->statut, ['brouillon', 'valide', 'validee'])
            )
            ->form([
                Forms\Components\Select::make('destinataire_id')
                    ->label('Transmettre à')
                    ->options(
                        fn() =>
                        User::actif()
                            ->where('id', '!=', auth()->id())
                            ->whereHas('roles', function ($q) {
                                $q->where(
                                    'niveau_hierarchique',
                                    '>=',
                                    auth()->user()->getNiveauHierarchique()
                                );
                            })
                            ->orderBy('name')
                            ->pluck('name', 'id')
                    )
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live(),

                Forms\Components\Select::make('action_attendue')
                    ->label('Action attendue')
                    ->options([
                        'validation'    => 'Validation',
                        'engagement'    => 'Engagement',
                        'verification'  => 'Vérification',
                        'signature'     => 'Signature',
                        'information'   => 'Pour information',
                    ])
                    ->required()
                    ->default('validation'),

                Forms\Components\Textarea::make('commentaire')
                    ->label('Commentaire')
                    ->rows(3),

                Forms\Components\Select::make('priorite')
                    ->label('Priorité')
                    ->options(fn(Get $get) => self::priorites($get))
                    ->default('normale')
                    ->required(),

                Forms\Components\DatePicker::make('date_limite')
                    ->label('Date limite (optionnel)')
                    ->minDate(now()),
            ])
            ->action(function ($record, array $data) {

                $expediteur   = auth()->user();
                $destinataire = User::actif()->findOrFail($data['destinataire_id']);

                // 🔒 Sécurité serveur : priorité
                if (
                    in_array($data['priorite'], ['haute', 'urgente']) &&
                    !$expediteur->peutImposerPrioriteA($destinataire)
                ) {
                    throw new \Exception(
                        "Vous ne pouvez pas imposer une priorité élevée à un supérieur hiérarchique."
                    );
                }

                $record->transmettreA(
                    $destinataire,
                    $data['action_attendue'],
                    $data['commentaire'] ?? null,
                    [
                        'priorite'    => $data['priorite'],
                        'date_limite' => $data['date_limite'] ?? null,
                    ]
                );

                Notification::make()
                    ->title('Document transmis')
                    ->success()
                    ->body("Transmis à {$destinataire->name}")
                    ->send();
            });
    }


    /* =========================
     | ACTION : RETOURNER
     ========================= */
    private static function retourner(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('retourner')
            ->label('Retourner')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('warning')
            ->visible(
                fn($record) =>
                method_exists($record, 'estDestinataireActuel')
                    && $record->estDestinataireActuel()
                    && $record->transmissionEnCours()
            )
            ->form([
                Forms\Components\Textarea::make('motif')
                    ->label('Motif du retour')
                    ->required()
                    ->rows(3),
            ])
            ->requiresConfirmation()
            ->modalHeading('Retourner pour correction')
            ->modalDescription("Le document sera retourné à l'expéditeur")
            ->action(function ($record, array $data) {
                // 1. Rejeter la transmission
                Transmission::where('document_type', get_class($record))
                    ->where('document_id', $record->id)
                    ->where('destinataire_id', auth()->id())
                    ->where('statut', 'en_attente')
                    ->first()
                    ?->rejeter($data['motif']);

                // 2. Remettre le document en brouillon pour permettre la modification
                $record->update(['statut' => 'brouillon']);

                // 3. Notifier l'émetteur original
                $emission = Transmission::where('document_type', get_class($record))
                    ->where('document_id', $record->id)
                    ->whereNotNull('expediteur_id')
                    ->latest()
                    ->first();

                if ($emission?->expediteur) {
                    Notification::make()
                        ->title('Document retourné pour correction')
                        ->warning()
                        ->body("Le document " . ($record->numero ?? '') . " vous a été retourné. Motif : {$data['motif']}")
                        ->sendToDatabase($emission->expediteur);
                }

                // 4. Notifier l'utilisateur courant
                Notification::make()
                    ->title('Document retourné')
                    ->warning()
                    ->body('Le document a été remis en brouillon chez l\'émetteur.')
                    ->send();
            });
    }

    /* =========================
     | ACTION : CLÔTURER TRANSMISSION
     ========================= */
    private static function cloturer(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('cloturer_transmission')
            ->label('Clôturer')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(
                fn($record) =>
                method_exists($record, 'estDestinataireActuel')
                    && $record->estDestinataireActuel()
                    && $record->transmissionEnCours()
            )
            ->form([
                Forms\Components\Textarea::make('reponse')
                    ->label('Réponse / Commentaire')
                    ->rows(3),
            ])
            ->requiresConfirmation()
            ->action(function ($record, array $data) {
                // Clôturer la transmission dans le document
                $record->cloturerTransmission($data['reponse'] ?? null);

                // ✅ Traiter la transmission avec la réponse
                Transmission::where('document_type', get_class($record))
                    ->where('document_id', $record->id)
                    ->where('destinataire_id', auth()->id())
                    ->where('statut', 'en_attente')
                    ->first()
                    ?->traiter($data['reponse'] ?? 'Transmission clôturée');

                Notification::make()
                    ->title('Transmission clôturée')
                    ->success()
                    ->send();
            });
    }

    /* =========================
     | ACTION : HISTORIQUE
     ========================= */
    private static function historique(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('historique_transmissions')
            ->label('Historique')
            ->icon('heroicon-o-clock')
            ->color('gray')
            ->visible(
                fn($record) =>
                method_exists($record, 'aEteTransmis') && $record->aEteTransmis()
            )
            ->modalHeading(
                fn($record) =>
                'Historique des transmissions - ' . ($record->numero ?? $record->id)
            )
            ->modalContent(
                fn($record) =>
                view('filament.modals.historique-transmissions', [
                    'transmissions' => $record->historiqueTransmissions(),
                ])
            )
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fermer');
    }

    /* =========================
     | UTIL : PRIORITÉS
     ========================= */
    private static function priorites(Get $get): array
    {
        $destinataireId = $get('destinataire_id');

        if (!$destinataireId) {
            return ['normale' => 'Normale'];
        }

        $destinataire = User::find($destinataireId);
        $expediteur   = auth()->user();

        if (!$destinataire || !$expediteur) {
            return ['normale' => 'Normale'];
        }

        if ($expediteur->peutImposerPrioriteA($destinataire)) {
            return [
                'basse'   => 'Basse',
                'normale' => 'Normale',
                'haute'   => 'Haute',
                'urgente' => 'Urgente',
            ];
        }

        return [
            'basse'   => 'Basse',
            'normale' => 'Normale',
        ];
    }
}
