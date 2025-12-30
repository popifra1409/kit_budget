<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceResource\Pages;
use App\Models\Service;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationLabel = 'Services';

    protected static ?string $modelLabel = 'Service';

    protected static ?string $pluralModelLabel = 'Services';

    protected static ?string $navigationGroup = 'Configuration';

    protected static ?int $navigationSort = 1;

    /**
     * Permissions - Référentiel géré par SA/CS
     */
    public static function canViewAny(): bool
    {
        return auth()->check() ? auth()->user()->hasAnyRole([
            'super_admin',
            // 'operateur_budget',
            'chef_service_budget',
            'sous_directeur_budget',
            'directeur_general',
            // 'controleur_financier',
            // 'agence_comptable'
        ]) : false;
    }

    public static function canCreate(): bool
    {
        return auth()->check() ? auth()->user()->hasAnyRole([
            'super_admin',
            // 'chef_service_budget'
        ]) : false;
    }

    public static function canEdit($record): bool
    {
        return auth()->check() ? auth()->user()->hasAnyRole([
            'super_admin',
            // 'chef_service_budget'
        ]) : false;
    }

    public static function canDelete($record): bool
    {
        return auth()->check() ? auth()->user()->hasRole('super_admin') : false;
    }

    public static function canView($record): bool
    {
        return auth()->check() ? auth()->user()->hasAnyRole([
            'super_admin',
            // 'operateur_budget',
            'chef_service_budget',
            'sous_directeur_budget',
            'directeur_general',
            // 'controleur_financier',
            // 'agence_comptable'
        ]) : false;
    }

    /**
     * Action spéciale : Activer/désactiver
     */
    public static function canActiver($record): bool
    {
        return auth()->check() ? auth()->user()->hasAnyRole([
            'super_admin',
            'chef_service_budget'
        ]) : false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informations du Service')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->placeholder('Ex: SRV-001')
                            ->helperText('Code unique du service'),

                        Forms\Components\TextInput::make('nom')
                            ->label('Nom du service')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ex: Service Informatique'),

                        Forms\Components\Select::make('parent_id')
                            ->label('Service parent')
                            ->relationship('parent', 'nom')
                            ->searchable()
                            ->preload()
                            ->placeholder('Aucun (service racine)')
                            ->helperText('Optionnel : pour créer une hiérarchie'),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Responsable et Contact')
                    ->schema([
                        Forms\Components\TextInput::make('responsable')
                            ->label('Responsable')
                            ->maxLength(255)
                            ->placeholder('Ex: Jean Dupont'),

                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('telephone')
                            ->label('Téléphone')
                            ->tel()
                            ->maxLength(255),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Localisation')
                    ->schema([
                        Forms\Components\TextInput::make('batiment')
                            ->label('Bâtiment')
                            ->maxLength(255)
                            ->placeholder('Ex: Bâtiment A'),

                        Forms\Components\TextInput::make('bureau')
                            ->label('Bureau')
                            ->maxLength(255)
                            ->placeholder('Ex: Bureau 201'),

                        Forms\Components\Toggle::make('actif')
                            ->label('Actif')
                            ->default(true),
                    ])
                    ->columns(3),
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

                Tables\Columns\TextColumn::make('nom')
                    ->label('Nom')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('parent.nom')
                    ->label('Service parent')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('responsable')
                    ->label('Responsable')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('telephone')
                    ->label('Téléphone')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('batiment')
                    ->label('Bâtiment')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('bureau')
                    ->label('Bureau')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('enfants_count')
                    ->label('Sous-services')
                    ->counts('enfants')
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('bons_commande_count')
                    ->label('BC')
                    ->counts('bonsCommande')
                    ->badge()
                    ->color('success')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('actif')
                    ->label('Actif')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('actif')
                    ->label('Actif')
                    ->placeholder('Tous')
                    ->trueLabel('Actifs')
                    ->falseLabel('Inactifs'),

                Tables\Filters\SelectFilter::make('parent_id')
                    ->label('Service parent')
                    ->relationship('parent', 'nom')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListServices::route('/'),
            'create' => Pages\CreateService::route('/create'),
            'edit' => Pages\EditService::route('/{record}/edit'),
            'view' => Pages\ViewService::route('/{record}'),
        ];
    }
}
