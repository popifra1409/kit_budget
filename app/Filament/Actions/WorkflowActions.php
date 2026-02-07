<?php

namespace App\Filament\Actions;

use Filament\Tables;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use App\Models\User;

class WorkflowActions
{
    /**
     * Génère les actions standard du workflow administratif
     *
     * @param bool $avecEngagement  Active l'action "Engager"
     * @param string|null $pdfServiceClass  Classe du service PDF (ex: BonCommandePdfService::class)
     * @param string|null $pdfRouteName  Nom de la route pour l'aperçu PDF
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
        ?string $pdfRouteName = null
    ): array {
        $actions = [
            Tables\Actions\ViewAction::make(),

            Tables\Actions\EditAction::make()
                ->visible(fn($record) => $record->estModifiable() && !$record->estEnCoursDeTransmission()),
        ];

        // Actions PDF (si service fourni)
        if ($pdfServiceClass && $pdfRouteName) {
            $actions[] = self::pdfActions($pdfServiceClass, $pdfRouteName);
        }

        $actions = array_merge($actions, [
            self::valider(),
        ]);

        if ($avecEngagement) {
            $actions[] = self::engager();
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
            Tables\Actions\Action::make('apercu_pdf_simple')
                ->label('Aperçu BC')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->url(fn($record) => route('bons-commande.pdf.preview.simple', ['bonCommande' => $record->id]))
                ->openUrlInNewTab(),

            Tables\Actions\Action::make('apercu_pdf_complet')
                ->label('Aperçu BCA')
                ->icon('heroicon-o-eye')
                ->color('warning')
                ->url(fn($record) => route('bons-commande.pdf.preview.complet', ['bonCommande' => $record->id]))
                ->openUrlInNewTab(),

            Tables\Actions\Action::make('telecharger_pdf_simple')
                ->label('Télécharger BC')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->url(fn($record) => route('bons-commande.pdf.download.simple', ['bonCommande' => $record->id])),

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
                // Vérifier la permission ET l'état du document
                return auth()->user()?->can('valider_bon_commande')
                    && $record->statut === 'brouillon'
                    && !$record->estEnCoursDeTransmission();
            })
            ->requiresConfirmation()
            ->action(function ($record) {
                $record->valider(auth()->user());

                Notification::make()
                    ->title('Document validé')
                    ->success()
                    ->send();
            });
    }

    /* =========================
 | ACTION : ENGAGER
 ========================= */
    private static function engager(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('engager')
            ->label('Engager')
            ->icon('heroicon-o-banknotes')
            ->color('primary')
            ->visible(
                fn($record) =>
                in_array($record->statut, ['valide', 'validee'])
                    && !($record->engage ?? $record->engagee ?? false)
            )
            // ->disabled(fn(Get $get) => ! ($get('peut_engager') ?? false))
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
                                // ✅ CORRECTION : Utiliser map au lieu de pluck pour filtrer les null
                                return \App\Models\LigneBudgetaire::where('budget_id', $record->budget_id)
                                    ->with('nomenclature')
                                    ->get()
                                    ->filter(fn($ligne) => $ligne->nomenclature) // Filtrer les null
                                    ->mapWithKeys(function ($ligne) {
                                        $nomenclature = $ligne->nomenclature;
                                        $label = $nomenclature->code . ' - ' . $nomenclature->libelle;

                                        // Ajouter le disponible
                                        $disponible = $ligne->disponible_engagement ?? 0;
                                        $label .= ' (Dispo: ' . number_format($disponible, 0, ',', ' ') . ' FCFA)';

                                        return [$nomenclature->id => $label];
                                    })
                                    ->toArray();
                            })
                            ->helperText('Sélectionnez la ligne budgétaire à engager')
                            ->live()
                            ->afterStateUpdated(function ($state, $set) use ($record) {
                                // ✅ Afficher les détails de la ligne sélectionnée
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
                    // Pour BonCommande ou autres
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
                    ->options(User::whereNotNull('name')->pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live(),

                Forms\Components\Select::make('action_attendue')
                    ->label('Action attendue')
                    ->options([
                        'validation' => 'Validation',
                        'engagement' => 'Engagement',
                        'verification' => 'Vérification',
                        'signature' => 'Signature',
                        'information' => 'Pour information',
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
                    ->required()
                    ->live()
                    ->helperText(function (Get $get) {
                        $destinataireId = $get('destinataire_id');

                        if (!$destinataireId) {
                            return '';
                        }

                        $destinataire = User::find($destinataireId);
                        $expediteur = auth()->user();

                        if (!$expediteur->peutImposerPrioriteA($destinataire)) {
                            return '⚠️ Vous ne pouvez pas définir une priorité haute ou urgente pour un supérieur hiérarchique.';
                        }

                        return '';
                    }),

                Forms\Components\DatePicker::make('date_limite')
                    ->label('Date limite (optionnel)')
                    ->minDate(now()),
            ])
            ->action(function ($record, array $data) {
                try {
                    $destinataire = User::findOrFail($data['destinataire_id']);

                    $record->transmettreA(
                        $destinataire,
                        $data['action_attendue'],
                        $data['commentaire'] ?? null,
                        [
                            'priorite' => $data['priorite'],
                            'date_limite' => $data['date_limite'] ?? null,
                        ]
                    );

                    Notification::make()
                        ->title('Document transmis')
                        ->success()
                        ->body("Transmis à {$destinataire->name}")
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Erreur lors de la transmission')
                        ->danger()
                        ->body($e->getMessage())
                        ->send();
                }
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
            ->modalDescription('Le document sera retourné à l\'expéditeur avec votre motif')
            ->action(function ($record, array $data) {
                try {
                    $record->retournerPourCorrection($data['motif']);

                    Notification::make()
                        ->title('Document retourné')
                        ->warning()
                        ->body('Le document a été retourné à l\'expéditeur')
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Erreur')
                        ->danger()
                        ->body($e->getMessage())
                        ->send();
                }
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
            ->modalHeading('Clôturer la transmission')
            ->modalDescription('Confirmez que vous avez traité cette transmission')
            ->action(function ($record, array $data) {
                $record->cloturerTransmission($data['reponse'] ?? null);

                Notification::make()
                    ->title('Transmission clôturée')
                    ->success()
                    ->body('La transmission a été traitée avec succès')
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
        $expediteur = auth()->user();

        if ($destinataire && method_exists($expediteur, 'peutImposerPrioriteA') && $expediteur->peutImposerPrioriteA($destinataire)) {
            return [
                'basse' => 'Basse',
                'normale' => 'Normale',
                'haute' => 'Haute',
                'urgente' => 'Urgente',
            ];
        }

        return [
            'basse' => 'Basse',
            'normale' => 'Normale',
        ];
    }
}
