<?php

namespace App\Filament\Resources\BordereauEngagementResource\Pages;

use App\Filament\Resources\BordereauEngagementResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Forms;

class ViewBordereauEngagement extends ViewRecord
{
    protected static string $resource = BordereauEngagementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // ✅ Éditer (seulement en brouillon)
            Actions\EditAction::make()
                ->visible(fn($record) => $record->statut === 'brouillon'),

            // ✅ Gérer les engagements (ajouter/retirer)
            Actions\Action::make('gerer_engagements')
                ->label('Gérer les engagements')
                ->icon('heroicon-o-queue-list')
                ->color('info')
                ->visible(fn($record) => $record->statut === 'brouillon')
                ->modalHeading('Ajouter des engagements au bordereau')
                ->modalWidth('5xl')
                ->form([
                    Forms\Components\CheckboxList::make('engagements')
                        ->label('Sélectionnez les engagements à ajouter')
                        ->options(function ($record) {
                            return \App\Models\Engagement::query()
                                ->where('statut', 'provisoire')
                                ->where('exercice_id', $record->exercice_id)
                                ->whereDoesntHave('lignesBordereau')
                                ->get()
                                ->mapWithKeys(function ($engagement) {
                                    $beneficiaire = $engagement->beneficiaire;
                                    $nomBenef = $beneficiaire->raison_sociale
                                        ?? $beneficiaire->nom_complet
                                        ?? $beneficiaire->name
                                        ?? 'N/A';

                                    $label = $engagement->numero . ' - ' . $nomBenef . ' - ' .
                                        number_format($engagement->montant_engage, 0, ',', ' ') . ' FCFA';

                                    return [$engagement->id => $label];
                                });
                        })
                        ->columns(1)
                        ->searchable(),
                ])
                ->action(function ($record, array $data) {
                    try {
                        $ajoutees = 0;
                        foreach ($data['engagements'] ?? [] as $engagementId) {
                            $engagement = \App\Models\Engagement::find($engagementId);
                            if ($engagement) {
                                $record->ajouterEngagement($engagement);
                                $ajoutees++;
                            }
                        }

                        Notification::make()
                            ->title("{$ajoutees} engagement(s) ajouté(s)")
                            ->success()
                            ->send();

                        return redirect()->route('filament.admin.resources.bordereau-engagements.view', ['record' => $record]);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Erreur')
                            ->danger()
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            // ✅ Télécharger PDF
            Actions\Action::make('telecharger_pdf')
                ->label('Télécharger PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('primary')
                ->url(fn($record) => route('pdf.telecharger', [
                    'etat' => 'bordereau_engagement',
                    'id' => $record->id,
                ]))
                ->openUrlInNewTab(),

            // ✅ Afficher PDF
            Actions\Action::make('afficher_pdf')
                ->label('Afficher PDF')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn($record) => route('pdf.afficher', [
                    'etat' => 'bordereau_engagement',
                    'id' => $record->id,
                ]))
                ->openUrlInNewTab(),

            // ✅ TRANSMETTRE (visible seulement en brouillon)
            Actions\Action::make('transmettre')
                ->label('Transmettre')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->visible(function ($record) {
                    return $record->statut === 'brouillon'
                        && $record->nombre_engagements > 0;
                })
                ->requiresConfirmation()
                ->modalHeading('Transmettre le bordereau')
                ->modalDescription(fn($record) => "Vous êtes sur le point de transmettre ce bordereau contenant {$record->nombre_engagements} engagement(s) pour un montant total de " . number_format($record->montant_total, 0, ',', ' ') . " FCFA.")
                ->form([
                    Forms\Components\Select::make('destinataire_id')
                        ->label('Destinataire')
                        ->required()
                        ->searchable()
                        ->options(function () {
                            // ✅ CORRECTION : Utiliser get() puis pluck
                            return \App\Models\User::query()
                                ->whereHas('roles', function ($query) {
                                    $query->whereIn('name', ['controleur_financier', 'daaf', 'directeur_general']);
                                })
                                ->orderBy('name')
                                ->get()
                                ->pluck('name', 'id');
                        })
                        ->placeholder('Sélectionnez un destinataire')
                        ->helperText('Sélectionnez le contrôleur financier ou le responsable destinataire')
                        ->native(false), // ✅ Utiliser le select Filament au lieu du natif

                    Forms\Components\Textarea::make('observations')
                        ->label('Observations')
                        ->rows(3)
                        ->placeholder('Observations ou commentaires éventuels...'),
                ])
                ->action(function ($record, array $data) {
                    try {
                        $destinataire = \App\Models\User::findOrFail($data['destinataire_id']);

                        $record->transmettre(
                            auth()->user(),
                            $destinataire,
                            $data['observations'] ?? null
                        );

                        Notification::make()
                            ->title('Bordereau transmis avec succès')
                            ->success()
                            ->body("Le bordereau {$record->numero} a été transmis à {$destinataire->name}")
                            ->send();

                        return redirect()->route('filament.admin.resources.bordereau-engagements.view', ['record' => $record]);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Erreur lors de la transmission')
                            ->danger()
                            ->body($e->getMessage())
                            ->persistent()
                            ->send();
                    }
                }),

            // ✅ RÉCEPTIONNER
            Actions\Action::make('receptionner')
                ->label('Réceptionner')
                ->icon('heroicon-o-inbox-arrow-down')
                ->color('info')
                ->visible(fn($record) => $record->statut === 'transmis' && $record->detenu_par_id === auth()->id())
                ->requiresConfirmation()
                ->action(function ($record) {
                    try {
                        $record->receptionner(auth()->user());

                        Notification::make()
                            ->title('Bordereau réceptionné')
                            ->success()
                            ->send();

                        return redirect()->route('filament.admin.resources.bordereau-engagements.view', ['record' => $record]);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Erreur')
                            ->danger()
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            // ✅ VALIDER
            Actions\Action::make('valider')
                ->label('Valider')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn($record) => in_array($record->statut, ['en_cours', 'transmis']))
                ->requiresConfirmation()
                ->modalHeading('Valider le bordereau')
                ->modalDescription(fn($record) => "Confirmer la validation de ce bordereau contenant {$record->nombre_engagements} engagement(s) pour un montant total de " . number_format($record->montant_total, 0, ',', ' ') . " FCFA ?")
                ->action(function ($record) {
                    try {
                        $record->valider(auth()->user());

                        Notification::make()
                            ->title('Bordereau validé')
                            ->success()
                            ->body("Les {$record->nombre_engagements} engagements sont maintenant définitifs")
                            ->send();

                        return redirect()->route('filament.admin.resources.bordereau-engagements.view', ['record' => $record]);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Erreur')
                            ->danger()
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            // ✅ REJETER
            Actions\Action::make('rejeter')
                ->label('Rejeter')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn($record) => in_array($record->statut, ['en_cours', 'transmis']))
                ->form([
                    Forms\Components\Textarea::make('motif')
                        ->label('Motif du rejet')
                        ->required()
                        ->rows(3),
                ])
                ->action(function ($record, array $data) {
                    try {
                        $record->rejeter(auth()->user(), $data['motif']);

                        Notification::make()
                            ->title('Bordereau rejeté')
                            ->warning()
                            ->send();

                        return redirect()->route('filament.admin.resources.bordereau-engagements.view', ['record' => $record]);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Erreur')
                            ->danger()
                            ->body($e->getMessage())
                            ->send();
                    }
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Informations générales')
                    ->schema([
                        Infolists\Components\TextEntry::make('numero')
                            ->label('Numéro')
                            ->weight('bold')
                            ->copyable(),

                        Infolists\Components\TextEntry::make('date_emission')
                            ->label('Date d\'émission')
                            ->date('d/m/Y'),

                        Infolists\Components\TextEntry::make('exercice')
                            ->label('Exercice')
                            ->badge(),

                        Infolists\Components\TextEntry::make('statut')
                            ->label('Statut')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'brouillon' => 'gray',
                                'transmis' => 'info',
                                'en_cours' => 'warning',
                                'valide' => 'success',
                                'rejete_total', 'rejete_partiel' => 'danger',
                                'retourne' => 'warning',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn(string $state): string => str_replace('_', ' ', ucfirst($state))),

                        Infolists\Components\TextEntry::make('montant_total')
                            ->label('Montant total')
                            ->money('XAF')
                            ->weight('bold')
                            ->color('success'),

                        Infolists\Components\TextEntry::make('nombre_engagements')
                            ->label('Nombre d\'engagements')
                            ->badge()
                            ->color('primary'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Budget')
                    ->schema([
                        Infolists\Components\TextEntry::make('budget.libelle')
                            ->label('Budget'),

                        Infolists\Components\TextEntry::make('objet')
                            ->label('Objet')
                            ->columnSpanFull()
                            ->placeholder('Aucun objet défini'),
                    ])
                    ->columns(2),

                // ✅ CORRECTION : Section des engagements avec message conditionnel
                Infolists\Components\Section::make('Engagements')
                    ->schema([
                        // ✅ Afficher la liste si > 0 engagements
                        Infolists\Components\RepeatableEntry::make('lignes')
                            ->label('Liste des engagements')
                            ->schema([
                                Infolists\Components\TextEntry::make('numero_ligne')
                                    ->label('N°'),

                                Infolists\Components\TextEntry::make('engagement.numero')
                                    ->label('N° Engagement'),

                                Infolists\Components\TextEntry::make('beneficiaire')
                                    ->label('Bénéficiaire')
                                    ->getStateUsing(function ($record) {
                                        $beneficiaire = $record->engagement?->beneficiaire;
                                        return $beneficiaire?->raison_sociale
                                            ?? $beneficiaire?->nom_complet
                                            ?? $beneficiaire?->name
                                            ?? 'N/A';
                                    }),

                                Infolists\Components\TextEntry::make('engagement.objet')
                                    ->label('Objet')
                                    ->limit(50),

                                Infolists\Components\TextEntry::make('engagement.montant_engage')
                                    ->label('Montant')
                                    ->money('XAF'),

                                Infolists\Components\TextEntry::make('statut_ligne')
                                    ->label('Statut')
                                    ->badge()
                                    ->color(fn(string $state): string => match ($state) {
                                        'en_attente' => 'warning',
                                        'valide' => 'success',
                                        'rejete' => 'danger',
                                        default => 'gray',
                                    }),

                                Infolists\Components\TextEntry::make('observations')
                                    ->label('Observations')
                                    ->placeholder('Aucune'),
                            ])
                            ->columns(7)
                            ->visible(fn($record) => $record->nombre_engagements > 0),

                        // ✅ Afficher un message si 0 engagement
                        Infolists\Components\TextEntry::make('message_vide')
                            ->label('')
                            ->default('Aucun engagement ajouté à ce bordereau. Cliquez sur "Gérer les engagements" pour en ajouter.')
                            ->color('warning')
                            ->icon('heroicon-o-information-circle')
                            ->visible(fn($record) => $record->nombre_engagements == 0)
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Infolists\Components\Section::make('Suivi et validation')
                    ->schema([
                        Infolists\Components\TextEntry::make('emetteur.name')
                            ->label('Émis par'),

                        Infolists\Components\TextEntry::make('detenuPar.name')
                            ->label('Détenu par')
                            ->placeholder('N/A'),

                        Infolists\Components\TextEntry::make('validateur.name')
                            ->label('Validé par')
                            ->visible(fn($record) => $record->valide_par),

                        Infolists\Components\TextEntry::make('date_validation')
                            ->label('Date de validation')
                            ->dateTime('d/m/Y H:i')
                            ->visible(fn($record) => $record->date_validation),

                        Infolists\Components\TextEntry::make('motif_rejet')
                            ->label('Motif du rejet')
                            ->visible(fn($record) => $record->motif_rejet)
                            ->columnSpanFull()
                            ->color('danger'),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }
}
