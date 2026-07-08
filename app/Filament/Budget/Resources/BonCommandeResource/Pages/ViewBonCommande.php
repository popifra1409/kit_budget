<?php

namespace App\Filament\Budget\Resources\BonCommandeResource\Pages;

use App\Filament\Budget\Resources\BonCommandeResource;
use App\Models\Transmission;
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
        $this->record = $this->record->fresh([
            'lignes.nomenclature',
            'fournisseur',
            'budget',
            'engagement',
        ]);
    }

    // =========================================================
    // HELPERS TRANSMISSION
    // =========================================================
    protected function estEnTransmission(): bool
    {
        return Transmission::where('document_type', get_class($this->record))
            ->where('document_id', $this->record->id)
            ->where('statut', 'en_attente')
            ->exists();
    }

    protected function estDestinataire(): bool
    {
        return Transmission::where('document_type', get_class($this->record))
            ->where('document_id', $this->record->id)
            ->where('destinataire_id', auth()->id())
            ->where('statut', 'en_attente')
            ->exists();
    }

    protected function transmissionEnCours(): ?Transmission
    {
        return Transmission::where('document_type', get_class($this->record))
            ->where('document_id', $this->record->id)
            ->where('statut', 'en_attente')
            ->with('destinataire', 'expediteur')
            ->latest('date_transmission')
            ->first();
    }

    protected function getVerifications(): array
    {
        if ($this->verificationsCache === null) {
            $this->record = $this->record->fresh(['lignes.nomenclature', 'fournisseur', 'budget']);
            $this->verificationsCache = $this->record->verifierDisponibiliteBudgetaire();
        }
        return $this->verificationsCache;
    }

    // =========================================================
    // ACTIONS
    // =========================================================
    protected function getHeaderActions(): array
    {
        return [

            // ── Badge transmission ────────────────────────────
            Actions\Action::make('badge_transmission')
                ->label(function () {
                    $t = $this->transmissionEnCours();
                    if (!$t) return '';
                    $action = match ($t->action_attendue) {
                        'validation'   => 'pour validation',
                        'engagement'   => 'pour engagement',
                        'verification' => 'pour vérification',
                        'signature'    => 'pour signature',
                        'information'  => 'pour information',
                        default        => '',
                    };
                    return "🔒 Transmis à {$t->destinataire?->name} {$action}";
                })
                ->color('warning')
                ->disabled()
                ->visible(fn() => $this->estEnTransmission()),

            // ── Actualiser ────────────────────────────────────
            Actions\Action::make('actualiser')
                ->label('Actualiser')
                ->icon('heroicon-o-arrow-path')->color('gray')
                ->action(function () {
                    $this->record->refresh();
                    $this->record->load(['lignes', 'fournisseur', 'budget', 'engagement']);
                    $this->verificationsCache = null;
                    Notification::make()->title('✅ Données actualisées')->success()->send();
                }),

            // ── Modifier ──────────────────────────────────────
            Actions\EditAction::make()
                ->visible(
                    fn() =>
                    !$this->estEnTransmission()
                        && $this->record->estModifiable()
                        && static::getResource()::canEdit($this->record)
                ),

            // ── Supprimer ─────────────────────────────────────
            Actions\DeleteAction::make()
                ->visible(
                    fn() =>
                    !$this->estEnTransmission()
                        && $this->record->statut === 'brouillon'
                        && static::getResource()::canDelete($this->record)
                )
                ->requiresConfirmation(),

            // ✅ Changer mode arrondi — uniquement en brouillon
            Actions\Action::make('changer_mode_arrondi')
                ->label(
                    fn() => ($this->record->mode_arrondi ?? true)
                        ? '🔢 Passer en mode décimal'
                        : '🏦 Passer en mode arrondi'
                )
                ->icon('heroicon-o-calculator')
                ->color('gray')
                ->outlined()
                ->visible(
                    fn() =>
                    $this->record->statut === 'brouillon'
                        && auth()->user()?->can('update_bon_commande')
                )
                ->modalHeading(
                    fn() => ($this->record->mode_arrondi ?? true)
                        ? 'Passer en mode décimal'
                        : 'Passer en mode arrondi FCFA'
                )
                ->modalDescription(fn() => new \Illuminate\Support\HtmlString(
                    ($this->record->mode_arrondi ?? true)
                        ? '<div class="p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20 text-blue-800 dark:text-blue-200 border border-blue-300 text-sm">'
                        . '🔢 <strong>Mode décimal</strong><br>'
                        . 'TVA, IR, TTC et NAP conserveront leurs décimales.<br>'
                        . '<em>Ex : TVA = 792 849,75 FCFA au lieu de 792 850 FCFA</em>'
                        . '</div>'
                        : '<div class="p-3 rounded-lg bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-200 border border-green-300 text-sm">'
                        . '🏦 <strong>Mode arrondi FCFA</strong><br>'
                        . 'TVA, IR, TTC et NAP seront arrondis à l\'entier FCFA.<br>'
                        . '<em>Ex : TVA = 792 850 FCFA au lieu de 792 849,75 FCFA</em>'
                        . '</div>'
                ))
                ->modalSubmitActionLabel('Confirmer le changement')
                ->action(function () {
                    try {
                        $this->record->mode_arrondi = !($this->record->mode_arrondi ?? true);
                        $this->record->load('lignes');
                        $this->record->calculerMontants();
                        $this->record->saveQuietly();

                        Notification::make()
                            ->success()
                            ->title('Mode de calcul mis à jour')
                            ->body(($this->record->mode_arrondi)
                                    ? '✅ Mode arrondi FCFA — montants recalculés'
                                    : '✅ Mode décimal — montants conservés avec décimales'
                            )
                            ->send();

                        $this->redirect(
                            BonCommandeResource::getUrl('view', ['record' => $this->record->id])
                        );
                    } catch (\Exception $e) {
                        Notification::make()
                            ->danger()->title('❌ Erreur')
                            ->body($e->getMessage())->send();
                    }
                }),

            // ── Valider ───────────────────────────────────────
            Actions\Action::make('valider')
                ->label('Valider')
                ->icon('heroicon-o-check-circle')->color('warning')
                ->visible(
                    fn() =>
                    !$this->estEnTransmission()
                        && $this->record->statut === 'brouillon'
                        && static::getResource()::canValider($this->record)
                )
                ->requiresConfirmation()
                ->modalHeading('Valider le bon de commande')
                ->modalDescription(fn() => "Valider le BC n° {$this->record->numero} ?")
                ->action(function () {
                    $this->record->valider(auth()->user());
                    $this->record->refresh();
                    Notification::make()
                        ->title('✅ BC validé')->success()
                        ->body("Le BC {$this->record->numero} a été validé.")
                        ->send();
                    $this->refreshFormData(['statut']);
                }),

            // ── Engager ───────────────────────────────────────
            Actions\Action::make('engager')
                ->label(function () {
                    $this->record = $this->record->fresh(['lignes.nomenclature', 'fournisseur', 'budget']);
                    $v = $this->record->verifierDisponibiliteBudgetaire();
                    return $v['peut_engager'] ? 'Engager le Budget' : '⚠️ Crédit insuffisant';
                })
                ->icon('heroicon-o-currency-dollar')
                ->color(function () {
                    $this->record = $this->record->fresh(['lignes.nomenclature', 'fournisseur', 'budget']);
                    $v = $this->record->verifierDisponibiliteBudgetaire();
                    return $v['peut_engager'] ? 'success' : 'danger';
                })
                ->visible(
                    fn() =>
                    !$this->estEnTransmission()
                        && $this->record->statut === 'valide'
                        && !$this->record->engagement_id
                        && static::getResource()::canEngager($this->record)
                )
                ->tooltip(function () {
                    $this->record = $this->record->fresh(['lignes.nomenclature', 'fournisseur', 'budget']);
                    $v = $this->record->verifierDisponibiliteBudgetaire();
                    if (!$v['peut_engager']) {
                        $details = collect($v['lignes_budgetaires'])
                            ->filter(fn($l) => !$l['suffisant'])
                            ->map(
                                fn($l) =>
                                "{$l['nomenclature']->code} : manque "
                                    . number_format($l['manque'], 0, ',', ' ') . " FCFA"
                            )->implode("\n");
                        return "Crédit insuffisant :\n" . $details;
                    }
                    return "Cliquez pour engager le budget";
                })
                ->modalHeading(fn() => "Engagement budgétaire — BC N° {$this->record->numero}")
                ->modalDescription('Vérification de la disponibilité budgétaire')
                ->modalWidth('5xl')
                ->modalContent(function () {
                    $this->record = $this->record->fresh(['lignes.nomenclature', 'fournisseur', 'budget']);
                    $this->verificationsCache = null;
                    $verifications = $this->record->verifierDisponibiliteBudgetaire();
                    return view('filament.modals.engagement-budget-verification', [
                        'bonCommande'   => $this->record,
                        'verifications' => $verifications,
                    ]);
                })
                ->modalSubmitActionLabel(function () {
                    $this->record = $this->record->fresh(['lignes.nomenclature']);
                    $this->verificationsCache = null;
                    $v = $this->record->verifierDisponibiliteBudgetaire();
                    return $v['peut_engager']
                        ? "✅ Confirmer l'engagement"
                        : "❌ Crédit insuffisant";
                })
                ->modalCancelActionLabel('Annuler')
                ->disabled(function () {
                    $this->record = $this->record->fresh(['lignes.nomenclature']);
                    $this->verificationsCache = null;
                    return !$this->record->verifierDisponibiliteBudgetaire()['peut_engager'];
                })
                ->action(function () {
                    try {
                        $this->verificationsCache = null;
                        $this->record = $this->record->fresh(['lignes.nomenclature', 'fournisseur', 'budget']);
                        $verifications = $this->record->verifierDisponibiliteBudgetaire();

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

                        $engagement = $this->record->engagerBudget($verifications);
                        $this->record->refresh();
                        $this->verificationsCache = null;

                        Notification::make()
                            ->title('✅ Budget engagé')->success()
                            ->body("BC {$this->record->numero} — Engagement : {$engagement->numero}")
                            ->duration(5000)->send();
                        $this->refreshFormData(['statut', 'engage']);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('❌ Erreur engagement')
                            ->danger()->body($e->getMessage())->persistent()->send();
                    }
                }),

            // ── Désengager ────────────────────────────────────
            Actions\Action::make('desengager')
                ->label("Annuler l'engagement")
                ->icon('heroicon-o-arrow-uturn-left')->color('warning')
                ->visible(
                    fn() =>
                    !$this->estEnTransmission()
                        && $this->record->engage
                        && $this->record->peutEtreDesengage()
                        && static::getResource()::canDesengager($this->record)
                )
                ->requiresConfirmation()
                ->modalHeading("Annuler l'engagement")
                ->modalDescription(function () {
                    $engagement = \App\Models\Engagement::where('engageable_id', $this->record->id)
                        ->where(function ($q) {
                            $q->where('engageable_type', \App\Models\BonCommande::class)
                                ->orWhere('engageable_type', 'bon_commande');
                        })->first();
                    $num = $engagement?->numero ?? '—';
                    return new \Illuminate\Support\HtmlString(
                        "<div class='text-red-600 dark:text-red-400 font-semibold'>"
                            . "L'engagement N° <strong>{$num}</strong> sera supprimé définitivement.<br>"
                            . "Les crédits seront libérés sur la ligne budgétaire."
                            . "</div>"
                    );
                })
                ->modalSubmitActionLabel('🔓 Confirmer le désengagement')
                ->action(function () {
                    try {
                        $this->record->desengagerBudget();
                        $this->record->refresh();
                        $this->verificationsCache = null;
                        Notification::make()
                            ->title('✅ Engagement annulé — crédits libérés')
                            ->success()->send();
                        $this->refreshFormData(['statut', 'engage']);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('❌ Erreur')->danger()
                            ->body($e->getMessage())->persistent()->send();
                    }
                }),

            // ── Annuler ───────────────────────────────────────
            Actions\Action::make('annuler')
                ->label('Annuler')
                ->icon('heroicon-o-x-circle')->color('danger')
                ->visible(
                    fn() =>
                    !$this->estEnTransmission()
                        && $this->record->statut !== 'brouillon'
                        && $this->record->peutEtreAnnule()
                        && static::getResource()::canAnnuler($this->record)
                )
                ->form([
                    Forms\Components\Placeholder::make('info_annulation')
                        ->label('')
                        ->content(
                            fn() => $this->record->engage
                                ? new \Illuminate\Support\HtmlString(
                                    '<div class="rounded-lg p-3 text-sm font-semibold '
                                        . 'bg-red-50 dark:bg-red-900/30 '
                                        . 'text-red-700 dark:text-red-300 '
                                        . 'border border-red-300 dark:border-red-700">'
                                        . "❌ Ce BC est engagé. Annulez d'abord l'engagement."
                                        . '</div>'
                                )
                                : new \Illuminate\Support\HtmlString(
                                    '<div class="rounded-lg p-3 text-sm '
                                        . 'bg-yellow-50 dark:bg-yellow-900/30 '
                                        . 'text-yellow-800 dark:text-yellow-200 '
                                        . 'border border-yellow-300 dark:border-yellow-700">'
                                        . '⚠️ Le BC sera annulé. Il restera récupérable.'
                                        . '</div>'
                                )
                        )
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('motif')
                        ->label("Motif d'annulation")
                        ->rows(3)->required()
                        ->hidden(fn() => $this->record->engage),
                ])
                ->requiresConfirmation()
                ->modalHeading('Annuler le bon de commande')
                ->action(function (array $data) {
                    try {
                        $this->record->annuler($data['motif'] ?? null);
                        $this->record->refresh();
                        Notification::make()->title('⚠️ BC annulé')->warning()->send();
                        $this->refreshFormData(['statut']);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('❌ Erreur')->danger()
                            ->body($e->getMessage())->persistent()->send();
                    }
                }),

            // ── Récupérer ─────────────────────────────────────
            Actions\Action::make('recuperer')
                ->label('Récupérer')
                ->icon('heroicon-o-arrow-path')->color('success')
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
                            '<div class="rounded-lg p-3 text-sm '
                                . 'bg-green-50 dark:bg-green-900/30 '
                                . 'text-green-800 dark:text-green-200 '
                                . 'border border-green-300 dark:border-green-700">'
                                . '✅ Le BC sera remis en <strong>Brouillon</strong> — modifiable et réengageable.'
                                . '</div>'
                        ))
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('motif')
                        ->label('Motif de récupération')
                        ->required()->rows(3)
                        ->placeholder('Ex: Changement de fournisseur, correction des montants...'),
                ])
                ->requiresConfirmation()
                ->modalHeading('Récupérer le bon de commande annulé')
                ->modalSubmitActionLabel('🔄 Confirmer la récupération')
                ->action(function (array $data) {
                    try {
                        $this->record->recuperer($data['motif']);
                        $this->record->refresh();
                        Notification::make()
                            ->title('✅ BC récupéré — remis en Brouillon')
                            ->success()
                            ->body("Vous pouvez maintenant modifier le BC {$this->record->numero}.")
                            ->duration(5000)->send();
                        return redirect(
                            static::getResource()::getUrl('edit', ['record' => $this->record->id])
                        );
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('❌ Impossible de récupérer le BC')
                            ->danger()->body($e->getMessage())->persistent()->send();
                    }
                }),

            // ── Transmettre ───────────────────────────────────
            Actions\Action::make('transmettre')
                ->label('Transmettre')
                ->icon('heroicon-o-paper-airplane')->color('info')
                ->visible(
                    fn() =>
                    !$this->estEnTransmission()
                        && in_array($this->record->statut, ['brouillon', 'valide'])
                )
                ->form([
                    Forms\Components\Select::make('destinataire_id')
                        ->label('Transmettre à')
                        ->options(fn() => \App\Models\User::where('id', '!=', auth()->id())
                            ->orderBy('name')->pluck('name', 'id'))
                        ->required()->searchable()->preload(),

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
                        ->options([
                            'basse'   => 'Basse',
                            'normale' => 'Normale',
                            'haute'   => 'Haute',
                            'urgente' => 'Urgente',
                        ])
                        ->default('normale')->required(),

                    Forms\Components\DatePicker::make('date_limite')
                        ->label('Date limite')->minDate(now()),
                ])
                ->action(function (array $data) {
                    $destinataire = \App\Models\User::findOrFail($data['destinataire_id']);

                    \DB::beginTransaction();
                    try {
                        Transmission::where('document_type', get_class($this->record))
                            ->where('document_id', $this->record->id)
                            ->where('statut', 'en_attente')
                            ->get()
                            ->each(fn($t) => $t->annuler('Remplacée'));

                        $this->record->transmettreA(
                            $destinataire,
                            $data['action_attendue'],
                            $data['commentaire'] ?? null,
                            [
                                'priorite'    => $data['priorite'],
                                'date_limite' => $data['date_limite'] ?? null,
                            ]
                        );

                        \DB::commit();

                        Notification::make()
                            ->title('📥 Bon de commande à traiter')
                            ->info()
                            ->body(
                                "Le BC {$this->record->numero} vous a été transmis par "
                                    . auth()->user()->name
                                    . " pour : " . $data['action_attendue']
                            )
                            ->sendToDatabase($destinataire);

                        Notification::make()
                            ->title('📤 Transmis à ' . $destinataire->name)
                            ->success()->send();

                        $this->redirect(
                            BonCommandeResource::getUrl('view', ['record' => $this->record->id])
                        );
                    } catch (\Exception $e) {
                        \DB::rollBack();
                        Notification::make()
                            ->title('❌ ' . $e->getMessage())
                            ->danger()->persistent()->send();
                    }
                }),

            // ── Retourner (destinataire uniquement) ───────────
            Actions\Action::make('retourner')
                ->label('↩ Retourner')
                ->icon('heroicon-o-arrow-uturn-left')->color('warning')
                ->visible(fn() => $this->estDestinataire())
                ->form([
                    Forms\Components\Textarea::make('motif')
                        ->label('Motif du retour')->required()->rows(3),
                ])
                ->requiresConfirmation()
                ->modalHeading('Retourner pour correction')
                ->modalDescription("Le BC sera remis en brouillon chez l'émetteur.")
                ->action(function (array $data) {
                    try {
                        $this->record->retournerPourCorrection($data['motif']);
                        Notification::make()
                            ->title('↩ BC retourné pour correction')
                            ->warning()->send();
                        $this->redirect(
                            BonCommandeResource::getUrl('view', ['record' => $this->record->id])
                        );
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('❌ ' . $e->getMessage())
                            ->danger()->send();
                    }
                }),

            // ── Clôturer transmission ──────────────────────────
            Actions\Action::make('cloturer_transmission')
                ->label('✅ Clôturer')
                ->icon('heroicon-o-check-circle')->color('success')
                ->visible(fn() => $this->estDestinataire())
                ->form([
                    Forms\Components\Textarea::make('reponse')
                        ->label('Réponse / Commentaire')->rows(3),
                ])
                ->requiresConfirmation()
                ->modalHeading('Clôturer la transmission')
                ->action(function (array $data) {
                    try {
                        $this->record->cloturerTransmission($data['reponse'] ?? null);
                        Notification::make()
                            ->title('✅ Transmission clôturée')
                            ->success()->send();
                        $this->redirect(
                            BonCommandeResource::getUrl('view', ['record' => $this->record->id])
                        );
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('❌ ' . $e->getMessage())
                            ->danger()->send();
                    }
                }),

            // ── Historique transmissions ──────────────────────
            Actions\Action::make('historique_transmissions')
                ->label('Historique')
                ->icon('heroicon-o-clock')->color('gray')
                ->visible(fn() => $this->record->aEteTransmis())
                ->modalHeading('Historique des transmissions — ' . $this->record->numero)
                ->modalContent(fn() => view('filament.modals.historique-transmissions', [
                    'transmissions' => $this->record->historiqueTransmissions(),
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fermer'),
        ];
    }

    // =========================================================
    // INFOLIST
    // =========================================================
    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([

            // ── État / transmission ───────────────────────────
            Infolists\Components\Section::make('État du bon de commande')
                ->schema([
                    Infolists\Components\TextEntry::make('statut_transmission')
                        ->label('')
                        ->state(function () {
                            $t = $this->transmissionEnCours();
                            if (!$t) return null;
                            return "🔒 Transmis à {$t->destinataire?->name} — {$t->getActionLabel()}";
                        })
                        ->color('warning')->badge()
                        ->visible(fn() => $this->estEnTransmission())
                        ->columnSpanFull(),

                    Infolists\Components\TextEntry::make('statut_modification')
                        ->label('')
                        ->state(
                            fn($record) => $record->engage
                                ? "🔒 BC engagé — utilisez \"Annuler l'engagement\" pour le rendre modifiable."
                                : null
                        )
                        ->color('warning')->badge()
                        ->visible(fn($record) => $record->engage && !$this->estEnTransmission())
                        ->columnSpanFull(),
                ])
                ->visible(fn($record) => $record->engage || $this->estEnTransmission()),

            // ── Vérification budgétaire ───────────────────────
            Infolists\Components\Section::make('Vérification budgétaire')
                ->schema([
                    Infolists\Components\TextEntry::make('credit_disponible')
                        ->label('Statut du crédit')
                        ->state(function ($record) {
                            if ($record->engagement_id) return '✅ Budget déjà engagé';
                            if ($record->statut !== 'valide') return 'BC non validé';
                            $v = $record->verifierDisponibiliteBudgetaire();
                            if ($v['peut_engager']) return '✅ Crédit suffisant';
                            $details = collect($v['lignes_budgetaires'])
                                ->filter(fn($l) => !$l['suffisant'])
                                ->map(
                                    fn($l) =>
                                    "{$l['nomenclature']->code} : manque "
                                        . number_format($l['manque'], 0, ',', ' ') . " FCFA"
                                )->implode(' | ');
                            return '⚠️ Crédit insuffisant : ' . $details;
                        })
                        ->badge()
                        ->color(function ($record) {
                            if ($record->engagement_id) return 'success';
                            if ($record->statut !== 'valide') return 'gray';
                            return $record->verifierDisponibiliteBudgetaire()['peut_engager']
                                ? 'success' : 'danger';
                        })
                        ->columnSpanFull(),
                ])
                ->visible(fn($record) => $record->statut === 'valide'),

            // ── Informations générales ────────────────────────
            Infolists\Components\Section::make('Informations générales')
                ->schema([
                    Infolists\Components\TextEntry::make('numero')
                        ->label('Numéro BC')->copyable()
                        ->size(Infolists\Components\TextEntry\TextEntrySize::Large)->weight('bold'),
                    Infolists\Components\TextEntry::make('budget.libelle')->label('Budget'),
                    Infolists\Components\TextEntry::make('statut')
                        ->label('Statut')->badge()
                        ->color(fn(string $state) => match ($state) {
                            'brouillon'           => 'gray',
                            'valide'              => 'warning',
                            'engage'              => 'primary',
                            'en_cours'            => 'info',
                            'livre_partiellement' => 'success',
                            'livre'               => 'success',
                            'annule'              => 'danger',
                            default               => 'gray',
                        })
                        ->formatStateUsing(fn(string $state) => match ($state) {
                            'brouillon'           => 'Brouillon',
                            'valide'              => 'Validé',
                            'engage'              => 'Engagé',
                            'en_cours'            => 'En cours',
                            'livre_partiellement' => 'Livré partiellement',
                            'livre'               => 'Livré',
                            'annule'              => 'Annulé',
                            default               => $state,
                        }),
                    Infolists\Components\TextEntry::make('date_emission')
                        ->label("Date d'émission")->date('d/m/Y'),
                    Infolists\Components\TextEntry::make('date_livraison_prevue')
                        ->label('Livraison prévue')->date('d/m/Y')->placeholder('Non renseignée'),
                    Infolists\Components\TextEntry::make('date_livraison_effective')
                        ->label('Livraison effective')->date('d/m/Y')->placeholder('Non livrée')
                        ->visible(fn($record) => $record->date_livraison_effective),
                ])
                ->columns(3),

            // ── Fournisseur et Service ────────────────────────
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

            // ── Détails Financiers ────────────────────────────
            // ✅ Section unifiée — mode_arrondi intégré directement
            //    Suppression des doublons qui existaient (montant_ht etc. × 2)
            Infolists\Components\Section::make('Détails Financiers')
                ->schema([

                    // ── Mode de calcul ────────────────────────
                    Infolists\Components\TextEntry::make('mode_arrondi')
                        ->label('Mode de calcul')
                        ->badge()
                        ->formatStateUsing(
                            fn($state) => ($state ?? true)
                                ? '🏦 Arrondi entier FCFA'
                                : '🔢 Valeurs décimales exactes'
                        )
                        ->color(fn($state) => ($state ?? true) ? 'success' : 'info')
                        ->helperText(
                            fn($record) => ($record->mode_arrondi ?? true)
                                ? 'TVA = HT × ' . ($record->lignes->first()?->taux_tva ?? 19.25) . '% → arrondi à l\'entier FCFA'
                                : 'TVA = HT × ' . ($record->lignes->first()?->taux_tva ?? 19.25) . '% → décimales conservées'
                        )
                        ->columnSpanFull(),

                    // ── Montants (formatage adapté au mode) ───
                    Infolists\Components\TextEntry::make('montant_ht')
                        ->label('Montant HT')
                        ->formatStateUsing(
                            fn($state, $record) =>
                            number_format($state, ($record->mode_arrondi ?? true) ? 0 : 2, ',', ' ') . ' FCFA'
                        )
                        ->color('info')
                        ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                    Infolists\Components\TextEntry::make('montant_tva')
                        ->label(
                            fn($record) =>
                            'TVA (' . ($record->lignes->first()?->taux_tva ?? 19.25) . '%)'
                        )
                        ->formatStateUsing(
                            fn($state, $record) =>
                            $state <= 0
                                ? 'EXONÉRÉE'
                                : number_format($state, ($record->mode_arrondi ?? true) ? 0 : 2, ',', ' ') . ' FCFA'
                        )
                        ->color('warning')
                        ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                    Infolists\Components\TextEntry::make('montant_ttc')
                        ->label('Montant TTC')
                        ->formatStateUsing(
                            fn($state, $record) =>
                            number_format($state, ($record->mode_arrondi ?? true) ? 0 : 2, ',', ' ') . ' FCFA'
                        )
                        ->color('success')
                        ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                        ->weight('bold'),

                    Infolists\Components\TextEntry::make('montant_ir')
                        ->label(
                            fn($record) =>
                            'IR (' . ($record->lignes->first()?->taux_ir ?? 5.5) . '%)'
                        )
                        ->formatStateUsing(
                            fn($state, $record) =>
                            number_format($state, ($record->mode_arrondi ?? true) ? 0 : 2, ',', ' ') . ' FCFA'
                        )
                        ->color('danger')
                        ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                    Infolists\Components\TextEntry::make('montant_tsr')
                        ->label('Montant TSR')
                        ->formatStateUsing(
                            fn($state, $record) =>
                            number_format($state, ($record->mode_arrondi ?? true) ? 0 : 2, ',', ' ') . ' FCFA'
                        )
                        ->color('danger')
                        ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                    Infolists\Components\TextEntry::make('net_a_percevoir')
                        ->label('Net à Percevoir (NAP)')
                        ->formatStateUsing(
                            fn($state, $record) =>
                            number_format(
                                $record->net_a_percevoir ?? 0,
                                ($record->mode_arrondi ?? true) ? 0 : 2,
                                ',',
                                ' '
                            ) . ' FCFA'
                        )
                        ->color('primary')
                        ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                        ->weight('bold')
                        ->helperText('HT − IR'),

                    Infolists\Components\TextEntry::make('lignes_count')
                        ->label('Nombre de lignes')
                        ->state(fn($record) => $record->lignes->count())
                        ->badge()->color('gray'),

                ])
                ->columns(3),

            // ── Engagement Budgétaire ─────────────────────────
            Infolists\Components\Section::make('Engagement Budgétaire')
                ->schema([
                    Infolists\Components\TextEntry::make('engage')
                        ->label('Budget engagé')->badge()
                        ->formatStateUsing(fn($state) => $state ? 'Oui' : 'Non')
                        ->color(fn($state) => $state ? 'success' : 'gray'),
                    Infolists\Components\TextEntry::make('montant_engage')
                        ->label('Montant engagé')
                        ->formatStateUsing(
                            fn($state, $record) =>
                            number_format($state, ($record->mode_arrondi ?? true) ? 0 : 2, ',', ' ') . ' FCFA'
                        )
                        ->visible(fn($record) => $record->engage),
                    Infolists\Components\TextEntry::make('date_engagement')
                        ->label("Date d'engagement")->dateTime('d/m/Y H:i')
                        ->visible(fn($record) => $record->engage),
                ])
                ->columns(3)
                ->visible(fn($record) => $record->engage),

            // ── Objet ─────────────────────────────────────────
            Infolists\Components\Section::make('Objet')
                ->schema([
                    Infolists\Components\TextEntry::make('objet')->label('')->columnSpanFull(),
                ]),

            // ── Validation ────────────────────────────────────
            Infolists\Components\Section::make('Validation')
                ->schema([
                    Infolists\Components\TextEntry::make('validateur.name')
                        ->label('Validé par')->placeholder('Non validé'),
                    Infolists\Components\TextEntry::make('date_validation')
                        ->label('Date de validation')->dateTime('d/m/Y H:i')->placeholder('Non validé'),
                ])
                ->columns(2)
                ->visible(fn($record) => $record->valide_par),

            // ── Observations ──────────────────────────────────
            Infolists\Components\Section::make('Observations')
                ->schema([
                    Infolists\Components\TextEntry::make('observations')
                        ->label('')->placeholder('Aucune observation')->columnSpanFull(),
                ])
                ->collapsible()->collapsed(),
        ]);
    }
}
