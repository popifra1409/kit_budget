<?php

namespace App\Filament\Budget\Resources\EngagementResource\Pages;

use App\Filament\Budget\Resources\EngagementResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;

class ViewEngagement extends ViewRecord
{
    protected static string $resource = EngagementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn($record) => $record->statut === 'provisoire' && !$record->engageable_id),

            // =============================================
            // ✅ ACTION : VALIDER (PASSER DÉFINITIF)
            // =============================================
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
                        "Montant: " . number_format($record->montant_engage, 0, ',', ' ') . " FCFA\n\n" .
                        "Cette action est irréversible."
                )
                ->action(function ($record) {
                    try {
                        $record->passerDefinitif(auth()->user());

                        Notification::make()
                            ->title('✅ Engagement passé en définitif')
                            ->success()
                            ->body("L'engagement {$record->numero} est maintenant définitif. Vous pouvez créer les ordonnances de paiement.")
                            ->send();

                        return redirect()->route('filament.budget.resources.engagements.view', ['record' => $record]);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('❌ Erreur')
                            ->danger()
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            // =============================================
            // ✅ ACTION : CRÉER LES ORDONNANCES DE PAIEMENT
            // =============================================
            Actions\Action::make('creer_ordonnances')
                ->label('Créer les OP')
                ->icon('heroicon-o-document-currency-dollar')
                ->color('primary')
                ->visible(function ($record) {
                    return $record->statut === 'definitif'
                        && !$record->hasOrdonnancesPaiement();
                })
                ->requiresConfirmation()
                ->modalHeading('Créer les ordonnances de paiement')
                ->modalDescription(
                    fn($record) =>
                    "Voulez-vous créer les ordonnances de paiement pour l'engagement {$record->numero} ?\n\n" .
                        "Montant total : " . number_format($record->montant_engage, 0, ',', ' ') . " FCFA"
                )
                ->modalContent(function ($record) {
                    // ✅ Charger les relations nécessaires
                    $record->load(['engageable', 'beneficiaire']);

                    // Utiliser la méthode du modèle pour extraire TOUS les montants
                    $donnees = $record->extraireDonneesDocument();

                    return view('filament.modals.recap-ordonnances', [
                        'engagement' => $record,
                        'donnees' => $donnees,
                    ]);
                })
                ->modalWidth('3xl')
                ->action(function ($record) {
                    try {
                        $ordonnances = $record->creerOrdonnancesPaiement();

                        $message = "✅ Ordonnances créées avec succès :\n\n";

                        if (isset($ordonnances['standard'])) {
                            $message .= "• OP Standard : {$ordonnances['standard']->numero}\n";
                            $message .= "  Montant : " . number_format($ordonnances['standard']->montant_net, 0, ',', ' ') . " FCFA\n\n";
                        }

                        if (isset($ordonnances['impot'])) {
                            $message .= "• OP Impôt : {$ordonnances['impot']->numero}\n";
                            $message .= "  Montant : " . number_format($ordonnances['impot']->montant_net, 0, ',', ' ') . " FCFA";
                        }

                        Notification::make()
                            ->title('Ordonnances créées')
                            ->success()
                            ->body($message)
                            ->duration(10000)
                            ->send();

                        return redirect()->route('filament.budget.resources.engagements.view', ['record' => $record]);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('❌ Erreur lors de la création des ordonnances')
                            ->danger()
                            ->body($e->getMessage())
                            ->persistent()
                            ->send();
                    }
                }),

            // =============================================
            // ✅ ACTION : VOIR LES ORDONNANCES (si elles existent)
            // =============================================
            Actions\Action::make('voir_ordonnances')
                ->label('Voir les OP')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->visible(fn($record) => $record->hasOrdonnancesPaiement())
                ->modalHeading(fn($record) => "Ordonnances de paiement - {$record->numero}")
                ->modalContent(function ($record) {
                    $ordonnances = $record->ordonnancesPaiement()->with('beneficiaire')->get();

                    return view('filament.modals.ordonnances-list', [
                        'ordonnances' => $ordonnances,
                        'engagement' => $record,
                    ]);
                })
                ->modalWidth('5xl')
                ->modalSubmitActionLabel(false)
                ->modalCancelActionLabel('Fermer'),

            // =============================================
            // ✅ ACTION : ANNULER
            // =============================================
            Actions\Action::make('annuler')
                ->label('Annuler')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn($record) => $record->statut === 'provisoire' && $record->peutEtreAnnule())
                ->requiresConfirmation()
                ->modalHeading('Annuler l\'engagement')
                ->modalDescription('Confirmer l\'annulation de cet engagement ? Le budget sera libéré.')
                ->action(function ($record) {
                    try {
                        $record->annuler();

                        Notification::make()
                            ->title('✅ Engagement annulé')
                            ->success()
                            ->body("L'engagement {$record->numero} a été annulé. Le budget a été libéré.")
                            ->send();

                        return redirect()->route('filament.budget.resources.engagements.index');
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('❌ Erreur lors de l\'annulation')
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
                Infolists\Components\Section::make('Informations générales')
                    ->schema([
                        Infolists\Components\TextEntry::make('numero')
                            ->label('Numéro')
                            ->weight('bold')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('reference_document')
                            ->label('Référence document')
                            ->placeholder('Aucune'),
                        Infolists\Components\TextEntry::make('type_engagement')
                            ->label('Type')
                            ->badge(),
                        Infolists\Components\TextEntry::make('date_engagement')
                            ->label('Date engagement')
                            ->date('d/m/Y'),
                        Infolists\Components\TextEntry::make('montant_engage')
                            ->label('Montant engagé')
                            ->money('XAF')
                            ->weight('bold')
                            ->color('success'),
                        Infolists\Components\TextEntry::make('statut')
                            ->label('Statut')
                            ->badge()
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
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Budget et nomenclature')
                    ->schema([
                        Infolists\Components\TextEntry::make('budget.libelle')
                            ->label('Budget'),
                        Infolists\Components\TextEntry::make('exercice.annee')
                            ->label('Exercice')
                            ->badge(),
                        Infolists\Components\TextEntry::make('nomenclaturePrincipale.code')
                            ->label('Code nomenclature')
                            ->badge()
                            ->color('warning'),
                        Infolists\Components\TextEntry::make('nomenclaturePrincipale.libelle')
                            ->label('Libellé nomenclature')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Bénéficiaire')
                    ->schema([
                        Infolists\Components\TextEntry::make('beneficiaire_type')
                            ->label('Type de bénéficiaire')
                            ->formatStateUsing(fn($state) => match ($state) {
                                'App\Models\Fournisseur' => 'Fournisseur',
                                'App\Models\Personnel' => 'Personnel',
                                'App\Models\User' => 'Utilisateur',
                                default => class_basename($state ?? ''),
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
                                'BonCommande' => 'Bon de commande',
                                'DecisionAdministrative' => 'Décision administrative',
                                default => $state ? class_basename($state) : 'Manuel',
                            })
                            ->badge(),
                        Infolists\Components\TextEntry::make('engageable.numero')
                            ->label('Numéro document')
                            ->placeholder('N/A'),
                        Infolists\Components\TextEntry::make('objet')
                            ->label('Objet')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                // =============================================
                // ✅ SECTION : DÉTAILS DES MONTANTS ET RETENUES
                // VERSION CORRIGÉE AVEC NOUVELLES TAXES DA
                // =============================================
                Infolists\Components\Section::make('Détails des montants')
                    ->schema([
                        // Montant TTC/Brut (label dynamique selon type)
                        Infolists\Components\TextEntry::make('montant_ttc')
                            ->label(function ($record) {
                                if ($record->estBonCommande()) {
                                    return 'Montant TTC';
                                } else {
                                    return 'Montant brut';
                                }
                            })
                            ->money('XAF')
                            ->weight('bold')
                            ->color('primary')
                            ->getStateUsing(function ($record) {
                                $record->load('engageable');
                                $donnees = $record->extraireDonneesDocument();
                                return $donnees['montant_ttc'] ?? $donnees['montant_brut'] ?? 0;
                            }),

                        // =============================================
                        // ✅ RETENUES - BON DE COMMANDE
                        // =============================================
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('retenue_ir')
                                    ->label('Impôt sur Revenu (IR)')
                                    ->money('XAF')
                                    ->color('danger')
                                    ->getStateUsing(function ($record) {
                                        $record->load('engageable');
                                        $donnees = $record->extraireDonneesDocument();
                                        return $donnees['montant_ir'] ?? 0;
                                    }),

                                Infolists\Components\TextEntry::make('retenue_tva')
                                    ->label('TVA')
                                    ->money('XAF')
                                    ->color('danger')
                                    ->getStateUsing(function ($record) {
                                        $record->load('engageable');
                                        $donnees = $record->extraireDonneesDocument();
                                        return $donnees['montant_tva'] ?? 0;
                                    }),

                                Infolists\Components\TextEntry::make('retenue_tsr')
                                    ->label('TSR')
                                    ->money('XAF')
                                    ->color('danger')
                                    ->getStateUsing(function ($record) {
                                        $record->load('engageable');
                                        $donnees = $record->extraireDonneesDocument();
                                        return $donnees['montant_tsr'] ?? 0;
                                    }),
                            ])
                            ->visible(fn($record) => $record->estBonCommande()),

                        // =============================================
                        // ✅ RETENUES - DÉCISION ADMINISTRATIVE (CORRIGÉ)
                        // Avec TVA, Redevance audiovisuelle, FEICOM
                        // =============================================
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                // Retenue IR (si > 0)
                                Infolists\Components\TextEntry::make('retenue_ir_da')
                                    ->label('Impôt sur Revenu (IR)')
                                    ->money('XAF')
                                    ->color('danger')
                                    ->getStateUsing(function ($record) {
                                        $record->load('engageable');
                                        $donnees = $record->extraireDonneesDocument();
                                        return $donnees['montant_ir'] ?? 0;
                                    })
                                    ->visible(function ($record) {
                                        $record->load('engageable');
                                        $donnees = $record->extraireDonneesDocument();
                                        return ($donnees['montant_ir'] ?? 0) > 0;
                                    }),

                                // Retenue CNPS (si > 0)
                                Infolists\Components\TextEntry::make('retenue_cnps')
                                    ->label('CNPS')
                                    ->money('XAF')
                                    ->color('danger')
                                    ->getStateUsing(function ($record) {
                                        $record->load('engageable');
                                        $donnees = $record->extraireDonneesDocument();
                                        return $donnees['montant_cnps'] ?? 0;
                                    })
                                    ->visible(function ($record) {
                                        $record->load('engageable');
                                        $donnees = $record->extraireDonneesDocument();
                                        return ($donnees['montant_cnps'] ?? 0) > 0;
                                    }),

                                // Retenue IRNC (si > 0)
                                Infolists\Components\TextEntry::make('retenue_irnc')
                                    ->label('IRNC')
                                    ->money('XAF')
                                    ->color('danger')
                                    ->getStateUsing(function ($record) {
                                        $record->load('engageable');
                                        $donnees = $record->extraireDonneesDocument();
                                        return $donnees['montant_irnc'] ?? 0;
                                    })
                                    ->visible(function ($record) {
                                        $record->load('engageable');
                                        $donnees = $record->extraireDonneesDocument();
                                        return ($donnees['montant_irnc'] ?? 0) > 0;
                                    }),

                                // ✅ NOUVELLE TAXE : TVA (si > 0)
                                Infolists\Components\TextEntry::make('retenue_tva_da')
                                    ->label('TVA')
                                    ->money('XAF')
                                    ->color('danger')
                                    ->getStateUsing(function ($record) {
                                        $record->load('engageable');
                                        $donnees = $record->extraireDonneesDocument();
                                        return $donnees['montant_tva'] ?? 0;
                                    })
                                    ->visible(function ($record) {
                                        $record->load('engageable');
                                        $donnees = $record->extraireDonneesDocument();
                                        return ($donnees['montant_tva'] ?? 0) > 0;
                                    }),

                                // ✅ NOUVELLE TAXE : Redevance audiovisuelle (si > 0)
                                Infolists\Components\TextEntry::make('retenue_redevance')
                                    ->label('Redevance audiovisuelle')
                                    ->money('XAF')
                                    ->color('danger')
                                    ->getStateUsing(function ($record) {
                                        $record->load('engageable');
                                        $donnees = $record->extraireDonneesDocument();
                                        return $donnees['montant_redevance'] ?? 0;
                                    })
                                    ->visible(function ($record) {
                                        $record->load('engageable');
                                        $donnees = $record->extraireDonneesDocument();
                                        return ($donnees['montant_redevance'] ?? 0) > 0;
                                    }),

                                // ✅ NOUVELLE TAXE : FEICOM (si > 0)
                                Infolists\Components\TextEntry::make('retenue_feicom')
                                    ->label('FEICOM')
                                    ->money('XAF')
                                    ->color('danger')
                                    ->getStateUsing(function ($record) {
                                        $record->load('engageable');
                                        $donnees = $record->extraireDonneesDocument();
                                        return $donnees['montant_feicom'] ?? 0;
                                    })
                                    ->visible(function ($record) {
                                        $record->load('engageable');
                                        $donnees = $record->extraireDonneesDocument();
                                        return ($donnees['montant_feicom'] ?? 0) > 0;
                                    }),

                                // Autres retenues (si > 0)
                                Infolists\Components\TextEntry::make('autres_retenues')
                                    ->label('Autres retenues')
                                    ->money('XAF')
                                    ->color('danger')
                                    ->getStateUsing(function ($record) {
                                        $record->load('engageable');
                                        $donnees = $record->extraireDonneesDocument();
                                        return $donnees['autres_retenues'] ?? 0;
                                    })
                                    ->visible(function ($record) {
                                        $record->load('engageable');
                                        $donnees = $record->extraireDonneesDocument();
                                        return ($donnees['autres_retenues'] ?? 0) > 0;
                                    }),
                            ])
                            ->visible(fn($record) => $record->estDecision()),

                        // =============================================
                        // ✅ TOTAL RETENUES (CORRIGÉ POUR DA)
                        // =============================================
                        Infolists\Components\TextEntry::make('total_retenues')
                            ->label('Total retenues et impôts')
                            ->money('XAF')
                            ->weight('bold')
                            ->color('warning')
                            ->getStateUsing(function ($record) {
                                $record->load('engageable');
                                $donnees = $record->extraireDonneesDocument();

                                if ($record->estBonCommande()) {
                                    return ($donnees['montant_ir'] ?? 0)
                                        + ($donnees['montant_tva'] ?? 0)
                                        + ($donnees['montant_tsr'] ?? 0);
                                } else {
                                    // ✅ CORRECTION : Inclure TOUTES les nouvelles taxes DA
                                    return ($donnees['montant_ir'] ?? 0)
                                        + ($donnees['montant_cnps'] ?? 0)
                                        + ($donnees['montant_irnc'] ?? 0)
                                        + ($donnees['montant_tva'] ?? 0)          // ✅ NOUVEAU
                                        + ($donnees['montant_redevance'] ?? 0)    // ✅ NOUVEAU
                                        + ($donnees['montant_feicom'] ?? 0)       // ✅ NOUVEAU
                                        + ($donnees['autres_retenues'] ?? 0);
                                }
                            }),

                        // Montant Net
                        Infolists\Components\TextEntry::make('montant_net_final')
                            ->label('💰 Montant Net à payer')
                            ->money('XAF')
                            ->weight('bold')
                            ->color('success')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->getStateUsing(function ($record) {
                                $record->load('engageable');
                                $donnees = $record->extraireDonneesDocument();
                                return $donnees['montant_net'];
                            }),
                    ])
                    ->columns(2)
                    ->visible(fn($record) => $record->engageable_id !== null)
                    ->collapsible(),

                Infolists\Components\Section::make('Lignes d\'engagement')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('lignes')
                            ->label('Détail des lignes')
                            ->schema([
                                Infolists\Components\TextEntry::make('numero_ligne')
                                    ->label('N°'),
                                Infolists\Components\TextEntry::make('nomenclature.code')
                                    ->label('Code'),
                                Infolists\Components\TextEntry::make('nomenclature.libelle')
                                    ->label('Libellé'),
                                Infolists\Components\TextEntry::make('montant')
                                    ->label('Montant')
                                    ->money('XAF'),
                            ])
                            ->columns(4),
                    ])
                    ->collapsible()
                    ->visible(fn($record) => $record->lignes()->exists()),

                Infolists\Components\Section::make('Ordonnances de paiement')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('ordonnancesPaiement')
                            ->label('Liste des ordonnances')
                            ->schema([
                                Infolists\Components\TextEntry::make('numero')
                                    ->label('Numéro')
                                    ->weight('bold'),
                                Infolists\Components\TextEntry::make('type_ordonnance')
                                    ->label('Type')
                                    ->badge()
                                    ->formatStateUsing(fn($state) => match ($state) {
                                        'standard' => 'Standard',
                                        'impot' => 'Impôt',
                                        default => $state,
                                    }),
                                Infolists\Components\TextEntry::make('montant_net')
                                    ->label('Montant')
                                    ->money('XAF'),
                                Infolists\Components\TextEntry::make('date_emission')
                                    ->label('Date émission')
                                    ->date('d/m/Y'),
                                Infolists\Components\TextEntry::make('statut')
                                    ->label('Statut')
                                    ->badge()
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
                            ->label('Créé le')
                            ->dateTime('d/m/Y H:i'),
                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Modifié le')
                            ->dateTime('d/m/Y H:i'),
                        Infolists\Components\TextEntry::make('date_validation')
                            ->label('Validé le')
                            ->dateTime('d/m/Y H:i')
                            ->visible(fn($record) => $record->date_validation),
                        Infolists\Components\TextEntry::make('engagePar.name')
                            ->label('Validé par')
                            ->visible(fn($record) => $record->engage_par),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }
}
