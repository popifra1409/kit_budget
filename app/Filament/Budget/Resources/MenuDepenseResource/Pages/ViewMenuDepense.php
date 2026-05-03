<?php
// app/Filament/Budget/Resources/MenuDepenseResource/Pages/ViewMenuDepense.php

namespace App\Filament\Budget\Resources\MenuDepenseResource\Pages;

use App\Filament\Budget\Resources\MenuDepenseResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
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
                ->visible(
                    fn($record) =>
                    $record->statut === 'actif'
                        && auth()->user()?->can('suspendre_menu_depense')
                )
                ->requiresConfirmation()
                ->action(function ($record) {
                    $record->update(['statut' => 'suspendu']);
                    Notification::make()->title('Menu Dépense suspendu')->warning()->send();
                    $this->refreshFormData(['statut']);
                }),

            Actions\Action::make('reactiver')
                ->label('Réactiver')
                ->icon('heroicon-o-play-circle')->color('success')
                ->visible(
                    fn($record) =>
                    $record->statut === 'suspendu'
                        && auth()->user()?->can('suspendre_menu_depense')
                )
                ->requiresConfirmation()
                ->action(function ($record) {
                    $record->update(['statut' => 'actif']);
                    Notification::make()->title('Menu Dépense réactivé')->success()->send();
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
                ->requiresConfirmation()
                ->form([
                    Forms\Components\DatePicker::make('date_cloture')
                        ->label('Date de clôture')->default(now())->required(),
                    Forms\Components\Textarea::make('observations')
                        ->label('Observations')->rows(2),
                ])
                ->action(function ($record, array $data) {
                    $record->update([
                        'statut'       => 'cloture',
                        'date_cloture' => $data['date_cloture'],
                        'observations' => ($record->observations ?? '')
                            . "\n\n--- CLÔTURÉ LE " . now()->format('d/m/Y') . " ---\n"
                            . ($data['observations'] ?? ''),
                    ]);
                    Notification::make()->title('✅ Menu Dépense clôturé')->success()->send();
                    $this->refreshFormData(['statut']);
                }),

            Actions\Action::make('reapprovisionner')
                ->label('Réapprovisionner')
                ->icon('heroicon-o-arrow-path')->color('primary')
                ->visible(
                    fn($record) =>
                    $record->statut === 'actif'
                        && auth()->user()?->can('reapprovisionner_menu_depense')
                )
                ->modalHeading('Réapprovisionner le Menu Dépense')
                ->form([
                    Forms\Components\Select::make('decision_administrative_id')
                        ->label('Nouvelle DA engagée')
                        ->options(function ($record) {
                            return \App\Models\DecisionAdministrative::where('statut', 'engagee')
                                ->get()
                                ->mapWithKeys(fn($da) => [
                                    $da->id => "{$da->numero} — {$da->objet} — "
                                        . number_format($da->montant_net, 0, ',', ' ')
                                        . " FCFA"
                                ]);
                        })
                        ->required()->searchable(),
                ])
                ->action(function ($record, array $data) {
                    $da = \App\Models\DecisionAdministrative::findOrFail(
                        $data['decision_administrative_id']
                    );
                    $record->reapprovisionner($da);
                    Notification::make()
                        ->title('✅ Menu Dépense réapprovisionné')
                        ->success()
                        ->body("+ " . number_format($da->montant_net, 0, ',', ' ') . " FCFA")
                        ->send();
                    $this->refreshFormData(['montant_alloue', 'montant_disponible']);
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([

            Infolists\Components\Section::make('Identification')
                ->schema([
                    Infolists\Components\TextEntry::make('numero')
                        ->label('N° MDE')->copyable()->weight('bold'),

                    Infolists\Components\TextEntry::make('libelle')
                        ->label('Désignation')->columnSpan(2),

                    Infolists\Components\TextEntry::make('statut')
                        ->label('Statut')->badge()
                        ->color(fn($state) => match ($state) {
                            'actif'    => 'success',
                            'suspendu' => 'warning',
                            'cloture'  => 'danger',
                            default    => 'gray',
                        })
                        ->formatStateUsing(fn($state) => match ($state) {
                            'actif'    => 'Actif',
                            'suspendu' => 'Suspendu',
                            'cloture'  => 'Clôturé',
                            default    => $state,
                        }),

                    Infolists\Components\TextEntry::make('responsable.name')
                        ->label('Responsable'),

                    Infolists\Components\TextEntry::make('exercice.annee')
                        ->label('Exercice')->badge()->color('info'),

                    Infolists\Components\TextEntry::make('date_creation')
                        ->label('Date de création')->date('d/m/Y'),
                ])
                ->columns(3),

            // ✅ Situation financière
            Infolists\Components\Section::make('Situation financière')
                ->schema([
                    Infolists\Components\TextEntry::make('montant_alloue')
                        ->label('💰 Montant total alloué')
                        ->formatStateUsing(
                            fn($state) =>
                            number_format((float) $state, 0, ',', ' ') . ' FCFA'
                        )
                        ->color('info')->weight('bold'),

                    Infolists\Components\TextEntry::make('montant_decaisse')
                        ->label('🏦 Total décaissé')
                        ->formatStateUsing(
                            fn($state) =>
                            number_format((float) $state, 0, ',', ' ') . ' FCFA'
                        )
                        ->color('warning'),

                    Infolists\Components\TextEntry::make('montant_depense')
                        ->label('💸 Total dépensé')
                        ->formatStateUsing(
                            fn($state) =>
                            number_format((float) $state, 0, ',', ' ') . ' FCFA'
                        )
                        ->color('danger'),

                    Infolists\Components\TextEntry::make('montant_disponible')
                        ->label('✅ Disponible')
                        ->formatStateUsing(
                            fn($state) =>
                            number_format((float) $state, 0, ',', ' ') . ' FCFA'
                        )
                        ->color(
                            fn($record) =>
                            $record->montant_disponible < 0 ? 'danger' : 'success'
                        )
                        ->weight('bold'),

                    Infolists\Components\TextEntry::make('taux_consommation')
                        ->label('📊 Taux consommation')
                        ->getStateUsing(
                            fn($record) =>
                            number_format($record->taux_consommation, 1) . '%'
                        )
                        ->badge()
                        ->color(fn($record) => match (true) {
                            $record->taux_consommation >= 90 => 'danger',
                            $record->taux_consommation >= 70 => 'warning',
                            default                          => 'success',
                        }),
                ])
                ->columns(4),

            // ✅ Décisions sources (NOUVEAU — spécifique Menu Dépense)
            Infolists\Components\Section::make('Décisions Administratives sources')
                ->description(
                    'Chaque décision correspond à une ligne d\'engagement '
                        . 'différente qui alimente ce Menu Dépense.'
                )
                ->schema([
                    Infolists\Components\RepeatableEntry::make('decisionsSource')
                        ->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make('decisionAdministrative.numero')
                                ->label('N° DA')
                                ->badge()->color('primary')
                                ->copyable(),

                            Infolists\Components\TextEntry::make('nomenclature.code')
                                ->label('Nomenclature')
                                ->badge()->color('gray'),

                            Infolists\Components\TextEntry::make('nomenclature.libelle')
                                ->label('Libellé nomenclature'),

                            Infolists\Components\TextEntry::make('montant_da')
                                ->label('Montant')
                                ->formatStateUsing(
                                    fn($state) =>
                                    number_format((float) $state, 0, ',', ' ') . ' FCFA'
                                )
                                ->color('success')->weight('bold'),

                            Infolists\Components\TextEntry::make('decisionAdministrative.statut')
                                ->label('Statut DA')
                                ->badge()
                                ->color(fn($state) => match ($state) {
                                    'engagee' => 'success',
                                    'validee' => 'warning',
                                    default   => 'gray',
                                }),
                        ])
                        ->columns(5)
                        ->columnSpanFull(),
                ])
                ->visible(fn($record) => $record->decisionsSource()->count() > 0)
                ->collapsible(),

            Infolists\Components\Section::make('Observations')
                ->schema([
                    Infolists\Components\TextEntry::make('observations')
                        ->label('')
                        ->placeholder('Aucune observation')
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->collapsed(),
        ]);
    }
}
