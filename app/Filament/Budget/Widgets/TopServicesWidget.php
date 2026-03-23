<?php

namespace App\Filament\Budget\Widgets;

use App\Services\StatistiquesBudgetaires;
use Filament\Widgets\Widget;

class TopServicesWidget extends Widget
{
    protected static ?int $sort = 4;

    protected static string $view = 'filament.widgets.top-services-widget';

    protected int | string | array $columnSpan = 'full';

    public function getViewData(): array
    {
        return [
            'services' => StatistiquesBudgetaires::getTopServices(10),
        ];
    }
}
