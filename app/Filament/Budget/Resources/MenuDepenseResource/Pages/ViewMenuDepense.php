<?php

namespace App\Filament\Budget\Resources\MenuDepenseResource\Pages;

use App\Filament\Budget\Resources\MenuDepenseResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Support\Enums\FontWeight;
use Filament\Notifications\Notification;
use Filament\Forms;

class ViewMenuDepense extends ViewRecord
{
    protected static string $resource = MenuDepenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn($record) => $record->statut === 'actif'),

            Actions\Action::make('suspendre')
                ->label('Suspendre')
                ->icon('heroicon-o-pause-circle')->color('warning')
                ->requiresConfirmation()
                ->visible(
                    fn($record) =>
                    $record->statut === 'actif'
                        && auth()->user()?->can('suspendre_menu_depense')
                )
                ->action(function ($record) {
                    $record->update(['statut' => 'suspendu']);
                    Notification::make()->title('Menu Dépense suspendu')->warning()->send();
                    $this->refreshFormData(['statut']);
                }),

            Actions\Action::make('reactiver')
                ->label('Réactiver')
                ->icon('heroicon-o-play-circle')->color('success')
                ->requiresConfirmation()
                ->visible(
                    fn($record) =>
                    $record->statut === 'suspendu'
                        && auth()->user()?->can('suspendre_menu_depense')
                )
                ->action(function ($record) {
                    $record->update(['statut' => 'actif']);
                    Notification::make()->title('✅ Réactivé')->success()->send();
                    $this->refreshFormData(['statut']);
                }),

            Actions\Action::make('cloturer')
                ->label('Clôturer')
                ->icon('heroicon-o-lock-closed')->color('danger')
                ->visible(
                    fn($record) =>
                    in_array($record->statut, ['actif', 'suspendu'])
                        && auth()->user()?->can('cloturer_menu_depense')
                )
                ->form([
                    Forms\Components\DatePicker::make('date_cloture')
                        ->label('Date de clôture')->default(now())->required(),
                    Forms\Components\Textarea::make('observations')
                        ->label('Observations de clôture')->rows(2),
                ])
                ->action(function ($record, array $data) {
                    $record->update([
                        'statut'       => 'cloture',
                        'date_cloture' => $data['date_cloture'],
                        'observations' => ($record->observations ?? '')
                            . "\n\n--- CLÔTURÉ LE " . now()->format('d/m/Y') . " ---\n"
                            . ($data['observations'] ?? ''),
                    ]);
                    Notification::make()->title('✅ Clôturé')->success()->send();
                    $this->refreshFormData(['statut']);
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

            // ══ Situation générale ════════════════════════════
            Infolists\Components\Section::make('Situation générale')
                ->schema([
                    Infolists\Components\Grid::make(3)->schema([

                        Infolists\Components\TextEntry::make('montant_disponible')
                            ->label('Montant disponible')
                            ->money('XAF')->weight(FontWeight::Bold)->size('xl')
                            ->color(
                                fn($record) => ($record->montant_disponible ?? 0) < 0 ? 'danger' : 'success'
                            ),

                        Infolists\Components\TextEntry::make('statut')
                            ->label('Statut')
                            ->badge()->size('xl')
                            ->color(fn($state) => match ($state) {
                                'actif'    => 'success',
                                'suspendu' => 'warning',
                                'cloture'  => 'danger',
                                default    => 'gray',
                            })
                            ->formatStateUsing(fn($state) => match ($state) {
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

            // ══ Identification ════════════════════════════════
            Infolists\Components\Section::make('Identification')
                ->schema([
                    Infolists\Components\Grid::make(3)->schema([

                        Infolists\Components\TextEntry::make('numero')
                            ->label('Numéro MDE')
                            ->weight(FontWeight::Bold)->copyable()
                            ->icon('heroicon-o-hashtag'),

                        Infolists\Components\TextEntry::make('exercice.annee')
                            ->label('Exercice')->badge()->color('info')
                            ->icon('heroicon-o-calendar'),

                        Infolists\Components\TextEntry::make('date_creation')
                            ->label('Date de création')->date('d/m/Y')
                            ->icon('heroicon-o-calendar-days'),
                    ]),

                    Infolists\Components\TextEntry::make('libelle')
                        ->label('Désignation')
                        ->weight(FontWeight::Medium)->size('lg')
                        ->columnSpanFull(),

                    Infolists\Components\TextEntry::make('objet')
                        ->label('Objet du Menu Dépense')
                        ->placeholder('Non renseigné')->columnSpanFull(),

                    Infolists\Components\Grid::make(2)->schema([
                        Infolists\Components\TextEntry::make('responsable.name')
                            ->label('Responsable')
                            ->weight(FontWeight::Bold)
                            ->icon('heroicon-o-user'),

                        Infolists\Components\TextEntry::make('budget.libelle')
                            ->label('Budget')->badge()->color('primary'),
                    ]),
                ])
                ->columns(3)->icon('heroicon-o-identification'),

            // ══ Dotation ══════════════════════════════════════
            Infolists\Components\Section::make('Dotation et état financier')
                ->schema([
                    Infolists\Components\Grid::make(3)->schema([

                        // ✅ Encaisse annuelle
                        Infolists\Components\TextEntry::make('encaisse_annuelle')
                            ->label('Encaisse annuelle')
                            ->money('XAF')->weight(FontWeight::Bold)->color('primary'),

                        // ✅ Net à décaisser = cumul DA sources
                        Infolists\Components\TextEntry::make('montant_alloue')
                            ->label('Net à décaisser (cumul DA)')
                            ->money('XAF')->weight(FontWeight::Bold),

                        // ✅ Encaisse restante calculée
                        Infolists\Components\TextEntry::make('encaisse_restante')
                            ->label('Encaisse restante')
                            ->getStateUsing(
                                fn($record) =>
                                max(0, ($record->encaisse_annuelle ?? 0)
                                    - ($record->montant_alloue ?? 0))
                            )
                            ->money('XAF')->weight(FontWeight::Bold)
                            ->color(
                                fn($record) =>
                                max(0, ($record->encaisse_annuelle ?? 0)
                                    - ($record->montant_alloue ?? 0)) <= 0
                                    ? 'danger' : 'success'
                            ),
                    ]),

                    Infolists\Components\Grid::make(3)->schema([

                        Infolists\Components\TextEntry::make('montant_decaisse')
                            ->label('Montant décaissé')
                            ->money('XAF')->color('warning'),

                        Infolists\Components\TextEntry::make('montant_depense')
                            ->label('Montant dépensé')
                            ->money('XAF')->color('danger'),

                        Infolists\Components\TextEntry::make('montant_disponible')
                            ->label('💰 Disponible')
                            ->money('XAF')->weight(FontWeight::Bold)
                            ->color(
                                fn($record) => ($record->montant_disponible ?? 0) < 0 ? 'danger' : 'success'
                            ),
                    ]),

                    // ✅ Nombre de DA sources
                    Infolists\Components\TextEntry::make('decisionsSource_count')
                        ->label('Décisions sources liées')
                        ->getStateUsing(
                            fn($record) =>
                            $record->decisionsSource()->count() . ' DA(s)'
                        )
                        ->badge()->color('info'),
                ])
                ->icon('heroicon-o-banknotes'),

            // ══ Clôture ───────────────────────────────────────
            Infolists\Components\Section::make('Clôture')
                ->schema([
                    Infolists\Components\TextEntry::make('date_cloture')
                        ->label('Date de clôture')->date('d/m/Y')
                        ->icon('heroicon-o-lock-closed'),
                ])
                ->visible(fn($record) => $record->statut === 'cloture')
                ->icon('heroicon-o-lock-closed'),

            // ══ Observations ──────────────────────────────────
            Infolists\Components\Section::make('Observations')
                ->schema([
                    Infolists\Components\TextEntry::make('observations')
                        ->label('')->placeholder('Aucune observation')
                        ->columnSpanFull(),
                ])
                ->collapsible()->collapsed()
                ->visible(fn($record) => !empty($record->observations))
                ->icon('heroicon-o-chat-bubble-left-right'),

            // ══ Métadonnées ───────────────────────────────────
            Infolists\Components\Section::make('Métadonnées')
                ->schema([
                    Infolists\Components\Grid::make(2)->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Créé le')->dateTime('d/m/Y à H:i')
                            ->icon('heroicon-o-clock'),
                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Modifié le')->dateTime('d/m/Y à H:i')
                            ->since()->icon('heroicon-o-clock'),
                    ]),
                ])
                ->collapsible()->collapsed()
                ->icon('heroicon-o-information-circle'),
        ]);
    }
}
