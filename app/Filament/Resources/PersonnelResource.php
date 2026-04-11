<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PersonnelResource\Pages;
use App\Models\Personnel;
use App\Models\Service;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class PersonnelResource extends Resource
{
    protected static ?string $model = Personnel::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Personnel';

    protected static ?string $modelLabel = 'Personnel';

    protected static ?string $pluralModelLabel = 'Personnels';

    protected static ?string $navigationGroup = 'Paramétrage';

    protected static ?int $navigationSort = 1;

    /**
     * Permissions - Gestion du personnel
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_personnel') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->can('view_personnel') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_personnel') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('update_personnel') ?? false;
    }

    public static function canDelete($record): bool
    {
        // Ne peut supprimer que si aucune décision administrative liée
        if (!auth()->user()?->can('delete_personnel')) {
            return false;
        }

        return $record->decisionsAdministratives()->count() === 0;
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identité')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('matricule')
                                    ->label('Matricule')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(50)
                                    ->placeholder('Ex: MAT-2026-0001')
                                    ->helperText('Matricule unique du personnel'),

                                Forms\Components\Select::make('civilite')
                                    ->label('Civilité')
                                    ->options([
                                        'M.' => 'M.',
                                        'Mme' => 'Mme',
                                        'Mlle' => 'Mlle',
                                    ]),

                                Forms\Components\Select::make('sexe')
                                    ->label('Sexe')
                                    ->options([
                                        'M' => 'Masculin',
                                        'F' => 'Féminin',
                                    ])
                                    ->required(),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('nom')
                                    ->label('Nom')
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('prenoms')
                                    ->label('Prénoms')
                                    ->required()
                                    ->maxLength(255),
                            ]),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\DatePicker::make('date_naissance')
                                    ->label('Date de naissance')
                                    ->maxDate(now()->subYears(18))
                                    ->displayFormat('d/m/Y'),

                                Forms\Components\TextInput::make('lieu_naissance')
                                    ->label('Lieu de naissance')
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('nationalite')
                                    ->label('Nationalité')
                                    ->default('Camerounaise')
                                    ->maxLength(255),
                            ]),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Affectation professionnelle')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('service_id')
                                    ->label('Service')
                                    ->options(Service::where('actif', true)->pluck('nom', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Forms\Components\TextInput::make('fonction')
                                    ->label('Fonction')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Ex: Chef de Service'),
                            ]),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('grade')
                                    ->label('Grade')
                                    ->maxLength(255),

                                Forms\Components\Select::make('categorie')
                                    ->label('Catégorie')
                                    ->options([
                                        'A' => 'Catégorie A',
                                        'B' => 'Catégorie B',
                                        'C' => 'Catégorie C',
                                        'D' => 'Catégorie D',
                                    ])
                                    ->searchable(),

                                Forms\Components\TextInput::make('echelon')
                                    ->label('Échelon')
                                    ->maxLength(255),
                            ]),

                        Forms\Components\TextInput::make('indice')
                            ->label('Indice')
                            ->maxLength(255),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Dates importantes')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\DatePicker::make('date_prise_service')
                                    ->label('Date de prise de service')
                                    ->displayFormat('d/m/Y'),

                                Forms\Components\DatePicker::make('date_titularisation')
                                    ->label('Date de titularisation')
                                    ->displayFormat('d/m/Y')
                                    ->after('date_prise_service'),

                                Forms\Components\DatePicker::make('date_depart_retraite')
                                    ->label('Date de départ à la retraite')
                                    ->displayFormat('d/m/Y')
                                    ->after('date_titularisation'),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Forms\Components\Section::make('Contact')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('telephone')
                                    ->label('Téléphone')
                                    ->tel()
                                    ->maxLength(255)
                                    ->placeholder('+237 6XX XX XX XX'),

                                Forms\Components\TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->maxLength(255),
                            ]),

                        Forms\Components\Textarea::make('adresse')
                            ->label('Adresse')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->collapsible()
                    ->collapsed(),

                Forms\Components\Section::make('Informations bancaires et administratives')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('numero_cni')
                                    ->label('N° CNI')
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('numero_cnps')
                                    ->label('N° CNPS')
                                    ->maxLength(255),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('numero_compte_bancaire')
                                    ->label('N° Compte bancaire')
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('banque')
                                    ->label('Banque')
                                    ->maxLength(255),
                            ]),
                    ])
                    ->columns(1)
                    ->collapsible()
                    ->collapsed(),

                Forms\Components\Section::make('Statut et compte utilisateur')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('statut')
                                    ->label('Statut')
                                    ->options([
                                        'actif' => 'Actif',
                                        'conge' => 'En congé',
                                        'detache' => 'Détaché',
                                        'disponibilite' => 'En disponibilité',
                                        'suspendu' => 'Suspendu',
                                        'retraite' => 'Retraité',
                                        'demissionnaire' => 'Démissionnaire',
                                    ])
                                    ->required()
                                    ->default('actif'),

                                Forms\Components\Toggle::make('actif')
                                    ->label('Actif')
                                    ->default(true)
                                    ->helperText('Désactiver pour masquer dans les sélections'),
                            ]),

                        Forms\Components\Select::make('user_id')
                            ->label('Compte utilisateur associé')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Lier ce personnel à un compte utilisateur existant')
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('matricule')
                    ->label('Matricule')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('nom')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('prenoms')
                    ->label('Prénoms')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('fonction')
                    ->label('Fonction')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('service.nom')
                    ->label('Service')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('categorie')
                    ->label('Cat.')
                    ->colors([
                        'primary' => 'A',
                        'success' => 'B',
                        'warning' => 'C',
                        'danger' => 'D',
                    ])
                    ->toggleable(),

                Tables\Columns\TextColumn::make('telephone')
                    ->label('Téléphone')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
                    ->colors([
                        'success' => 'actif',
                        'info' => 'conge',
                        'warning' => fn($state) => in_array($state, ['detache', 'disponibilite']),
                        'danger' => fn($state) => in_array($state, ['suspendu', 'demissionnaire']),
                        'secondary' => 'retraite',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'actif' => 'Actif',
                        'conge' => 'Congé',
                        'detache' => 'Détaché',
                        'disponibilite' => 'Disponibilité',
                        'suspendu' => 'Suspendu',
                        'retraite' => 'Retraité',
                        'demissionnaire' => 'Démissionnaire',
                        default => $state,
                    }),

                Tables\Columns\IconColumn::make('actif')
                    ->label('Actif')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('service_id')
                    ->label('Service')
                    ->relationship('service', 'nom')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('statut')
                    ->label('Statut')
                    ->options([
                        'actif' => 'Actif',
                        'conge' => 'Congé',
                        'detache' => 'Détaché',
                        'disponibilite' => 'Disponibilité',
                        'suspendu' => 'Suspendu',
                        'retraite' => 'Retraité',
                        'demissionnaire' => 'Démissionnaire',
                    ]),

                Tables\Filters\TernaryFilter::make('actif')
                    ->label('Actif')
                    ->placeholder('Tous')
                    ->trueLabel('Actifs uniquement')
                    ->falseLabel('Inactifs uniquement'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('nom', 'asc');
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
            'index' => Pages\ListPersonnels::route('/'),
            'create' => Pages\CreatePersonnel::route('/create'),
            'edit' => Pages\EditPersonnel::route('/{record}/edit'),
            'view' => Pages\ViewPersonnel::route('/{record}'),
        ];
    }
}
