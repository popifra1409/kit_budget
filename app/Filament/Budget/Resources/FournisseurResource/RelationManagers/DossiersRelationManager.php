<?php

namespace App\Filament\Budget\Resources\FournisseurResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Notifications\Notification;
use App\Services\DossierFournisseurService;

class DossiersRelationManager extends RelationManager
{
    protected static string $relationship = 'dossiers';
    protected static ?string $title       = 'Dossiers Fournisseur';
    protected static ?string $icon        = 'heroicon-o-folder-open';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('numero_dossier')
            ->columns([
                Tables\Columns\TextColumn::make('numero_dossier')
                    ->label('N° Dossier')
                    ->weight('bold')->copyable()->searchable()->sortable(),

                Tables\Columns\BadgeColumn::make('type_dossier')
                    ->label('Type')
                    ->formatStateUsing(fn($state) => match ($state) {
                        'bon_commande'            => 'BC',
                        'decision_administrative' => 'DA',
                        'marche'                  => 'Marché',
                        'prestation'              => 'Prestation',
                        default                   => 'Autre',
                    })
                    ->colors([
                        'primary' => 'bon_commande',
                        'warning' => 'decision_administrative',
                        'success' => 'marche',
                        'info'    => 'prestation',
                        'gray'    => 'autre',
                    ]),

                Tables\Columns\TextColumn::make('reference_principale')
                    ->label('Référence')->searchable(),

                Tables\Columns\TextColumn::make('objet')
                    ->label('Objet')->limit(30),

                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
                    ->formatStateUsing(fn($state) => match ($state) {
                        'ouvert'             => 'Ouvert',
                        'en_cours'           => 'En cours',
                        'attente_pieces'     => 'Attente pièces',
                        'attente_validation' => 'Attente validation',
                        'attente_paiement'   => 'Attente paiement',
                        'cloture'            => '✅ Clôturé',
                        'annule'             => 'Annulé',
                        default              => $state,
                    })
                    ->colors([
                        'success' => 'cloture',
                        'warning' => fn($state) => in_array($state, ['attente_pieces', 'attente_validation', 'attente_paiement']),
                        'info'    => 'en_cours',
                        'primary' => 'ouvert',
                        'danger'  => 'annule',
                    ]),

                Tables\Columns\TextColumn::make('montant_total')
                    ->label('Montant')->money('XAF')->sortable(),

                Tables\Columns\TextColumn::make('montant_paye')
                    ->label('Payé')->money('XAF')->color('success')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('pieces_count')
                    ->label('Pièces')
                    ->counts('pieces')
                    ->badge()->color('info'),

                Tables\Columns\TextColumn::make('date_ouverture')
                    ->label('Ouvert le')->date('d/m/Y')->sortable(),
            ])
            ->defaultSort('date_ouverture', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('statut')
                    ->options([
                        'ouvert'             => 'Ouvert',
                        'en_cours'           => 'En cours',
                        'attente_pieces'     => 'Attente pièces',
                        'attente_validation' => 'Attente validation',
                        'attente_paiement'   => 'Attente paiement',
                        'cloture'            => 'Clôturé',
                        'annule'             => 'Annulé',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('type_dossier')
                    ->label('Type')
                    ->options([
                        'bon_commande'            => 'Bon de Commande',
                        'decision_administrative' => 'Décision Administrative',
                        'marche'                  => 'Marché',
                        'prestation'              => 'Prestation',
                        'autre'                   => 'Autre',
                    ]),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    // ── Voir le dossier complet ───────────────────
                    Tables\Actions\Action::make('voir_dossier')
                        ->label('Voir le dossier')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->url(fn($record) => route(
                            'filament.budget.resources.dossier-fournisseurs.view',
                            ['record' => $record->id]
                        ))
                        ->openUrlInNewTab(),

                    // ── Ajouter une pièce ─────────────────────────
                    Tables\Actions\Action::make('ajouter_piece')
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
                                ->placeholder('Ex: Facture N° 2025-001'),

                            Forms\Components\FileUpload::make('fichier_upload')
                                ->label('Fichier (PDF, image)')
                                ->disk('public')
                                ->directory('dossiers-fournisseurs')
                                ->maxSize(10240)
                                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                                ->columnSpanFull(),

                            Forms\Components\Textarea::make('observations')
                                ->label('Observations')->rows(2)->columnSpanFull(),
                        ])
                        ->action(function ($record, array $data) {
                            \App\Models\PieceDossier::create([
                                'dossier_fournisseur_id' => $record->id,
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
                            Notification::make()->title('✅ Pièce ajoutée')->success()->send();
                        }),

                    // ── Clôturer ──────────────────────────────────
                    Tables\Actions\Action::make('cloturer')
                        ->label('Clôturer')
                        ->icon('heroicon-o-lock-closed')
                        ->color('success')
                        ->visible(fn($record) => !in_array($record->statut, ['cloture', 'annule']))
                        ->requiresConfirmation()
                        ->action(function ($record) {
                            $record->update([
                                'statut'       => 'cloture',
                                'date_cloture' => now(),
                            ]);
                            Notification::make()->title('✅ Dossier clôturé')->success()->send();
                        }),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')
                    ->button()
                    ->size('sm'),
            ], position: ActionsPosition::BeforeColumns)
            ->headerActions([])
            ->bulkActions([]);
    }

    public function form(Form $form): Form
    {
        // ✅ Pas de création manuelle — les dossiers sont créés automatiquement
        return $form->schema([]);
    }
}
