<?php

namespace App\Filament\Resources\BonCommandeResource\Pages;

use App\Filament\Resources\BonCommandeResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;

class ViewBonCommande extends ViewRecord
{
    protected static string $resource = BonCommandeResource::class;

    protected ?array $verificationsCache = null;

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
            Actions\EditAction::make()
                ->visible(false),

            Actions\DeleteAction::make()
                ->visible(fn() => static::getResource()::canDelete($this->record))
                ->requiresConfirmation(),

            Actions\Action::make('valider')
                ->label('Valider')
                ->icon('heroicon-o-check-circle')
                ->color('warning')
                ->visible(fn($record) => $record->statut === 'brouillon')
                ->requiresConfirmation()
                ->action(function ($record) {
                    $record->valider(auth()->user());
                    Notification::make()
                        ->title('BC validé')
                        ->success()
                        ->send();
                }),

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
                ->visible(fn($record) => $record->statut === 'valide' && !$record->engagement_id)
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

            Actions\Action::make('annuler')
                ->label('Annuler')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn($record) => ! in_array($record->statut, ['annule', 'livre']))
                ->requiresConfirmation()
                ->action(fn($record) => $record->annuler()),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
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
