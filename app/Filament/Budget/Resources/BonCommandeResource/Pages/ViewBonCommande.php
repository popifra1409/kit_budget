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

    public function mount(int|string $record): void
    {
        parent::mount($record);
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

    // =========================================================
    // ACTIONS
    // =========================================================
    protected function getHeaderActions(): array
    {
        if ($this->record->estEnCoursDeTransmission()) {
            $transmission = $this->record->transmissions()
                ->where('statut', 'en_attente')
                ->latest()->first();

            if ($transmission && $transmission->destinataire_id !== auth()->id()) {
                Notification::make()
                    ->warning()
                    ->title('Document en cours de transmission')
                    ->body("Ce document a été transmis à {$transmission->destinataire->name} et n'est plus modifiable.")
                    ->persistent()->send();
            }
        }

        return [
            // ── Actualiser ────────────────────────────────────
            Actions\ViewAction::make()
                ->label('Actualiser')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function () {
                    $this->record->refresh();
                    $this->record->load(['lignes', 'fournisseur', 'budget', 'engagement']);
                    Notification::make()->title('✅ Données actualisées')->success()->send();
                }),

            // ── Modifier ──────────────────────────────────────
            Actions\EditAction::make()
                ->visible(
                    fn() =>
                    $this->record->estModifiable()
                    && static::getResource()::canEdit($this->record)
                ),

            // ── Supprimer ─────────────────────────────────────
            Actions\DeleteAction::make()
                ->visible(
                    fn() =>
                    $this->record->statut === 'brouillon'
                    && static::getResource()::canDelete($this->record)
                )
                ->requiresConfirmation(),

            // ── Valider ───────────────────────────────────────
            Actions\Action::make('valider')
                ->label('Valider')
                ->icon('heroicon-o-check-circle')
                ->color('warning')
                ->visible(
                    fn() =>
                    $this->record->statut === 'brouillon'
                    && static::getResource()::canValider($this->record)
                )
                ->requiresConfirmation()
                ->modalHeading('Valider le bon de commande')
                ->modalDescription(fn() => "Valider le BC n° {$this->record->numero} ?")
                ->action(function () {
                    $this->record->valider(auth()->user());
                    $this->record->refresh();
                    Notification::make()
                        ->title('✅ BC validé')
                        ->success()
                        ->body("Le bon de commande {$this->record->numero} a été validé avec succès.")
                        ->send();
                    $this->refreshFormData(['statut']);
                }),

            // ── Engager ───────────────────────────────────────
            Actions\Action::make('engager')
                ->label(function () {
                    $verifications = $this->record->verifierDisponibiliteBudgetaire();
                    return $verifications['peut_engager']
                        ? 'Engager le Budget'
                        : '⚠️ Crédit insuffisant';
                })
                ->icon('heroicon-o-currency-dollar')
                ->color(function () {
                    $verifications = $this->record->verifierDisponibiliteBudgetaire();
                    return $verifications['peut_engager'] ? 'success' : 'danger';
                })
                ->visible(
                    fn() =>
                    $this->record->statut === 'valide'
                    && !$this->record->engagement_id
                    && static::getResource()::canEngager($this->record)
                )
                ->tooltip(function () {
                    $verifications = $this->record->verifierDisponibiliteBudgetaire();
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
                ->modalHeading(fn() => "Engagement budgétaire - BC N° {$this->record->numero}")
                ->modalDescription('Vérification de la disponibilité budgétaire')
                ->modalWidth('5xl')
                ->modalContent(function () {
                    $verifications = $this->record->verifierDisponibiliteBudgetaire();
                    return view('filament.modals.engagement-budget-verification', [
                        'bonCommande' => $this->record,
                        'verifications' => $verifications,
                    ]);
                })
                ->modalSubmitActionLabel(function () {
                    $verifications = $this->record->verifierDisponibiliteBudgetaire();
                    return $verifications['peut_engager']
                        ? '✅ Confirmer l\'engagement'
                        : '❌ Crédit insuffisant';
                })
                ->modalCancelActionLabel('Annuler')
                ->disabled(function () {
                    $verifications = $this->record->verifierDisponibiliteBudgetaire();
                    return !$verifications['peut_engager'];
                })
                ->action(function () {
                    try {
                        $verifications = $this->record->verifierDisponibiliteBudgetaire();

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
                                ->body(implode("\n", $details))
                                ->persistent()->send();
                            return;
                        }

                        $engagement = $this->record->engagerBudget($verifications);
                        $this->record->refresh();
                        Notification::make()
                            ->title('✅ Budget engagé avec succès')
                            ->success()
                            ->body("BC {$this->record->numero} engagé. Engagement : {$engagement->numero}")
                            ->duration(5000)->send();
                        $this->refreshFormData(['statut', 'engage']);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('❌ Erreur lors de l\'engagement')
                            ->danger()->body($e->getMessage())->persistent()->send();
                    }
                }),

            // ── Annuler l'engagement (désengager) ─────────────
            Actions\Action::make('desengager')
                ->label('Annuler l\'engagement')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(
                    fn() =>
                    $this->record->engage
                    && $this->record->peutEtreDesengage()
                    && static::getResource()::canDesengager($this->record)
                )
                ->requiresConfirmation()
                ->modalHeading('Annuler l\'engagement du bon de commande')
                ->modalDescription(function () {
                    // ✅ Requête directe — contourne morphMap
                    $engagement = \App\Models\Engagement::where('engageable_id', $this->record->id)
                        ->where(function ($q) {
                        $q->where('engageable_type', \App\Models\BonCommande::class)
                            ->orWhere('engageable_type', 'bon_commande');
                    })->first();

                    $numEngagement = $engagement?->numero ?? '—';

                    return new \Illuminate\Support\HtmlString(
                        "<div style='color:#dc2626;font-weight:600;'>
                        L'engagement N° <strong>{$numEngagement}</strong> sera supprimé définitivement.<br>
                        Les crédits seront libérés sur la ligne budgétaire.<br><br>
                        Vous pourrez ensuite annuler ou réengager le bon de commande.
                        </div>"
                    );
                })
                ->modalSubmitActionLabel('🔓 Confirmer le désengagement')
                ->modalCancelActionLabel('Annuler')
                ->action(function () {
                    try {
                        $this->record->desengagerBudget();
                        $this->record->refresh();
                        Notification::make()
                            ->title('✅ Engagement annulé — crédits libérés')
                            ->success()
                            ->body("Le BC {$this->record->numero} est de nouveau en statut Validé.")
                            ->duration(5000)->send();
                        $this->refreshFormData(['statut', 'engage']);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('❌ Impossible de désengager')
                            ->danger()->body($e->getMessage())->persistent()->send();
                    }
                }),

            // ── Annuler le BC ─────────────────────────────────
            Actions\Action::make('annuler')
                ->label('Annuler')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(
                    fn() =>
                    $this->record->peutEtreAnnule()
                    && static::getResource()::canAnnuler($this->record)
                )
                ->form([
                    Forms\Components\Placeholder::make('info_annulation')
                        ->label('')
                        ->content(
                            fn() => $this->record->engage
                            ? new \Illuminate\Support\HtmlString(
                                '<div style="background:#fef2f2;border:1px solid #dc2626;
                                             border-radius:.5rem;padding:.75rem;color:#dc2626;font-weight:600;">
                                ❌ Ce BC est engagé.<br>
                                Veuillez d\'abord annuler l\'engagement via le bouton
                                "Annuler l\'engagement", puis revenez annuler le BC.</div>'
                            )
                            : new \Illuminate\Support\HtmlString(
                                '<div style="background:#fef9c3;border:1px solid #ca8a04;
                                             border-radius:.5rem;padding:.75rem;">
                                ⚠️ Le bon de commande sera annulé. Il restera récupérable.</div>'
                            )
                        )
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('motif')
                        ->label('Motif d\'annulation')
                        ->rows(3)->required()
                        ->hidden(fn() => $this->record->engage),
                ])
                ->requiresConfirmation()
                ->modalHeading('Annuler le bon de commande')
                ->action(function (array $data) {
                    try {
                        $this->record->annuler($data['motif'] ?? null);
                        $this->record->refresh();
                        Notification::make()
                            ->title('⚠️ BC annulé')
                            ->warning()->send();
                        $this->refreshFormData(['statut']);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('❌ Erreur')->danger()
                            ->body($e->getMessage())->persistent()->send();
                    }
                }),

            // ── Récupérer le BC ───────────────────────────────
            Actions\Action::make('recuperer')
                ->label('Récupérer')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->visible(
                    fn() =>
                    $this->record->statut === 'annule'
                    && $this->record->peutEtreRecupere()
                    && static::getResource()::canRecuperer($this->record)
                )
                ->form([
                    Forms\Components\Placeholder::make('info_recuperation')
                        ->label('')
                        ->content(new \Illuminate\Support\HtmlString(
                            '<div style="background:#f0fdf4;border:1px solid #16a34a;
                                         border-radius:.5rem;padding:.75rem;">
                            ✅ Le BC sera remis en <strong>Brouillon</strong> — modifiable et réengageable.
                            </div>'
                        ))
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('motif')
                        ->label('Motif de récupération')
                        ->required()->rows(3)
                        ->placeholder('Ex: Changement de fournisseur, correction des montants...')
                        ->helperText('Indiquez pourquoi vous récupérez ce document'),
                ])
                ->requiresConfirmation()
                ->modalHeading('Récupérer le bon de commande annulé')
                ->modalSubmitActionLabel('🔄 Confirmer la récupération')
                ->modalCancelActionLabel('Annuler')
                ->action(function (array $data) {
                    try {
                        $this->record->recuperer($data['motif']);
                        $this->record->refresh();
                        Notification::make()
                            ->title('✅ BC récupéré — remis en Brouillon')
                            ->success()
                            ->body("Vous pouvez maintenant modifier et réengager le BC {$this->record->numero}.")
                            ->duration(5000)->send();

                        return redirect(static::getResource()::getUrl('edit', ['record' => $this->record->id]));
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('❌ Impossible de récupérer le BC')
                            ->danger()->body($e->getMessage())->persistent()->send();
                    }
                }),
        ];
    }

    // =========================================================
    // INFOLIST (inchangé)
    // =========================================================
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
                                    return '🔒 Ce bon de commande est engagé et ne peut plus être modifié. Utilisez le bouton "Annuler l\'engagement" pour le rendre modifiable.';
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
                                if ($record->engagement_id)
                                    return '✅ Budget déjà engagé';
                                if ($record->statut !== 'valide')
                                    return 'Bon de commande non validé';

                                $v = $record->verifierDisponibiliteBudgetaire();
                                if ($v['peut_engager'])
                                    return '✅ Crédit suffisant - Engagement possible';

                                $details = [];
                                foreach ($v['lignes_budgetaires'] as $ligne) {
                                    if (!$ligne['suffisant']) {
                                        $details[] = "{$ligne['nomenclature']->code} : manque " .
                                            number_format($ligne['manque'], 0, ',', ' ') . " FCFA";
                                    }
                                }
                                return '⚠️ Crédit insuffisant : ' . implode(' | ', $details);
                            })
                            ->badge()
                            ->color(function ($record) {
                                if ($record->engagement_id)
                                    return 'success';
                                if ($record->statut !== 'valide')
                                    return 'gray';
                                $v = $record->verifierDisponibiliteBudgetaire();
                                return $v['peut_engager'] ? 'success' : 'danger';
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
                            ->visible(fn($record) => !$record->estModifiable()),

                        Infolists\Components\TextEntry::make('numero')
                            ->label('Numéro BC')->copyable()
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)->weight('bold'),

                        Infolists\Components\TextEntry::make('budget.libelle')->label('Budget'),

                        Infolists\Components\TextEntry::make('statut')
                            ->label('Statut')->badge()
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
                            ->label('Date d\'émission')->date('d/m/Y'),

                        Infolists\Components\TextEntry::make('date_livraison_prevue')
                            ->label('Livraison prévue')->date('d/m/Y')->placeholder('Non renseignée'),

                        Infolists\Components\TextEntry::make('date_livraison_effective')
                            ->label('Livraison effective')->date('d/m/Y')->placeholder('Non livrée')
                            ->visible(fn($record) => $record->date_livraison_effective),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Fournisseur et Service')
                    ->schema([
                        Infolists\Components\TextEntry::make('fournisseur.raison_sociale')->label('Fournisseur'),
                        Infolists\Components\TextEntry::make('fournisseur.telephone')
                            ->label('Téléphone fournisseur')->placeholder('-'),
                        Infolists\Components\TextEntry::make('serviceDemandeur.nom')->label('Service demandeur'),
                        Infolists\Components\TextEntry::make('serviceDemandeur.responsable')
                            ->label('Responsable service')->placeholder('-'),
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
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)->weight('bold'),

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
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)->weight('bold')
                            ->helperText('HT - IR (montant perçu par le fournisseur)'),

                        Infolists\Components\TextEntry::make('lignes_count')
                            ->label('Nombre de lignes')
                            ->state(fn($record) => $record->lignes->count())
                            ->badge()->color('gray'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Engagement Budgétaire')
                    ->schema([
                        Infolists\Components\TextEntry::make('engage')
                            ->label('Budget engagé')->badge()
                            ->formatStateUsing(fn($state) => $state ? 'Oui' : 'Non')
                            ->color(fn($state) => $state ? 'success' : 'gray'),

                        Infolists\Components\TextEntry::make('montant_engage')
                            ->label('Montant engagé')
                            ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ') . ' FCFA')
                            ->visible(fn($record) => $record->engage),

                        Infolists\Components\TextEntry::make('date_engagement')
                            ->label('Date d\'engagement')->dateTime('d/m/Y H:i')
                            ->visible(fn($record) => $record->engage),
                    ])
                    ->columns(3)
                    ->visible(fn($record) => $record->engage),

                Infolists\Components\Section::make('Objet')
                    ->schema([
                        Infolists\Components\TextEntry::make('objet')->label('')->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Validation')
                    ->schema([
                        Infolists\Components\TextEntry::make('validateur.name')
                            ->label('Validé par')->placeholder('Non validé'),
                        Infolists\Components\TextEntry::make('date_validation')
                            ->label('Date de validation')->dateTime('d/m/Y H:i')->placeholder('Non validé'),
                    ])
                    ->columns(2)
                    ->visible(fn($record) => $record->valide_par),

                Infolists\Components\Section::make('Observations')
                    ->schema([
                        Infolists\Components\TextEntry::make('observations')
                            ->label('')->placeholder('Aucune observation')->columnSpanFull(),
                    ])
                    ->collapsible()->collapsed(),
            ]);
    }
}