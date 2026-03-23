<?php

namespace App\Filament\Budget\Resources\BonCommandeResource\Pages;

use App\Filament\Budget\Resources\BonCommandeResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;

class ViewBonCommande extends ViewRecord
{
    protected static string $resource = BonCommandeResource::class;

    protected ?array $verificationsCache = null;

    // ✅ AJOUT : Rafraîchir les données après le montage
    public function mount(int | string $record): void
    {
        parent::mount($record);

        // ✅ Rafraîchir pour avoir les montants à jour
        $this->record->refresh();
        $this->record->load(['lignes', 'fournisseur', 'budget', 'engagement']);
    }

    protected function getVerifications(): array
    {
        if ($this->verificationsCache === null) {
            $this->verificationsCache = $this->record->verifierDisponibiliteBudgetaire();
        }
        return $this->verificationsCache;
    }

    protected function getHeaderActions(): array
    {
        // Afficher un message si en cours de transmission
        if ($this->record->estEnCoursDeTransmission()) {
            $transmission = $this->record->transmissions()
                ->where('statut', 'en_attente')
                ->latest()
                ->first();

            if ($transmission && $transmission->destinataire_id !== auth()->id()) {
                \Filament\Notifications\Notification::make()
                    ->warning()
                    ->title('Document en cours de transmission')
                    ->body("Ce document a été transmis à {$transmission->destinataire->name} et n'est plus modifiable.")
                    ->persistent()
                    ->send();
            }
        }

        return [
            // ✅ Voir (toujours visible)
            Actions\ViewAction::make()
                ->label('Actualiser')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function () {
                    $this->record->refresh();
                    $this->record->load(['lignes', 'fournisseur', 'budget', 'engagement']);

                    Notification::make()
                        ->title('✅ Données actualisées')
                        ->success()
                        ->send();
                }),

            // ✅ Modifier (seulement si modifiable = pas engagé)
            Actions\EditAction::make()
                ->visible(
                    fn() =>
                    $this->record->estModifiable()
                        && static::getResource()::canEdit($this->record)
                ),


            // ✅ Supprimer (si brouillon et droits)
            Actions\DeleteAction::make()
                ->visible(
                    fn() =>
                    $this->record->statut === 'brouillon'
                        && static::getResource()::canDelete($this->record)
                )
                ->requiresConfirmation(),

            // ✅ Valider (si brouillon ET permission de validation)
            Actions\Action::make('valider')
                ->label('Valider')
                ->icon('heroicon-o-check-circle')
                ->color('warning')
                ->visible(
                    fn($record) =>
                    $record->statut === 'brouillon'
                        && static::getResource()::canValider($record)
                )
                ->requiresConfirmation()
                ->modalHeading('Valider le bon de commande')
                ->modalDescription(fn($record) => "Valider le BC n° {$record->numero} ?")
                ->action(function ($record) {
                    $record->valider(auth()->user());
                    $record->refresh();

                    Notification::make()
                        ->title('✅ BC validé')
                        ->success()
                        ->body("Le bon de commande {$record->numero} a été validé avec succès.")
                        ->send();
                }),

            // ✅ Engager (si validé et non engagé ET permission)
            Actions\Action::make('engager')
                ->label(function ($record) {
                    $verifications = $record->verifierDisponibiliteBudgetaire();
                    return $verifications['peut_engager']
                        ? 'Engager le Budget'
                        : '⚠️ Crédit insuffisant';
                })
                ->icon('heroicon-o-currency-dollar')
                ->color(function ($record) {
                    $verifications = $record->verifierDisponibiliteBudgetaire();
                    return $verifications['peut_engager'] ? 'success' : 'danger';
                })
                ->visible(
                    fn($record) =>
                    $record->statut === 'valide'
                        && !$record->engagement_id
                        && static::getResource()::canEngager($record)
                )
                ->tooltip(function ($record) {
                    $verifications = $record->verifierDisponibiliteBudgetaire();
                    if (!$verifications['peut_engager']) {
                        $details = [];
                        foreach ($verifications['lignes_budgetaires'] as $ligne) {
                            if (!$ligne['suffisant']) {
                                $details[] = "{$ligne['nomenclature']->code} : manque " .
                                    number_format($ligne['manque'], 0, ',', ' ') . " FCFA";
                            }
                        }
                        return "Crédit budgétaire insuffisant :\n" . implode("\n", $details);
                    }
                    return "Cliquez pour engager le budget";
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
                    return $verifications['peut_engager'] ? '✅ Confirmer l\'engagement' : '❌ Crédit insuffisant';
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
                                ->body("L'engagement ne peut pas être créé :\n\n" . implode("\n", $details))
                                ->persistent()
                                ->send();
                            return;
                        }

                        $engagement = $record->engagerBudget($verifications);
                        $record->refresh();

                        Notification::make()
                            ->title('✅ Budget engagé avec succès')
                            ->success()
                            ->body("Le bon de commande {$record->numero} a été engagé. Engagement créé : {$engagement->numero}")
                            ->duration(5000)
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('❌ Erreur lors de l\'engagement')
                            ->danger()
                            ->body($e->getMessage())
                            ->persistent()
                            ->send();
                    }
                }),

            Actions\Action::make('desengager')
                ->label('Désengager')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(
                    fn($record) =>
                    $record->engage
                        && $record->peutEtreDesengage()
                        && static::getResource()::canDesengager($record)
                )
                ->requiresConfirmation()
                ->modalHeading('Désengager le bon de commande')
                ->modalDescription(function ($record) {
                    return "⚠️ Confirmer le désengagement du BC n° {$record->numero} ?\n\n" .
                        "Cette action va :\n" .
                        "• Annuler l'engagement budgétaire\n" .
                        "• Libérer les crédits budgétaires\n" .
                        "• Remettre le BC en statut 'brouillon'\n" .
                        "• Permettre à nouveau la modification du BC";
                })
                ->modalSubmitActionLabel('🔓 Confirmer le désengagement')
                ->modalCancelActionLabel('Annuler')
                ->action(function ($record) {
                    try {
                        $record->desengagerBudget();
                        $record->refresh();

                        Notification::make()
                            ->title('✅ BC désengagé avec succès')
                            ->success()
                            ->body("Le BC {$record->numero} a été désengagé. Les crédits budgétaires ont été libérés. Vous pouvez maintenant le modifier.")
                            ->duration(5000)
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('❌ Impossible de désengager')
                            ->danger()
                            ->body($e->getMessage())
                            ->persistent()
                            ->send();
                    }
                }),

            // ✅ Annuler (si permission)
            Actions\Action::make('annuler')
                ->label('Annuler')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(
                    fn($record) =>
                    !in_array($record->statut, ['annule', 'livre'])
                        && static::getResource()::canAnnuler($record)
                )
                ->requiresConfirmation()
                ->modalHeading('Annuler le bon de commande')
                ->modalDescription('⚠️ Cette action annulera le bon de commande.')
                ->action(function ($record) {
                    $record->annuler();
                    $record->refresh();

                    Notification::make()
                        ->title('⚠️ BC annulé')
                        ->warning()
                        ->send();
                }),

            Actions\Action::make('recuperer')
                ->label('Récupérer')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->visible(
                    fn($record) =>
                    $record->statut === 'annule'
                        && $record->peutEtreRecupere()
                        && static::getResource()::canRecuperer($record)
                )
                ->requiresConfirmation()
                ->modalHeading('Récupérer le bon de commande')
                ->modalDescription(function ($record) {
                    return "⚠️ Confirmer la récupération du BC n° {$record->numero} ?\n\n" .
                        "Cette action va :\n" .
                        "• Libérer les crédits budgétaires (si engagé)\n" .
                        "• Réinitialiser la validation et l'engagement\n" .
                        "• Remettre le BC en statut 'Brouillon'\n" .
                        "• Permettre la modification du BC\n\n" .
                        "Vous pourrez ensuite :\n" .
                        "• Modifier le fournisseur/bénéficiaire\n" .
                        "• Modifier les montants\n" .
                        "• Valider à nouveau\n" .
                        "• Engager à nouveau";
                })
                ->form([
                    Forms\Components\Textarea::make('motif')
                        ->label('Motif de récupération')
                        ->required()
                        ->rows(3)
                        ->placeholder('Ex: Changement de fournisseur, correction des montants...')
                        ->helperText('Indiquez pourquoi vous récupérez ce document'),
                ])
                ->modalSubmitActionLabel('🔄 Confirmer la récupération')
                ->modalCancelActionLabel('Annuler')
                ->action(function ($record, array $data) {
                    try {
                        $record->recuperer($data['motif']);
                        $record->refresh();

                        Notification::make()
                            ->title('✅ BC récupéré avec succès')
                            ->success()
                            ->body("Le BC {$record->numero} a été récupéré et remis en brouillon. Vous pouvez maintenant le modifier.")
                            ->duration(5000)
                            ->send();

                        // Rediriger vers la page d'édition
                        //return redirect()->route('filament.budget.resources.bons-commande.edit', ['record' => $record->id]);
                        return redirect(static::getResource()::getUrl('edit', ['record' => $record->id]));
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('❌ Impossible de récupérer le BC')
                            ->danger()
                            ->body($e->getMessage())
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('État du bon de commande')
                    ->schema([
                        Infolists\Components\TextEntry::make('statut_modification')
                            ->label('')
                            ->state(function ($record) {
                                if ($record->engage) {
                                    return '🔒 Ce bon de commande est engagé et ne peut plus être modifié. Utilisez le bouton "Désengager" pour le rendre modifiable.';
                                }
                                return null;
                            })
                            ->color('warning')
                            ->badge()
                            ->visible(fn($record) => $record->engage)
                            ->columnSpanFull(),
                    ])
                    ->visible(fn($record) => $record->engage),

                Infolists\Components\Section::make('Vérification budgétaire')
                    ->schema([
                        Infolists\Components\TextEntry::make('credit_disponible')
                            ->label('Statut du crédit')
                            ->state(function ($record) {
                                if ($record->engagement_id) {
                                    return '✅ Budget déjà engagé';
                                }

                                if ($record->statut !== 'valide') {
                                    return 'Bon de commande non validé';
                                }

                                $verifications = $record->verifierDisponibiliteBudgetaire();

                                if ($verifications['peut_engager']) {
                                    return '✅ Crédit suffisant - Engagement possible';
                                }

                                $details = [];
                                foreach ($verifications['lignes_budgetaires'] as $ligne) {
                                    if (!$ligne['suffisant']) {
                                        $details[] = "{$ligne['nomenclature']->code} : manque " .
                                            number_format($ligne['manque'], 0, ',', ' ') . " FCFA";
                                    }
                                }

                                return '⚠️ Crédit insuffisant : ' . implode(' | ', $details);
                            })
                            ->badge()
                            ->color(function ($record) {
                                if ($record->engagement_id) {
                                    return 'success';
                                }

                                if ($record->statut !== 'valide') {
                                    return 'gray';
                                }

                                $verifications = $record->verifierDisponibiliteBudgetaire();
                                return $verifications['peut_engager'] ? 'success' : 'danger';
                            })
                            ->columnSpanFull(),
                    ])
                    ->visible(fn($record) => $record->statut === 'valide'),

                Infolists\Components\Section::make('Informations générales')
                    ->schema([
                        Infolists\Components\TextEntry::make('verrou')
                            ->label('')
                            ->state(fn($record) => $record->estModifiable() ? null : '🔒 Document verrouillé')
                            ->color('danger')
                            ->visible(fn($record) => ! $record->estModifiable()),

                        Infolists\Components\TextEntry::make('numero')
                            ->label('Numéro BC')
                            ->copyable()
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('budget.libelle')
                            ->label('Budget'),

                        Infolists\Components\TextEntry::make('statut')
                            ->label('Statut')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'brouillon' => 'gray',
                                'valide' => 'warning',
                                'engage' => 'primary',
                                'en_cours' => 'info',
                                'livre_partiellement' => 'success',
                                'livre' => 'success',
                                'annule' => 'danger',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn(string $state): string => match ($state) {
                                'brouillon' => 'Brouillon',
                                'valide' => 'Validé',
                                'engage' => 'Engagé',
                                'en_cours' => 'En cours',
                                'livre_partiellement' => 'Livré partiellement',
                                'livre' => 'Livré',
                                'annule' => 'Annulé',
                                default => $state,
                            }),

                        Infolists\Components\TextEntry::make('date_emission')
                            ->label('Date d\'émission')
                            ->date('d/m/Y'),

                        Infolists\Components\TextEntry::make('date_livraison_prevue')
                            ->label('Livraison prévue')
                            ->date('d/m/Y')
                            ->placeholder('Non renseignée'),

                        Infolists\Components\TextEntry::make('date_livraison_effective')
                            ->label('Livraison effective')
                            ->date('d/m/Y')
                            ->placeholder('Non livrée')
                            ->visible(fn($record) => $record->date_livraison_effective),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Fournisseur et Service')
                    ->schema([
                        Infolists\Components\TextEntry::make('fournisseur.raison_sociale')
                            ->label('Fournisseur'),

                        Infolists\Components\TextEntry::make('fournisseur.telephone')
                            ->label('Téléphone fournisseur')
                            ->placeholder('-'),

                        Infolists\Components\TextEntry::make('serviceDemandeur.nom')
                            ->label('Service demandeur'),

                        Infolists\Components\TextEntry::make('serviceDemandeur.responsable')
                            ->label('Responsable service')
                            ->placeholder('-'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Détails Financiers')
                    ->schema([
                        Infolists\Components\TextEntry::make('montant_ht')
                            ->label('Montant HT')
                            ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ') . ' FCFA')
                            ->color('info')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                        Infolists\Components\TextEntry::make('montant_tva')
                            ->label('Montant TVA')
                            ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ') . ' FCFA')
                            ->color('warning')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                        Infolists\Components\TextEntry::make('montant_ttc')
                            ->label('Montant TTC')
                            ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ') . ' FCFA')
                            ->color('success')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('montant_ir')
                            ->label('Montant IR')
                            ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ') . ' FCFA')
                            ->color('danger')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                        Infolists\Components\TextEntry::make('montant_tsr')
                            ->label('Montant TSR')
                            ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ') . ' FCFA')
                            ->color('danger')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                        Infolists\Components\TextEntry::make('net_a_percevoir')
                            ->label('Net à Percevoir')
                            ->formatStateUsing(fn($record) => number_format($record->net_a_percevoir, 0, ',', ' ') . ' FCFA')
                            ->color('primary')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->weight('bold')
                            ->helperText('HT - IR (montant perçu par le fournisseur)'),

                        Infolists\Components\TextEntry::make('lignes_count')
                            ->label('Nombre de lignes')
                            ->state(fn($record) => $record->lignes->count())
                            ->badge()
                            ->color('gray'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Engagement Budgétaire')
                    ->schema([
                        Infolists\Components\TextEntry::make('engage')
                            ->label('Budget engagé')
                            ->badge()
                            ->formatStateUsing(fn($state) => $state ? 'Oui' : 'Non')
                            ->color(fn($state) => $state ? 'success' : 'gray'),

                        Infolists\Components\TextEntry::make('montant_engage')
                            ->label('Montant engagé')
                            ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ') . ' FCFA')
                            ->visible(fn($record) => $record->engage),

                        Infolists\Components\TextEntry::make('date_engagement')
                            ->label('Date d\'engagement')
                            ->dateTime('d/m/Y H:i')
                            ->visible(fn($record) => $record->engage),
                    ])
                    ->columns(3)
                    ->visible(fn($record) => $record->engage),

                Infolists\Components\Section::make('Objet')
                    ->schema([
                        Infolists\Components\TextEntry::make('objet')
                            ->label('')
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Validation')
                    ->schema([
                        Infolists\Components\TextEntry::make('validateur.name')
                            ->label('Validé par')
                            ->placeholder('Non validé'),

                        Infolists\Components\TextEntry::make('date_validation')
                            ->label('Date de validation')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Non validé'),
                    ])
                    ->columns(2)
                    ->visible(fn($record) => $record->valide_par),

                Infolists\Components\Section::make('Observations')
                    ->schema([
                        Infolists\Components\TextEntry::make('observations')
                            ->label('')
                            ->placeholder('Aucune observation')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
