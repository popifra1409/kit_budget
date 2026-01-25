<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ParametresStructureResource\Pages;
use App\Models\ParametresStructure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ParametresStructureResource extends Resource
{
    protected static ?string $model = ParametresStructure::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Paramètres Structure';

    protected static ?string $modelLabel = 'Paramètres';

    protected static ?string $pluralModelLabel = 'Paramètres Structure';

    protected static ?string $navigationGroup = 'Configuration';

    protected static ?int $navigationSort = 100;

    /**
     * Permissions - Paramètres système
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_parametres_structure') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->can('view_parametres_structure') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_parametres_structure') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('update_parametres_structure') ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('delete_parametres_structure') ?? false;
    }

    /**
     * Action spéciale : Activer un paramètre
     */
    public static function canActiver($record): bool
    {
        return auth()->user()?->can('activer_parametres_structure') ?? false;
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informations Principales')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('nom_structure')
                                    ->label('Nom de la Structure')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(2)
                                    ->placeholder('Ex: Centre Hospitalier Universitaire de Yaoundé'),

                                Forms\Components\TextInput::make('sigle')
                                    ->label('Sigle')
                                    ->maxLength(50)
                                    ->placeholder('Ex: CHUY'),
                            ]),

                        Forms\Components\FileUpload::make('logo')
                            ->label('Logo de la Structure')
                            ->image()
                            ->directory('logos')
                            ->disk('public')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/jpg', 'image/svg+xml'])
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                null,
                                '16:9',
                                '4:3',
                                '1:1',
                            ])
                            ->maxSize(2048)
                            ->helperText('Format accepté : PNG, JPG, SVG. Taille maximale : 2MB. Dimensions recommandées : 200x200px ou 400x100px')
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('actif')
                            ->label('Structure Active')
                            ->default(true)
                            ->helperText('Une seule structure peut être active à la fois'),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Coordonnées')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Textarea::make('adresse')
                                    ->label('Adresse')
                                    ->rows(2)
                                    ->columnSpanFull(),

                                Forms\Components\TextInput::make('ville')
                                    ->label('Ville')
                                    ->default('Yaoundé'),

                                Forms\Components\TextInput::make('pays')
                                    ->label('Pays')
                                    ->default('Cameroun'),

                                Forms\Components\TextInput::make('boite_postale')
                                    ->label('Boîte Postale')
                                    ->placeholder('Ex: BP 1234'),

                                Forms\Components\TextInput::make('telephone')
                                    ->label('Téléphone')
                                    ->tel()
                                    ->placeholder('Ex: +237 222 XX XX XX'),

                                Forms\Components\TextInput::make('fax')
                                    ->label('Fax')
                                    ->tel(),

                                Forms\Components\TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->placeholder('Ex: contact@structure.cm'),

                                Forms\Components\TextInput::make('site_web')
                                    ->label('Site Web')
                                    ->url()
                                    ->placeholder('Ex: https://www.structure.cm'),
                            ]),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Informations Officielles')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('ministere_tutelle')
                                    ->label('Ministère de Tutelle')
                                    ->placeholder('Ex: Ministère de la Santé Publique')
                                    ->columnSpanFull(),

                                Forms\Components\TextInput::make('numero_contribuable')
                                    ->label('Numéro de Contribuable')
                                    ->placeholder('Ex: M000000000000X'),

                                Forms\Components\TextInput::make('rccm')
                                    ->label('RCCM')
                                    ->placeholder('Ex: RC/YAO/XXXX/X/X/XXXX'),
                            ]),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('En-tête Documents (Bilingue)')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('pays_gauche')
                                    ->label('Pays (Français)')
                                    ->default('REPUBLIQUE DU CAMEROUN'),

                                Forms\Components\TextInput::make('pays_droite')
                                    ->label('Pays (Anglais)')
                                    ->default('REPUBLIC OF CAMEROON'),

                                Forms\Components\TextInput::make('devise_gauche')
                                    ->label('Devise (Français)')
                                    ->default('Paix – Travail - Patrie'),

                                Forms\Components\TextInput::make('devise_droite')
                                    ->label('Devise (Anglais)')
                                    ->default('Peace – Work - Fatherland'),
                            ]),
                    ])
                    ->columns(2)
                    ->collapsed(),

                Forms\Components\Section::make('Hiérarchie Administrative')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('direction_generale')
                                    ->label('Direction Générale (FR)')
                                    ->placeholder('Ex: DIRECTION GENERALE'),

                                Forms\Components\TextInput::make('direction_generale_en')
                                    ->label('Direction Générale (EN)')
                                    ->placeholder('Ex: DIRECTORATE GENERAL'),

                                Forms\Components\TextInput::make('sous_direction')
                                    ->label('Sous-Direction (FR)')
                                    ->placeholder('Ex: Sous-Direction des Finances et de la Comptabilité'),

                                Forms\Components\TextInput::make('sous_direction_en')
                                    ->label('Sous-Direction (EN)')
                                    ->placeholder('Ex: Sub-Department of Finances and Accounting'),
                            ]),
                    ])
                    ->columns(2)
                    ->collapsed(),

                Forms\Components\Section::make('Signatures Par Défaut')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('nom_ordonnateur')
                                    ->label('Nom de l\'Ordonnateur')
                                    ->placeholder('Ex: Dr. Jean DUPONT'),

                                Forms\Components\TextInput::make('fonction_ordonnateur')
                                    ->label('Fonction de l\'Ordonnateur')
                                    ->placeholder('Ex: Directeur Général')
                                    ->default('LE DIRECTEUR GENERAL'),

                                Forms\Components\TextInput::make('nom_comptable')
                                    ->label('Nom du Comptable')
                                    ->placeholder('Ex: Marie MBARGA'),

                                Forms\Components\TextInput::make('fonction_comptable')
                                    ->label('Fonction du Comptable')
                                    ->placeholder('Ex: Chef de l\'Agence Comptable')
                                    ->default('LE CHEF DE L\'AGENCE COMPTABLE'),
                            ]),
                    ])
                    ->columns(2)
                    ->collapsed(),

                Forms\Components\Section::make('Paramètres Budgétaires')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('taux_tva_defaut')
                                    ->label('Taux TVA par Défaut (%)')
                                    ->numeric()
                                    ->default(19.25)
                                    ->suffix('%')
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.01),

                                Forms\Components\TextInput::make('monnaie')
                                    ->label('Monnaie')
                                    ->default('FCFA')
                                    ->maxLength(10),

                                Forms\Components\TextInput::make('exercice_courant')
                                    ->label('Exercice en Cours')
                                    ->numeric()
                                    ->default(now()->year)
                                    ->minValue(2000)
                                    ->maxValue(2100)
                                    ->helperText('Si vide, utilise l\'année en cours'),
                            ]),
                    ])
                    ->columns(3)
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo')
                    ->label('Logo')
                    ->circular()
                    ->defaultImageUrl(url('/images/placeholder-logo.png')),

                Tables\Columns\TextColumn::make('nom_structure')
                    ->label('Structure')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('sigle')
                    ->label('Sigle')
                    ->badge()
                    ->searchable(),

                Tables\Columns\TextColumn::make('ville')
                    ->label('Ville')
                    ->searchable(),

                Tables\Columns\TextColumn::make('telephone')
                    ->label('Téléphone')
                    ->icon('heroicon-m-phone')
                    ->searchable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->icon('heroicon-m-envelope')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\IconColumn::make('actif')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('exercice_courant')
                    ->label('Exercice')
                    ->badge()
                    ->color('info')
                    ->default(now()->year),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('actif')
                    ->label('Structure Active')
                    ->trueLabel('Uniquement actives')
                    ->falseLabel('Uniquement inactives')
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListParametresStructures::route('/'),
            'create' => Pages\CreateParametresStructure::route('/create'),
            'view' => Pages\ViewParametresStructure::route('/{record}'),
            'edit' => Pages\EditParametresStructure::route('/{record}/edit'),
        ];
    }
}
