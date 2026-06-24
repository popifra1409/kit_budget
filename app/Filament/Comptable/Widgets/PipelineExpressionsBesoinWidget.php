<?php

namespace App\Filament\Comptable\Widgets;

use Filament\Widgets\Widget;
use App\Models\ExpressionBesoin;

class PipelineExpressionsBesoinWidget extends Widget
{
    protected static ?int    $sort    = 3;
    protected static string  $view    = 'filament.comptable.widgets.pipeline-expressions-besoin';
    protected int | string | array $columnSpan = 'full';

    public function getViewData(): array
    {
        $stats = ExpressionBesoin::selectRaw('statut, COUNT(*) as total')
            ->groupBy('statut')
            ->pluck('total', 'statut')
            ->toArray();

        return [
            'pipeline' => [
                ['statut' => 'brouillon',   'label' => 'Brouillon',           'icon' => '📝', 'color' => 'gray',    'count' => $stats['brouillon']   ?? 0],
                ['statut' => 'soumis',      'label' => 'Soumis',              'icon' => '📤', 'color' => 'warning', 'count' => $stats['soumis']      ?? 0],
                ['statut' => 'valide',      'label' => 'Validé (comptable)',   'icon' => '✅', 'color' => 'success', 'count' => $stats['valide']      ?? 0],
                ['statut' => 'signe_dg',    'label' => 'Signé DG',            'icon' => '✍️', 'color' => 'primary', 'count' => $stats['signe_dg']    ?? 0],
                ['statut' => 'en_commande', 'label' => 'En commande',         'icon' => '🛒', 'color' => 'info',    'count' => $stats['en_commande'] ?? 0],
                ['statut' => 'satisfait',   'label' => 'Satisfait',           'icon' => '🎯', 'color' => 'success', 'count' => $stats['satisfait']   ?? 0],
            ],
            'total' => ExpressionBesoin::count(),
            'ce_mois' => ExpressionBesoin::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)->count(),
        ];
    }
}
