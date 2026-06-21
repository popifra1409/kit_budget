<?php

namespace App\Filament\Comptable\Resources;

use App\Filament\Comptable\Resources\OrdreEntreeResource\Pages;
use App\Models\OrdreEntree;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class OrdreEntreeResource extends Resource
{
    protected static ?string $model           = OrdreEntree::class;
    protected static ?string $navigationIcon  = 'heroicon-o-inbox-arrow-down';
    protected static ?string $navigationLabel = 'Ordres d\'Entrée';
    protected static ?string $modelLabel      = 'Ordre d\'Entrée';
    protected static ?string $pluralModelLabel = 'Ordres d\'Entrée';
    protected static ?string $navigationGroup = 'Acquisition des biens';
    protected static ?int    $navigationSort  = 40;

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('Informations OE')
                ->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('numero')
                            ->label('N° OE')->disabled()->dehydrated(),

                        Forms\Components\TextInput::make('numero_ordre')
                            ->label('N° d\'ordre'),

                        Forms\Components\DatePicker::make('date_oe')
                            ->label('Date OE')->required(),
                    ]),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('reception_id')
                            ->label('Réception liée')
                            ->relationship('reception', 'numero')
                            ->searchable()->required(),

                        Forms\Components\Select::make('fournisseur_id')
                            ->label('Fournisseur')
                            ->relationship('fournisseur', 'raison_sociale')
                            ->searchable()->required(),
                    ]),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('numero_prise_en_charge')
                            ->label('N° Prise en charge (verso facture)'),

                        Forms\Components\DatePicker::make('date_prise_en_charge')
                            ->label('Date prise en charge'),
                    ]),

                    Forms\Components\TextInput::make('montant_total')
                        ->label('Montant total')->numeric()->suffix('FCFA'),

                    Forms\Components\Textarea::make('observations')
                        ->label('Observations')->rows(2),
                ]),

            Forms\Components\Section::make('Signatures')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('comptable_matieres_id')
                            ->label('Comptable-matières')
                            ->relationship('comptableMatieres', 'name')
                            ->searchable()->preload()->required(),

                        Forms\Components\Select::make('ordonnateur_id')
                            ->label('Ordonnateur-matières')
                            ->relationship('ordonnateur', 'name')
                            ->searchable()->preload()->required(),
                    ]),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Toggle::make('signe_comptable')
                            ->label('Signé comptable')->inline(false),
                        Forms\Components\Toggle::make('signe_ordonnateur')
                            ->label('Signé ordonnateur')->inline(false),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('N° OE')->searchable()->sortable()->weight('bold')->copyable(),

                Tables\Columns\TextColumn::make('date_oe')
                    ->label('Date')->date('d/m/Y')->sortable(),

                Tables\Columns\TextColumn::make('reception.numero')
                    ->label('Réception')->badge()->color('info'),

                Tables\Columns\TextColumn::make('fournisseur.raison_sociale')
                    ->label('Fournisseur')->limit(25),

                Tables\Columns\TextColumn::make('numero_prise_en_charge')
                    ->label('N° PC')->badge()->color('gray'),

                Tables\Columns\TextColumn::make('montant_total')
                    ->label('Montant')->money('XAF')->alignEnd(),

                Tables\Columns\TextColumn::make('signatures')
                    ->label('Signatures')
                    ->state(
                        fn($record) => ($record->signe_comptable ? '✅' : '⬜') . ' Comptable  ' .
                            ($record->signe_ordonnateur ? '✅' : '⬜') . ' Ordonnateur'
                    ),

                Tables\Columns\BadgeColumn::make('statut')
                    ->colors([
                        'secondary' => 'brouillon',
                        'success'   => 'signe',
                        'primary'   => 'transmis',
                        'danger'    => 'annule',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => $record->estModifiable()),

                Tables\Actions\Action::make('signer_comptable')
                    ->label('Signer (Comptable)')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->visible(fn($record) => !$record->signe_comptable)
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->signer('comptable');
                        Notification::make()->title('✅ Signé par le comptable')->success()->send();
                    }),

                Tables\Actions\Action::make('signer_ordonnateur')
                    ->label('Signer (Ordonnateur)')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn($record) => $record->signe_comptable && !$record->signe_ordonnateur)
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->signer('ordonnateur');
                        Notification::make()->title('✅ OE signé par l\'ordonnateur')->success()->send();
                    }),

                Tables\Actions\Action::make('transmettre')
                    ->label('Transmettre au budget')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->visible(fn($record) => $record->statut === 'signe')
                    ->requiresConfirmation()
                    ->modalDescription('L\'OE sera transmis au service du budget pour paiement.')
                    ->action(function ($record) {
                        $record->update(['statut' => 'transmis']);
                        Notification::make()->title('📤 OE transmis au budget')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOrdresEntree::route('/'),
            'view'   => Pages\ViewOrdreEntree::route('/{record}'),
            'edit'   => Pages\EditOrdreEntree::route('/{record}/edit'),
        ];
    }
}
