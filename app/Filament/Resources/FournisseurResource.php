<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FournisseurResource\Pages;
use App\Models\Fournisseur;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class FournisseurResource extends Resource
{
    protected static ?string $model = Fournisseur::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Fournisseurs';

    protected static ?string $modelLabel = 'Fournisseur';

    protected static ?string $pluralModelLabel = 'Fournisseurs';

    protected static ?string $navigationGroup = 'Configuration';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identification')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->placeholder('Ex: FRS-001'),

                        Forms\Components\TextInput::make('raison_sociale')
                            ->label('Raison sociale')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('sigle')
                            ->label('Sigle / Nom commercial')
                            ->maxLength(255)
                            ->placeholder('Ex: CAMTEL'),

                        Forms\Components\TextInput::make('rccm')
                            ->label('N° RCCM')
                            ->maxLength(255)
                            ->placeholder('Ex: RC/DLA/2020/B/1234'),

                        Forms\Components\TextInput::make('nif')
                            ->label('NIF')
                            ->maxLength(255)
                            ->placeholder('Ex: M012345678901Z'),

                        Forms\Components\TextInput::make('forme_juridique')
                            ->label('Forme juridique')
                            ->maxLength(255)
                            ->placeholder('Ex: SARL, SA, EI'),

                        Forms\Components\Select::make('type')
                            ->label('Type de fournisseur')
                            ->options([
                                'biens' => 'Biens',
                                'services' => 'Services',
                                'travaux' => 'Travaux',
                                'mixte' => 'Mixte',
                            ])
                            ->required()
                            ->default('mixte'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Coordonnées')
                    ->schema([
                        Forms\Components\TextInput::make('adresse')
                            ->label('Adresse')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('ville')
                            ->label('Ville')
                            ->maxLength(255)
                            ->default('Douala'),

                        Forms\Components\TextInput::make('pays')
                            ->label('Pays')
                            ->maxLength(255)
                            ->default('Cameroun'),

                        Forms\Components\TextInput::make('telephone')
                            ->label('Téléphone')
                            ->tel()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('site_web')
                            ->label('Site web')
                            ->url()
                            ->maxLength(255)
                            ->placeholder('https://'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Contact Principal')
                    ->schema([
                        Forms\Components\TextInput::make('contact_nom')
                            ->label('Nom du contact')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('contact_fonction')
                            ->label('Fonction')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('contact_telephone')
                            ->label('Téléphone')
                            ->tel()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('contact_email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                    ])
                    ->columns(4)
                    ->collapsible(),

                Forms\Components\Section::make('Informations Bancaires')
                    ->schema([
                        Forms\Components\TextInput::make('banque')
                            ->label('Banque')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('iban')
                            ->label('IBAN')
                            ->maxLength(255)
                            ->placeholder('Ex: CM21 1234 5678 9012 3456 7890 1234'),

                        Forms\Components\TextInput::make('code_swift')
                            ->label('Code SWIFT/BIC')
                            ->maxLength(255)
                            ->placeholder('Ex: BICMCMCX'),

                        Forms\Components\TextInput::make('numero_compte')
                            ->label('Numéro de compte')
                            ->maxLength(255),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Forms\Components\Section::make('Statut')
                    ->schema([
                        Forms\Components\Toggle::make('actif')
                            ->label('Actif')
                            ->default(true),

                        Forms\Components\Toggle::make('blackliste')
                            ->label('Blacklisté')
                            ->default(false)
                            ->reactive(),

                        Forms\Components\Textarea::make('motif_blacklist')
                            ->label('Motif de blacklist')
                            ->rows(3)
                            ->visible(fn(callable $get) => $get('blackliste'))
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('observations')
                            ->label('Observations')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
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
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('raison_sociale')
                    ->label('Raison sociale')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(40),

                Tables\Columns\TextColumn::make('sigle')
                    ->label('Sigle')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Type')
                    ->colors([
                        'primary' => 'biens',
                        'success' => 'services',
                        'warning' => 'travaux',
                        'info' => 'mixte',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'biens' => 'Biens',
                        'services' => 'Services',
                        'travaux' => 'Travaux',
                        'mixte' => 'Mixte',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('telephone')
                    ->label('Téléphone')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('ville')
                    ->label('Ville')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('bons_commande_count')
                    ->label('BC')
                    ->counts('bonsCommande')
                    ->badge()
                    ->color('success'),

                Tables\Columns\IconColumn::make('actif')
                    ->label('Actif')
                    ->boolean(),

                Tables\Columns\IconColumn::make('blackliste')
                    ->label('Blacklisté')
                    ->boolean()
                    ->trueColor('danger')
                    ->falseColor('success'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Type')
                    ->options([
                        'biens' => 'Biens',
                        'services' => 'Services',
                        'travaux' => 'Travaux',
                        'mixte' => 'Mixte',
                    ]),

                Tables\Filters\TernaryFilter::make('actif')
                    ->label('Actif')
                    ->placeholder('Tous')
                    ->trueLabel('Actifs')
                    ->falseLabel('Inactifs'),

                Tables\Filters\TernaryFilter::make('blackliste')
                    ->label('Blacklisté')
                    ->placeholder('Tous')
                    ->trueLabel('Blacklistés')
                    ->falseLabel('Non blacklistés'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('blacklister')
                    ->label('Blacklister')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn($record) => !$record->blackliste)
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('motif')
                            ->label('Motif de blacklist')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        $record->blacklister($data['motif']);
                        Notification::make()
                            ->title('Fournisseur blacklisté')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('rehabiliter')
                    ->label('Réhabiliter')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn($record) => $record->blackliste)
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->rehabiliter();
                        Notification::make()
                            ->title('Fournisseur réhabilité')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('code', 'asc');
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
            'index' => Pages\ListFournisseurs::route('/'),
            'create' => Pages\CreateFournisseur::route('/create'),
            'edit' => Pages\EditFournisseur::route('/{record}/edit'),
            'view' => Pages\ViewFournisseur::route('/{record}'),
        ];
    }
}
