<?php

namespace App\Filament\Budget\Resources\OrdonnancePaiementResource\Pages;

use App\Filament\Budget\Resources\OrdonnancePaiementResource;
use App\Models\OrdonnancePaiement;
use App\Models\Engagement;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use App\Models\EtatConfig;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class ViewOrdonnancePaiement extends ViewRecord
{
    protected static string $resource = OrdonnancePaiementResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // Informations générales
                Infolists\Components\Section::make('Informations générales')
                    ->schema([
                        Infolists\Components\TextEntry::make('numero')
                            ->label('N° Ordonnance')
                            ->badge()
                            ->color('primary')
                            ->copyable(),

                        Infolists\Components\TextEntry::make('type_ordonnance')
                            ->label('Type')
                            ->formatStateUsing(fn($state) => match ($state) {
                                'standard' => 'Standard (Fournisseur)',
                                'impot'    => 'Impôt (Direction des Impôts)',
                                default    => $state,
                            })
                            ->badge()
                            ->color(fn($state) => $state === 'standard' ? 'primary' : 'warning'),

                        Infolists\Components\TextEntry::make('engagement.numero')
                            ->label('Engagement')
                            ->url(fn($record) => $record->engagement
                                ? route('filament.budget.resources.engagements.view', $record->engagement)
                                : null)
                            ->color('info'),

                        Infolists\Components\TextEntry::make('statut_label')
                            ->label('Statut')
                            ->badge()
                            ->color(fn($record) => $record->statut_color),

                        Infolists\Components\TextEntry::make('objet')
                            ->label('Objet')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                // Montants
                Infolists\Components\Section::make('Détail des montants')
                    ->schema([
                        Infolists\Components\TextEntry::make('montant_brut')
                            ->label('Montant brut')
                            ->money('XAF')
                            ->state(fn($record) => $record->fresh()->montant_brut)
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('montant_impot')
                            ->label('Montant impôt/IR')
                            ->money('XAF')
                            ->state(fn($record) => $record->fresh()->montant_impot)
                            ->color('warning'),

                        Infolists\Components\TextEntry::make('montant_net')
                            ->label('Montant net à payer')
                            ->money('XAF')
                            ->state(fn($record) => $record->fresh()->montant_net)
                            ->color('success')
                            ->weight('bold')
                            ->size('lg'),

                        Infolists\Components\TextEntry::make('montant_pec')
                            ->label('Montant PEC Médical')
                            ->money('XAF')
                            ->visible(fn($record) => $record->type_ordonnance === 'impot'),
                    ])
                    ->columns(4),

                // Dates et références
                Infolists\Components\Section::make('Dates et références')
                    ->schema([
                        Infolists\Components\TextEntry::make('date_emission')
                            ->label('Date d\'émission')
                            ->date('d/m/Y'),

                        Infolists\Components\TextEntry::make('mois_emission')
                            ->label('Mois d\'émission')
                            ->formatStateUsing(fn($state) => $state ? sprintf('%02d', $state) : '-'),

                        Infolists\Components\TextEntry::make('periode')
                            ->label('Période'),

                        Infolists\Components\TextEntry::make('numero_bon')
                            ->label('N° Bon de caisse')
                            ->placeholder('-'),

                        Infolists\Components\TextEntry::make('numero_emission')
                            ->label('N° d\'émission')
                            ->placeholder('-'),

                        Infolists\Components\TextEntry::make('numero_op')
                            ->label('N° OP')
                            ->placeholder('-'),
                    ])
                    ->columns(3),

                // Bénéficiaire
                Infolists\Components\Section::make('Bénéficiaire')
                    ->schema([
                        Infolists\Components\TextEntry::make('beneficiaire.raison_sociale')
                            ->label('Raison sociale')
                            ->default(fn($record) => $record->beneficiaire?->name ?? 'Direction des Impôts')
                            ->placeholder('Direction des Impôts'),

                        Infolists\Components\TextEntry::make('beneficiaire.telephone')
                            ->label('Téléphone')
                            ->placeholder('-')
                            ->visible(fn($record) => $record->beneficiaire),

                        Infolists\Components\TextEntry::make('beneficiaire.email')
                            ->label('Email')
                            ->placeholder('-')
                            ->visible(fn($record) => $record->beneficiaire),
                    ])
                    ->columns(3)
                    ->collapsible(),

                // Paiement
                Infolists\Components\Section::make('Informations de paiement')
                    ->schema([
                        Infolists\Components\TextEntry::make('date_paiement')
                            ->label('Date de paiement')
                            ->date('d/m/Y')
                            ->placeholder('Non payé'),

                        Infolists\Components\TextEntry::make('reference_paiement')
                            ->label('Référence de paiement')
                            ->placeholder('Non renseignée'),
                    ])
                    ->columns(2)
                    ->visible(fn($record) => $record->statut === 'payee')
                    ->collapsible(),

                // Observations
                Infolists\Components\Section::make('Observations')
                    ->schema([
                        Infolists\Components\TextEntry::make('observations')
                            ->label('')
                            ->columnSpanFull()
                            ->placeholder('Aucune observation'),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn($record) => $record->observations),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            // ── Éditer ───────────────────────────────────────────────
            Actions\EditAction::make()
                ->visible(fn() => OrdonnancePaiementResource::canEdit($this->record)),

            // ── Télécharger PDF ──────────────────────────────────────
            Actions\Action::make('telecharger')
                ->label('Télécharger PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->form([
                    Forms\Components\Select::make('variante')
                        ->label('Modèle d\'état')
                        ->options(fn() => EtatConfig::variantesPour(
                            $this->record->type_ordonnance === 'impot'
                                ? 'ordonnance_paiement_impot'
                                : 'ordonnance_paiement'
                        ))
                        ->default(fn() => EtatConfig::defautPour(
                            $this->record->type_ordonnance === 'impot'
                                ? 'ordonnance_paiement_impot'
                                : 'ordonnance_paiement'
                        )?->code)
                        ->required()
                        ->helperText('⭐ = modèle par défaut'),
                ])
                ->action(function (array $data) {
                    $this->dispatch('open-url-new-tab', url: route('pdf.telecharger', [
                        'etat' => $data['variante'],
                        'id'   => $this->record->id,
                    ]));
                }),

            // ── Aperçu PDF ───────────────────────────────────────────
            Actions\Action::make('apercu')
                ->label('Aperçu PDF')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->form([
                    Forms\Components\Select::make('variante')
                        ->label('Modèle d\'état')
                        ->options(fn() => EtatConfig::variantesPour(
                            $this->record->type_ordonnance === 'impot'
                                ? 'ordonnance_paiement_impot'
                                : 'ordonnance_paiement'
                        ))
                        ->default(fn() => EtatConfig::defautPour(
                            $this->record->type_ordonnance === 'impot'
                                ? 'ordonnance_paiement_impot'
                                : 'ordonnance_paiement'
                        )?->code)
                        ->required()
                        ->helperText('⭐ = modèle par défaut'),
                ])
                ->action(function (array $data) {
                    $this->dispatch('open-url-new-tab', url: route('pdf.afficher', [
                        'etat' => $data['variante'],
                        'id'   => $this->record->id,
                    ]));
                })
                ->openUrlInNewTab(),

            // ════════════════════════════════════════════════════════
            // ✅ SUPPRIMER OP STANDARD
            //    Remplace Actions\DeleteAction::make() par défaut
            //    → supprime l'OPT liée automatiquement
            //    → remet l'engagement à 'provisoire'
            //    → redirige vers la liste
            // ════════════════════════════════════════════════════════
            Actions\Action::make('supprimer_op')
                ->label('Supprimer')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(
                    fn() =>
                    $this->record->type_ordonnance === 'standard'
                        && $this->record->statut !== 'payee'
                        && OrdonnancePaiementResource::canDelete($this->record)
                )
                ->requiresConfirmation()
                ->modalIcon('heroicon-o-exclamation-triangle')
                ->modalHeading(fn() => 'Supprimer ' . $this->record->numero . ' ?')
                ->modalDescription(function () {
                    $opt = OrdonnancePaiement::where('engagement_id', $this->record->engagement_id)
                        ->where('type_ordonnance', 'impot')
                        ->first();

                    $msg = 'Cette action est <strong>irréversible</strong>.'
                        . ' L\'engagement associé sera remis à l\'état <strong>Provisoire</strong>.';

                    if ($opt) {
                        $msg .= '<br><br>⚠️ L\'ordonnance impôt <strong>'
                            . $opt->numero
                            . '</strong> sera également supprimée automatiquement.';
                    }

                    return new \Illuminate\Support\HtmlString($msg);
                })
                ->action(function () {
                    DB::transaction(function () {
                        // 1. Supprimer l'OPT liée (même engagement)
                        OrdonnancePaiement::where('engagement_id', $this->record->engagement_id)
                            ->where('type_ordonnance', 'impot')
                            ->each(fn($opt) => $opt->delete());

                        // 2. Remettre l'engagement à 'provisoire'
                        if ($this->record->engagement_id) {
                            Engagement::where('id', $this->record->engagement_id)
                                ->update(['statut' => 'provisoire']);
                        }

                        // 3. Supprimer l'OP
                        $numero = $this->record->numero;
                        $this->record->delete();

                        Notification::make()
                            ->title('✅ Ordonnance supprimée')
                            ->body($numero . ' supprimée. Engagement remis à l\'état Provisoire.')
                            ->success()
                            ->send();
                    });

                    $this->redirect(OrdonnancePaiementResource::getUrl('index'));
                }),

            // ════════════════════════════════════════════════════════
            // ✅ SUPPRIMER OPT (Impôt)
            //    → supprime l'OPT
            //    → checkbox optionnelle pour supprimer aussi l'OP génératrice
            // ════════════════════════════════════════════════════════
            Actions\Action::make('supprimer_opt')
                ->label('Supprimer')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(
                    fn() =>
                    $this->record->type_ordonnance === 'impot'
                        && $this->record->statut !== 'payee'
                        && OrdonnancePaiementResource::canDelete($this->record)
                )
                ->modalIcon('heroicon-o-exclamation-triangle')
                ->modalHeading(fn() => 'Supprimer ' . $this->record->numero . ' ?')
                ->modalDescription(function () {
                    $op = OrdonnancePaiement::where('engagement_id', $this->record->engagement_id)
                        ->where('type_ordonnance', 'standard')
                        ->first();

                    if ($op) {
                        return new \Illuminate\Support\HtmlString(
                            'Vous supprimez uniquement l\'OPT <strong>' . $this->record->numero . '</strong>.'
                                . '<br>L\'OP génératrice <strong>' . $op->numero . '</strong> sera <u>conservée</u>.'
                                . '<br><br>Cochez l\'option ci-dessous pour la supprimer aussi '
                                . '(l\'engagement sera alors remis à <strong>Provisoire</strong>).'
                        );
                    }

                    return new \Illuminate\Support\HtmlString(
                        'Suppression de l\'OPT <strong>' . $this->record->numero . '</strong>. Action irréversible.'
                    );
                })
                ->form([
                    Forms\Components\Checkbox::make('supprimer_op_aussi')
                        ->label(function () {
                            $op = OrdonnancePaiement::where('engagement_id', $this->record->engagement_id)
                                ->where('type_ordonnance', 'standard')
                                ->first();
                            return $op
                                ? '⚠️ Supprimer aussi l\'OP génératrice ' . $op->numero . ' (remet l\'engagement à Provisoire)'
                                : 'Supprimer aussi l\'OP génératrice (remet l\'engagement à Provisoire)';
                        })
                        ->default(false),
                ])
                ->action(function (array $data) {
                    DB::transaction(function () use ($data) {
                        $numeroOpt = $this->record->numero;

                        if ($data['supprimer_op_aussi'] ?? false) {
                            $op = OrdonnancePaiement::where('engagement_id', $this->record->engagement_id)
                                ->where('type_ordonnance', 'standard')
                                ->first();

                            if ($op) {
                                $numeroOp = $op->numero;
                                $op->delete();

                                if ($this->record->engagement_id) {
                                    Engagement::where('id', $this->record->engagement_id)
                                        ->update(['statut' => 'provisoire']);
                                }

                                $this->record->delete();

                                Notification::make()
                                    ->title('✅ OPT et OP supprimées')
                                    ->body($numeroOpt . ' + ' . $numeroOp . ' supprimées. Engagement remis à Provisoire.')
                                    ->success()
                                    ->send();

                                $this->redirect(OrdonnancePaiementResource::getUrl('index'));
                                return;
                            }
                        }

                        // Supprimer uniquement l'OPT
                        $this->record->delete();

                        Notification::make()
                            ->title('✅ OPT supprimée')
                            ->body($numeroOpt . ' supprimée. L\'OP génératrice est conservée.')
                            ->success()
                            ->send();

                        $this->redirect(OrdonnancePaiementResource::getUrl('index'));
                    });
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->record->refresh();

        return $data;
    }
}
