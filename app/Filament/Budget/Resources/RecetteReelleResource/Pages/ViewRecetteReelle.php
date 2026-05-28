<?php

namespace App\Filament\Budget\Resources\RegieAvanceResource\Pages;

use App\Filament\Budget\Resources\RegieAvanceResource;
use App\Models\DecisionAdministrative;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Support\Enums\FontWeight;
use Filament\Notifications\Notification;
use Filament\Forms;

class ViewRegieAvance extends ViewRecord
{
    protected static string $resource = RegieAvanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn($record) => $record->statut === 'actif'),

            // ── Suspendre ────────────────────────────────────
            Actions\Action::make('suspendre')
                ->label('Suspendre')
                ->icon('heroicon-o-pause-circle')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Suspendre la régie')
                ->modalDescription('La régie sera suspendue — aucune dépense ne pourra être enregistrée.')
                ->modalSubmitActionLabel('Suspendre')
                ->visible(
                    fn($record) =>
                    $record->statut === 'actif'
                        && auth()->user()?->can('suspendre_regie_avance')
                )
                ->action(function ($record) {
                    $record->update(['statut' => 'suspendu']);
                    Notification::make()->title('Régie suspendue')->warning()->send();
                    $this->refreshFormData(['statut']);
                }),

            // ── Réactiver ────────────────────────────────────
            Actions\Action::make('reactiver')
                ->label('Réactiver')
                ->icon('heroicon-o-play-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Réactiver la régie')
                ->modalDescription('La régie sera réactivée et les dépenses pourront reprendre.')
                ->modalSubmitActionLabel('Réactiver')
                ->visible(
                    fn($record) =>
                    $record->statut === 'suspendu'
                        && auth()->user()?->can('suspendre_regie_avance')
                )
                ->action(function ($record) {
                    $record->update(['statut' => 'actif']);
                    Notification::make()->title('✅ Régie réactivée')->success()->send();
                    $this->refreshFormData(['statut']);
                }),

            // ── Clôturer ─────────────────────────────────────
            Actions\Action::make('cloturer')
                ->label('Clôturer')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Clôturer la régie')
                ->modalDescription('La régie sera définitivement clôturée. Cette action est irréversible.')
                ->modalSubmitActionLabel('Clôturer définitivement')
                ->visible(
                    fn($record) =>
                    in_array($record->statut, ['actif', 'suspendu'])
                        && auth()->user()?->can('cloturer_regie_avance')
                )
                ->form([
                    Forms\Components\DatePicker::make('date_cloture')
                        ->label('Date de clôture')
                        ->default(now())->required(),
                    Forms\Components\Textarea::make('observations')
                        ->label('Observations de clôture')->rows(2),
                ])
                ->action(function ($record, array $data) {
                    $record->update([
                        'statut'       => 'cloture',
                        'date_cloture' => $data['date_cloture'],
                        'observations' => ($record->observations ?? '')
                            . "\n\n--- CLÔTURÉE LE " . now()->format('d/m/Y') . " ---\n"
                            . ($data['observations'] ?? ''),
                    ]);
                    Notification::make()->title('✅ Régie clôturée')->success()->send();
                    $this->refreshFormData(['statut']);
                }),

            // ── Réapprovisionner ──────────────────────────────
            Actions\Action::make('reapprovisionner')
                ->label('Réapprovisionner')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation(false)
                ->modalHeading('Réapprovisionner la régie')
                ->modalSubmitActionLabel('Réapprovisionner')
                ->visible(
                    fn($record) =>
                    $record->statut === 'actif'
                        && auth()->user()?->can('reapprovisionner_regie_avance')
                )
                ->form([
                    Forms\Components\Select::make('decision_administrative_id')
                        ->label('Nouvelle DA engagée')
                        ->options(function () {
                            return DecisionAdministrative::where('statut', 'engagee')
                                ->get()
                                ->mapWithKeys(fn($da) => [
                                    $da->id => "{$da->numero} — {$da->objet} "
                                        . "(" . number_format($da->montant_net, 0, ',', ' ') . " FCFA)"
                                ]);
                        })
                        ->required()->searchable()
                        ->helperText('DA engagée source — les montants seront pré-remplis'),
                ])
                ->action(function ($record, array $data) {
                    $da = DecisionAdministrative::findOrFail($data['decision_administrative_id']);
                    $record->reapprovisionner($da);
                    Notification::make()
                        ->title('✅ Régie réapprovisionnée')
                        ->success()
                        ->body("+ " . number_format($da->montant_net, 0, ',', ' ') . " FCFA")
                        ->send();
                    $this->refreshFormData([
                        'montant_alloue',
                        'montant_decaisse',
                        'montant_disponible',
                    ]);
                }),

            Actions\DeleteAction::make()
                ->visible(
                    fn($record) =>
                    $record->statut === 'actif'
                        && ($record->montant_depense ?? 0) == 0
                        && auth()->user()->hasRole('super_admin')
                ),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([

            // ==========================================
            // SECTION: SITUATION GENERALE
            // ==========================================
            Infolists\Components\Section::make('Situation générale')
                ->schema([
                    Infolists\Components\Grid::make(3)->schema([

                        Infolists\Components\TextEntry::make('montant_disponible')
                            ->label('Montant disponible')
                            ->money('XAF')
                            ->weight(FontWeight::Bold)
                            ->size('xl')
                            ->color(
                                fn($record) => ($record->montant_disponible ?? 0) < 0 ? 'danger' : 'success'
                            ),

                        Infolists\Components\TextEntry::make('statut')
                            ->label('Statut')
                            ->badge()->size('xl')
                            ->color(fn(string $state): string => match ($state) {
                                'actif'    => 'success',
                                'suspendu' => 'warning',
                                'cloture'  => 'danger',
                                default    => 'gray',
                            })
                            ->formatStateUsing(fn(string $state): string => match ($state) {
                                'actif'    => '🟢 Actif',
                                'suspendu' => '⏸️ Suspendu',
                                'cloture'  => '🔒 Clôturé',
                                default    => $state,
                            }),

                        Infolists\Components\TextEntry::make('taux_consommation')
                            ->label('Taux de consommation')
                            ->getStateUsing(
                                fn($record) =>
                                number_format($record->taux_consommation, 1) . '%'
                            )
                            ->badge()->size('xl')
                            ->color(fn($record) => match (true) {
                                $record->taux_consommation >= 90 => 'danger',
                                $record->taux_consommation >= 70 => 'warning',
                                default                          => 'success',
                            }),
                    ]),
                ])
                ->columnSpanFull()
                ->icon('heroicon-o-chart-bar'),

            // ==========================================
            // SECTION: IDENTIFICATION
            // ==========================================
            Infolists\Components\Section::make('Identification')
                ->schema([
                    Infolists\Components\Grid::make(3)->schema([

                        Infolists\Components\TextEntry::make('numero')
                            ->label('Numéro RAV')
                            ->weight(FontWeight::Bold)
                            ->copyable()
                            ->copyMessage('Numéro copié !')
                            ->copyMessageDuration(1500)
                            ->icon('heroicon-o-hashtag'),

                        Infolists\Components\TextEntry::make('exercice.annee')
                            ->label('Exercice')
                            ->badge()
                            ->color(
                                fn($record) =>
                                $record->exercice?->estActif() ? 'success' : 'gray'
                            )
                            ->icon('heroicon-o-calendar'),

                        Infolists\Components\TextEntry::make('date_creation')
                            ->label('Date de création')
                            ->date('d/m/Y')
                            ->weight(FontWeight::Bold)
                            ->icon('heroicon-o-calendar-days'),
                    ]),

                    Infolists\Components\TextEntry::make('libelle')
                        ->label('Désignation')
                        ->columnSpanFull()
                        ->weight(FontWeight::Medium)
                        ->size('lg'),

                    Infolists\Components\TextEntry::make('objet')
                        ->label('Objet de la régie')
                        ->placeholder('Non renseigné')
                        ->columnSpanFull(),

                    Infolists\Components\Grid::make(2)->schema([

                        Infolists\Components\TextEntry::make('responsable.name')
                            ->label('Responsable / Régisseur')
                            ->weight(FontWeight::Bold)
                            ->icon('heroicon-o-user'),

                        Infolists\Components\TextEntry::make('budget.libelle')
                            ->label('Budget')
                            ->badge()->color('primary'),
                    ]),
                ])
                ->columns(3)
                ->icon('heroicon-o-identification'),

            // ==========================================
            // SECTION: DOTATION ET ÉTAT FINANCIER
            // ==========================================
            Infolists\Components\Section::make('Dotation et état financier')
                ->schema([
                    Infolists\Components\Grid::make(3)->schema([

                        // ✅ Encaisse annuelle
                        Infolists\Components\TextEntry::make('encaisse_annuelle')
                            ->label('Encaisse annuelle')
                            ->money('XAF')
                            ->weight(FontWeight::Bold)
                            ->color('primary'),

                        // ✅ Net à décaisser
                        Infolists\Components\TextEntry::make('montant_alloue')
                            ->label('Net à décaisser')
                            ->money('XAF')
                            ->weight(FontWeight::Bold),

                        // ✅ Encaisse restante calculée
                        Infolists\Components\TextEntry::make('encaisse_restante')
                            ->label('Encaisse restante')
                            ->getStateUsing(
                                fn($record) =>
                                max(0, ($record->encaisse_annuelle ?? 0) - ($record->montant_alloue ?? 0))
                            )
                            ->money('XAF')
                            ->weight(FontWeight::Bold)
                            ->color(
                                fn($record) =>
                                max(0, ($record->encaisse_annuelle ?? 0) - ($record->montant_alloue ?? 0)) <= 0
                                    ? 'danger' : 'success'
                            ),
                    ]),

                    Infolists\Components\Grid::make(3)->schema([

                        Infolists\Components\TextEntry::make('montant_decaisse')
                            ->label('Montant décaissé')
                            ->money('XAF')
                            ->color('warning'),

                        Infolists\Components\TextEntry::make('montant_depense')
                            ->label('Montant dépensé')
                            ->money('XAF')
                            ->color('danger'),

                        Infolists\Components\TextEntry::make('montant_disponible')
                            ->label('💰 Disponible')
                            ->money('XAF')
                            ->weight(FontWeight::Bold)
                            ->color(
                                fn($record) => ($record->montant_disponible ?? 0) < 0 ? 'danger' : 'success'
                            ),
                    ]),
                ])
                ->icon('heroicon-o-banknotes'),

            // ==========================================
            // SECTION: DÉCISION ADMINISTRATIVE SOURCE
            // ==========================================
            Infolists\Components\Section::make('Décision Administrative source')
                ->schema([
                    Infolists\Components\Grid::make(3)->schema([

                        Infolists\Components\TextEntry::make('decisionAdministrative.numero')
                            ->label('N° DA')
                            ->badge()->color('primary')
                            ->copyable()
                            ->placeholder('Non associée'),

                        Infolists\Components\TextEntry::make('decisionAdministrative.date_decision')
                            ->label('Date décision')
                            ->date('d/m/Y')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('decisionAdministrative.statut')
                            ->label('Statut DA')
                            ->badge()
                            ->placeholder('—'),
                    ]),

                    Infolists\Components\Grid::make(2)->schema([

                        Infolists\Components\TextEntry::make('decisionAdministrative.montant_net')
                            ->label('Montant net DA')
                            ->money('XAF')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('decisionAdministrative.objet')
                            ->label('Objet DA')
                            ->placeholder('—'),
                    ]),
                ])
                ->visible(fn($record) => $record->decision_administrative_id !== null)
                ->collapsible()
                ->icon('heroicon-o-document-check'),

            // ==========================================
            // SECTION: CLÔTURE
            // ==========================================
            Infolists\Components\Section::make('Clôture')
                ->schema([
                    Infolists\Components\Grid::make(2)->schema([

                        Infolists\Components\TextEntry::make('date_cloture')
                            ->label('Date de clôture')
                            ->date('d/m/Y')
                            ->icon('heroicon-o-lock-closed'),

                        Infolists\Components\TextEntry::make('observations')
                            ->label('Observations de clôture')
                            ->placeholder('—'),
                    ]),
                ])
                ->visible(fn($record) => $record->statut === 'cloture')
                ->icon('heroicon-o-lock-closed'),

            // ==========================================
            // SECTION: OBSERVATIONS
            // ==========================================
            Infolists\Components\Section::make('Observations')
                ->schema([
                    Infolists\Components\TextEntry::make('observations')
                        ->label('')
                        ->placeholder('Aucune observation')
                        ->columnSpanFull(),
                ])
                ->icon('heroicon-o-chat-bubble-left-right')
                ->collapsed()
                ->collapsible()
                ->visible(fn($record) => !empty($record->observations)),

            // ==========================================
            // SECTION: MÉTADONNÉES
            // ==========================================
            Infolists\Components\Section::make('Métadonnées')
                ->schema([
                    Infolists\Components\Grid::make(2)->schema([

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Créé le')
                            ->dateTime('d/m/Y à H:i')
                            ->icon('heroicon-o-clock'),

                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Modifié le')
                            ->dateTime('d/m/Y à H:i')
                            ->icon('heroicon-o-clock')
                            ->since(),
                    ]),
                ])
                ->collapsed()
                ->collapsible()
                ->icon('heroicon-o-information-circle'),
        ]);
    }
}
