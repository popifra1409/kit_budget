<?php

namespace App\Filament\Comptable\Resources;

use App\Filament\Comptable\Resources\ReceptionResource\Pages;
use App\Models\Reception;
use App\Models\Article;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class ReceptionResource extends Resource
{
    protected static ?string $model           = Reception::class;
    protected static ?string $navigationIcon  = 'heroicon-o-truck';
    protected static ?string $navigationLabel = 'Réceptions';
    protected static ?string $modelLabel      = 'Réception';
    protected static ?string $pluralModelLabel = 'Réceptions';
    protected static ?string $navigationGroup = 'Acquisition des biens';
    protected static ?int    $navigationSort  = 30;

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('Informations générales')
                ->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('numero')
                            ->label('N° Réception')
                            ->default(fn() => Reception::genererNumero())
                            ->disabled()->dehydrated(),

                        Forms\Components\DatePicker::make('date_reception')
                            ->label('Date réception')->default(now())->required(),

                        Forms\Components\Select::make('exercice_id')
                            ->label('Exercice')
                            ->relationship('exercice', 'annee')
                            ->default(fn() => \App\Models\Exercice::getActif()?->id)
                            ->required(),
                    ]),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('fournisseur_id')
                            ->label('Fournisseur')
                            ->relationship('fournisseur', 'raison_sociale')
                            ->searchable()->preload()->required(),

                        Forms\Components\Select::make('bon_commande_id')
                            ->label('Bon de Commande')
                            ->relationship('bonCommande', 'numero')
                            ->searchable()->nullable(),
                    ]),
                ]),

            Forms\Components\Section::make('Documents fournisseur')
                ->schema([
                    Forms\Components\Grid::make(4)->schema([
                        Forms\Components\TextInput::make('numero_bordereau_livraison')
                            ->label('N° Bordereau livraison'),
                        Forms\Components\DatePicker::make('date_bordereau_livraison')
                            ->label('Date BL'),
                        Forms\Components\TextInput::make('numero_facture')
                            ->label('N° Facture'),
                        Forms\Components\DatePicker::make('date_facture')
                            ->label('Date facture'),
                    ]),
                    Forms\Components\TextInput::make('montant_facture')
                        ->label('Montant facture')->numeric()->suffix('FCFA'),
                ])
                ->collapsed(),

            Forms\Components\Section::make('Commission de réception')
                ->description('Les 4 membres doivent signer le PV de réception')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('president_commission_id')
                            ->label('Président (Ordonnateur-matières)')
                            ->relationship('presidentCommission', 'name')
                            ->searchable()->preload()->required(),

                        Forms\Components\Select::make('comptable_matieres_id')
                            ->label('Comptable-matières')
                            ->relationship('comptableMatieres', 'name')
                            ->searchable()->preload()->required(),

                        Forms\Components\Select::make('service_technique_id')
                            ->label('Service technique / Ingénieur')
                            ->relationship('serviceTechnique', 'name')
                            ->searchable()->preload(),

                        Forms\Components\TextInput::make('representant_prestataire_nom')
                            ->label('Représentant prestataire (nom)'),
                    ]),
                ]),

            Forms\Components\Section::make('Articles réceptionnés')
                ->schema([
                    Forms\Components\Repeater::make('lignes')
                        ->relationship('lignes')
                        ->schema([
                            Forms\Components\Grid::make(12)->schema([
                                Forms\Components\Select::make('article_id')
                                    ->label('Article')
                                    ->options(
                                        fn() => Article::actif()
                                            ->orderBy('designation')
                                            ->get()
                                            ->mapWithKeys(fn($a) => [
                                                $a->id => "[{$a->code}] {$a->designation}"
                                            ])
                                    )
                                    ->searchable()->required()
                                    ->columnSpan(4),

                                Forms\Components\TextInput::make('quantite_commandee')
                                    ->label('Qté commandée')
                                    ->numeric()->default(0)->columnSpan(2),

                                Forms\Components\TextInput::make('quantite_livree')
                                    ->label('Qté livrée')
                                    ->numeric()->required()->default(0)
                                    ->live(onBlur: true)
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('quantite_conforme')
                                    ->label('Qté conforme')
                                    ->numeric()->required()->default(0)
                                    ->live(onBlur: true)
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('prix_unitaire')
                                    ->label('Prix unitaire')
                                    ->numeric()->suffix('FCFA')
                                    ->columnSpan(2),

                                Forms\Components\Textarea::make('motif_rejet')
                                    ->label('Motif rejet si non conforme')
                                    ->rows(1)->columnSpan(12)
                                    ->visible(
                                        fn(Get $get) =>
                                        intval($get('quantite_livree')) > intval($get('quantite_conforme'))
                                    ),
                            ]),
                        ])
                        ->defaultItems(1)
                        ->addActionLabel('Ajouter un article')
                        ->itemLabel(
                            fn(array $state): ?string =>
                            Article::find($state['article_id'] ?? null)?->designation
                        ),
                ]),

            Forms\Components\Section::make('Observations & Réserves')
                ->schema([
                    Forms\Components\Textarea::make('observations')
                        ->label('Observations')->rows(2),
                    Forms\Components\Textarea::make('reserves')
                        ->label('Réserves éventuelles')->rows(2),
                ])
                ->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('N°')->searchable()->sortable()->weight('bold')->copyable(),

                Tables\Columns\TextColumn::make('date_reception')
                    ->label('Date')->date('d/m/Y')->sortable(),

                Tables\Columns\TextColumn::make('fournisseur.raison_sociale')
                    ->label('Fournisseur')->searchable()->limit(25),

                Tables\Columns\TextColumn::make('bonCommande.numero')
                    ->label('N° BC')->badge()->color('info'),

                Tables\Columns\TextColumn::make('lignes_count')
                    ->label('Articles')->counts('lignes')->badge()->color('warning'),

                Tables\Columns\TextColumn::make('montant_facture')
                    ->label('Montant')->money('XAF')->alignEnd(),

                // Indicateur signatures
                Tables\Columns\TextColumn::make('nombre_signataires')
                    ->label('Signatures')
                    ->state(fn($record) => $record->nombre_signataires . '/4')
                    ->badge()
                    ->color(fn($record) => $record->tout_signe ? 'success' : 'warning'),

                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
                    ->colors([
                        'secondary' => 'brouillon',
                        'warning'   => 'en_cours',
                        'success'   => 'pv_signe',
                        'primary'   => 'integre',
                        'danger'    => 'annule',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('statut')
                    ->options([
                        'brouillon' => 'Brouillon',
                        'en_cours'  => 'En cours',
                        'pv_signe'  => 'PV signé',
                        'integre'   => 'Intégré en stock',
                        'annule'    => 'Annulé',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => $record->statut === 'brouillon'),

                // Signer PV
                Tables\Actions\Action::make('signer')
                    ->label('Signer PV')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->visible(fn($record) => in_array($record->statut, ['brouillon', 'en_cours']))
                    ->form([
                        Forms\Components\Select::make('partie')
                            ->label('Signataire')
                            ->options([
                                'prestataire' => 'Prestataire',
                                'technique'   => 'Service technique',
                                'comptable'   => 'Comptable-matières',
                                'ordonnateur' => 'Ordonnateur-matières',
                            ])
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $record->signer($data['partie']);
                        $msg = $record->tout_signe
                            ? '✅ PV signé par toutes les parties'
                            : "Signature {$data['partie']} enregistrée";
                        Notification::make()->title($msg)->success()->send();
                    }),

                // Intégrer en stock
                Tables\Actions\Action::make('integrer')
                    ->label('Intégrer en stock')
                    ->icon('heroicon-o-inbox-arrow-down')
                    ->color('success')
                    ->visible(fn($record) => $record->statut === 'pv_signe')
                    ->requiresConfirmation()
                    ->modalDescription('Crée l\'Ordre d\'Entrée et met à jour les stocks.')
                    ->action(function ($record) {
                        try {
                            $oe = $record->integrerEnStock();
                            Notification::make()
                                ->title('✅ Stock mis à jour')
                                ->success()
                                ->body("Ordre d'Entrée créé : {$oe->numero}")
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('❌ Erreur')->danger()->body($e->getMessage())->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListReceptions::route('/'),
            'create' => Pages\CreateReception::route('/create'),
            'view'   => Pages\ViewReception::route('/{record}'),
            'edit'   => Pages\EditReception::route('/{record}/edit'),
        ];
    }
}
