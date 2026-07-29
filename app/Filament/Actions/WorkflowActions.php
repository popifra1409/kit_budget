<?php

namespace App\Filament\Actions;

use Filament\Tables;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use App\Models\Transmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class WorkflowActions
{
    public static function make(
        bool    $avecEngagement      = false,
        ?string $pdfServiceClass     = null,
        ?string $pdfRouteName        = null,
        bool    $avecModalEngagement = true
    ): array {
        $actions = [

            // ── Modifier ──────────────────────────────────────────
            Tables\Actions\EditAction::make()
                ->visible(function ($record) {
                    // ✅ Bloqué si en transmission
                    if (self::estEnTransmission($record)) return false;
                    if (method_exists($record, 'peutEtreModifiePar')) {
                        return $record->peutEtreModifiePar();
                    }
                    return $record->estModifiable();
                })
                ->tooltip(function ($record) {
                    if (self::estEnTransmission($record)) {
                        return '🔒 Document en cours de transmission';
                    }
                    return null;
                }),
        ];

        if ($pdfServiceClass && $pdfRouteName) {
            $actions[] = self::pdfActions($pdfServiceClass, $pdfRouteName);
        }

        $actions[] = self::valider();

        if ($avecEngagement) {
            $actions[] = $avecModalEngagement
                ? self::engagerAvecModal()
                : self::engagerSimple();
        }

        $actions = array_merge($actions, [
            self::transmettre(),
            self::rappelerTransmission(),  // ✅ Juste après transmettre
            self::retourner(),
            self::cloturer(),
            self::forcerCloture(),
            self::historique(),
            self::annuler(),              // ✅ NOUVEAU
            self::supprimer(),            // ✅ NOUVEAU
        ]);

        return $actions;
    }

    // =========================================================
    // HELPERS CENTRAUX — morphMap compatible
    // =========================================================

    /**
     * ✅ Vérifie la transmission en tenant compte du morphMap
     * Cherche avec le nom complet ET l'alias morphMap
     */
    private static function estEnTransmission(mixed $record): bool
    {
        // Via le trait si disponible (méthode la plus fiable)
        if (method_exists($record, 'estEnCoursDeTransmission')) {
            return $record->estEnCoursDeTransmission();
        }

        // Fallback direct en base
        return Transmission::where('document_id', $record->id)
            ->where('statut', 'en_attente')
            ->where(function ($q) use ($record) {
                $q->where('document_type', get_class($record))
                    ->orWhere('document_type', self::morphAlias($record));
            })
            ->exists();
    }

    private static function estDestinataire(mixed $record): bool
    {
        if (method_exists($record, 'estDestinataireActuel')) {
            return $record->estDestinataireActuel();
        }

        return Transmission::where('document_id', $record->id)
            ->where('destinataire_id', auth()->id())
            ->where('statut', 'en_attente')
            ->where(function ($q) use ($record) {
                $q->where('document_type', get_class($record))
                    ->orWhere('document_type', self::morphAlias($record));
            })
            ->exists();
    }

    /**
     * ✅ Retourne l'alias morphMap du modèle si configuré
     */
    private static function morphAlias(mixed $record): string
    {
        $map = \Illuminate\Database\Eloquent\Relations\Relation::morphMap();
        return array_search(get_class($record), $map) ?: get_class($record);
    }

    // =========================================================
    // PDF
    // =========================================================
    public static function pdfActionsOnly(
        string $serviceClass,
        string $routeName
    ): Tables\Actions\ActionGroup {
        return self::pdfActions($serviceClass, $routeName);
    }

    private static function pdfActions(
        string $serviceClass,
        string $routeName
    ): Tables\Actions\ActionGroup {
        return Tables\Actions\ActionGroup::make([
            Tables\Actions\Action::make('apercu_pdf_simple')
                ->label('Aperçu BC')->icon('heroicon-o-eye')->color('info')
                ->url(fn($record) => route('bons-commande.pdf.preview.simple', ['id' => $record->id]))
                ->openUrlInNewTab(),

            Tables\Actions\Action::make('telecharger_pdf_simple')
                ->label('Télécharger BC')->icon('heroicon-o-arrow-down-tray')->color('success')
                ->url(fn($record) => route('bons-commande.pdf.download.simple', ['id' => $record->id])),

            Tables\Actions\Action::make('separator_1')
                ->label('─────────────────')->disabled()->color('gray'),

            Tables\Actions\Action::make('apercu_pdf_complet')
                ->label('Aperçu BCA')->icon('heroicon-o-eye')->color('warning')
                ->url(fn($record) => route('bons-commande.pdf.preview.complet', ['id' => $record->id]))
                ->openUrlInNewTab(),

            Tables\Actions\Action::make('telecharger_pdf_complet')
                ->label('Télécharger BCA')->icon('heroicon-o-arrow-down-tray')->color('danger')
                ->url(fn($record) => route('bons-commande.pdf.download.complet', ['id' => $record->id])),
        ])
            ->label('Télécharger')->icon('heroicon-o-document')
            ->size('sm')->color('success')->button()
            ->visible(fn($record) => !in_array($record->statut, ['brouillon']));
    }

    // =========================================================
    // VALIDER
    // =========================================================
    private static function valider(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('valider')
            ->label('Valider')
            ->icon('heroicon-o-check-circle')->color('warning')
            ->visible(function ($record) {
                $permission = self::permissionPour($record, 'valider');
                if (!auth()->user()?->can($permission)) return false;
                if (!in_array($record->statut, ['brouillon'])) return false;

                // ✅ En transmission : seul le destinataire pour 'validation' peut valider
                if (self::estEnTransmission($record)) {
                    if (!self::estDestinataire($record)) return false;
                    $t = $record->transmissionEnCours();
                    return $t?->action_attendue === 'validation';
                }

                // ✅ Hors transmission : créateur uniquement
                return $record->created_by === auth()->id();
            })
            ->requiresConfirmation()
            ->modalHeading('Valider le document')
            ->action(function ($record) {
                $record->valider(auth()->user());

                // ✅ Clôturer la transmission si c'était pour validation
                Transmission::where('document_id', $record->id)
                    ->where('destinataire_id', auth()->id())
                    ->where('statut', 'en_attente')
                    ->where('action_attendue', 'validation')
                    ->where(function ($q) use ($record) {
                        $q->where('document_type', get_class($record))
                            ->orWhere('document_type', self::morphAlias($record));
                    })
                    ->first()
                    ?->traiter('Document validé');

                Notification::make()->title('✅ Document validé')->success()->send();
            });
    }

    // =========================================================
    // ENGAGER AVEC MODAL (BonCommande)
    // =========================================================
    private static function engagerAvecModal(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('engager')
            ->label('Engager')
            ->icon('heroicon-o-currency-dollar')->color('success')
            ->visible(function ($record) {
                if (!($record instanceof \App\Models\BonCommande)) return false;
                if (!auth()->user()?->can('engager_bon_commande')) return false;
                if ($record->statut !== 'valide' || $record->engagement_id) return false;

                // ✅ En transmission : seul le destinataire pour 'engagement'
                if (self::estEnTransmission($record)) {
                    if (!self::estDestinataire($record)) return false;
                    $t = $record->transmissionEnCours();
                    return $t?->action_attendue === 'engagement';
                }

                return $record->created_by === auth()->id()
                    || !self::estEnTransmission($record);
            })
            ->modalHeading(fn($record) => "Engagement budgétaire — BC N° {$record->numero}")
            ->modalDescription('Vérification de la disponibilité budgétaire')
            ->modalWidth('5xl')
            ->modalContent(function ($record) {
                $verifications = $record->verifierDisponibiliteBudgetaire();
                return view('filament.modals.engagement-budget-verification', [
                    'bonCommande'   => $record,
                    'verifications' => $verifications,
                ]);
            })
            ->modalSubmitActionLabel(function ($record) {
                $v = $record->verifierDisponibiliteBudgetaire();
                return $v['peut_engager']
                    ? "✅ Confirmer l'engagement"
                    : "❌ Crédit insuffisant";
            })
            ->disabled(fn($record) => !$record->verifierDisponibiliteBudgetaire()['peut_engager'])
            ->action(function ($record) {
                try {
                    $verifications = $record->verifierDisponibiliteBudgetaire();
                    if (!$verifications['peut_engager']) {
                        $details = collect($verifications['lignes_budgetaires'])
                            ->filter(fn($l) => !$l['suffisant'])
                            ->map(
                                fn($l) =>
                                "• {$l['nomenclature']->code} : manque "
                                    . number_format($l['manque'], 0, ',', ' ') . " FCFA"
                            )->implode("\n");
                        Notification::make()
                            ->title('❌ Crédit insuffisant')
                            ->danger()->body($details)->persistent()->send();
                        return;
                    }

                    $engagement = $record->engagerBudget($verifications);

                    Transmission::where('document_id', $record->id)
                        ->where('destinataire_id', auth()->id())
                        ->where('action_attendue', 'engagement')
                        ->where('statut', 'en_attente')
                        ->first()
                        ?->traiter("Budget engagé — N° {$engagement->numero}");

                    Notification::make()
                        ->title('✅ Budget engagé')
                        ->success()->body("Engagement : {$engagement->numero}")->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('❌ Erreur engagement')
                        ->danger()->body($e->getMessage())->persistent()->send();
                }
            });
    }

    // =========================================================
    // ENGAGER SIMPLE (DecisionAdministrative)
    // =========================================================
    private static function engagerSimple(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('engager')
            ->label('Engager')
            ->icon('heroicon-o-banknotes')->color('primary')
            ->visible(function ($record) {
                $permission = self::permissionPour($record, 'engager');
                if (!auth()->user()?->can($permission)) return false;
                if (!in_array($record->statut, ['valide', 'validee'])) return false;
                if ($record->engage ?? $record->engagee ?? false) return false;

                // ✅ En transmission : seul destinataire pour 'engagement'
                if (self::estEnTransmission($record)) {
                    if (!self::estDestinataire($record)) return false;
                    $t = $record->transmissionEnCours();
                    return $t?->action_attendue === 'engagement';
                }

                return true;
            })
            ->requiresConfirmation()
            ->modalHeading('Engager le budget')
            ->modalDescription(
                fn($record) =>
                "Créer un engagement pour " . ($record->numero ?? 'ce document')
            )
            ->form(function ($record) {
                if ($record instanceof \App\Models\DecisionAdministrative) {
                    return [
                        Forms\Components\Select::make('nomenclature_id')
                            ->label('Nomenclature budgétaire')
                            ->required()->searchable()->preload()
                            ->options(function () use ($record) {
                                return \App\Models\LigneBudgetaire::where('budget_id', $record->budget_id)
                                    ->with('nomenclature')->get()
                                    ->filter(fn($l) => $l->nomenclature)
                                    ->mapWithKeys(fn($l) => [
                                        $l->nomenclature->id =>
                                        $l->nomenclature->code . ' — ' . $l->nomenclature->libelle
                                            . ' (Dispo: '
                                            . number_format($l->disponible_engagement ?? 0, 0, ',', ' ')
                                            . ' FCFA)',
                                    ])->toArray();
                            })
                            ->live()
                            ->afterStateUpdated(function (string|int|null $state, Set $set) use ($record) {
                                if (!$state) return;
                            }),

                        Forms\Components\Placeholder::make('montant_info')
                            ->label('Montants de la décision')
                            ->content(fn() => new \Illuminate\Support\HtmlString(
                                '<div style="font-family:monospace;line-height:1.8;">'
                                    . '<strong>Montant brut :</strong> '
                                    . number_format($record->montant_brut ?? 0, 0, ',', ' ') . ' FCFA<br>'
                                    . '<strong>IR (' . number_format($record->taux_ir ?? 0, 2) . '%) :</strong> '
                                    . number_format($record->montant_ir ?? 0, 0, ',', ' ') . ' FCFA<br>'
                                    . '<strong>IR(NC) (' . number_format($record->taux_irnc ?? 0, 2) . '%) :</strong> '
                                    . number_format($record->montant_irnc ?? 0, 0, ',', ' ') . ' FCFA<br>'
                                    . '<strong>CNPS (' . number_format($record->taux_cnps ?? 0, 2) . '%) :</strong> '
                                    . number_format($record->montant_cnps ?? 0, 0, ',', ' ') . ' FCFA<br>'
                                    . '<strong style="color:green;">Net à engager :</strong> '
                                    . '<strong>'
                                    . number_format($record->montant_net ?? 0, 0, ',', ' ')
                                    . ' FCFA</strong>'
                                    . '</div>'
                            )),

                        Forms\Components\Placeholder::make('ligne_info')
                            ->label('Crédit disponible')
                            ->content(function (Get $get) use ($record) {
                                $nomId = $get('nomenclature_id');
                                if (!$nomId) return 'Sélectionnez une nomenclature';
                                $ligne = \App\Models\LigneBudgetaire::where('budget_id', $record->budget_id)
                                    ->where('nomenclature_id', $nomId)->first();
                                if (!$ligne) return 'Introuvable';
                                $ok    = $ligne->peutEngager($record->montant_net ?? 0);
                                $color = $ok ? 'green' : 'red';
                                return new \Illuminate\Support\HtmlString(
                                    '<div style="font-family:monospace;line-height:1.8;">'
                                        . '<strong>Disponible :</strong> <span style="color:' . $color . ';">'
                                        . number_format($ligne->disponible_engagement ?? 0, 0, ',', ' ')
                                        . ' FCFA</span><br>'
                                        . ($ok ? '✅ Crédit suffisant' : '❌ Crédit insuffisant')
                                        . '</div>'
                                );
                            })
                            ->hidden(fn(Get $get) => !$get('nomenclature_id')),
                    ];
                }
                return [
                    Forms\Components\TextInput::make('montant_engage')
                        ->label('Montant à engager')->numeric()->required()
                        ->default(fn() => $record->montant_total ?? $record->montant_ht ?? 0)
                        ->suffix('FCFA'),
                ];
            })
            ->action(function ($record, array $data) {
                try {
                    if (!method_exists($record, 'engagerBudget')) {
                        throw new \Exception("engagerBudget() n'existe pas");
                    }
                    if ($record instanceof \App\Models\DecisionAdministrative) {
                        if (empty($data['nomenclature_id'])) {
                            throw new \Exception('Sélectionnez une nomenclature');
                        }
                        $record->engagerBudget($data['nomenclature_id']);
                    } else {
                        $montant = $data['montant_engage']
                            ?? $record->montant_total ?? $record->montant_ht ?? 0;
                        try {
                            $record->engagerBudget($montant);
                        } catch (\ArgumentCountError $e) {
                            $record->engagerBudget();
                        }
                    }
                    $record->refresh();
                    Notification::make()
                        ->title('✅ Budget engagé')
                        ->success()
                        ->body("Engagement : " . ($record->engagement?->numero ?? 'N/A'))
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('❌ Erreur engagement')
                        ->danger()->body($e->getMessage())->persistent()->send();
                }
            });
    }

    // =========================================================
    // TRANSMETTRE
    // =========================================================
    private static function transmettre(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('transmettre')
            ->label('Transmettre')
            ->icon('heroicon-o-paper-airplane')->color('info')
            ->visible(function ($record) {
                // ✅ Bloqué si déjà en transmission
                if (self::estEnTransmission($record)) return false;
                return in_array($record->statut, ['brouillon', 'valide', 'validee']);
            })
            ->form([
                Forms\Components\Select::make('destinataire_id')
                    ->label('Transmettre à')
                    ->options(function () {
                        // ✅ Sans scope actif() pour éviter les erreurs
                        return User::where('id', '!=', auth()->id())
                            ->orderBy('name')
                            ->pluck('name', 'id');
                    })
                    ->required()->searchable()->preload()->live(),

                Forms\Components\Select::make('action_attendue')
                    ->label('Action attendue')
                    ->options([
                        'validation'   => 'Validation',
                        'engagement'   => 'Engagement',
                        'verification' => 'Vérification',
                        'signature'    => 'Signature',
                        'information'  => 'Pour information',
                    ])
                    ->required()->default('validation'),

                Forms\Components\Textarea::make('commentaire')
                    ->label('Commentaire')->rows(3),

                Forms\Components\Select::make('priorite')
                    ->label('Priorité')
                    ->options(fn(Get $get) => self::priorites($get))
                    ->default('normale')->required(),

                Forms\Components\DatePicker::make('date_limite')
                    ->label('Date limite (optionnel)')->minDate(now()),
            ])
            ->action(function ($record, array $data) {
                $expediteurId = auth()->id();
                $destinataire = User::findOrFail($data['destinataire_id']);

                if (!$expediteurId) {
                    Notification::make()
                        ->title('❌ Utilisateur non authentifié')
                        ->danger()->send();
                    return;
                }

                try {
                    // Annuler les transmissions actives précédentes
                    Transmission::where('document_id', $record->id)
                        ->where('statut', 'en_attente')
                        ->where(function ($q) use ($record) {
                            $q->where('document_type', get_class($record))
                                ->orWhere('document_type', self::morphAlias($record));
                        })
                        ->get()
                        ->each(fn($t) => $t->annuler('Remplacée'));

                    $record->transmettreA(
                        $destinataire,
                        $data['action_attendue'],
                        $data['commentaire'] ?? null,
                        [
                            'priorite'    => $data['priorite'],
                            'date_limite' => $data['date_limite'] ?? null,
                        ]
                    );

                    // Vérifier création
                    $created = Transmission::where('document_id', $record->id)
                        ->where('statut', 'en_attente')
                        ->where(function ($q) use ($record) {
                            $q->where('document_type', get_class($record))
                                ->orWhere('document_type', self::morphAlias($record));
                        })
                        ->exists();

                    if (!$created) {
                        Notification::make()
                            ->title('❌ Échec — transmission non créée')
                            ->danger()->send();
                        return;
                    }

                    Notification::make()
                        ->title('📤 Transmis à ' . $destinataire->name)
                        ->success()->send();
                } catch (\Exception $e) {
                    \Log::error('Erreur transmission', [
                        'record' => get_class($record) . '#' . $record->id,
                        'error'  => $e->getMessage(),
                    ]);
                    Notification::make()
                        ->title('❌ ' . $e->getMessage())
                        ->danger()->persistent()->send();
                }
            });
    }

    // =========================================================
    // RAPPELER MA TRANSMISSION
    // =========================================================
    private static function rappelerTransmission(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('rappeler_transmission')
            ->label(function ($record) {
                $temps = method_exists($record, 'tempsRestantAnnulation')
                    ? $record->tempsRestantAnnulation()
                    : null;
                return $temps ? "↩ Rappeler ({$temps})" : '↩ Rappeler';
            })
            ->icon('heroicon-o-arrow-uturn-left')->color('gray')
            ->visible(function ($record) {
                // ✅ Doit être en transmission ET être l'émetteur ET dans le délai
                if (!self::estEnTransmission($record)) return false;
                if (!method_exists($record, 'peutAnnulerSaTransmission')) return false;
                return $record->peutAnnulerSaTransmission();
            })
            ->form([
                Forms\Components\Placeholder::make('info')
                    ->label('')
                    ->content(function ($record) {
                        $temps = method_exists($record, 'tempsRestantAnnulation')
                            ? ($record->tempsRestantAnnulation() ?? '—')
                            : '—';
                        return new \Illuminate\Support\HtmlString(
                            '<div class="rounded-lg p-3 text-sm '
                                . 'bg-yellow-50 dark:bg-yellow-900/30 '
                                . 'text-yellow-800 dark:text-yellow-200 '
                                . 'border border-yellow-300 dark:border-yellow-700">'
                                . "⏱️ Temps restant : <strong>{$temps}</strong><br>"
                                . "Le destinataire sera notifié et le document remis en brouillon."
                                . '</div>'
                        );
                    })->columnSpanFull(),

                Forms\Components\Textarea::make('raison')
                    ->label('Raison du rappel')
                    ->placeholder('Ex: Erreur de montant, mauvais destinataire...')
                    ->rows(2),
            ])
            ->requiresConfirmation()
            ->modalHeading('Rappeler la transmission')
            ->modalDescription('Le document sera remis en brouillon chez vous.')
            ->action(function ($record, array $data) {
                try {
                    $record->annulerMaTransmission($data['raison'] ?? null);
                    Notification::make()
                        ->title('↩ Transmission rappelée')
                        ->success()
                        ->body('Le document est de nouveau disponible.')
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('❌ ' . $e->getMessage())
                        ->danger()->send();
                }
            });
    }

    // =========================================================
    // RETOURNER
    // =========================================================
    private static function retourner(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('retourner')
            ->label('Retourner')
            ->icon('heroicon-o-arrow-uturn-left')->color('warning')
            ->visible(fn($record) => self::estDestinataire($record))
            ->form([
                Forms\Components\Textarea::make('motif')
                    ->label('Motif du retour')->required()->rows(3),
            ])
            ->requiresConfirmation()
            ->modalHeading('Retourner pour correction')
            ->modalDescription("Le document sera remis en brouillon chez l'émetteur.")
            ->action(function ($record, array $data) {
                try {
                    $record->retournerPourCorrection($data['motif']);
                    Notification::make()
                        ->title('↩ Document retourné')
                        ->warning()
                        ->body("Remis en brouillon chez l'émetteur.")
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('❌ ' . $e->getMessage())
                        ->danger()->send();
                }
            });
    }

    // =========================================================
    // CLÔTURER
    // =========================================================
    private static function cloturer(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('cloturer_transmission')
            ->label('Clôturer')
            ->icon('heroicon-o-check-circle')->color('success')
            ->visible(fn($record) => self::estDestinataire($record))
            ->form([
                Forms\Components\Textarea::make('reponse')
                    ->label('Réponse / Commentaire')->rows(3),
            ])
            ->requiresConfirmation()
            ->modalHeading('Clôturer la transmission')
            ->action(function ($record, array $data) {
                try {
                    $record->cloturerTransmission($data['reponse'] ?? null);
                    Notification::make()
                        ->title('✅ Transmission clôturée')
                        ->success()->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('❌ ' . $e->getMessage())
                        ->danger()->send();
                }
            });
    }

    /**
     * ✅ NOUVEAU — Clôture forcée réservée aux admins, visible uniquement
     * quand le document est en transmission mais que l'utilisateur connecté
     * N'EST PAS le destinataire réel (sinon "Clôturer" suffit déjà).
     */
    private static function forcerCloture(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('forcer_cloture_transmission')
            ->label('Forcer la clôture (Admin)')
            ->icon('heroicon-o-shield-exclamation')
            ->color('danger')
            ->visible(
                fn($record) =>
                self::estEnTransmission($record)
                    && !self::estDestinataire($record)
                    && (auth()->user()?->hasAnyRole(['super_admin', 'admin']) ?? false)
            )
            ->form([
                Forms\Components\Textarea::make('motif')
                    ->label('Motif de la clôture forcée')
                    ->required()
                    ->rows(3)
                    ->helperText('Obligatoire — cette action est tracée dans l\'historique et notifiée au destinataire et à l\'expéditeur d\'origine.'),
            ])
            ->requiresConfirmation()
            ->modalHeading('⚠️ Forcer la clôture de cette transmission')
            ->modalDescription('Cette action clôture la transmission à la place du destinataire réel. Réservé aux cas exceptionnels (destinataire absent, erreur d\'aiguillage...).')
            ->action(function ($record, array $data) {
                try {
                    $record->forcerClotureTransmission($data['motif']);
                    Notification::make()
                        ->title('✅ Transmission clôturée de force')
                        ->warning()
                        ->body('Cette action a été tracée dans l\'historique.')
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('❌ ' . $e->getMessage())
                        ->danger()->send();
                }
            });
    }

    // =========================================================
    // HISTORIQUE
    // =========================================================
    private static function historique(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('historique_transmissions')
            ->label('Historique')
            ->icon('heroicon-o-clock')->color('gray')
            ->visible(
                fn($record) =>
                method_exists($record, 'aEteTransmis') && $record->aEteTransmis()
            )
            ->modalHeading(
                fn($record) =>
                'Historique — ' . ($record->numero ?? $record->id)
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

    // =========================================================
    // ✅ NOUVEAU — ANNULER
    // =========================================================
    private static function annuler(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('annuler')
            ->label('Annuler')
            ->icon('heroicon-o-x-circle')->color('danger')
            ->visible(function ($record) {
                // ✅ Bloqué si en transmission
                if (self::estEnTransmission($record)) return false;

                $permission = self::permissionPour($record, 'annuler');
                if (!auth()->user()?->can($permission)) return false;

                return !in_array($record->statut, ['annule', 'annulee']);
            })
            ->requiresConfirmation()
            ->modalHeading('Annuler le document')
            ->form([
                Forms\Components\Textarea::make('motif')
                    ->label("Motif d'annulation")
                    ->required()->rows(2),
            ])
            ->action(function ($record, array $data) {
                try {
                    if (method_exists($record, 'annuler')) {
                        $record->annuler($data['motif'] ?? null);
                    } else {
                        $record->update(['statut' => 'annule']);
                    }
                    Notification::make()
                        ->title('⚠️ Document annulé')
                        ->warning()->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('❌ ' . $e->getMessage())
                        ->danger()->send();
                }
            });
    }

    // =========================================================
    // ✅ NOUVEAU — SUPPRIMER
    // =========================================================
    private static function supprimer(): Tables\Actions\DeleteAction
    {
        return Tables\Actions\DeleteAction::make()
            ->visible(function ($record) {
                // ✅ Bloqué si en transmission
                if (self::estEnTransmission($record)) return false;

                if (!in_array($record->statut, ['brouillon'])) return false;

                $permission = self::permissionPour($record, 'delete');
                return auth()->user()?->can($permission) ?? false;
            });
    }

    // =========================================================
    // HELPER : Permission dynamique
    // =========================================================
    private static function permissionPour(mixed $record, string $action): string
    {
        return match (true) {
            $record instanceof \App\Models\BonCommande
            => "{$action}_bon_commande",
            $record instanceof \App\Models\DecisionAdministrative
            => "{$action}_decision_administrative",
            default
            => "{$action}_" . strtolower(class_basename($record)),
        };
    }

    // =========================================================
    // HELPER : Priorités
    // =========================================================
    private static function priorites(Get $get): array
    {
        $destinataireId = $get('destinataire_id');
        if (!$destinataireId) return ['normale' => 'Normale'];

        $destinataire = User::find($destinataireId);
        $expediteur   = auth()->user();
        if (!$destinataire || !$expediteur) return ['normale' => 'Normale'];

        if (
            method_exists($expediteur, 'peutImposerPrioriteA')
            && $expediteur->peutImposerPrioriteA($destinataire)
        ) {
            return [
                'basse'   => 'Basse',
                'normale' => 'Normale',
                'haute'   => 'Haute',
                'urgente' => 'Urgente',
            ];
        }

        return ['basse' => 'Basse', 'normale' => 'Normale'];
    }
}
