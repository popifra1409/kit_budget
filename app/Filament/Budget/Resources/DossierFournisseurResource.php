<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\DossierFournisseurResource\Pages;
use App\Models\DossierFournisseur;
use App\Models\Fournisseur;
use App\Models\User;
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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Exercice')
                    ->schema([ExerciceSelect::make()])
                    ->collapsible()
                    ->collapsed(fn($record) => $record !== null),

                Forms\Components\Section::make('Informations du dossier')
                    ->schema([
                        Forms\Components\Select::make('fournisseur_id')
                            ->label('Fournisseur')
                            ->options(Fournisseur::pluck('raison_sociale', 'id'))
                            ->required()->searchable()->preload()->live()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('raison_sociale')->required()->maxLength(255),
                                Forms\Components\TextInput::make('email')->email()->maxLength(255),
                                Forms\Components\TextInput::make('telephone')->tel()->maxLength(255),
                            ]),

                        Forms\Components\Select::make('type_dossier')
                            ->label('Type de dossier')
                            ->options([
                                'bon_commande'            => 'Bon de Commande',
                                'marche'                  => 'Marché',
                                'decision_administrative' => 'Décision Administrative',
                                'prestation'              => 'Prestation',
                                'autre'                   => 'Autre',
                            ])
                            ->required()->default('bon_commande'),

                        Forms\Components\TextInput::make('numero_dossier')
                            ->label('N° Dossier')->disabled()->dehydrated(false)
                            ->placeholder('Généré automatiquement')
                            ->visible(fn($record) => $record === null),

                        Forms\Components\TextInput::make('reference_principale')
                            ->label('Référence principale')
                            ->placeholder('Ex: BC-2025-001')->maxLength(255),

                        Forms\Components\TextInput::make('objet')
                            ->label('Objet')->required()->maxLength(255)->columnSpanFull(),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')->rows(3)->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Montants')
                    ->schema([
                        Forms\Components\TextInput::make('montant_total')
                            ->label('Montant total')->numeric()->prefix('FCFA')->required()->default(0),
                        Forms\Components\TextInput::make('montant_engage')
                            ->label('Montant engagé')->numeric()->prefix('FCFA')->default(0)->disabled()->dehydrated(),
                        Forms\Components\TextInput::make('montant_facture')
                            ->label('Montant facturé')->numeric()->prefix('FCFA')->default(0)->disabled()->dehydrated(),
                        Forms\Components\TextInput::make('montant_paye')
                            ->label('Montant payé')->numeric()->prefix('FCFA')->default(0)->disabled()->dehydrated(),
                    ])
                    ->columns(4),

                Forms\Components\Section::make('Dates & Responsabilité')
                    ->schema([
                        Forms\Components\DatePicker::make('date_ouverture')
                            ->label('Date d\'ouverture')->required()->default(now()),
                        Forms\Components\DatePicker::make('date_limite_livraison')
                            ->label('Date limite livraison')
                            ->helperText('Date limite de livraison attendue'),
                        Forms\Components\Select::make('responsable_id')
                            ->label('Responsable du dossier')
                            ->options(User::pluck('name', 'id'))->searchable()->preload()->default(auth()->id()),
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
                            ])
                            ->default('ouvert')->required()
                            ->visible(fn($record) => $record !== null),
                    ])
                    ->columns(4),

                Forms\Components\Section::make('Observations')
                    ->schema([
                        Forms\Components\Textarea::make('observations')
                            ->label('Observations')->rows(3)->columnSpanFull(),
                    ])
                    ->collapsible()->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->persistFiltersInSession()
            ->columns([
                Tables\Columns\TextColumn::make('numero_dossier')
                    ->label('N° Dossier')->searchable()->sortable()->weight('bold')->copyable(),

                Tables\Columns\TextColumn::make('fournisseur.raison_sociale')
                    ->label('Fournisseur')->searchable()->sortable()->limit(30),

                Tables\Columns\BadgeColumn::make('type_dossier')
                    ->label('Type')
                    ->formatStateUsing(fn($record) => $record->type_dossier_label)
                    ->colors([
                        'primary' => 'bon_commande',
                        'success' => 'marche',
                        'warning' => 'decision_administrative',
                        'info'    => 'prestation',
                        'gray'    => 'autre',
                    ]),

                Tables\Columns\TextColumn::make('reference_principale')
                    ->label('Référence')->searchable()->toggleable(),

                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
                    ->formatStateUsing(fn($record) => $record->statut_label)
                    ->color(fn($record) => $record->statut_color),

                Tables\Columns\TextColumn::make('montant_total')
                    ->label('Montant')->money('XAF')->sortable(),

                Tables\Columns\TextColumn::make('taux_realisation')
                    ->label('Réalisation')->suffix('%')
                    ->color(fn($state) => match (true) {
                        $state >= 100 => 'success',
                        $state >= 50  => 'warning',
                        default       => 'danger',
                    })
                    ->weight('bold'),

                Tables\Columns\IconColumn::make('est_en_retard')
                    ->label('Retard')->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')->falseColor('success')->toggleable(),

                Tables\Columns\TextColumn::make('date_ouverture')
                    ->label('Ouvert le')->date('d/m/Y')->sortable()->toggleable(),

                Tables\Columns\TextColumn::make('responsable.name')
                    ->label('Responsable')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // ✅ Filtre période — Aujourd'hui appliqué par défaut
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
                            // ✅ default('today') = valeur affichée dans le select
                            ->default('today')
                            ->placeholder('Toutes les périodes'),
                    ])
                    ->query(function ($query, array $data) {
                        // ✅ ?? 'today' = applique le filtre même sans soumission explicite
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
                        'marche'                  => 'Marché',
                        'decision_administrative' => 'Décision Administrative',
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

                Tables\Filters\Filter::make('en_retard')
                    ->label('En retard')
                    ->query(fn($query) => $query->enRetard())
                    ->toggle(),
            ])

            // ════════════════════════════════════════════════════════
            // ✅ ACTIONS — un seul ActionGroup aligné à gauche
            //    Pattern identique à BonCommandeResource
            // ════════════════════════════════════════════════════════
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')
                    ->button()
                    ->size('sm'),
            ], position: ActionsPosition::BeforeColumns)

            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListDossierFournisseurs::route('/'),
            'create' => Pages\CreateDossierFournisseur::route('/create'),
            'view'   => Pages\ViewDossierFournisseur::route('/{record}'),
            'edit'   => Pages\EditDossierFournisseur::route('/{record}/edit'),
        ];
    }
}
