<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\DossierFournisseurResource\Pages;
use App\Models\DossierFournisseur;
use App\Models\PieceDossier;
use App\Models\Fournisseur;
use App\Models\User;
use App\Services\DossierFournisseurService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Notifications\Notification;
use App\Filament\Forms\Components\ExerciceSelect;

class DossierFournisseurResource extends Resource
{
    protected static ?string $model = DossierFournisseur::class;
    protected static ?string $navigationIcon = 'heroicon-o-folder-open';
    protected static ?string $navigationLabel = 'Dossiers Fournisseurs';
    protected static ?string $modelLabel = 'Dossier Fournisseur';
    protected static ?string $pluralModelLabel = 'Dossiers Fournisseurs';
    protected static ?string $navigationGroup = 'Fournisseurs & Documents';
    protected static ?int $navigationSort = 2;

    // ════════════════════════════════════════════════════════
    // PERMISSIONS
    // ════════════════════════════════════════════════════════

    public static function canCreate(): bool
    {
        // ✅ Création automatique uniquement — pas de bouton "Créer"
        return false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_dossier_fournisseur') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->can('view_dossier_fournisseur') ?? false;
    }

    public static function canEdit($record): bool
    {
        // ✅ Éditer = ajouter des pièces manuelles uniquement
        return auth()->user()?->can('ajouter_piece_dossier') ?? false;
    }

    public static function canDelete($record): bool
    {
        return false; // Dossiers non supprimables manuellement
    }

    // ════════════════════════════════════════════════════════
    // FORM — Édition = ajout de pièces manuelles uniquement
    // ════════════════════════════════════════════════════════

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // ── Informations du dossier (lecture seule) ──
                Forms\Components\Section::make('Dossier')
                    ->schema([
                        Forms\Components\TextInput::make('numero_dossier')
                            ->label('N° Dossier')->disabled()->dehydrated(false),
                        Forms\Components\TextInput::make('reference_principale')
                            ->label('Référence')->disabled()->dehydrated(false),
                        Forms\Components\TextInput::make('objet')
                            ->label('Objet')->disabled()->dehydrated(false)->columnSpanFull(),
                        Forms\Components\Select::make('statut')
                            ->label('Statut')
                            ->options([
                                'ouvert'             => 'Ouvert',
                                'en_cours'           => 'En cours',
                                'attente_pieces'     => 'Attente pièces',
                                'attente_validation' => 'Attente validation',
                                'attente_paiement'   => 'Attente paiement',
                                'cloture'            => 'Clôturé',
                                'annule'             => 'Annulé',
                            ]),
                        Forms\Components\Textarea::make('observations')
                            ->label('Observations')->rows(2)->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->collapsible(),

                // ✅ AJOUT DE PIÈCES MANUELLES
                Forms\Components\Section::make('➕ Ajouter une pièce numérisée')
                    ->description('Factures proforma/définitives, PV de réception, contrats, ou tout autre document.')
                    ->schema([
                        Forms\Components\Repeater::make('nouvelles_pieces')
                            ->label('')
                            ->schema([
                                Forms\Components\Select::make('type_piece')
                                    ->label('Type de pièce')
                                    ->options([
                                        'facture_proforma'  => 'Facture Proforma',
                                        'facture_definitive' => 'Facture Définitive',
                                        'pv_reception'      => 'PV de Réception',
                                        'contrat'           => 'Contrat / Marché',
                                        'bon_livraison'     => 'Bon de Livraison',
                                        'autre'             => 'Autre document',
                                    ])
                                    ->required(),

                                Forms\Components\TextInput::make('libelle')
                                    ->label('Libellé / Description')
                                    ->required()
                                    ->placeholder('Ex: Facture N° 2025-001 du 15/06/2025'),

                                Forms\Components\FileUpload::make('fichier_upload')
                                    ->label('Fichier (PDF, image)')
                                    ->disk('public')
                                    ->directory('dossiers-fournisseurs')
                                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                                    ->maxSize(10240) // 10 MB
                                    ->columnSpanFull(),

                                Forms\Components\Textarea::make('observations')
                                    ->label('Observations')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->addActionLabel('+ Ajouter une pièce')
                            ->defaultItems(0)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    // ════════════════════════════════════════════════════════
    // TABLE — groupée par fournisseur chronologiquement
    // ════════════════════════════════════════════════════════

    public static function table(Table $table): Table
    {
        return $table
            ->persistFiltersInSession()
            ->columns([
                Tables\Columns\TextColumn::make('numero_dossier')
                    ->label('N° Dossier')->searchable()->sortable()->weight('bold')->copyable(),

                Tables\Columns\TextColumn::make('fournisseur.raison_sociale')
                    ->label('Fournisseur')->searchable()->sortable()->limit(30)
                    ->weight('bold')->color('primary'),

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
                    ->label('Objet')->limit(35)->searchable(),

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

            // ✅ GROUPEMENT PAR FOURNISSEUR — chronologique dans chaque groupe
            ->groups([
                Tables\Grouping\Group::make('fournisseur.raison_sociale')
                    ->label('Fournisseur')
                    ->collapsible()
                    ->titlePrefixedWithLabel(false),
            ])
            ->defaultGroup('fournisseur.raison_sociale')
            ->groupsInDropdownOnDesktop()

            ->filters([
                // ✅ Filtre période — Aujourd'hui par défaut
                Tables\Filters\Filter::make('periode')
                    ->form([
                        Forms\Components\Select::make('periode')
                            ->label('Période d\'ouverture')
                            ->options([
                                'today'        => 'Aujourd\'hui',
                                'yesterday'    => 'Hier',
                                'this_week'    => 'Cette semaine',
                                'last_week'    => 'Semaine dernière',
                                'this_month'   => 'Ce mois',
                                'last_month'   => 'Mois dernier',
                                'this_quarter' => 'Ce trimestre',
                                'last_quarter' => 'Trimestre dernier',
                                'this_year'    => 'Cette année',
                                'last_year'    => 'Année dernière',
                            ])
                            ->default('today')
                            ->placeholder('Toutes les périodes'),
                    ])
                    ->query(function ($query, array $data) {
                        $periode = $data['periode'] ?? 'today';
                        return match ($periode) {
                            'today'        => $query->whereDate('date_ouverture', today()),
                            'yesterday'    => $query->whereDate('date_ouverture', today()->subDay()),
                            'this_week'    => $query->whereBetween('date_ouverture', [now()->startOfWeek(), now()->endOfWeek()]),
                            'last_week'    => $query->whereBetween('date_ouverture', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()]),
                            'this_month'   => $query->whereMonth('date_ouverture', now()->month)->whereYear('date_ouverture', now()->year),
                            'last_month'   => $query->whereMonth('date_ouverture', now()->subMonth()->month)->whereYear('date_ouverture', now()->subMonth()->year),
                            'this_quarter' => $query->whereBetween('date_ouverture', [now()->startOfQuarter(), now()->endOfQuarter()]),
                            'last_quarter' => $query->whereBetween('date_ouverture', [now()->subQuarter()->startOfQuarter(), now()->subQuarter()->endOfQuarter()]),
                            'this_year'    => $query->whereYear('date_ouverture', now()->year),
                            'last_year'    => $query->whereYear('date_ouverture', now()->subYear()->year),
                            default        => $query,
                        };
                    })
                    ->indicateUsing(function (array $data): ?string {
                        $labels = [
                            'today'        => 'Aujourd\'hui',
                            'yesterday'    => 'Hier',
                            'this_week'    => 'Cette semaine',
                            'last_week'    => 'Semaine dernière',
                            'this_month'   => 'Ce mois',
                            'last_month'   => 'Mois dernier',
                            'this_quarter' => 'Ce trimestre',
                            'last_quarter' => 'Trimestre dernier',
                            'this_year'    => 'Cette année',
                            'last_year'    => 'Année dernière',
                        ];
                        $p = $data['periode'] ?? 'today';
                        return 'Période : ' . ($labels[$p] ?? $p);
                    }),

                Tables\Filters\SelectFilter::make('fournisseur_id')
                    ->label('Fournisseur')
                    ->relationship('fournisseur', 'raison_sociale')
                    ->searchable()->preload(),

                Tables\Filters\SelectFilter::make('type_dossier')
                    ->label('Type')
                    ->options([
                        'bon_commande'            => 'Bon de Commande',
                        'decision_administrative' => 'Décision Administrative',
                        'marche'                  => 'Marché',
                        'prestation'              => 'Prestation',
                        'autre'                   => 'Autre',
                    ]),

                Tables\Filters\SelectFilter::make('statut')
                    ->label('Statut')
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
            ])

            ->actions([
                Tables\Actions\ActionGroup::make([

                    Tables\Actions\ViewAction::make(),

                    // ✅ Éditer = ajouter des pièces manuelles
                    Tables\Actions\EditAction::make()
                        ->label('Ajouter des pièces')
                        ->icon('heroicon-o-paper-clip'),

                    // ── Changer statut ────────────────────────────
                    Tables\Actions\Action::make('changer_statut')
                        ->label('Changer le statut')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->form([
                            Forms\Components\Select::make('statut')
                                ->label('Nouveau statut')
                                ->options([
                                    'ouvert'             => 'Ouvert',
                                    'en_cours'           => 'En cours',
                                    'attente_pieces'     => 'Attente pièces',
                                    'attente_validation' => 'Attente validation',
                                    'attente_paiement'   => 'Attente paiement',
                                    'cloture'            => 'Clôturé',
                                    'annule'             => 'Annulé',
                                ])
                                ->required(),
                            Forms\Components\Textarea::make('observations')
                                ->label('Motif / Observations')->rows(2),
                        ])
                        ->action(function ($record, array $data) {
                            $update = ['statut' => $data['statut']];
                            if (!empty($data['observations'])) {
                                $update['observations'] = ($record->observations ? $record->observations . "\n\n" : '')
                                    . now()->format('d/m/Y H:i') . ' — ' . $data['observations'];
                            }
                            if ($data['statut'] === 'cloture') {
                                $update['date_cloture'] = now();
                            }
                            $record->update($update);
                            Notification::make()
                                ->title('Statut mis à jour → ' . $data['statut'])
                                ->success()->send();
                        }),

                    // ── Clôturer ──────────────────────────────────
                    Tables\Actions\Action::make('cloturer')
                        ->label('Clôturer le dossier')
                        ->icon('heroicon-o-lock-closed')
                        ->color('success')
                        ->visible(fn($record) => !in_array($record->statut, ['cloture', 'annule']))
                        ->requiresConfirmation()
                        ->modalHeading('Clôturer ce dossier fournisseur')
                        ->modalDescription('Cette action marque le dossier comme clôturé. Le dossier restera consultable.')
                        ->action(function ($record) {
                            $record->update([
                                'statut'       => 'cloture',
                                'date_cloture' => now(),
                            ]);
                            Notification::make()
                                ->title('✅ Dossier clôturé')
                                ->success()->send();
                        }),

                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')
                    ->button()
                    ->size('sm'),
            ], position: ActionsPosition::BeforeColumns)

            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([]),
            ])
            ->defaultSort('fournisseur_id', 'asc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListDossierFournisseurs::route('/'),
            'view'   => Pages\ViewDossierFournisseur::route('/{record}'),
            'edit'   => Pages\EditDossierFournisseur::route('/{record}/edit'),
        ];
    }
}
