<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RecetteReelleResource\Pages;
use App\Models\RecetteReelle;
use App\Models\Exercice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Forms\Components\ExerciceSelect;

class RecetteReelleResource extends Resource
{
    protected static ?string $model = RecetteReelle::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Recettes Réelles';

    protected static ?string $modelLabel = 'Recette Réelle';

    protected static ?string $pluralModelLabel = 'Recettes Réelles';

    protected static ?string $navigationGroup = 'Gestion Budgétaire';

    protected static ?int $navigationSort = 3;

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

                Forms\Components\Section::make('Identification')
                    ->schema([
                        Forms\Components\TextInput::make('numero')
                            ->label('Numéro')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Auto-généré')
                            ->helperText('Généré automatiquement: REC-2026-000001'),

                        Forms\Components\Select::make('prevision_recette_mensuelle_id')
                            ->label('Prévision Mensuelle')
                            ->relationship('previsionRecetteMensuelle', 'id')
                            ->getOptionLabelFromRecordUsing(function ($record) {
                                $ligne = $record->lignePrevisionRecette;
                                return $record->periode . ' - ' . $ligne->code_nomenclature . ' ' . $ligne->libelle_nomenclature;
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Sélectionner le mois et la nomenclature')
                            ->columnSpanFull()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $prevision = \App\Models\PrevisionRecetteMensuelle::find($state);
                                    if ($prevision) {
                                        $ligne = $prevision->lignePrevisionRecette;
                                        $set('code_nomenclature', $ligne->code_nomenclature);
                                        $set('mois', $prevision->mois);
                                        $set('annee', $prevision->annee);
                                    }
                                }
                            }),

                        Forms\Components\Hidden::make('code_nomenclature'),
                        Forms\Components\Hidden::make('mois'),
                        Forms\Components\Hidden::make('annee'),

                        Forms\Components\TextInput::make('libelle')
                            ->label('Libellé')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Description de la recette')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Montant et Dates')
                    ->schema([
                        Forms\Components\TextInput::make('montant')
                            ->label('Montant')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->suffix('FCFA')
                            ->placeholder('0')
                            ->columnSpan(2),

                        Forms\Components\DatePicker::make('date_recette')
                            ->label('Date d\'Encaissement')
                            ->required()
                            ->default(now())
                            ->maxDate(now()),

                        Forms\Components\DatePicker::make('date_comptabilisation')
                            ->label('Date de Comptabilisation')
                            ->maxDate(now()),
                    ])
                    ->columns(4),

                Forms\Components\Section::make('Informations Payeur')
                    ->schema([
                        Forms\Components\TextInput::make('payeur')
                            ->label('Nom du Payeur')
                            ->maxLength(255)
                            ->placeholder('Nom de la personne/entreprise'),

                        Forms\Components\Select::make('mode_paiement')
                            ->label('Mode de Paiement')
                            ->options([
                                'Espèces' => 'Espèces',
                                'Chèque' => 'Chèque',
                                'Virement' => 'Virement Bancaire',
                                'Carte' => 'Carte Bancaire',
                                'Mobile Money' => 'Mobile Money',
                                'Autre' => 'Autre',
                            ])
                            ->searchable(),

                        Forms\Components\TextInput::make('reference_paiement')
                            ->label('Référence de Paiement')
                            ->maxLength(100)
                            ->placeholder('N° chèque, référence virement, etc.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Statut et Validation')
                    ->schema([
                        Forms\Components\Select::make('statut')
                            ->label('Statut')
                            ->options([
                                'prevue' => 'Prévue',
                                'encaissee' => 'Encaissée',
                                'comptabilisee' => 'Comptabilisée',
                                'validee' => 'Validée',
                            ])
                            ->required()
                            ->default('encaissee'),

                        Forms\Components\Placeholder::make('validation_info')
                            ->label('Information de Validation')
                            ->content(
                                fn($record) => $record && $record->estValidee()
                                    ? "Validée le " . $record->validee_le->format('d/m/Y à H:i') . " par " . $record->validateur?->name
                                    : 'Non validée'
                            )
                            ->hidden(fn($record) => !$record),
                    ])
                    ->columns(2),

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
                    ->label('N°')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable()
                    ->copyMessage('Numéro copié!')
                    ->copyMessageDuration(1500),

                Tables\Columns\BadgeColumn::make('exercice.annee')
                    ->label('Exercice')
                    ->sortable()
                    ->colors([
                        'success' => fn($record) => $record->exercice?->estActif(),
                        'warning' => fn($record) => $record->exercice?->estCloture(),
                    ]),

                Tables\Columns\TextColumn::make('previsionRecetteMensuelle.periode')
                    ->label('Mois')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('previsionRecetteMensuelle.lignePrevisionRecette.libelle_nomenclature')
                    ->label('Nomenclature')
                    ->searchable()
                    ->limit(40)
                    ->wrap()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('libelle')
                    ->label('Libellé')
                    ->searchable()
                    ->limit(40)
                    ->wrap(),

                Tables\Columns\TextColumn::make('date_recette')
                    ->label('Date Encaissement')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('montant')
                    ->label('Montant')
                    ->money('XAF', locale: 'fr')
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\TextColumn::make('payeur')
                    ->label('Payeur')
                    ->searchable()
                    ->limit(30)
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('mode_paiement')
                    ->label('Mode')
                    ->colors([
                        'success' => 'Virement',
                        'info' => 'Chèque',
                        'warning' => 'Espèces',
                        'primary' => ['Mobile Money', 'Carte'],
                    ])
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
                    ->colors([
                        'gray' => 'prevue',
                        'info' => 'encaissee',
                        'warning' => 'comptabilisee',
                        'success' => 'validee',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'prevue' => 'Prévue',
                        'encaissee' => 'Encaissée',
                        'comptabilisee' => 'Comptabilisée',
                        'validee' => 'Validée',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('validateur.name')
                    ->label('Validé par')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('exercice_id')
                    ->label('Exercice')
                    ->relationship('exercice', 'annee')
                    ->searchable()
                    ->preload()
                    ->default(fn() => Exercice::getActif()?->id),

                Tables\Filters\SelectFilter::make('mois')
                    ->label('Mois')
                    ->options([
                        1 => 'Janvier',
                        2 => 'Février',
                        3 => 'Mars',
                        4 => 'Avril',
                        5 => 'Mai',
                        6 => 'Juin',
                        7 => 'Juillet',
                        8 => 'Août',
                        9 => 'Septembre',
                        10 => 'Octobre',
                        11 => 'Novembre',
                        12 => 'Décembre'
                    ])
                    ->default(now()->month),

                Tables\Filters\SelectFilter::make('statut')
                    ->label('Statut')
                    ->options([
                        'prevue' => 'Prévue',
                        'encaissee' => 'Encaissée',
                        'comptabilisee' => 'Comptabilisée',
                        'validee' => 'Validée',
                    ]),

                Tables\Filters\SelectFilter::make('mode_paiement')
                    ->label('Mode de Paiement')
                    ->options([
                        'Espèces' => 'Espèces',
                        'Chèque' => 'Chèque',
                        'Virement' => 'Virement',
                        'Carte' => 'Carte',
                        'Mobile Money' => 'Mobile Money',
                    ]),

                Tables\Filters\Filter::make('date_recette')
                    ->form([
                        Forms\Components\DatePicker::make('date_debut')
                            ->label('Du'),
                        Forms\Components\DatePicker::make('date_fin')
                            ->label('Au'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['date_debut'], fn($q, $date) => $q->where('date_recette', '>=', $date))
                            ->when($data['date_fin'], fn($q, $date) => $q->where('date_recette', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('comptabiliser')
                    ->label('Comptabiliser')
                    ->icon('heroicon-o-check-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn(RecetteReelle $record) => $record->comptabiliser())
                    ->visible(fn(RecetteReelle $record) => $record->estEncaissee())
                    ->successNotificationTitle('Recette comptabilisée'),

                Tables\Actions\Action::make('valider')
                    ->label('Valider')
                    ->icon('heroicon-o-shield-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn(RecetteReelle $record) => $record->valider(auth()->id()))
                    ->visible(fn(RecetteReelle $record) => $record->estComptabilisee() && auth()->user()->hasAnyRole(['super_admin', 'agence_comptable', 'controleur_financier']))
                    ->successNotificationTitle('Recette validée'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('date_recette', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRecetteReelles::route('/'),
            'create' => Pages\CreateRecetteReelle::route('/create'),
            'edit' => Pages\EditRecetteReelle::route('/{record}/edit'),
            'view' => Pages\ViewRecetteReelle::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('annee', now()->year)
            ->where('statut', 'encaissee')
            ->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}
