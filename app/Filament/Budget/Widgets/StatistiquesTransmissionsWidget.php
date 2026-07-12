<?php

namespace App\Filament\Budget\Widgets;

use App\Models\Transmission;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatistiquesTransmissionsWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    // ✅ Polling 15s — se met à jour rapidement après clôture
    protected static ?string $pollingInterval = '15s';

    protected function getStats(): array
    {
        $userId    = auth()->id();
        $dashboard = route('filament.budget.pages.dashboard');

        // ── Comptages destinataire ─────────────────────────────
        $mesTachesEnAttente = Transmission::pourDestinataire($userId)
            ->enAttente()->count();

        $mesTachesUrgentes = Transmission::pourDestinataire($userId)
            ->enAttente()->urgentes()->count();

        $mesTachesEnRetard = Transmission::pourDestinataire($userId)
            ->enAttente()->get()
            ->filter(fn($t) => $t->estEnRetard())->count();

        $mesEnvois = Transmission::deExpediteur($userId)
            ->enAttente()->count();

        // ✅ Clôturées aujourd'hui par l'utilisateur (destinataire)
        $clotureeesAujourdhui = Transmission::pourDestinataire($userId)
            ->where('statut', 'traite')
            ->whereDate('date_traitement', today())
            ->count();

        // ✅ Clôturées par moi (expéditeur) = rappelées
        $rappeleesAujourdhui = Transmission::deExpediteur($userId)
            ->where('statut', 'annule')
            ->whereDate('date_traitement', today())
            ->count();

        $stats = [

            Stat::make('Mes tâches en attente', $mesTachesEnAttente)
                ->description('Documents à traiter')
                ->descriptionIcon('heroicon-m-inbox')
                ->color($mesTachesEnAttente > 0 ? 'warning' : 'success')
                ->chart($this->getChartData('destinataire', $userId))
                ->url($dashboard),

            Stat::make('Tâches urgentes', $mesTachesUrgentes)
                ->description('Priorité haute/urgente')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($mesTachesUrgentes > 0 ? 'danger' : 'success')
                ->url($dashboard),

            Stat::make('En retard', $mesTachesEnRetard)
                ->description('Date limite dépassée')
                ->descriptionIcon('heroicon-m-clock')
                ->color($mesTachesEnRetard > 0 ? 'danger' : 'success')
                ->url($dashboard),

            Stat::make('Mes envois en attente', $mesEnvois)
                ->description('En attente de traitement')
                ->descriptionIcon('heroicon-m-paper-airplane')
                ->color($mesEnvois > 0 ? 'info' : 'success')
                ->chart($this->getChartData('expediteur', $userId))
                ->url($dashboard),

            // ✅ Clôturées aujourd'hui — indicateur d'activité
            Stat::make('Clôturées aujourd\'hui', $clotureeesAujourdhui)
                ->description('Transmissions traitées ce jour')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->url($dashboard),
        ];

        // ── Stats globales pour admin ──────────────────────────
        if (auth()->user()->can('view_all_transmissions')) {

            $totalEnAttente = Transmission::enAttente()->count();

            $totalTraitees7j = Transmission::where('statut', 'traite')
                ->whereBetween('date_traitement', [now()->subDays(7), now()])
                ->count();

            $totalRetournees = Transmission::where('statut', 'retourne')
                ->whereBetween('date_traitement', [now()->subDays(7), now()])
                ->count();

            $stats[] = Stat::make('Total en attente (système)', $totalEnAttente)
                ->description('Toutes les transmissions')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color($totalEnAttente > 10 ? 'danger' : ($totalEnAttente > 0 ? 'warning' : 'success'))
                ->url($dashboard);

            $stats[] = Stat::make('Traitées (7 jours)', $totalTraitees7j)
                ->description('Dernière semaine')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->url($dashboard);

            $stats[] = Stat::make('Retournées (7 jours)', $totalRetournees)
                ->description('Pour correction')
                ->descriptionIcon('heroicon-m-arrow-uturn-left')
                ->color($totalRetournees > 0 ? 'warning' : 'gray')
                ->url($dashboard);
        }

        return $stats;
    }

    protected function getChartData(string $type, int $userId): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date    = now()->subDays($i)->startOfDay();
            $data[]  = Transmission::query()
                ->when($type === 'destinataire', fn($q) => $q->pourDestinataire($userId))
                ->when($type === 'expediteur',   fn($q) => $q->deExpediteur($userId))
                ->whereDate('date_transmission', $date)
                ->count();
        }
        return $data;
    }

    public static function canView(): bool
    {
        return auth()->check();
    }
}
