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

            Actions\Action::make('pdf')
                ->label('PDF')
                ->icon('heroicon-o-document-arrow-down')->color('gray')
                ->visible(fn($record) => $record->statut !== 'brouillon')
                ->url(fn($record) => route('bcr.pdf', $record))
                ->openUrlInNewTab(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
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

            Infolists\Components\Section::make('Objet')
                ->schema([
                    Infolists\Components\TextEntry::make('objet')
                        ->label('')->columnSpanFull(),
                ]),

            Infolists\Components\Section::make('Montants')
                ->schema([
                    Infolists\Components\TextEntry::make('montant_ht')
                        ->label('Montant HT')
                        ->formatStateUsing(
                            fn($state) =>
                            number_format($state, 0, ',', ' ') . ' FCFA'
                        ),
                    Infolists\Components\TextEntry::make('montant_tva')
                        ->label('TVA')
                        ->formatStateUsing(
                            fn($state) =>
                            number_format($state, 0, ',', ' ') . ' FCFA'
                        ),
                    Infolists\Components\TextEntry::make('montant_ttc')
                        ->label('Montant TTC')
                        ->formatStateUsing(
                            fn($state) =>
                            number_format($state, 0, ',', ' ') . ' FCFA'
                        )->weight('bold'),
                    Infolists\Components\TextEntry::make('montant_ir')
                        ->label('IR')
                        ->formatStateUsing(
                            fn($state) =>
                            number_format($state, 0, ',', ' ') . ' FCFA'
                        )->color('warning'),
                    Infolists\Components\TextEntry::make('net_a_payer')
                        ->label('✅ Net à payer')
                        ->formatStateUsing(
                            fn($state) =>
                            number_format($state, 0, ',', ' ') . ' FCFA'
                        )->color('success')->weight('bold'),
                ])
                ->columns(5),
        ]);
    }
}
