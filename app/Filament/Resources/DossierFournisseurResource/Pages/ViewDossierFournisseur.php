<?php

namespace App\Filament\Resources\DossierFournisseurResource\Pages;

use App\Filament\Resources\DossierFournisseurResource;
use App\Models\PieceDossier;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;

class ViewDossierFournisseur extends ViewRecord
{
    protected static string $resource = DossierFournisseurResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // Informations générales
                Infolists\Components\Section::make('Informations du dossier')
                    ->schema([
                        Infolists\Components\TextEntry::make('numero_dossier')
                            ->label('N° Dossier')
                            ->badge()
                            ->color('primary')
                            ->copyable(),

                        Infolists\Components\TextEntry::make('fournisseur.raison_sociale')
                            ->label('Fournisseur')
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('type_dossier_label')
                            ->label('Type de dossier')
                            ->badge(),

                        Infolists\Components\TextEntry::make('reference_principale')
                            ->label('Référence principale'),

                        Infolists\Components\TextEntry::make('statut_label')
                            ->label('Statut')
                            ->badge()
                            ->color(fn($record) => $record->statut_color),

                        Infolists\Components\TextEntry::make('objet')
                            ->label('Objet')
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('description')
                            ->label('Description')
                            ->columnSpanFull()
                            ->visible(fn($record) => $record->description),
                    ])
                    ->columns(3),

                // Montants
                Infolists\Components\Section::make('Suivi financier')
                    ->schema([
                        Infolists\Components\TextEntry::make('montant_total')
                            ->label('Montant total')
                            ->money('XAF')
                            ->color('primary')
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('montant_engage')
                            ->label('Montant engagé')
                            ->money('XAF')
                            ->color('info'),

                        Infolists\Components\TextEntry::make('montant_facture')
                            ->label('Montant facturé')
                            ->money('XAF')
                            ->color('warning'),

                        Infolists\Components\TextEntry::make('montant_paye')
                            ->label('Montant payé')
                            ->money('XAF')
                            ->color('success'),

                        Infolists\Components\TextEntry::make('montant_reste')
                            ->label('Reste à payer')
                            ->money('XAF')
                            ->color('danger')
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('taux_realisation')
                            ->label('Taux de réalisation')
                            ->suffix('%')
                            ->badge()
                            ->color(fn($state) => match (true) {
                                $state >= 100 => 'success',
                                $state >= 50 => 'warning',
                                default => 'danger',
                            }),
                    ])
                    ->columns(3),

                // Dates et responsabilité
                Infolists\Components\Section::make('Dates et responsabilité')
                    ->schema([
                        Infolists\Components\TextEntry::make('date_ouverture')
                            ->label('Date d\'ouverture')
                            ->date('d/m/Y'),

                        Infolists\Components\TextEntry::make('date_limite_livraison')
                            ->label('Date limite livraison')
                            ->date('d/m/Y')
                            ->color(fn($record) => $record->est_en_retard ? 'danger' : 'gray')
                            ->icon(fn($record) => $record->est_en_retard ? 'heroicon-o-exclamation-triangle' : null),

                        Infolists\Components\TextEntry::make('date_cloture')
                            ->label('Date de clôture')
                            ->date('d/m/Y')
                            ->visible(fn($record) => $record->date_cloture),

                        Infolists\Components\TextEntry::make('jours_depuis_ouverture')
                            ->label('Durée')
                            ->suffix(' jours'),

                        Infolists\Components\TextEntry::make('responsable.name')
                            ->label('Responsable')
                            ->icon('heroicon-o-user'),

                        Infolists\Components\TextEntry::make('createur.name')
                            ->label('Créé par')
                            ->icon('heroicon-o-user'),
                    ])
                    ->columns(3),

                // Pièces du dossier
                Infolists\Components\Section::make('Pièces du dossier')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('pieces')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('type_piece_label')
                                    ->label('Type'),

                                Infolists\Components\TextEntry::make('nom_fichier')
                                    ->label('Fichier')
                                    ->icon(fn($record) => $record->icon)
                                    ->color(fn($record) => $record->color),

                                Infolists\Components\TextEntry::make('taille_formatee')
                                    ->label('Taille'),

                                Infolists\Components\IconEntry::make('valide')
                                    ->label('Validé')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-check-circle')
                                    ->falseIcon('heroicon-o-x-circle')
                                    ->trueColor('success')
                                    ->falseColor('gray'),

                                Infolists\Components\TextEntry::make('ajoutePar.name')
                                    ->label('Ajouté par'),

                                Infolists\Components\TextEntry::make('date_ajout')
                                    ->label('Date')
                                    ->since(),
                            ])
                            ->columns(6),
                    ])
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
            Actions\EditAction::make(),

            // Ajouter une pièce
            Actions\Action::make('ajouter_piece')
                ->label('Ajouter une pièce')
                ->icon('heroicon-o-paper-clip')
                ->color('success')
                ->form([
                    Forms\Components\Select::make('type_piece')
                        ->label('Type de pièce')
                        ->options([
                            'facture_proforma' => 'Facture Proforma',
                            'facture_definitive' => 'Facture Définitive',
                            'bordereau_livraison' => 'Bordereau de Livraison',
                            'pv_reception' => 'PV de Réception',
                            'certificat_service_fait' => 'Certificat Service Fait',
                            'ordre_paiement' => 'Ordre de Paiement',
                            'justificatif_paiement' => 'Justificatif de Paiement',
                            'piece_comptable' => 'Pièce Comptable',
                            'autre_document' => 'Autre Document',
                        ])
                        ->required()
                        ->live(),

                    Forms\Components\FileUpload::make('fichier')
                        ->label('Fichier')
                        ->required()
                        ->disk('public')
                        ->directory('dossiers-fournisseurs')
                        ->maxSize(10240) // 10 MB
                        ->storeFileNamesIn('nom_fichier_original')
                        ->acceptedFileTypes([
                            'application/pdf',
                            'image/*',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                        ]),

                    Forms\Components\Hidden::make('nom_fichier_original'),

                    // Ajouter un champ montant pour les factures et paiements
                    Forms\Components\TextInput::make('montant')
                        ->label('Montant')
                        ->numeric()
                        ->prefix('FCFA')
                        ->visible(fn(Forms\Get $get) => in_array($get('type_piece'), ['facture_proforma', 'facture_definitive', 'justificatif_paiement']))
                        ->helperText('Le montant sera mis à jour dans le dossier'),

                    Forms\Components\Textarea::make('commentaire')
                        ->label('Commentaire')
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    try {
                        // Filament retourne le chemin du fichier déjà stocké
                        $cheminFichier = $data['fichier'];
                        $nomFichierOriginal = $data['nom_fichier_original'] ?? basename($cheminFichier);

                        // Obtenir les informations du fichier
                        $cheminComplet = storage_path('app/public/' . $cheminFichier);
                        $typeMime = mime_content_type($cheminComplet);
                        $taille = filesize($cheminComplet);

                        $metadata = [];
                        if (isset($data['montant'])) {
                            $metadata['montant'] = $data['montant'];
                        }

                        $this->record->ajouterPiece([
                            'type_piece' => $data['type_piece'],
                            'nom_fichier' => $nomFichierOriginal,
                            'chemin_fichier' => $cheminFichier,
                            'type_mime' => $typeMime,
                            'taille' => $taille,
                            'commentaire' => $data['commentaire'] ?? null,
                            'metadata' => $metadata,
                        ]);

                        Notification::make()
                            ->title('Pièce ajoutée')
                            ->success()
                            ->body('La pièce a été ajoutée au dossier')
                            ->send();

                        // Recharger pour voir la nouvelle pièce
                        return redirect()->to(static::getUrl(['record' => $this->record]));
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Erreur')
                            ->danger()
                            ->body($e->getMessage())
                            ->send();
                    }
                }),
            // NOUVELLE ACTION : Créer les Ordonnances de Paiement
            Actions\Action::make('creer_ordonnances')
                ->label('Créer les OP')
                ->icon('heroicon-o-banknotes')
                ->color('primary')
                ->visible(fn($record) => !$record->hasOrdonnancesPaiement() && $record->statut !== 'cloture')
                ->requiresConfirmation()
                ->modalHeading('Créer les Ordonnances de Paiement')
                ->modalDescription('Cela va créer automatiquement l\'OP Standard (fournisseur) et l\'OP Impôt (si applicable). Les OP seront ajoutées au dossier.')
                ->action(function () {
                    try {
                        $ordonnances = $this->record->creerOrdonnancesPaiement();

                        $message = "OP créées : ";
                        if (isset($ordonnances['standard'])) {
                            $message .= "Standard ({$ordonnances['standard']->numero})";
                        }
                        if (isset($ordonnances['impot'])) {
                            $message .= ", Impôt ({$ordonnances['impot']->numero})";
                        }

                        Notification::make()
                            ->title('Ordonnances créées avec succès')
                            ->success()
                            ->body($message)
                            ->send();

                        return redirect()->to(static::getUrl(['record' => $this->record]));
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Erreur')
                            ->danger()
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            // NOUVELLE ACTION : Voir les Ordonnances de Paiement
            Actions\Action::make('voir_ordonnances')
                ->label('Voir les OP')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->visible(fn($record) => $record->hasOrdonnancesPaiement())
                ->url(function () {
                    $engagement = $this->record->getEngagementPrincipal();
                    return $engagement
                        ? route('filament.admin.resources.ordonnance-paiements.index') . '?tableFilters[engagement_id][value]=' . $engagement->id
                        : route('filament.admin.resources.ordonnance-paiements.index');
                }),

            // Clôturer le dossier
            Actions\Action::make('cloturer')
                ->label('Clôturer')
                ->icon('heroicon-o-lock-closed')
                ->color('success')
                ->visible(fn($record) => $record->statut !== 'cloture')
                ->requiresConfirmation()
                ->form([
                    Forms\Components\Textarea::make('motif')
                        ->label('Motif de clôture')
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    $this->record->cloturer($data['motif'] ?? null);

                    Notification::make()
                        ->title('Dossier clôturé')
                        ->success()
                        ->send();
                }),

            Actions\DeleteAction::make(),
        ];
    }
}
