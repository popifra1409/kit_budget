<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrdonnancePaiementResource\Pages;
use App\Models\OrdonnancePaiement;
use App\Models\Engagement;
use App\Models\Fournisseur;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use App\Filament\Forms\Components\ExerciceSelect;

class OrdonnancePaiementResource extends Resource
{
    protected static ?string $model = OrdonnancePaiement::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Ordonnances de Paiement';

    protected static ?string $modelLabel = 'Ordonnance de Paiement';

    protected static ?string $pluralModelLabel = 'Ordonnances de Paiement';

    protected static ?string $navigationGroup = 'Commandes & Engagement';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Exercice')
                    ->schema([
                        ExerciceSelect::make(),
                    ])
                    ->collapsible()
                    ->collapsed(fn($record) => $record !== null),

                Forms\Components\Section::make('Informations générales')
                    ->schema([
                        Forms\Components\Select::make('type_ordonnance')
                            ->label('Type d\'ordonnance')
                            ->options([
                                'standard' => 'Standard (Fournisseur)',
                                'impot' => 'Impôt (Direction des Impôts)',
                            ])
                            ->required()
                            ->default('standard')
                            ->live(),

                        Forms\Components\TextInput::make('numero')
                            ->label('N° Ordonnance')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Généré automatiquement')
                            ->visible(fn($record) => $record === null),

                        Forms\Components\Select::make('engagement_id')
                            ->label('Engagement')
                            ->options(Engagement::with('nomenclaturePrincipale')
                                ->get()
                                ->mapWithKeys(fn($eng) => [
                                    $eng->id => "{$eng->numero} - {$eng->objet} (" . number_format($eng->montant_engage, 0, ',', ' ') . " FCFA)"
                                ]))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    $engagement = Engagement::with('bonCommande')->find($state);
                                    if ($engagement) {
                                        $set('objet', $engagement->objet);
                                        $set('montant_brut', $engagement->montant_engage);

                                        // Calcul automatique pour standard
                                        if ($engagement->bonCommande) {
                                            $ir = $engagement->bonCommande->montant_ir ?? 0;
                                            $net = $engagement->montant_engage - $ir;
                                            $set('montant_impot', $ir);
                                            $set('montant_net', $net);
                                        }
                                    }
                                }
                            }),

                        Forms\Components\Textarea::make('objet')
                            ->label('Objet')
                            ->required()
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Montants')
                    ->schema([
                        Forms\Components\TextInput::make('montant_brut')
                            ->label('Montant brut')
                            ->numeric()
                            ->prefix('FCFA')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                                $impot = $get('montant_impot') ?? 0;
                                $set('montant_net', $state - $impot);
                            }),

                        Forms\Components\TextInput::make('montant_impot')
                            ->label('Montant impôt')
                            ->numeric()
                            ->prefix('FCFA')
                            ->default(0)
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                                $brut = $get('montant_brut') ?? 0;
                                $set('montant_net', $brut - $state);
                            }),

                        Forms\Components\TextInput::make('montant_net')
                            ->label('Montant net à payer')
                            ->numeric()
                            ->prefix('FCFA')
                            ->required()
                            ->disabled()
                            ->dehydrated(),

                        Forms\Components\TextInput::make('montant_pec')
                            ->label('Montant PEC Médical')
                            ->numeric()
                            ->prefix('FCFA')
                            ->default(0)
                            ->visible(fn(Forms\Get $get) => $get('type_ordonnance') === 'impot'),
                    ])
                    ->columns(4),

                Forms\Components\Section::make('Dates et Références')
                    ->schema([
                        Forms\Components\DatePicker::make('date_emission')
                            ->label('Date d\'émission')
                            ->required()
                            ->default(now())
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    $date = \Carbon\Carbon::parse($state);
                                    $set('mois_emission', $date->format('m'));
                                    $set('periode', $date->format('m/Y'));
                                }
                            }),

                        Forms\Components\TextInput::make('numero_bon')
                            ->label('N° Bon de caisse')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('numero_emission')
                            ->label('N° d\'émission')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('numero_op')
                            ->label('N° OP')
                            ->maxLength(255),

                        Forms\Components\Select::make('statut')
                            ->label('Statut')
                            ->options([
                                'brouillon' => 'Brouillon',
                                'emise' => 'Émise',
                                'visee' => 'Visée',
                                'payee' => 'Payée',
                                'annulee' => 'Annulée',
                            ])
                            ->default('brouillon')
                            ->required()
                            ->visible(fn($record) => $record !== null),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Paiement')
                    ->schema([
                        Forms\Components\DatePicker::make('date_paiement')
                            ->label('Date de paiement')
                            ->visible(fn($record) => $record && $record->statut === 'payee'),

                        Forms\Components\TextInput::make('reference_paiement')
                            ->label('Référence de paiement')
                            ->maxLength(255)
                            ->visible(fn($record) => $record && $record->statut === 'payee'),
                    ])
                    ->columns(2)
                    ->visible(fn($record) => $record && $record->statut === 'payee'),

                Forms\Components\Section::make('Observations')
                    ->schema([
                        Forms\Components\Textarea::make('observations')
                            ->label('Observations')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('N° OP')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\BadgeColumn::make('type_ordonnance')
                    ->label('Type')
                    ->formatStateUsing(fn($state) => match ($state) {
                        'standard' => 'Standard',
                        'impot' => 'Impôt',
                        default => $state,
                    })
                    ->colors([
                        'primary' => 'standard',
                        'warning' => 'impot',
                    ]),

                Tables\Columns\TextColumn::make('engagement.numero')
                    ->label('Engagement')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('objet')
                    ->label('Objet')
                    ->limit(40)
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
                    ->formatStateUsing(fn($record) => $record->statut_label)
                    ->color(fn($record) => $record->statut_color),

                Tables\Columns\TextColumn::make('montant_net')
                    ->label('Montant Net')
                    ->money('XAF')
                    ->sortable(),

                Tables\Columns\TextColumn::make('date_emission')
                    ->label('Date émission')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('date_paiement')
                    ->label('Date paiement')
                    ->date('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type_ordonnance')
                    ->label('Type')
                    ->options([
                        'standard' => 'Standard',
                        'impot' => 'Impôt',
                    ]),

                Tables\Filters\SelectFilter::make('statut')
                    ->label('Statut')
                    ->options([
                        'brouillon' => 'Brouillon',
                        'emise' => 'Émise',
                        'visee' => 'Visée',
                        'payee' => 'Payée',
                        'annulee' => 'Annulée',
                    ])
                    ->multiple(),

                Tables\Filters\Filter::make('date_emission')
                    ->form([
                        Forms\Components\DatePicker::make('date_emission_from')
                            ->label('Date d\'émission du'),
                        Forms\Components\DatePicker::make('date_emission_until')
                            ->label('Date d\'émission au'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['date_emission_from'], fn($q, $date) =>
                            $q->whereDate('date_emission', '>=', $date))
                            ->when($data['date_emission_until'], fn($q, $date) =>
                            $q->whereDate('date_emission', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('emettre')
                    ->label('Émettre')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn($record) => $record->statut === 'brouillon')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->emettre();

                        Notification::make()
                            ->title('Ordonnance émise')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('marquer_payee')
                    ->label('Marquer payée')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn($record) => in_array($record->statut, ['emise', 'visee']))
                    ->form([
                        Forms\Components\DatePicker::make('date_paiement')
                            ->label('Date de paiement')
                            ->required()
                            ->default(now()),
                        Forms\Components\TextInput::make('reference_paiement')
                            ->label('Référence de paiement')
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $record->marquerPayee($data['reference_paiement']);
                        $record->date_paiement = $data['date_paiement'];
                        $record->save();

                        Notification::make()
                            ->title('Paiement enregistré')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\ActionGroup::make([
                    // OP Standard
                    Tables\Actions\Action::make('telecharger_op')
                        ->label('OP Standard (PDF)')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->url(fn($record) => route('pdf.telecharger', [
                            'etat' => 'ordonnance_paiement',
                            'id' => $record->id
                        ])),

                    Tables\Actions\Action::make('afficher_op')
                        ->label('OP Standard (Aperçu)')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->url(fn($record) => route('pdf.afficher', [
                            'etat' => 'ordonnance_paiement',
                            'id' => $record->id
                        ]))
                        ->openUrlInNewTab(),

                    // OP Impôt
                    Tables\Actions\Action::make('telecharger_op_impot')
                        ->label('OP Impôt (PDF)')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('warning')
                        ->url(fn($record) => route('pdf.telecharger', [
                            'etat' => 'ordonnance_paiement_impot',
                            'id' => $record->id
                        ])),

                    Tables\Actions\Action::make('afficher_op_impot')
                        ->label('OP Impôt (Aperçu)')
                        ->icon('heroicon-o-eye')
                        ->color('gray')
                        ->url(fn($record) => route('pdf.afficher', [
                            'etat' => 'ordonnance_paiement_impot',
                            'id' => $record->id
                        ]))
                        ->openUrlInNewTab(),
                ])
                    ->label('Télécharger / Aperçu')
                    ->icon('heroicon-m-document-arrow-down')
                    ->size('sm')
                    ->color('success')
                    ->button(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrdonnancePaiements::route('/'),
            'create' => Pages\CreateOrdonnancePaiement::route('/create'),
            'view' => Pages\ViewOrdonnancePaiement::route('/{record}'),
            'edit' => Pages\EditOrdonnancePaiement::route('/{record}/edit'),
        ];
    }
}
