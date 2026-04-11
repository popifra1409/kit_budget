<?php

namespace App\Filament\Comptable\Resources;

use App\Filament\Comptable\Resources\FicheStockResource\Pages;
use App\Models\FicheStock;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FicheStockResource extends Resource
{
    protected static ?string $model = FicheStock::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Fiches de Stock';
    protected static ?string $modelLabel = 'Fiche de Stock';
    protected static ?string $pluralModelLabel = 'Fiches de Stock';
    protected static ?string $navigationGroup = 'Comptabilité Matières';
    protected static ?int $navigationSort = 50;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('N°')->searchable()->sortable()->weight('bold'),

                Tables\Columns\TextColumn::make('date_mouvement')
                    ->label('Date')->date('d/m/Y')->sortable(),

                Tables\Columns\TextColumn::make('article.designation')
                    ->label('Article')->searchable()->limit(30),

                Tables\Columns\TextColumn::make('article.code')
                    ->label('Code')->badge()->color('gray'),

                Tables\Columns\BadgeColumn::make('type_mouvement')
                    ->label('Type')
                    ->colors([
                        'success' => 'entree',
                        'danger' => 'sortie',
                        'info' => 'retour',
                        'warning' => 'ajustement',
                        'primary' => 'transfert',
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'entree' => '⬆ Entrée',
                        'sortie' => '⬇ Sortie',
                        'retour' => '↩ Retour',
                        'ajustement' => '⚖ Ajustement',
                        'transfert' => '↔ Transfert',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('quantite')
                    ->label('Quantité')->alignCenter()->weight('bold'),

                Tables\Columns\TextColumn::make('stock_avant')
                    ->label('Stock avant')->alignCenter()->color('gray'),

                Tables\Columns\TextColumn::make('stock_apres')
                    ->label('Stock après')->alignCenter()->weight('bold')
                    ->color(
                        fn($record) => $record->stock_apres <= ($record->article?->seuil_alerte ?? 0)
                            ? 'danger' : 'success'
                    ),

                Tables\Columns\TextColumn::make('prix_unitaire')
                    ->label('P.U')->money('XAF')->alignEnd(),

                Tables\Columns\TextColumn::make('valeur_totale')
                    ->label('Valeur')->money('XAF')->alignEnd()->weight('bold'),

                Tables\Columns\TextColumn::make('reference_document')
                    ->label('Référence')->badge()->color('info'),
            ])
            ->defaultSort('date_mouvement', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type_mouvement')
                    ->options([
                        'entree' => 'Entrée',
                        'sortie' => 'Sortie',
                        'retour' => 'Retour',
                        'ajustement' => 'Ajustement',
                    ]),

                Tables\Filters\SelectFilter::make('article_id')
                    ->label('Article')
                    ->relationship('article', 'designation')
                    ->searchable()->preload(),
            ])
            ->recordUrl(null); // Lecture seule
    }

    public static function canCreate(): bool
    {
        return false;
    } // Créé automatiquement

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFichesStock::route('/'),
        ];
    }
}
