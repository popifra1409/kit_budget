<?php

namespace App\Filament\Comptable\Widgets;

use Filament\Widgets\Widget;
use App\Models\ExpressionBesoin;
use App\Models\Reception;
use App\Models\BonSortieProvisoire;
use App\Models\OrdreSortie;

class ActionsRequisesWidget extends Widget
{
    protected static ?int    $sort    = 4;
    protected static string  $view    = 'filament.comptable.widgets.actions-requises';
    protected int | string | array $columnSpan = 'full';

    public function getViewData(): array
    {
        $actions = [];

        // ✅ Expressions soumises à valider
        $ebSoumises = ExpressionBesoin::where('statut', 'soumis')->count();
        if ($ebSoumises > 0) {
            $actions[] = [
                'icon'     => '📋',
                'couleur'  => '#f59e0b',
                'titre'    => "{$ebSoumises} expression(s) de besoin à valider",
                'detail'   => 'Soumises par les services — en attente de validation comptable + livraison du disponible',
                'url'      => route('filament.comptable.resources.expression-besoins.index', ['tableFilters[statut][value]' => 'soumis']),
                'bouton'   => 'Valider',
            ];
        }

        // ✅ Expressions validées à faire signer par le DG
        $ebASign = ExpressionBesoin::where('statut', 'valide')->count();
        if ($ebASign > 0) {
            $actions[] = [
                'icon'     => '✍️',
                'couleur'  => '#3b82f6',
                'titre'    => "{$ebASign} expression(s) à soumettre au DG",
                'detail'   => 'Validées par le comptable — en attente de signature DG',
                'url'      => route('filament.comptable.resources.expression-besoins.index', ['tableFilters[statut][value]' => 'valide']),
                'bouton'   => 'Voir',
            ];
        }

        // ✅ Réceptions à intégrer en stock
        $receptionsAIntegrer = 0;
        if (class_exists(\App\Models\Reception::class)) {
            $receptionsAIntegrer = \App\Models\Reception::where('statut', 'signe')
                ->count();
        }
        if ($receptionsAIntegrer > 0) {
            $actions[] = [
                'icon'     => '📦',
                'couleur'  => '#10b981',
                'titre'    => "{$receptionsAIntegrer} réception(s) à intégrer en stock",
                'detail'   => 'PV signé — en attente d\'intégration comptable',
                'url'      => route('filament.comptable.resources.receptions.index'),
                'bouton'   => 'Intégrer',
            ];
        }

        // ✅ BSP à traiter
        $bspATraiter = 0;
        if (class_exists(\App\Models\BonSortieProvisoire::class)) {
            $bspATraiter = \App\Models\BonSortieProvisoire::whereNotIn('statut', ['execute', 'annule'])
                ->count();
        }
        if ($bspATraiter > 0) {
            $actions[] = [
                'icon'     => '📤',
                'couleur'  => '#8b5cf6',
                'titre'    => "{$bspATraiter} BSP en cours de traitement",
                'detail'   => 'Bons de sortie provisoire — en attente de signature ou exécution',
                'url'      => '#',
                'bouton'   => 'Voir',
            ];
        }

        // ✅ Articles sous seuil d'alerte
        $alertesStock = \App\Models\Article::whereHas('stock', function ($q) {
            $q->whereColumn('quantite_disponible', '<=', 'articles.seuil_alerte');
        })->where('actif', true)->count();
        if ($alertesStock > 0) {
            $actions[] = [
                'icon'     => '⚠️',
                'couleur'  => '#ef4444',
                'titre'    => "{$alertesStock} article(s) en dessous du seuil d'alerte",
                'detail'   => 'Pensez à déclencher une expression de besoin pour réapprovisionner',
                'url'      => route('filament.comptable.resources.articles.index'),
                'bouton'   => 'Voir le stock',
            ];
        }

        if (empty($actions)) {
            $actions[] = [
                'icon'    => '✅',
                'couleur' => '#10b981',
                'titre'   => 'Aucune action requise',
                'detail'  => 'Tout est à jour — aucune tâche urgente en attente.',
                'url'     => null,
                'bouton'  => null,
            ];
        }

        return ['actions' => $actions];
    }
}
