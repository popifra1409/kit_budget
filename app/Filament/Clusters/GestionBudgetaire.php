<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class GestionBudgetaire extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'BUDGET';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'gestion-budgetaire';

    // Les groupes seront repliés par défaut
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $clusterNavigation = 'tabs';
}
