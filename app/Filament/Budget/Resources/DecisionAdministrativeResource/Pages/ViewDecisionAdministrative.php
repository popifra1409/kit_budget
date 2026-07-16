<?php

namespace App\Filament\Budget\Resources\DecisionAdministrativeResource\Pages;

use App\Filament\Budget\Resources\DecisionAdministrativeResource;
use App\Models\Transmission;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Forms;

class ViewDecisionAdministrative extends ViewRecord
{
    protected static string $resource = DecisionAdministrativeResource::class;

    // ✅ Cache — évite requêtes DB multiples par chargement de page
    private ?\App\Models\MemoireDepense $_memoireLieCache = null;
    private bool $_memoireLieCached = false;

    // =========================================================
    // HELPERS — morphMap compatible
    // =========================================================

    private function morphAlias(): string
    {
        $map = \Illuminate\Database\Eloquent\Relations\Relation::morphMap();
        return array_search(get_class($this->record), $map)
            ?: get_class($this->record);
    }

    protected function estEnTransmission(): bool
    {
        // ✅ Via le trait si disponible
        if (method_exists($this->record, 'estEnCoursDeTransmission')) {
            return $this->record->estEnCoursDeTransmission();
        }

        // ✅ Fallback avec morphMap compatible
        return Transmission::where('document_id', $this->record->id)
            ->where('statut', 'en_attente')
            ->where(function ($q) {
                $q->where('document_type', get_class($this->record))
                    ->orWhere('document_type', $this->morphAlias());
            })
            ->exists();
    }

    protected function estDestinataire(): bool
    {
        if (method_exists($this->record, 'estDestinataireActuel')) {
            return $this->record->estDestinataireActuel();
        }

        return Transmission::where('document_id', $this->record->id)
            ->where('destinataire_id', auth()->id())
            ->where('statut', 'en_attente')
            ->where(function ($q) {
                $q->where('document_type', get_class($this->record))
                    ->orWhere('document_type', $this->morphAlias());
            })
            ->exists();
    }

    protected function transmissionEnCours(): ?Transmission
    {
        if (method_exists($this->record, 'transmissionEnCours')) {
            return $this->record->transmissionEnCours();
        }

        return Transmission::where('document_id', $this->record->id)
            ->where('statut', 'en_attente')
            ->where(function ($q) {
                $q->where('document_type', get_class($this->record))
                    ->orWhere('document_type', $this->morphAlias());
            })
            ->with('destinataire', 'expediteur')
            ->latest('date_transmission')
            ->first();
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


            // ── 🔒 Bouton si DA liée à un MD (bloquant) ─────────
            Actions\Action::make('modifier_bloque')
                ->label('🔒 Modification impossible')
                ->icon('heroicon-o-lock-closed')
                ->color('warning')
                ->outlined()
                ->visible(function () {
                    if ($this->record->statut !== 'brouillon') return false;
                    return \App\Models\MemoireDepense::withoutGlobalScopes()
                        ->where('decision_administrative_id', $this->record->id)
                        ->exists();
                })
                ->modalHeading('Modification impossible')
                ->modalDescription(function () {
                    $md = \App\Models\MemoireDepense::withoutGlobalScopes()
                        ->where('decision_administrative_id', $this->record->id)
                        ->value('numero');
                    return new \Illuminate\Support\HtmlString(
                        '<div class="p-3 bg-amber-50 border border-amber-300 rounded text-sm text-amber-800">'
                            . '🔒 Cette DA est liée au Mémoire <strong>' . $md . '</strong>.<br><br>'
                            . 'Pour la modifier :<br>'
                            . '&nbsp;1. Annulez ou supprimez cette DA<br>'
                            . '&nbsp;2. Le mémoire sera remis en Brouillon<br>'
                            . '&nbsp;3. Modifiez le mémoire puis retransformez-le'
                            . '</div>'
                    );
                })
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fermer'),

            // ── Modifier (caché si DA liée à un MD) ──────────
            Actions\EditAction::make()
                ->visible(function () {
                    if ($this->estEnTransmission()) return false;
                    if ($this->record->statut !== 'brouillon') return false;
                    if (!DecisionAdministrativeResource::canEdit($this->record)) return false;
                    return !\App\Models\MemoireDepense::withoutGlobalScopes()
                        ->where('decision_administrative_id', $this->record->id)
                        ->exists();
                }),

            // ── Valider ──────────────────────────────────────
            // ✅ Valider → page Aperçu DA avant confirmation
            Actions\Action::make('valider')
                ->label('Valider')
                ->icon('heroicon-o-check-circle')->color('warning')
                ->visible(fn() => $this->peutValider())
                ->url(fn() => \App\Filament\Budget\Resources\DecisionAdministrativeResource::getUrl(
                    'apercu',
                    ['record' => $this->record]
                ))
                ->openUrlInNewTab(false),

            // ── Engager ──────────────────────────────────────
            Actions\Action::make('engager')
                ->label('Engager le Budget')
                ->icon('heroicon-o-banknotes')->color('primary')
                ->visible(
                    fn() =>
                    $this->record->statut === 'validee'
                        && !$this->record->engagee
                        && !$this->estEnTransmission()         // ✅
                        && DecisionAdministrativeResource::canEngager($this->record)
                )
                ->requiresConfirmation()
                ->form([
                    Forms\Components\Select::make('nomenclature_id')
                        ->label('Nomenclature budgétaire')
                        ->options(function () {
                            return \App\Models\LigneBudgetaire::where('budget_id', $this->record->budget_id)
                                ->with('nomenclature')->get()
                                ->filter(fn($lb) => $lb->nomenclature !== null)
                                ->mapWithKeys(fn($lb) => [
                                    $lb->nomenclature_id =>
                                    "{$lb->nomenclature->code} - {$lb->nomenclature->libelle} " .
                                        "(Dispo: " .
                                        number_format($lb->disponible_engagement, 0, ',', ' ') .
                                        " FCFA)"
                                ]);
                        })
                        ->required()->searchable()->preload(),
                ])
                ->action(function (array $data) {
                    try {
                        $this->record->engagerBudget($data['nomenclature_id']);
                        $this->record->refresh()->load('engagement');
                        Notification::make()
                            ->title('✅ Budget engagé')
                            ->success()
                            ->body("Engagement : " . ($this->record->engagement?->numero ?? 'N/A'))
                            ->send();
                        $this->refreshFormData(['statut', 'engagee']);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('❌ ' . $e->getMessage())->danger()->send();
                    }
                }),

            // ── Voir engagement ───────────────────────────────
            Actions\Action::make('voir_engagement')
                ->label("Voir l'Engagement")
                ->icon('heroicon-o-eye')->color('info')
                ->visible(fn() => $this->record->engagement_id && $this->record->engagement)
                ->url(
                    fn() =>
                    route('filament.budget.resources.engagements.view', $this->record->engagement)
                ),

            // ── Créer OP ─────────────────────────────────────
            Actions\Action::make('creer_op')
                ->label('Créer OP')
                ->icon('heroicon-o-document-currency-dollar')->color('success')
                ->visible(
                    fn() =>
                    $this->record->engagement
                        && $this->record->engagement->statut === 'definitif'
                        && !$this->record->engagement->hasOrdonnancesPaiement()
                        && !$this->estEnTransmission()         // ✅
                )
                ->requiresConfirmation()
                ->action(function () {
                    try {
                        $this->record->engagement->creerOrdonnancesPaiement();
                        Notification::make()->title('✅ Ordonnances créées')->success()->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('❌ ' . $e->getMessage())->danger()->persistent()->send();
                    }
                }),

            // ── PDF ──────────────────────────────────────────
            // ✅ Aperçu DA (remplace PDF inexistant)
            Actions\Action::make('apercu')
                ->label('Aperçu')
                ->icon('heroicon-o-eye')->color('gray')
                ->modalHeading(fn() => 'Aperçu — ' . $this->record->numero)
                ->modalContent(fn() => view('filament.modals.apercu-decision-administrative', [
                    'da' => $this->record->load([
                        'personnel',
                        'fournisseur',
                        'budget',
                        'typeDecision',
                        'exercice',
                        'engagement.nomenclaturePrincipale',
                    ]),
                ]))
                ->modalSubmitActionLabel('Fermer')
                ->modalCancelAction(false)
                ->modalWidth('3xl'),

            // ── Transmettre ───────────────────────────────────
            Actions\Action::make('transmettre')
                ->label('Transmettre')
                ->icon('heroicon-o-paper-airplane')->color('info')
                ->visible(
                    fn() =>
                    !$this->estEnTransmission()            // ✅
                        && in_array($this->record->statut, ['brouillon', 'validee', 'valide'])
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
                    try {
                        Transmission::where('document_id', $this->record->id)
                            ->where('statut', 'en_attente')
                            ->where(function ($q) {
                                $q->where('document_type', get_class($this->record))
                                    ->orWhere('document_type', $this->morphAlias());
                            })
                            ->get()->each(fn($t) => $t->annuler('Remplacée'));

                        $this->record->transmettreA(
                            $destinataire,
                            $data['action_attendue'],
                            $data['commentaire'] ?? null,
                            ['priorite' => $data['priorite'], 'date_limite' => $data['date_limite'] ?? null]
                        );

                        Notification::make()
                            ->title('📤 Transmis à ' . $destinataire->name)
                            ->success()->send();

                        $this->redirect(
                            DecisionAdministrativeResource::getUrl('view', ['record' => $this->record->id])
                        );
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('❌ ' . $e->getMessage())->danger()->send();
                    }
                }),

            // ── Rappeler ma transmission ──────────────────────
            Actions\Action::make('rappeler_transmission')
                ->label(function () {
                    $temps = method_exists($this->record, 'tempsRestantAnnulation')
                        ? $this->record->tempsRestantAnnulation() : null;
                    return $temps ? "↩ Rappeler ({$temps})" : '↩ Rappeler';
                })
                ->icon('heroicon-o-arrow-uturn-left')->color('gray')
                ->visible(
                    fn() =>
                    $this->estEnTransmission()
                        && method_exists($this->record, 'peutAnnulerSaTransmission')
                        && $this->record->peutAnnulerSaTransmission()
                )
                ->form([
                    Forms\Components\Textarea::make('raison')
                        ->label('Raison du rappel')->rows(2),
                ])
                ->requiresConfirmation()
                ->modalHeading('Rappeler la transmission')
                ->action(function (array $data) {
                    try {
                        $this->record->annulerMaTransmission($data['raison'] ?? null);
                        Notification::make()
                            ->title('↩ Transmission rappelée')->success()->send();
                        $this->redirect(
                            DecisionAdministrativeResource::getUrl('view', ['record' => $this->record->id])
                        );
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('❌ ' . $e->getMessage())->danger()->send();
                    }
                }),

            // ── Retourner (destinataire uniquement) ───────────
            Actions\Action::make('retourner')
                ->label('↩ Retourner')
                ->icon('heroicon-o-arrow-uturn-left')->color('warning')
                ->visible(fn() => $this->estDestinataire())  // ✅
                ->form([
                    Forms\Components\Textarea::make('motif')
                        ->label('Motif du retour')->required()->rows(3),
                ])
                ->requiresConfirmation()
                ->modalHeading('Retourner pour correction')
                ->action(function (array $data) {
                    try {
                        $this->record->retournerPourCorrection($data['motif']);
                        Notification::make()
                            ->title('↩ Retourné pour correction')->warning()->send();
                        $this->redirect(
                            DecisionAdministrativeResource::getUrl('view', ['record' => $this->record->id])
                        );
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('❌ ' . $e->getMessage())->danger()->send();
                    }
                }),

            // ── Clôturer (destinataire uniquement) ────────────
            Actions\Action::make('cloturer_transmission')
                ->label('✅ Clôturer')
                ->icon('heroicon-o-check-circle')->color('success')
                ->visible(fn() => $this->estDestinataire())  // ✅
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
                            ->title('✅ Transmission clôturée')->success()->send();
                        $this->redirect(
                            DecisionAdministrativeResource::getUrl('view', ['record' => $this->record->id])
                        );
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('❌ ' . $e->getMessage())->danger()->send();
                    }
                }),

            // ── Historique ────────────────────────────────────
            Actions\Action::make('historique_transmissions')
                ->label('Historique')
                ->icon('heroicon-o-clock')->color('gray')
                ->visible(
                    fn() =>
                    method_exists($this->record, 'aEteTransmis')
                        && $this->record->aEteTransmis()
                )
                ->modalHeading('Historique — ' . $this->record->numero)
                ->modalContent(fn() => view('filament.modals.historique-transmissions', [
                    'transmissions' => $this->record->historiqueTransmissions(),
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fermer'),

            // ── Annuler ───────────────────────────────────────
            Actions\Action::make('annuler')
                ->label('Annuler')
                ->icon('heroicon-o-x-circle')->color('danger')
                ->visible(
                    fn() =>
                    !in_array($this->record->statut, ['annulee'])
                        && !$this->estEnTransmission()          // ✅
                        && DecisionAdministrativeResource::canAnnuler($this->record)
                )
                ->form([
                    Forms\Components\Placeholder::make('warning')
                        ->label('')
                        ->content(function () {
                            $memoire = $this->getMemoireLie();
                            if ($this->record->engagee) {
                                return new \Illuminate\Support\HtmlString(
                                    '<div class="rounded-lg p-3 text-sm font-semibold '
                                        . 'bg-red-50 dark:bg-red-900/30 text-red-700 '
                                        . 'border border-red-300">'
                                        . '❌ Cette décision est engagée. Annulez d\'abord l\'engagement.'
                                        . '</div>'
                                );
                            }
                            // ✅ Avertissement MD lié
                            $html = '<div class="rounded-lg p-3 text-sm '
                                . 'bg-yellow-50 dark:bg-yellow-900/30 text-yellow-800 '
                                . 'border border-yellow-300 mb-2">'
                                . '⚠️ La décision sera annulée. Elle restera récupérable.'
                                . '</div>';
                            if ($memoire) {
                                $html .= '<div class="rounded-lg p-3 text-sm '
                                    . 'bg-blue-50 dark:bg-blue-900/20 text-blue-800 '
                                    . 'border border-blue-300">'
                                    . '📋 <strong>Cette DA est liée au Mémoire ' . $memoire->numero . '</strong><br>'
                                    . 'L\'annulation va également :<br>'
                                    . '&nbsp;&nbsp;1. Annuler la transformation du mémoire<br>'
                                    . '&nbsp;&nbsp;2. Remettre le mémoire en <strong>Brouillon</strong> (modifiable)'
                                    . '</div>';
                            }
                            return new \Illuminate\Support\HtmlString($html);
                        })
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('motif')
                        ->label("Motif d'annulation")
                        ->rows(3)->required()
                        ->hidden(fn() => $this->record->engagee),
                ])
                ->action(function (array $data) {
                    try {
                        \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
                            // 1. Annuler la DA
                            $this->record->annuler($data['motif'] ?? null);

                            // ✅ 2. Si MD lié → le remettre en brouillon
                            $this->reinitialiserMemoireLie($data['motif'] ?? null);
                        });

                        $memoire = null; // déjà réinitialisé
                        $memoire = $this->getMemoireLie();
                        Notification::make()
                            ->title('✅ Décision annulée')
                            ->success()
                            ->body($memoire
                                ? "Le Mémoire {$memoire->numero} est remis en Validé. "
                                . "Allez sur le mémoire et cliquez 'Dévalider' pour le modifier."
                                : 'La décision a été annulée.')
                            ->persistent()
                            ->send();
                        $this->redirect(
                            DecisionAdministrativeResource::getUrl('view', ['record' => $this->record->id])
                        );
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('❌ ' . $e->getMessage())->danger()->persistent()->send();
                    }
                }),

            // ── Désengager ────────────────────────────────────
            // ── Désengager ────────────────────────────────────────
            Actions\Action::make('desengager')
                ->label("Annuler l'engagement")
                ->icon('heroicon-o-arrow-uturn-left')->color('warning')
                ->visible(
                    fn() =>
                    $this->record->engagee
                        && !$this->estEnTransmission()
                        // ✅ Vérification permission ajoutée
                        && auth()->user()?->can('annuler_engagement')
                )
                ->requiresConfirmation()
                ->modalHeading("Annuler l'engagement")
                ->modalDescription(function () {
                    $num = \App\Models\Engagement::where('engageable_id', $this->record->id)
                        ->where(function ($q) {
                            $q->where('engageable_type', \App\Models\DecisionAdministrative::class)
                                ->orWhere('engageable_type', 'decision_administrative');
                        })
                        ->value('numero') ?? '—';
                    return new \Illuminate\Support\HtmlString(
                        "<div class='text-red-600 font-semibold'>"
                            . "L'engagement N° <strong>{$num}</strong> sera supprimé définitivement.<br>"
                            . "La DA reviendra à l'état <strong>Validée</strong>."
                            . "</div>"
                    );
                })
                ->action(function () {
                    try {
                        $this->record->desengagerBudget();
                        Notification::make()
                            ->title('✅ Engagement annulé')->success()->send();
                        $this->refreshFormData(['statut', 'engagee']);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('❌ ' . $e->getMessage())->danger()->persistent()->send();
                    }
                }),

            // ── Récupérer ─────────────────────────────────────
            Actions\Action::make('recuperer')
                ->label('Récupérer')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('success')
                ->visible(
                    fn() =>
                    $this->record->statut === 'annulee'
                        && DecisionAdministrativeResource::canRecuperer($this->record)
                )
                // ✅ FIX 1 — Pas de ->requiresConfirmation() quand on a un ->form([...])
                //    On remplace par modalHeading + modalDescription + modalSubmitActionLabel
                ->modalHeading('Récupérer la décision')
                ->modalDescription(fn() => new \Illuminate\Support\HtmlString(
                    '<div class="rounded-lg p-3 text-sm bg-green-50 dark:bg-green-900/20 '
                        . 'text-green-800 dark:text-green-200 border border-green-300">'
                        . '✅ La décision sera remise en <strong>Brouillon</strong> '
                        . 'et pourra être modifiée et réutilisée.'
                        . '</div>'
                ))
                ->modalSubmitActionLabel('Récupérer')
                ->form([
                    Forms\Components\Textarea::make('motif')
                        ->label('Motif de récupération (optionnel)')
                        ->rows(2)
                        ->placeholder('Ex: Correction des montants, modification du bénéficiaire...'),
                ])
                ->action(function (array $data) {
                    try {
                        $this->record->recuperer($data['motif'] ?? null);

                        Notification::make()
                            ->title('✅ Décision récupérée en Brouillon')
                            ->success()
                            ->body('La décision peut maintenant être modifiée et renvoyée.')
                            ->send();

                        // ✅ FIX 2 — Redirect complet au lieu de refreshFormData()
                        //    refreshFormData() ne réévalue pas ->visible() des actions header
                        //    Un redirect force le rechargement complet de la page
                        $this->redirect(
                            DecisionAdministrativeResource::getUrl('view', ['record' => $this->record->id])
                        );
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('❌ ' . $e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),

            // ── Supprimer ─────────────────────────────────────
            Actions\DeleteAction::make()
                ->visible(
                    fn() =>
                    $this->record->statut === 'brouillon'
                        && !$this->estEnTransmission()
                        && DecisionAdministrativeResource::canDelete($this->record)
                )
                // ✅ Avertissement + reset MD si DA liée à un MD
                ->modalDescription(function () {
                    $memoire = $this->getMemoireLie();
                    if (!$memoire) {
                        return 'Supprimer définitivement la décision ' . $this->record->numero . ' ?';
                    }
                    return new \Illuminate\Support\HtmlString(
                        '<div class="rounded-lg p-3 text-sm bg-red-50 border border-red-300 '
                            . 'text-red-800 mb-2">'
                            . '⚠️ <strong>Suppression définitive</strong> de la DA '
                            . $this->record->numero . '</div>'
                            . '<div class="rounded-lg p-3 text-sm bg-blue-50 border border-blue-300 text-blue-800">'
                            . '📋 <strong>Mémoire lié : ' . $memoire->numero . '</strong><br>'
                            . 'La suppression va :<br>'
                            . '&nbsp;&nbsp;1. Supprimer cette DA définitivement<br>'
                            . '&nbsp;&nbsp;2. Remettre le mémoire en <strong>Brouillon</strong>'
                            . '</div>'
                    );
                })
                ->before(function () {
                    // ✅ Remettre le MD en brouillon AVANT la suppression de la DA
                    $this->reinitialiserMemoireLie('DA supprimée');
                }),
        ];
    }

    // =========================================================
    // HELPER : Peut valider (créateur OU destinataire pour validation)
    // =========================================================
    // ✅ Récupère le mémoire lié — avec cache pour éviter N+1
    private function getMemoireLie(): ?\App\Models\MemoireDepense
    {
        if (!$this->_memoireLieCached) {
            // ✅ withoutGlobalScopes() — évite le filtre exercice_id
            //    qui empêche de trouver le MD d'un autre exercice
            $this->_memoireLieCache = \App\Models\MemoireDepense::withoutGlobalScopes()
                ->where('decision_administrative_id', $this->record->id)
                ->first();
            $this->_memoireLieCached = true;
        }
        return $this->_memoireLieCache;
    }

    // ✅ Étape 1 du workflow — Annuler la transformation
    //
    //    DA annulée → MD repasse à 'valide' (PAS directement brouillon)
    //    Raison : séparer "annuler la transformation" de "dévalider le MD"
    //    pour la traçabilité et la robustesse du workflow.
    //
    //    Ensuite l'utilisateur va sur le MD et clique "Dévalider"
    //    pour le remettre en brouillon et le rendre modifiable.
    private function reinitialiserMemoireLie(?string $motif = null): void
    {
        $memoire = $this->getMemoireLie();
        if (!$memoire) return;

        $memoire->updateQuietly([
            // ✅ 'valide' et non 'brouillon' — workflow en 2 étapes
            'statut'                     => 'valide',
            'decision_administrative_id' => null,
            'numero_decision'            => null,
            'date_decision'              => null,
            'observations'               => ($memoire->observations ?? '')
                . "\n\n--- TRANSFORMATION ANNULÉE LE " . now()->format('d/m/Y H:i') . " ---\n"
                . "DA annulée : {$this->record->numero}\n"
                . ($motif ? "Motif : {$motif}\n" : '')
                . "Par : " . auth()->user()->name
                . "\n→ Allez sur le Mémoire et cliquez 'Dévalider' pour le modifier.",
        ]);
    }

    private function peutValider(): bool
    {
        if (!DecisionAdministrativeResource::canValider($this->record)) return false;
        if ($this->record->statut !== 'brouillon') return false;

        // En transmission → seul le destinataire pour 'validation'
        if ($this->estEnTransmission()) {
            if (!$this->estDestinataire()) return false;
            $t = $this->transmissionEnCours();
            return $t?->action_attendue === 'validation';
        }

        // Hors transmission → créateur uniquement
        return $this->record->created_by === auth()->id();
    }

    // =========================================================
    // INFOLIST — inchangé (votre code existant)
    // =========================================================
    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Informations générales')
                    ->schema([
                        Infolists\Components\TextEntry::make('numero')
                            ->label('Numéro DA')
                            ->copyable()
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('budget.libelle')
                            ->label('Budget'),

                        Infolists\Components\TextEntry::make('statut')
                            ->label('Statut')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'brouillon'   => 'gray',
                                'validee'     => 'warning',
                                'engagee'     => 'primary',
                                'ordonnancee' => 'info',
                                'liquidee'    => 'success',
                                'payee'       => 'success',
                                'annulee'     => 'danger',
                                default       => 'gray',
                            })
                            ->formatStateUsing(fn(string $state): string => match ($state) {
                                'brouillon'   => 'Brouillon',
                                'validee'     => 'Validée',
                                'engagee'     => 'Engagée',
                                'ordonnancee' => 'Ordonnancée',
                                'liquidee'    => 'Liquidée',
                                'payee'       => 'Payée',
                                'annulee'     => 'Annulée',
                                default       => $state,
                            }),

                        Infolists\Components\TextEntry::make('typeDecision.libelle')
                            ->label('Type de décision')
                            ->badge(),

                        Infolists\Components\TextEntry::make('date_decision')
                            ->label('Date de décision')
                            ->date('d/m/Y'),

                        Infolists\Components\TextEntry::make('date_effet')
                            ->label("Date de prise d'effet")
                            ->date('d/m/Y')
                            ->placeholder('Non renseignée'),

                        Infolists\Components\TextEntry::make('date_fin')
                            ->label('Date de fin')
                            ->date('d/m/Y')
                            ->placeholder('Non renseignée')
                            ->visible(fn($record) => $record->date_fin),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Bénéficiaire')
                    ->schema([
                        Infolists\Components\TextEntry::make('type_beneficiaire')
                            ->label('Type')
                            ->badge()
                            ->formatStateUsing(fn($state) => match ($state) {
                                'personnel'   => 'Personnel (personne physique)',
                                'fournisseur' => 'Fournisseur (personne morale)',
                                default       => $state,
                            })
                            ->icon(fn($state) => match ($state) {
                                'personnel'   => 'heroicon-o-user',
                                'fournisseur' => 'heroicon-o-building-office',
                                default       => null,
                            })
                            ->color(fn($state) => match ($state) {
                                'personnel'   => 'info',
                                'fournisseur' => 'success',
                                default       => 'gray',
                            })
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('personnel.nom_complet')
                            ->label('Nom complet')
                            ->default('Non renseigné')
                            ->weight('bold')
                            ->visible(fn($record) => $record->type_beneficiaire === 'personnel'),

                        Infolists\Components\TextEntry::make('personnel.matricule')
                            ->label('Matricule')
                            ->badge()
                            ->visible(fn($record) => $record->type_beneficiaire === 'personnel'),

                        Infolists\Components\TextEntry::make('personnel.fonction')
                            ->label('Fonction')
                            ->visible(fn($record) => $record->type_beneficiaire === 'personnel'),

                        Infolists\Components\TextEntry::make('personnel.service.nom')
                            ->label('Service')
                            ->visible(fn($record) => $record->type_beneficiaire === 'personnel'),

                        Infolists\Components\TextEntry::make('fournisseur.raison_sociale')
                            ->label('Raison sociale')
                            ->default('Non renseigné')
                            ->weight('bold')
                            ->visible(fn($record) => $record->type_beneficiaire === 'fournisseur'),

                        Infolists\Components\TextEntry::make('fournisseur.sigle')
                            ->label('Sigle')
                            ->placeholder('Non renseigné')
                            ->visible(fn($record) => $record->type_beneficiaire === 'fournisseur'),

                        Infolists\Components\TextEntry::make('fournisseur.numero_contribuable')
                            ->label('N° Contribuable')
                            ->placeholder('Non renseigné')
                            ->visible(fn($record) => $record->type_beneficiaire === 'fournisseur'),

                        Infolists\Components\TextEntry::make('fournisseur.regimeFiscal.libelle')
                            ->label('Régime fiscal')
                            ->badge()->color('warning')
                            ->visible(fn($record) => $record->type_beneficiaire === 'fournisseur'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Montants et retenues')
                    ->schema([

                        Infolists\Components\TextEntry::make('mode_saisie')
                            ->label('Mode de saisie')
                            ->badge()
                            ->formatStateUsing(fn($state) => match ($state) {
                                'forfait' => '✍️ Forfaitaire (saisie libre)',
                                default   => '🔢 Calculé (formules)',
                            })
                            ->color(fn($state) => $state === 'forfait' ? 'warning' : 'info')
                            ->columnSpanFull(),

                        // ── MODE CALCULÉ ──────────────────────────
                        Infolists\Components\Group::make([

                            Infolists\Components\TextEntry::make('montant_brut')
                                ->label('💰 Montant brut (TTC)')
                                ->formatStateUsing(
                                    fn($state) =>
                                    number_format((float) $state, 0, ',', ' ') . ' FCFA'
                                )
                                ->color('info')
                                ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                ->weight('bold'),

                            Infolists\Components\TextEntry::make('montant_ht')
                                ->label('📐 Montant HT')
                                ->formatStateUsing(
                                    fn($state) =>
                                    number_format((float) ($state ?? 0), 0, ',', ' ') . ' FCFA'
                                )
                                ->color('primary')
                                ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                ->weight('bold')
                                ->helperText('Base de calcul des retenues'),

                            Infolists\Components\TextEntry::make('montant_tva_calcule')
                                ->label(fn($record) => "TVA ({$record->taux_tva}%)")
                                ->formatStateUsing(function ($record) {
                                    $montantTva = (float) ($record->montant_brut ?? 0)
                                        - (float) ($record->montant_ht ?? 0);
                                    return number_format($montantTva, 0, ',', ' ') . ' FCFA';
                                })
                                ->color('gray')
                                ->helperText('TVA incluse dans le brut')
                                ->visible(fn($record) => ($record->taux_tva ?? 0) > 0),

                            Infolists\Components\TextEntry::make('separator_retenues')
                                ->label('💸 Retenues (calculées sur HT)')
                                ->default('')
                                ->columnSpanFull()
                                ->extraAttributes([
                                    'class' =>
                                    'text-sm font-semibold text-gray-700 dark:text-gray-300 '
                                        . 'border-t border-gray-200 dark:border-gray-700 pt-3 mt-2'
                                ]),

                            Infolists\Components\TextEntry::make('montant_cnps')
                                ->label(
                                    fn($record) =>
                                    'CNPS (' . number_format($record->taux_cnps ?? 0, 2) . '%)'
                                )
                                ->formatStateUsing(
                                    fn($state) =>
                                    number_format((float) $state, 0, ',', ' ') . ' FCFA'
                                )
                                ->color('warning')
                                ->visible(fn($record) => ($record->montant_cnps ?? 0) > 0),

                            Infolists\Components\TextEntry::make('montant_ir')
                                ->label(
                                    fn($record) =>
                                    'IR (' . number_format($record->taux_ir ?? 0, 2) . '%)'
                                )
                                ->formatStateUsing(
                                    fn($state) =>
                                    number_format((float) $state, 0, ',', ' ') . ' FCFA'
                                )
                                ->color('warning')
                                ->visible(fn($record) => ($record->montant_ir ?? 0) > 0),

                            Infolists\Components\TextEntry::make('montant_irnc')
                                ->label(
                                    fn($record) =>
                                    'IR(NC) (' . number_format($record->taux_irnc ?? 0, 2) . '%)'
                                )
                                ->formatStateUsing(
                                    fn($state) =>
                                    number_format((float) $state, 0, ',', ' ') . ' FCFA'
                                )
                                ->color('warning')
                                ->visible(fn($record) => ($record->montant_irnc ?? 0) > 0),

                            Infolists\Components\TextEntry::make('montant_redevance_audiovisuelle')
                                ->label(function ($record) {
                                    return $record->type_redevance_audiovisuelle === 'taux'
                                        ? 'Redevance AV ('
                                        . number_format($record->taux_redevance_audiovisuelle ?? 0, 2)
                                        . '%)'
                                        : 'Redevance AV (forfait)';
                                })
                                ->formatStateUsing(
                                    fn($state) =>
                                    number_format((float) ($state ?? 0), 0, ',', ' ') . ' FCFA'
                                )
                                ->color('warning')
                                ->visible(
                                    fn($record) => ((float) ($record->getAttributes()['montant_redevance_audiovisuelle'] ?? 0)) > 0
                                ),

                            Infolists\Components\TextEntry::make('montant_feicom')
                                ->label(function ($record) {
                                    return $record->type_feicom === 'taux'
                                        ? 'FEICOM (' . number_format($record->taux_feicom ?? 0, 2) . '%)'
                                        : 'FEICOM (forfait)';
                                })
                                ->formatStateUsing(
                                    fn($state) =>
                                    number_format((float) ($state ?? 0), 0, ',', ' ') . ' FCFA'
                                )
                                ->color('warning')
                                ->visible(
                                    fn($record) => ((float) ($record->getAttributes()['montant_feicom'] ?? 0)) > 0
                                ),

                            Infolists\Components\TextEntry::make('autres_retenues')
                                ->label('Autres retenues')
                                ->formatStateUsing(
                                    fn($state) =>
                                    number_format((float) ($state ?? 0), 0, ',', ' ') . ' FCFA'
                                )
                                ->color('warning')
                                ->visible(fn($record) => ($record->autres_retenues ?? 0) > 0),

                            Infolists\Components\TextEntry::make('total_taxes')
                                ->label('📊 Total retenues')
                                ->getStateUsing(function ($record) {
                                    $attrs = $record->getAttributes();
                                    return (float) ($attrs['montant_cnps']                    ?? 0)
                                        + (float) ($attrs['montant_ir']                      ?? 0)
                                        + (float) ($attrs['montant_irnc']                    ?? 0)
                                        + (float) ($attrs['montant_redevance_audiovisuelle'] ?? 0)
                                        + (float) ($attrs['montant_feicom']                  ?? 0)
                                        + (float) ($attrs['autres_retenues']                 ?? 0);
                                })
                                ->formatStateUsing(
                                    fn($state) =>
                                    number_format((float) $state, 0, ',', ' ') . ' FCFA'
                                )
                                ->color('danger')->weight('bold')->columnSpanFull()
                                ->extraAttributes([
                                    'class' =>
                                    'border-t border-gray-200 dark:border-gray-700 pt-3 mt-2'
                                ]),

                            Infolists\Components\TextEntry::make('montant_net')
                                ->label('✅ Montant net à payer')
                                ->getStateUsing(function ($record) {
                                    $attrs     = $record->getAttributes();
                                    $ht        = (float) ($attrs['montant_ht'] ?? 0);
                                    $retenues  = (float) ($attrs['montant_cnps']                    ?? 0)
                                        + (float) ($attrs['montant_ir']                      ?? 0)
                                        + (float) ($attrs['montant_irnc']                    ?? 0)
                                        + (float) ($attrs['montant_redevance_audiovisuelle'] ?? 0)
                                        + (float) ($attrs['montant_feicom']                  ?? 0)
                                        + (float) ($attrs['autres_retenues']                 ?? 0);
                                    return $ht - $retenues;
                                })
                                ->formatStateUsing(
                                    fn($state) =>
                                    number_format((float) $state, 0, ',', ' ') . ' FCFA'
                                )
                                ->color('success')
                                ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                ->weight('bold')->columnSpanFull()
                                ->extraAttributes([
                                    'class' =>
                                    'border-t-2 border-green-500 dark:border-green-600 pt-3 mt-2'
                                ]),

                        ])
                            ->columns(3)
                            ->visible(
                                fn($record) => ($record->mode_saisie ?? 'calcule') === 'calcule'
                            ),

                        // ── MODE FORFAITAIRE ──────────────────────
                        Infolists\Components\Group::make([

                            Infolists\Components\TextEntry::make('montant_brut')
                                ->label('💰 Montant Brut (TTC)')
                                ->getStateUsing(
                                    fn($record) =>
                                    number_format((float) ($record->getRawOriginal('montant_brut')
                                        ?? $record->montant_brut), 0, ',', ' ') . ' FCFA'
                                )
                                ->color('info')
                                ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                ->weight('bold'),

                            Infolists\Components\TextEntry::make('montant_ht_forfait')
                                ->label('📐 Montant HT (saisi)')
                                ->getStateUsing(
                                    fn($record) =>
                                    number_format(
                                        (float) ($record->getAttributes()['montant_ht'] ?? 0),
                                        0,
                                        ',',
                                        ' '
                                    ) . ' FCFA'
                                )
                                ->color('primary')->weight('bold'),

                            Infolists\Components\TextEntry::make('montant_tva_forfait')
                                ->label('TVA (saisie)')
                                ->getStateUsing(
                                    fn($record) =>
                                    number_format(
                                        (float) ($record->getAttributes()['montant_tva'] ?? 0),
                                        0,
                                        ',',
                                        ' '
                                    ) . ' FCFA'
                                )
                                ->color('gray')
                                ->visible(
                                    fn($record) => ((float) ($record->getAttributes()['montant_tva'] ?? 0)) > 0
                                ),

                            Infolists\Components\TextEntry::make('separator_forfait')
                                ->label('💸 Retenues (saisies librement)')
                                ->default('')->columnSpanFull()
                                ->extraAttributes([
                                    'class' =>
                                    'text-sm font-semibold text-gray-700 border-t pt-3 mt-2'
                                ]),

                            Infolists\Components\TextEntry::make('montant_cnps_forfait')
                                ->label('CNPS')
                                ->getStateUsing(
                                    fn($record) =>
                                    number_format(
                                        (float) ($record->getAttributes()['montant_cnps'] ?? 0),
                                        0,
                                        ',',
                                        ' '
                                    ) . ' FCFA'
                                )
                                ->color('warning')
                                ->visible(
                                    fn($record) => ((float) ($record->getAttributes()['montant_cnps'] ?? 0)) > 0
                                ),

                            Infolists\Components\TextEntry::make('montant_ir_forfait')
                                ->label('IR')
                                ->getStateUsing(
                                    fn($record) =>
                                    number_format(
                                        (float) ($record->getAttributes()['montant_ir'] ?? 0),
                                        0,
                                        ',',
                                        ' '
                                    ) . ' FCFA'
                                )
                                ->color('warning')
                                ->visible(
                                    fn($record) => ((float) ($record->getAttributes()['montant_ir'] ?? 0)) > 0
                                ),

                            Infolists\Components\TextEntry::make('montant_irnc_forfait')
                                ->label('IR(NC)')
                                ->getStateUsing(
                                    fn($record) =>
                                    number_format(
                                        (float) ($record->getAttributes()['montant_irnc'] ?? 0),
                                        0,
                                        ',',
                                        ' '
                                    ) . ' FCFA'
                                )
                                ->color('warning')
                                ->visible(
                                    fn($record) => ((float) ($record->getAttributes()['montant_irnc'] ?? 0)) > 0
                                ),

                            Infolists\Components\TextEntry::make('montant_redevance_forfait')
                                ->label('Redevance audiovisuelle')
                                ->getStateUsing(
                                    fn($record) =>
                                    number_format(
                                        (float) ($record->getAttributes()['montant_redevance_audiovisuelle'] ?? 0),
                                        0,
                                        ',',
                                        ' '
                                    ) . ' FCFA'
                                )
                                ->color('warning')
                                ->visible(
                                    fn($record) => ((float) ($record->getAttributes()['montant_redevance_audiovisuelle'] ?? 0)) > 0
                                ),

                            Infolists\Components\TextEntry::make('montant_feicom_forfait')
                                ->label('FEICOM')
                                ->getStateUsing(
                                    fn($record) =>
                                    number_format(
                                        (float) ($record->getAttributes()['montant_feicom'] ?? 0),
                                        0,
                                        ',',
                                        ' '
                                    ) . ' FCFA'
                                )
                                ->color('warning')
                                ->visible(
                                    fn($record) => ((float) ($record->getAttributes()['montant_feicom'] ?? 0)) > 0
                                ),

                            Infolists\Components\TextEntry::make('autres_retenues_forfait')
                                ->label('Autres retenues')
                                ->getStateUsing(
                                    fn($record) =>
                                    number_format(
                                        (float) ($record->getAttributes()['autres_retenues'] ?? 0),
                                        0,
                                        ',',
                                        ' '
                                    ) . ' FCFA'
                                )
                                ->color('warning')
                                ->visible(
                                    fn($record) => ((float) ($record->getAttributes()['autres_retenues'] ?? 0)) > 0
                                ),

                            // ✅ Banque + Billetage (mode forfait uniquement)
                            Infolists\Components\TextEntry::make('banque_forfait')
                                ->label('💳 Banque')
                                ->getStateUsing(
                                    fn($record) =>
                                    number_format(
                                        (float) ($record->getAttributes()['banque'] ?? 0),
                                        0,
                                        ',',
                                        ' '
                                    ) . ' FCFA'
                                )
                                ->color('info')
                                ->visible(
                                    fn($record) => ((float) ($record->getAttributes()['banque'] ?? 0)) > 0
                                ),

                            Infolists\Components\TextEntry::make('billetage_forfait')
                                ->label('💵 Billetage')
                                ->getStateUsing(
                                    fn($record) =>
                                    number_format(
                                        (float) ($record->getAttributes()['billetage'] ?? 0),
                                        0,
                                        ',',
                                        ' '
                                    ) . ' FCFA'
                                )
                                ->color('info')
                                ->visible(
                                    fn($record) => ((float) ($record->getAttributes()['billetage'] ?? 0)) > 0
                                ),

                            Infolists\Components\TextEntry::make('total_retenues_forfait')
                                ->label('📊 Total retenues')
                                ->getStateUsing(function ($record) {
                                    $attrs = $record->getAttributes();
                                    return number_format(
                                        (float) ($attrs['montant_cnps']                    ?? 0)
                                            + (float) ($attrs['montant_ir']                    ?? 0)
                                            + (float) ($attrs['montant_irnc']                  ?? 0)
                                            + (float) ($attrs['montant_redevance_audiovisuelle'] ?? 0)
                                            + (float) ($attrs['montant_feicom']                ?? 0)
                                            + (float) ($attrs['autres_retenues']               ?? 0),
                                        0,
                                        ',',
                                        ' '
                                    ) . ' FCFA';
                                })
                                ->color('danger')->weight('bold')->columnSpanFull()
                                ->extraAttributes([
                                    'class' =>
                                    'border-t border-gray-200 pt-3 mt-2'
                                ]),

                            Infolists\Components\TextEntry::make('montant_net_forfait')
                                ->label('✅ Montant net à payer')
                                ->getStateUsing(function ($record) {
                                    $attrs     = $record->getAttributes();
                                    $ht        = (float) ($attrs['montant_ht'] ?? 0);
                                    $retenues  = (float) ($attrs['montant_cnps']                    ?? 0)
                                        + (float) ($attrs['montant_ir']                      ?? 0)
                                        + (float) ($attrs['montant_irnc']                    ?? 0)
                                        + (float) ($attrs['montant_redevance_audiovisuelle'] ?? 0)
                                        + (float) ($attrs['montant_feicom']                  ?? 0)
                                        + (float) ($attrs['autres_retenues']                 ?? 0);
                                    $net       = $ht - $retenues;
                                    $netStocke = (float) ($attrs['montant_net'] ?? 0);
                                    $ecart     = abs($net - $netStocke);
                                    $affichage = number_format($net, 0, ',', ' ') . ' FCFA';
                                    if ($ecart > 1) {
                                        $affichage .= ' ⚠️ (stocké : '
                                            . number_format($netStocke, 0, ',', ' ') . ' FCFA)';
                                    }
                                    return $affichage;
                                })
                                ->color('success')
                                ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                ->weight('bold')->columnSpanFull()
                                ->extraAttributes([
                                    'class' =>
                                    'border-t-2 border-green-500 pt-3 mt-2'
                                ]),

                        ])
                            ->columns(3)
                            ->visible(
                                fn($record) => ($record->mode_saisie ?? 'calcule') === 'forfait'
                            ),

                    ])
                    ->columns(1),

                Infolists\Components\Section::make('Engagement Budgétaire')
                    ->schema([
                        Infolists\Components\TextEntry::make('engagee')
                            ->label('Budget engagé')->badge()
                            ->formatStateUsing(fn($state) => $state ? 'Oui' : 'Non')
                            ->color(fn($state) => $state ? 'success' : 'gray'),

                        Infolists\Components\TextEntry::make('montant_engage')
                            ->label('Montant engagé')
                            ->formatStateUsing(
                                fn($state) =>
                                number_format($state, 0, ',', ' ') . ' FCFA'
                            )
                            ->visible(fn($record) => $record->engagee),

                        Infolists\Components\TextEntry::make('date_engagement')
                            ->label("Date d'engagement")
                            ->dateTime('d/m/Y H:i')
                            ->visible(fn($record) => $record->engagee),

                        Infolists\Components\TextEntry::make('engagement.numero')
                            ->label('N° Engagement')
                            ->copyable()
                            ->url(
                                fn($record) => $record->engagement
                                    ? route(
                                        'filament.budget.resources.engagements.view',
                                        $record->engagement
                                    )
                                    : null
                            )
                            ->color('primary')
                            ->visible(fn($record) => $record->engagement),
                    ])
                    ->columns(4)
                    ->visible(fn($record) => $record->engagee),

                Infolists\Components\Section::make('Objet')
                    ->schema([
                        Infolists\Components\TextEntry::make('objet')
                            ->label('')
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Références')
                    ->schema([
                        Infolists\Components\TextEntry::make('reference_decision')
                            ->label('Référence')
                            ->placeholder('Non renseignée'),

                        Infolists\Components\TextEntry::make('signataire')
                            ->label('Signataire')
                            ->placeholder('Non renseigné'),
                    ])
                    ->columns(2)->collapsible()->collapsed(),

                Infolists\Components\Section::make('Validation')
                    ->schema([
                        Infolists\Components\TextEntry::make('validateurUser.name')
                            ->label('Validée par')
                            ->placeholder('Non validée'),

                        Infolists\Components\TextEntry::make('date_validation')
                            ->label('Date de validation')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Non validée'),
                    ])
                    ->columns(2)
                    ->visible(fn($record) => $record->validee_par)
                    ->collapsible()->collapsed(),

                Infolists\Components\TextEntry::make('source_memoire')
                    ->label('')
                    ->getStateUsing(
                        fn($record) =>
                        str_starts_with($record->reference_decision ?? '', 'MD-')
                            ? "📋 Créée depuis le Mémoire N° {$record->reference_decision}"
                            : null
                    )
                    ->visible(
                        fn($record) =>
                        str_starts_with($record->reference_decision ?? '', 'MD-')
                    )
                    ->badge()->color('info')->columnSpanFull(),

                Infolists\Components\Section::make('Observations')
                    ->schema([
                        Infolists\Components\TextEntry::make('observations')
                            ->label('')
                            ->placeholder('Aucune observation')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()->collapsed(),
            ]);
    }
}