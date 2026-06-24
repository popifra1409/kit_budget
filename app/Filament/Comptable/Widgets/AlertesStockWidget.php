<?php

namespace App\Filament\Comptable\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\Article;

class AlertesStockWidget extends BaseWidget
{
    protected static ?int    $sort             = 2;
    protected static ?string $heading          = '⚠️ Alertes Stock';
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Article::query()
                    ->with('stock', 'categorieArticle', 'uniteMesure')
                    ->where('actif', true)
                    ->whereHas('stock', function ($q) {
                        $q->whereColumn('quantite_disponible', '<=', 'articles.seuil_alerte');
                    })
                    // ✅ jointure explicite pour le tri — remplace orderByRaw + defaultSort
                    ->join('stocks', 'stocks.article_id', '=', 'articles.id')
                    ->orderBy('stocks.quantite_disponible', 'asc')
                    ->select('articles.*') // ✅ évite les conflits de colonnes après le join
            )
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')->weight('bold')->copyable(),

                Tables\Columns\TextColumn::make('designation')
                    ->label('Article')->searchable()->limit(35),

                Tables\Columns\TextColumn::make('categorieArticle.libelle')
                    ->label('Catégorie')->badge()->color('gray'),

                Tables\Columns\TextColumn::make('stock_qty')
                    ->label('Qté disponible')
                    ->alignCenter()
                    ->badge()
                    ->color(fn($record) => $record->stock_qty <= 0 ? 'danger' : 'warning'),

                Tables\Columns\TextColumn::make('seuil_alerte')
                    ->label('Seuil alerte')
                    ->alignCenter()
                    ->badge()->color('gray'),

                Tables\Columns\TextColumn::make('uniteMesure.libelle')
                    ->label('Unité')->placeholder('—'),

                Tables\Columns\TextColumn::make('statut_alerte')
                    ->label('Statut')
                    ->getStateUsing(
                        fn($record) =>
                        $record->stock_qty <= 0 ? '🔴 Rupture' : '🟠 Critique'
                    )
                    ->badge()
                    ->color(fn($record) => $record->stock_qty <= 0 ? 'danger' : 'warning'),
            ])
            ->actions([
                Tables\Actions\Action::make('voir')
                    ->label('Voir')
                    ->icon('heroicon-o-eye')
                    ->url(fn($record) => route('filament.comptable.resources.articles.view', $record)),
            ])
            ->emptyStateHeading('✅ Aucune alerte stock')
            ->emptyStateDescription('Tous les articles sont au-dessus du seuil d\'alerte.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([5, 10, 25]);
    }
}
