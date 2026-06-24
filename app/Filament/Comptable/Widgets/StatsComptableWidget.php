<?php

namespace App\Filament\Comptable\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Article;
use App\Models\Stock;
use App\Models\ExpressionBesoin;
use App\Models\Reception;

class StatsComptableWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        // ✅ Articles en stock critique
        $articlesCritiques = Article::whereHas('stock', function ($q) {
            $q->whereColumn('quantite_disponible', '<=', 'articles.seuil_alerte')
                ->where('quantite_disponible', '>', 0);
        })->count();

        // ✅ Articles en rupture totale
        $articlesRupture = Article::whereHas('stock', function ($q) {
            $q->where('quantite_disponible', 0);
        })->where('actif', true)->count();

        // ✅ Valeur totale du stock
        $valeurStock = Stock::join('articles', 'stocks.article_id', '=', 'articles.id')
            ->selectRaw('SUM(stocks.quantite_disponible * articles.prix_unitaire_moyen) as valeur_totale')
            ->value('valeur_totale') ?? 0;

        // ✅ Expressions en attente de traitement
        $ebEnAttente = ExpressionBesoin::whereIn('statut', ['soumis', 'valide'])
            ->count();

        // ✅ Réceptions en attente d'intégration
        $receptionsEnAttente = Reception::whereNotIn('statut', ['integre', 'annule'])
            ->count();

        // ✅ Articles actifs total
        $totalArticles = Article::where('actif', true)->count();

        return [
            Stat::make('Articles en stock critique', $articlesCritiques)
                ->description("{$articlesRupture} en rupture totale")
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color($articlesCritiques > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-archive-box-x-mark')
                ->url(route('filament.comptable.resources.articles.index', ['tableFilters[stock_critique][isActive]' => true])),

            Stat::make('Valeur du stock', number_format($valeurStock, 0, ',', ' ') . ' FCFA')
                ->description("{$totalArticles} articles actifs")
                ->descriptionIcon('heroicon-o-archive-box')
                ->color('info')
                ->icon('heroicon-o-banknotes'),

            Stat::make('Expressions en attente', $ebEnAttente)
                ->description('Soumises ou validées — en cours de traitement')
                ->descriptionIcon('heroicon-o-clock')
                ->color($ebEnAttente > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-clipboard-document-list')
                ->url(route('filament.comptable.resources.expression-besoins.index')),

            Stat::make('Réceptions en attente', $receptionsEnAttente)
                ->description('À intégrer en stock')
                ->descriptionIcon('heroicon-o-truck')
                ->color($receptionsEnAttente > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-inbox-arrow-down'),
        ];
    }
}
