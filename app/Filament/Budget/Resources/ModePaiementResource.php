<?php

namespace App\Filament\Budget\Resources;

use App\Models\ModePaiement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ModePaiementResource extends Resource
{
    protected static ?string $model            = ModePaiement::class;
    protected static ?string $navigationIcon   = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel  = 'Modes de paiement';
    protected static ?string $modelLabel       = 'Mode de paiement';
    protected static ?string $pluralModelLabel = 'Modes de paiement';
    protected static ?string $navigationGroup  = 'Configuration Budget';
    protected static ?int    $navigationSort   = 10;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole(['super_admin', 'admin', 'daaf']) ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('Informations du mode de paiement')
                ->schema([
                    Forms\Components\TextInput::make('icone')
                        ->label('Icône (emoji)')
                        ->default('💳')
                        ->maxLength(10)
                        ->helperText('Ex: 🏦 💳 📄 💵 📱'),

                    Forms\Components\TextInput::make('code')
                        ->label('Code unique')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(50)
                        ->placeholder('Ex: virement_uba')
                        ->helperText('Identifiant unique, sans espaces'),

                    Forms\Components\TextInput::make('libelle')
                        ->label('Libellé')
                        ->required()
                        ->maxLength(100)
                        ->placeholder('Ex: Virement UBA Cameroun')
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('description')
                        ->label('Description')
                        ->rows(2)
                        ->nullable()
                        ->columnSpanFull()
                        ->placeholder('Description optionnelle du mode de paiement'),

                    Forms\Components\TextInput::make('ordre')
                        ->label('Ordre d\'affichage')
                        ->numeric()
                        ->default(0)
                        ->minValue(0),

                    Forms\Components\Toggle::make('actif')
                        ->label('Actif')
                        ->default(true)
                        ->helperText('Désactivez pour masquer sans supprimer'),
                ])
                ->columns(3),

            Forms\Components\Section::make('Applicabilité')
                ->description('Définissez sur quels types d\'ordonnances ce mode est disponible')
                ->schema([
                    Forms\Components\Toggle::make('applicable_op')
                        ->label('Applicable aux OP (Ordonnances de Paiement standard)')
                        ->default(true)
                        ->helperText('Paiement direct au fournisseur/prestataire'),

                    Forms\Components\Toggle::make('applicable_opt')
                        ->label('Applicable aux OPT (Retenues fiscales — IR, TSR...)')
                        ->default(false)
                        ->helperText('Reversement des retenues à la DGI/Trésor'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('icone')
                    ->label('')
                    ->width(40),

                Tables\Columns\TextColumn::make('libelle')
                    ->label('Mode de paiement')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                Tables\Columns\IconColumn::make('applicable_op')
                    ->label('OP Standard')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\IconColumn::make('applicable_opt')
                    ->label('OPT (Impôts)')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('ordre')
                    ->label('Ordre')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('actif')
                    ->label('Actif')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('actif')->label('Actif'),
                Tables\Filters\TernaryFilter::make('applicable_op')->label('Pour OP'),
                Tables\Filters\TernaryFilter::make('applicable_opt')->label('Pour OPT'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->reorderable('ordre')
            ->defaultSort('ordre')
            ->emptyStateHeading('Aucun mode de paiement')
            ->emptyStateDescription('Créez vos modes de paiement pour les utiliser dans les ordonnances.')
            ->emptyStateIcon('heroicon-o-credit-card');
    }

    public static function getPages(): array
    {
        return [
            'index'  => \App\Filament\Budget\Resources\ModePaiementResource\Pages\ListModePaiements::route('/'),
            'create' => \App\Filament\Budget\Resources\ModePaiementResource\Pages\CreateModePaiement::route('/create'),
            'edit'   => \App\Filament\Budget\Resources\ModePaiementResource\Pages\EditModePaiement::route('/{record}/edit'),
        ];
    }
}
