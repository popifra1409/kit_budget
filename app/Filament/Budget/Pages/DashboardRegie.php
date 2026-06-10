<?php

namespace App\Filament\Budget\Pages;

use Filament\Pages\Page;
use App\Models\RegieAvance;
use App\Models\DepenseRegie;
use App\Models\BonCommandeRegie;
use App\Models\DecaissementRegie;
use App\Models\Exercice;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardRegie extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Tableau de bord Régies';
    protected static ?string $navigationGroup = 'Régies & Menu Dépenses';
    protected static ?int    $navigationSort  = 0;
    protected static string  $view            = 'filament.budget.pages.dashboard-regie';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_any_regie_avance') ?? false;
    }

    public function getExerciceActif(): ?\App\Models\Exercice
    {
        return Exercice::getActif();
    }

    public function getStats(): array
    {
        $exercice = $this->getExerciceActif();

        $regies = RegieAvance::where('statut', 'actif')
            ->when($exercice, fn($q) => $q->where('exercice_id', $exercice->id))
            ->when(
                !auth()->user()?->hasAnyRole(['super_admin', 'admin', 'daaf', 'agence_comptable']),
                fn($q) => $q->where('responsable_id', auth()->id())
            )
            ->with(['lignes', 'decaissements', 'depenses', 'bonsCommande'])
            ->get();

        return [
            'nb_regies_actives'   => $regies->where('type', 'rav')->count(),
            'nb_md_actifs'        => $regies->where('type', 'menu_depense')->count(),
            'total_alloue'        => $regies->sum('montant_alloue'),
            'total_decaisse'      => $regies->sum('montant_decaisse'),
            'total_depense'       => $regies->sum('montant_depense'),
            'total_disponible'    => $regies->sum('montant_disponible'),
            'taux_moyen'          => $regies->count() > 0
                ? round($regies->avg('taux_consommation'), 1)
                : 0,
            'regies'              => $regies,
        ];
    }

    public function getLivreJournal(): \Illuminate\Support\Collection
    {
        $exercice = $this->getExerciceActif();

        $depenses = DepenseRegie::with([
            'regieAvance',
            'ligneRegieAvance.nomenclature',
            'fournisseur',
        ])
            ->when(
                !auth()->user()?->hasAnyRole(['super_admin', 'admin', 'daaf', 'agence_comptable']),
                fn($q) => $q->whereHas(
                    'regieAvance',
                    fn($r) => $r->where('responsable_id', auth()->id())
                )
            )
            ->whereIn('statut', ['valide', 'paye'])
            ->when($exercice, fn($q) => $q->whereHas(
                'regieAvance',
                fn($r) => $r->where('exercice_id', $exercice->id)
            ))
            ->orderBy('date_depense')
            ->get()
            ->map(fn($d) => [
                'date'         => $d->date_depense,
                'numero'       => $d->numero,
                'type'         => 'Achat Direct',
                'objet'        => $d->objet,
                'fournisseur'  => $d->fournisseur?->raison_sociale ?? $d->fournisseur_libre,
                'nomenclature' => $d->ligneRegieAvance?->nomenclature?->code,
                'regie'        => $d->regieAvance?->numero,
                'montant_ht'   => $d->montant_ht  ?? 0,
                'montant_tva'  => $d->montant_tva ?? 0,
                'montant_ttc'  => $d->montant_ttc ?? 0,
                'montant_ir'   => $d->montant_ir  ?? 0,
                'net_a_payer'  => $d->net_a_payer ?? 0,
                'statut'       => $d->statut,
            ])
            ->toBase(); // ✅ Eloquent Collection → Support Collection

        $bcrs = BonCommandeRegie::with([
            'regieAvance',
            'ligneRegieAvance.nomenclature',
            'fournisseur',
        ])
            ->where('engage', true)
            ->whereNotIn('statut', ['annule'])
            ->when(
                !auth()->user()?->hasAnyRole(['super_admin', 'admin', 'daaf', 'agence_comptable']),
                fn($q) => $q->whereHas(
                    'regieAvance',
                    fn($r) => $r->where('responsable_id', auth()->id())
                )
            )
            ->when($exercice, fn($q) => $q->whereHas(
                'regieAvance',
                fn($r) => $r->where('exercice_id', $exercice->id)
            ))
            ->orderBy('date_emission')
            ->get()
            ->map(fn($b) => [
                'date'         => $b->date_emission,
                'numero'       => $b->numero,
                'type'         => 'BCR/BCM',
                'objet'        => $b->objet,
                'fournisseur'  => $b->fournisseur?->raison_sociale,
                'nomenclature' => $b->ligneRegieAvance?->nomenclature?->code,
                'regie'        => $b->regieAvance?->numero,
                'montant_ht'   => $b->montant_ht  ?? 0,
                'montant_tva'  => $b->montant_tva ?? 0,
                'montant_ttc'  => $b->montant_ttc ?? 0,
                'montant_ir'   => $b->montant_ir  ?? 0,
                'net_a_payer'  => $b->net_a_payer ?? 0,
                'statut'       => $b->statut,
            ])
            ->toBase(); // ✅ Eloquent Collection → Support Collection

        // ✅ Support Collection::merge() accepte les arrays sans getKey()
        return $depenses->merge($bcrs)->sortBy('date')->values();
    }
}
