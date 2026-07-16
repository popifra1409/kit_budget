<?php

namespace App\Filament\Budget\Resources\BonCommandeRegieResource\Pages;

use App\Filament\Budget\Resources\BonCommandeRegieResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Forms;

class ViewBonCommandeRegie extends ViewRecord
{
    protected static string $resource = BonCommandeRegieResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn($record) => $record->statut === 'brouillon'),

            Actions\Action::make('valider')
                ->label('Valider')
                ->icon('heroicon-o-check-circle')->color('success')
                ->visible(
                    fn($record) =>
                    $record->statut === 'brouillon'
                        && auth()->user()?->can('valider_bon_commande_regie')
                )
                ->requiresConfirmation()
                ->action(function ($record) {
                    $record->update(['statut' => 'valide']);
                    Notification::make()->title('✅ BCR/BCM validé')->success()->send();
                    $this->refreshFormData(['statut']);
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([

            // ── Identification ────────────────────────────────
            Infolists\Components\Section::make('Identification')
                ->schema([
                    Infolists\Components\TextEntry::make('numero')
                        ->label('N° BCR/BCM')->copyable()->weight('bold'),
                    Infolists\Components\TextEntry::make('regieAvance.numero')
                        ->label('Régie source')->badge()->color('info'),
                    Infolists\Components\TextEntry::make('regieAvance.type')
                        ->label('Type')
                        ->formatStateUsing(fn($state) => match ($state) {
                            'rav'          => 'Régie d\'Avance',
                            'menu_depense' => 'Menu Dépense',
                            default        => $state,
                        })->badge(),
                    Infolists\Components\TextEntry::make('statut')
                        ->label('Statut')->badge()
                        ->color(fn($state) => match ($state) {
                            'brouillon' => 'gray',
                            'valide'    => 'warning',
                            'livre'     => 'success',
                            'paye'      => 'success',
                            'annule'    => 'danger',
                            default     => 'gray',
                        }),
                    Infolists\Components\TextEntry::make('date_emission')
                        ->label('Date d\'émission')->date('d/m/Y'),
                    Infolists\Components\TextEntry::make('fournisseur.raison_sociale')
                        ->label('Fournisseur')->weight('bold'),
                ])
                ->columns(3),

            // ── Objet ─────────────────────────────────────────
            Infolists\Components\Section::make('Objet')
                ->schema([
                    Infolists\Components\TextEntry::make('objet')
                        ->label('')->columnSpanFull(),
                ]),

            // ── Montants ──────────────────────────────────────
            Infolists\Components\Section::make('Montants')
                ->schema([
                    Infolists\Components\TextEntry::make('montant_ht')
                        ->label('Montant HT')
                        ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ') . ' FCFA'),

                    Infolists\Components\TextEntry::make('montant_tva')
                        ->label('TVA')
                        ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ') . ' FCFA'),

                    Infolists\Components\TextEntry::make('montant_ttc')
                        ->label('Montant TTC')
                        ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ') . ' FCFA')
                        ->weight('bold'),

                    Infolists\Components\TextEntry::make('montant_ir')
                        ->label('IR')
                        ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ') . ' FCFA')
                        ->color('warning'),

                    Infolists\Components\TextEntry::make('net_a_payer')
                        ->label('✅ Net à payer')
                        ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ') . ' FCFA')
                        ->color('success')->weight('bold'),
                ])
                ->columns(5),

            // ── ✅ Engagement (visible si BCR engagé) ─────────
            Infolists\Components\Section::make('Engagement')
                ->visible(fn($record) => $record && $record->engage)
                ->schema([
                    // Statut engagement global
                    Infolists\Components\TextEntry::make('statut_engagement_detail')
                        ->label('Statut engagement')
                        ->getStateUsing(function ($record) {
                            $pct   = (float) ($record->pourcentage_engage ?? 100);
                            $reste = (float) ($record->reste_a_engager    ?? 0);

                            if ($pct >= 100 || $reste <= 0) {
                                return '✅ Engagement total (100%)';
                            }
                            return "⚡ Engagement partiel — {$pct}%";
                        })
                        ->badge()
                        ->color(function ($record) {
                            $pct = (float) ($record->pourcentage_engage ?? 100);
                            return match (true) {
                                $pct >= 100 => 'success',
                                $pct >= 50  => 'warning',
                                default     => 'danger',
                            };
                        }),

                    // Montant engagé
                    Infolists\Components\TextEntry::make('montant_engage')
                        ->label('Montant engagé')
                        ->getStateUsing(function ($record) {
                            $engage = (float) $record->montant_engage > 0
                                ? (float) $record->montant_engage
                                : (float) $record->montant_ttc;
                            $pct = (float) ($record->pourcentage_engage ?? 100);
                            return number_format($engage, 0, ',', ' ')
                                . " FCFA ({$pct}%)";
                        })
                        ->color('primary')->weight('bold'),

                    // Reste à engager
                    Infolists\Components\TextEntry::make('reste_a_engager')
                        ->label('Reste à engager')
                        ->getStateUsing(function ($record) {
                            $reste = (float) ($record->reste_a_engager ?? 0);
                            if ($reste <= 0) return '✅ Soldé';
                            $pct = 100 - (float) ($record->pourcentage_engage ?? 100);
                            return number_format($reste, 0, ',', ' ')
                                . " FCFA ({$pct}%)";
                        })
                        ->color(function ($record) {
                            return ((float) ($record->reste_a_engager ?? 0)) > 0
                                ? 'danger'
                                : 'success';
                        }),

                    // Date engagement
                    Infolists\Components\TextEntry::make('date_engagement')
                        ->label('Date engagement')
                        ->dateTime('d/m/Y H:i')
                        ->placeholder('—'),

                    // ✅ Alerte visuelle si partiel non soldé
                    Infolists\Components\TextEntry::make('alerte_partiel')
                        ->label('')
                        ->getStateUsing(fn($record) => '')
                        ->columnSpanFull()
                        ->visible(
                            fn($record) =>
                            $record->engage
                                && (float) ($record->reste_a_engager ?? 0) > 0
                                && in_array($record->statut, ['valide', 'livre'])
                        )
                        ->hintIcon('heroicon-o-exclamation-triangle')
                        ->hintIconTooltip('Engagement partiel en cours')
                        ->hint(function ($record) {
                            $reste = (float) ($record->reste_a_engager ?? 0);
                            $pct   = (float) ($record->pourcentage_engage ?? 0);
                            return "⚠️ Engagement partiel à {$pct}% — "
                                . number_format($reste, 0, ',', ' ')
                                . " FCFA restants à engager avant paiement complet.";
                        }),
                ])
                ->columns(4),
        ]);
    }
}
