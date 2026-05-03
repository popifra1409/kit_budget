<?php

namespace App\Filament\Budget\Resources\AchatDirectResource\Pages;

use App\Filament\Budget\Resources\AchatDirectResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use App\Models\ProvisionLigneRegie;

class ViewAchatDirect extends ViewRecord
{
    protected static string $resource = AchatDirectResource::class;

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
                        && auth()->user()?->can('valider_depense_regie')
                )
                ->requiresConfirmation()
                ->action(function ($record) {
                    try {
                        if ($record->provision_ligne_regie_id) {
                            ProvisionLigneRegie::findOrFail($record->provision_ligne_regie_id)
                                ->debiter($record->montant_ttc);
                        }
                        $record->update(['statut' => 'valide']);
                        Notification::make()->title('✅ Validé')->success()->send();
                        $this->refreshFormData(['statut']);
                    } catch (\Exception $e) {
                        Notification::make()->title('❌ Erreur')
                            ->danger()->body($e->getMessage())->send();
                    }
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Identification')
                ->schema([
                    Infolists\Components\TextEntry::make('numero')
                        ->label('N°')->copyable()->weight('bold'),
                    Infolists\Components\TextEntry::make('regieAvance.libelle')
                        ->label('Régie'),
                    Infolists\Components\TextEntry::make('date_depense')
                        ->label('Date')->date('d/m/Y'),
                    Infolists\Components\TextEntry::make('statut')
                        ->label('Statut')->badge()
                        ->color(fn($state) => match ($state) {
                            'brouillon' => 'gray',
                            'valide'    => 'warning',
                            'paye'      => 'success',
                            'annule'    => 'danger',
                            default     => 'gray',
                        }),
                    Infolists\Components\TextEntry::make('objet')
                        ->label('Objet')->columnSpan(2),
                ])
                ->columns(3),

            Infolists\Components\Section::make('Montants')
                ->schema([
                    Infolists\Components\TextEntry::make('montant_ht')
                        ->label('MHT')
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
                        ->label('TTC')
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
                        ->label('✅ Net à Payer')
                        ->formatStateUsing(
                            fn($state) =>
                            number_format($state, 0, ',', ' ') . ' FCFA'
                        )->color('success')->weight('bold'),
                ])
                ->columns(5),
        ]);
    }
}
