<?php

namespace App\Filament\Budget\Widgets;

use App\Models\Transmission;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatistiquesTransmissionsWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $userId    = auth()->id();
        $dashboard = route('filament.budget.pages.dashboard');

        // ── Comptages ─────────────────────────────────────────
        $mesTachesEnAttente = Transmission::pourDestinataire($userId)
            ->enAttente()->count();

        $mesTachesUrgentes = Transmission::pourDestinataire($userId)
            ->enAttente()->urgentes()->count();

        $mesTachesEnRetard = Transmission::pourDestinataire($userId)
            ->enAttente()->get()
            ->filter(fn($t) => $t->estEnRetard())->count();

        $mesEnvois = Transmission::deExpediteur($userId)
            ->enAttente()->count();

        $stats = [

            Stat::make('Mes tâches en attente', $mesTachesEnAttente)
                ->description('Documents à traiter')
                ->descriptionIcon('heroicon-m-inbox')
                ->color('warning')
                ->chart($this->getChartData('destinataire', $userId))
                // ✅ Pointe vers le dashboard qui contient les widgets de transmission
                ->url($dashboard),

            Stat::make('Tâches urgentes', $mesTachesUrgentes)
                ->description('Priorité haute/urgente')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger')
                ->url($dashboard),

            Stat::make('En retard', $mesTachesEnRetard)
                ->description('Date limite dépassée')
                ->descriptionIcon('heroicon-m-clock')
                ->color('danger')
                ->url($dashboard),

            Stat::make('Mes envois en attente', $mesEnvois)
                ->description('En attente de traitement')
                ->descriptionIcon('heroicon-m-paper-airplane')
                ->color('info')
                ->chart($this->getChartData('expediteur', $userId))
                ->url($dashboard),
        ];

        // ── Stats globales pour admin ──────────────────────────
        if (auth()->user()->can('view_all_transmissions')) {

            $totalEnAttente = Transmission::enAttente()->count();
            $totalTraitees  = Transmission::where('statut', 'traite')
                ->whereBetween('date_traitement', [now()->subDays(7), now()])
                ->count();

            $stats[] = Stat::make('Total en attente (système)', $totalEnAttente)
                ->description('Toutes les transmissions')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color('gray')
                ->url($dashboard);

            $stats[] = Stat::make('Traitées (7 jours)', $totalTraitees)
                ->description('Dernière semaine')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->url($dashboard);
        }

        return $stats;
    }

    protected function getChartData(string $type, int $userId): array
    {
        $data = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();

            $count = Transmission::query()
                ->when($type === 'destinataire', fn($q) => $q->pourDestinataire($userId))
                ->when($type === 'expediteur',   fn($q) => $q->deExpediteur($userId))
                ->whereDate('date_transmission', $date)
                ->count();

            $data[] = $count;
        }

        return $data;
    }

    public static function canView(): bool
    {
        return auth()->check();
    }
}