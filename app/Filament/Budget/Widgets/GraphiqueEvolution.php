<?php

namespace App\Filament\Budget\Widgets;

use Filament\Widgets\ChartWidget;

class GraphiqueEvolution extends ChartWidget
{
    protected static ?string $heading = 'Chart';

    protected function getData(): array
    {
        return [
            //
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
