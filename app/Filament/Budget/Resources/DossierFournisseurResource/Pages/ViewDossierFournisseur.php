<?php

namespace App\Filament\Budget\Resources\DossierFournisseurResource\Pages;

use App\Filament\Budget\Resources\DossierFournisseurResource;
use App\Models\PieceDossier;
use App\Services\DossierFournisseurService;
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
                // ── Informations générales ─────────────────────────
                Infolists\Components\Section::make('Informations du dossier')
                    ->schema([
                        Infolists\Components\TextEntry::make('numero_dossier')
                            ->label('N° Dossier')
                            ->badge()->color('primary')->copyable(),

                        Infolists\Components\TextEntry::make('fournisseur.raison_sociale')
                            ->label('Fournisseur')->weight('bold'),

                        Infolists\Components\TextEntry::make('type_dossier_label')
                            ->label('Type de dossier')->badge(),

                        Infolists\Components\TextEntry::make('reference_principale')
                            ->label('Référence principale'),

                        Infolists\Components\TextEntry::make('statut_label')
                            ->label('Statut')->badge()
                            ->color(fn($record) => $record->statut_color),

                        Infolists\Components\TextEntry::make('objet')
                            ->label('Objet')->columnSpanFull(),

                        Infolists\Components\TextEntry::make('description')
                            ->label('Description')->columnSpanFull()
                            ->visible(fn($record) => $record->description),
                    ])
                    ->columns(3),

                // ── Suivi financier ────────────────────────────────
                Infolists\Components\Section::make('Suivi financier')
                    ->schema([
                        Infolists\Components\TextEntry::make('montant_total')
                            ->label('Montant total')->money('XAF')->color('primary')->weight('bold'),

                        Infolists\Components\TextEntry::make('montant_engage')
                            ->label('Montant engagé')->money('XAF')->color('info'),

                        Infolists\Components\TextEntry::make('montant_facture')
                            ->label('Montant facturé')->money('XAF')->color('warning'),

                        Infolists\Components\TextEntry::make('montant_paye')
                            ->label('Montant payé')->money('XAF')->color('success'),

                        Infolists\Components\TextEntry::make('montant_reste')
                            ->label('Reste à payer')->money('XAF')->color('danger')->weight('bold'),

                        Infolists\Components\TextEntry::make('taux_realisation')
                            ->label('Taux de réalisation')->suffix('%')->badge()
                            ->color(fn($state) => match (true) {
                                $state >= 100 => 'success',
                                $state >= 50  => 'warning',
                                default       => 'danger',
                            }),
                    ])
                    ->columns(3),

                // ── Dates et responsabilité ────────────────────────
                Infolists\Components\Section::make('Dates et responsabilité')
                    ->schema([
                        Infolists\Components\TextEntry::make('date_ouverture')
                            ->label('Date d\'ouverture')->date('d/m/Y'),

                        Infolists\Components\TextEntry::make('date_limite_livraison')
                            ->label('Date limite livraison')->date('d/m/Y')
                            ->color(fn($record) => $record->est_en_retard ? 'danger' : 'gray')
                            ->icon(fn($record) => $record->est_en_retard ? 'heroicon-o-exclamation-triangle' : null),

                        Infolists\Components\TextEntry::make('date_cloture')
                            ->label('Date de clôture')->date('d/m/Y')
                            ->visible(fn($record) => $record->date_cloture),

                        Infolists\Components\TextEntry::make('jours_depuis_ouverture')
                            ->label('Durée')->suffix(' jours'),

                        Infolists\Components\TextEntry::make('responsable.name')
                            ->label('Responsable')->icon('heroicon-o-user'),

                        Infolists\Components\TextEntry::make('createur.name')
                            ->label('Créé par')->icon('heroicon-o-user'),
                    ])
                    ->columns(3),

                // ── Pièces du dossier ─────────────────────────────
                Infolists\Components\Section::make('Pièces du dossier')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('pieces')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('source')
                                    ->label('Source')
                                    ->badge()
                                    ->color(fn($state) => $state === 'automatique' ? 'info' : 'success')
                                    ->formatStateUsing(fn($state) => $state === 'automatique' ? '🤖 Auto' : '📎 Manuel'),

                                Infolists\Components\TextEntry::make('type_piece')
                                    ->label('Type')
                                    ->formatStateUsing(fn($state) => DossierFournisseurService::TYPES[$state] ?? $state),

                                Infolists\Components\TextEntry::make('libelle')
                                    ->label('Libellé / Fichier')
                                    ->getStateUsing(
                                        fn($record) =>
                                        $record->libelle
                                            ?? $record->nom_fichier
                                            ?? '—'
                                    ),

                                Infolists\Components\TextEntry::make('observations')
                                    ->label('Observations')
                                    ->placeholder('—')
                                    ->limit(40),

                                Infolists\Components\IconEntry::make('valide')
                                    ->label('Validé')->boolean()
                                    ->trueIcon('heroicon-o-check-circle')
                                    ->falseIcon('heroicon-o-clock')
                                    ->trueColor('success')->falseColor('gray'),

                                // ✅ Lien téléchargement si fichier uploadé
                                Infolists\Components\TextEntry::make('fichier_upload')
                                    ->label('Télécharger')
                                    ->getStateUsing(
                                        fn($record) => $record->fichier_upload
                                            ? asset('storage/' . $record->fichier_upload)
                                            : null
                                    )
                                    ->url(fn($state) => $state)
                                    ->openUrlInNewTab()
                                    ->placeholder('—')
                                    ->formatStateUsing(fn($state) => $state ? '📥 Ouvrir' : '—'),
                            ])
                            ->columns(6),
                    ])
                    ->collapsible(),

                // ── Observations ───────────────────────────────────
                Infolists\Components\Section::make('Observations')
                    ->schema([
                        Infolists\Components\TextEntry::make('observations')
                            ->label('')->columnSpanFull()->placeholder('Aucune observation'),
                    ])
                    ->collapsible()->collapsed()
                    ->visible(fn($record) => $record->observations),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            // ── Ajouter une pièce numérisée ───────────────────────
            Actions\Action::make('ajouter_piece')
                ->label('Ajouter une pièce')
                ->icon('heroicon-o-paper-clip')
                ->color('success')
                ->form([
                    Forms\Components\Select::make('type_piece')
                        ->label('Type de pièce')
                        ->options([
                            'facture_proforma'        => 'Facture Proforma',
                            'facture_definitive'      => 'Facture Définitive',
                            'bon_livraison'           => 'Bon de Livraison',
                            'pv_reception'            => 'PV de Réception',
                            'certificat_service_fait' => 'Certificat Service Fait',
                            'justificatif_paiement'   => 'Justificatif de Paiement',
                            'contrat'                 => 'Contrat / Marché',
                            'autre'                   => 'Autre Document',
                        ])
                        ->required()->live(),

                    Forms\Components\TextInput::make('libelle')
                        ->label('Libellé / Description')
                        ->required()
                        ->placeholder('Ex: Facture N° 2025-001 du 15/06/2025'),

                    Forms\Components\FileUpload::make('fichier_upload')
                        ->label('Fichier (PDF, image, Word, Excel)')
                        ->disk('public')
                        ->directory('dossiers-fournisseurs')
                        ->maxSize(10240)
                        ->acceptedFileTypes([
                            'application/pdf',
                            'image/jpeg',
                            'image/png',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->columnSpanFull(),

                    // ✅ Montant — visible uniquement pour les factures
                    Forms\Components\TextInput::make('montant')
                        ->label('Montant (FCFA)')
                        ->numeric()->prefix('FCFA')
                        ->visible(fn(Forms\Get $get) => in_array($get('type_piece'), [
                            'facture_proforma',
                            'facture_definitive',
                            'justificatif_paiement',
                        ]))
                        ->helperText('Met à jour le montant facturé du dossier'),

                    Forms\Components\Textarea::make('observations')
                        ->label('Observations')->rows(2)->columnSpanFull(),
                ])
                ->action(function (array $data) {
                    try {
                        PieceDossier::create([
                            'dossier_fournisseur_id' => $this->record->id,
                            'type_piece'             => $data['type_piece'],
                            'source'                 => 'manuelle',
                            'libelle'                => $data['libelle'],
                            'fichier_upload'         => $data['fichier_upload'] ?? null,
                            'nom_fichier'            => $data['fichier_upload'] ?? $data['libelle'],
                            'chemin_fichier'         => $data['fichier_upload'] ?? '',
                            'observations'           => $data['observations'] ?? null,
                            'valide'                 => false,
                            'document_type'          => null,
                            'document_id'            => null,
                        ]);

                        // ✅ Mettre à jour montant_facture si facture
                        if (!empty($data['montant']) && in_array($data['type_piece'], ['facture_proforma', 'facture_definitive'])) {
                            $this->record->increment('montant_facture', (float) $data['montant']);
                        }

                        Notification::make()
                            ->title('✅ Pièce ajoutée au dossier')
                            ->success()->send();

                        $this->refreshFormData(['pieces']);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('❌ Erreur')->danger()->body($e->getMessage())->send();
                    }
                }),

            // ── Voir les OP liées ─────────────────────────────────
            Actions\Action::make('voir_ordonnances')
                ->label('Voir les OP')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->visible(
                    fn($record) => $record->pieces()
                        ->whereIn('type_piece', ['ordonnance_paiement', 'ordonnance_impot'])
                        ->exists()
                )
                ->url(
                    fn() =>
                    route('filament.budget.resources.ordonnance-paiements.index')
                ),

            // ── Changer le statut ──────────────────────────────────
            Actions\Action::make('changer_statut')
                ->label('Changer le statut')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn($record) => !in_array($record->statut, ['cloture', 'annule']))
                ->form([
                    Forms\Components\Select::make('statut')
                        ->label('Nouveau statut')
                        ->options([
                            'ouvert'             => 'Ouvert',
                            'en_cours'           => 'En cours',
                            'attente_pieces'     => 'Attente pièces',
                            'attente_validation' => 'Attente validation',
                            'attente_paiement'   => 'Attente paiement',
                        ])
                        ->required(),
                    Forms\Components\Textarea::make('observations')
                        ->label('Motif / Observations')->rows(2),
                ])
                ->action(function (array $data) {
                    $update = ['statut' => $data['statut']];
                    if (!empty($data['observations'])) {
                        $update['observations'] = ($this->record->observations
                            ? $this->record->observations . "\n\n" : '')
                            . now()->format('d/m/Y H:i') . ' — ' . $data['observations'];
                    }
                    $this->record->update($update);
                    Notification::make()->title('Statut mis à jour')->success()->send();
                    $this->refreshFormData(['statut', 'observations']);
                }),

            // ── Clôturer ──────────────────────────────────────────
            Actions\Action::make('cloturer')
                ->label('Clôturer')
                ->icon('heroicon-o-lock-closed')
                ->color('success')
                ->visible(fn($record) => $record->statut !== 'cloture')
                ->requiresConfirmation()
                ->modalHeading('Clôturer ce dossier fournisseur')
                ->modalDescription('Le dossier sera marqué comme clôturé. Il restera consultable.')
                ->form([
                    Forms\Components\Textarea::make('motif')
                        ->label('Motif de clôture (optionnel)')->rows(3),
                ])
                ->action(function (array $data) {
                    $observations = ($this->record->observations
                        ? $this->record->observations . "\n\n" : '')
                        . now()->format('d/m/Y H:i') . ' — CLÔTURÉ'
                        . (!empty($data['motif']) ? ' : ' . $data['motif'] : '');

                    $this->record->update([
                        'statut'       => 'cloture',
                        'date_cloture' => now(),
                        'observations' => $observations,
                    ]);

                    Notification::make()->title('✅ Dossier clôturé')->success()->send();
                }),
        ];
    }
}
