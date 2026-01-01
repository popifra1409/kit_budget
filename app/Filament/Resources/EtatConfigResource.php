<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EtatConfigResource\Pages;
use App\Models\EtatConfig;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EtatConfigResource extends Resource
{
    protected static ?string $model = EtatConfig::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Configuration États PDF';

    protected static ?string $modelLabel = 'État PDF';

    protected static ?string $pluralModelLabel = 'États PDF';

    protected static ?string $navigationGroup = 'Configuration';

    protected static ?int $navigationSort = 99;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informations générales')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('code')
                                    ->label('Code unique')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255)
                                    ->helperText('Ex: certificat_engagement, bon_commande'),

                                Forms\Components\TextInput::make('nom')
                                    ->label('Nom de l\'état')
                                    ->required()
                                    ->maxLength(255)
                                    ->helperText('Ex: CERTIFICAT D\'ENGAGEMENT'),
                            ]),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('template')
                                    ->label('Template Blade')
                                    ->required()
                                    ->maxLength(255)
                                    ->helperText('Ex: pdf.templates.certificat-engagement'),

                                Forms\Components\Select::make('categorie')
                                    ->label('Catégorie')
                                    ->options([
                                        'Budgétaire' => 'Budgétaire',
                                        'Commercial' => 'Commercial',
                                        'Comptable' => 'Comptable',
                                        'Administratif' => 'Administratif',
                                    ])
                                    ->default('Budgétaire'),

                                Forms\Components\TextInput::make('ordre')
                                    ->label('Ordre d\'affichage')
                                    ->numeric()
                                    ->default(0),
                            ]),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('actif')
                            ->label('État actif')
                            ->default(true)
                            ->inline(false),
                    ]),

                Forms\Components\Section::make('Configuration des champs')
                    ->schema([
                        Forms\Components\KeyValue::make('champs_variables')
                            ->label('Champs variables')
                            ->keyLabel('Nom du champ')
                            ->valueLabel('Configuration (JSON)')
                            ->helperText('Définir les champs qui seront extraits des données'),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Calculs automatiques')
                    ->schema([
                        Forms\Components\KeyValue::make('calculs')
                            ->label('Formules de calcul')
                            ->keyLabel('Nom du calcul')
                            ->valueLabel('Configuration (JSON)')
                            ->helperText('Ex: nombre_en_lettres, somme, etc.'),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Configuration visuelle')
                    ->schema([
                        Forms\Components\Tabs::make('config_visuelle')
                            ->tabs([
                                Forms\Components\Tabs\Tab::make('En-tête')
                                    ->schema([
                                        Forms\Components\KeyValue::make('entete_config')
                                            ->label('Configuration en-tête')
                                            ->default([
                                                'afficher_logo' => true,
                                                'institution' => 'MINISTERE DE LA SANTE PUBLIQUE',
                                                'etablissement' => 'HOPITAL GENERAL DE YAOUNDE',
                                            ]),
                                    ]),

                                Forms\Components\Tabs\Tab::make('Signatures')
                                    ->schema([
                                        Forms\Components\KeyValue::make('signature_config')
                                            ->label('Configuration signatures'),
                                    ]),

                                Forms\Components\Tabs\Tab::make('Options PDF')
                                    ->schema([
                                        Forms\Components\KeyValue::make('options_pdf')
                                            ->label('Options PDF')
                                            ->default([
                                                'orientation' => 'portrait',
                                                'page-size' => 'A4',
                                            ]),
                                    ]),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('nom')
                    ->label('Nom')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\BadgeColumn::make('categorie')
                    ->label('Catégorie')
                    ->colors([
                        'primary' => 'Budgétaire',
                        'success' => 'Commercial',
                        'warning' => 'Comptable',
                        'danger' => 'Administratif',
                    ]),

                Tables\Columns\IconColumn::make('actif')
                    ->label('Actif')
                    ->boolean(),

                Tables\Columns\TextColumn::make('ordre')
                    ->label('Ordre')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('categorie')
                    ->label('Catégorie')
                    ->options([
                        'Budgétaire' => 'Budgétaire',
                        'Commercial' => 'Commercial',
                        'Comptable' => 'Comptable',
                        'Administratif' => 'Administratif',
                    ]),

                Tables\Filters\TernaryFilter::make('actif')
                    ->label('Actif'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('ordre');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEtatConfigs::route('/'),
            'create' => Pages\CreateEtatConfig::route('/create'),
            'edit' => Pages\EditEtatConfig::route('/{record}/edit'),
        ];
    }
}
