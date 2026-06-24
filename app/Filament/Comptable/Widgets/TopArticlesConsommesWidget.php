<?php

namespace App\Filament\Comptable\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\LigneExpressionBesoin;

class TopArticlesConsommesWidget extends BaseWidget
{
    protected static ?int    $sort    = 6;
    protected static ?string $heading = '🏆 Top Articles les plus demandés (exercice en cours)';
    protected int | string | array $columnSpan = 'full';

    // ✅ public, pas protected
    public function getTableRecordKey(\Illuminate\Database\Eloquent\Model $record): string
    {
        return (string) $record->article_id;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                LigneExpressionBesoin::query()
                    ->whereNotNull('article_id')
                    ->whereHas(
                        'expressionBesoin',
                        fn($q) =>
                        $q->whereYear('date_expression', now()->year)
                    )
                    ->selectRaw('
                        article_id,
                        SUM(quantite_demandee) as total_demande,
                        SUM(quantite_a_commander) as total_a_commander,
                        COUNT(*) as nb_expressions
                    ')
                    ->groupBy('article_id')
                    ->orderByDesc('total_demande')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('article.code')
                    ->label('Code')->weight('bold'),

                Tables\Columns\TextColumn::make('article.designation')
                    ->label('Article')->limit(35),

                Tables\Columns\TextColumn::make('article.categorieArticle.libelle')
                    ->label('Catégorie')->badge()->color('gray'),

                Tables\Columns\TextColumn::make('total_demande')
                    ->label('Qté demandée')
                    ->alignCenter()
                    ->badge()->color('warning'),

                Tables\Columns\TextColumn::make('total_a_commander')
                    ->label('Qté à commander')
                    ->alignCenter()
                    ->badge()->color('danger'),

                Tables\Columns\TextColumn::make('nb_expressions')
                    ->label('Nb expressions')
                    ->alignCenter()
                    ->badge()->color('info'),

                Tables\Columns\TextColumn::make('article.stock.quantite_disponible')
                    ->label('Stock actuel')
                    ->alignCenter()
                    ->badge()
                    ->color(
                        fn($record) => ($record->article?->stock?->quantite_disponible ?? 0)
                            <= ($record->article?->seuil_alerte ?? 0)
                            ? 'danger'
                            : 'success'
                    ),
            ])
            ->emptyStateHeading('Aucune demande enregistrée cette année')
            ->emptyStateIcon('heroicon-o-chart-bar')
            ->paginated(false);
    }
}
