<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class GestionBudgetaire extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'BUDGET';

    protected static ?string $navigationGroup = 'Commandes & Engagement';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'gestion-budgetaire';
}
