<?php

namespace App\Filament\Budget\Resources\RecetteReelleResource\Pages;

use App\Filament\Budget\Resources\RecetteReelleResource;
use Filament\Resources\Pages\Page;
use Filament\Actions\Action;
use App\Models\Exercice;
use App\Models\PrevisionRecette;
use Illuminate\Support\Facades\DB;

class SuiviRecettes extends Page
{
    protected static string $resource = RecetteReelleResource::class;
    protected static string $view     = 'filament.pages.suivi-recettes';

    public string $search      = '';
    public ?int   $exerciceId  = null;
    public ?int   $previsionId = null;
    public int    $lastRefresh = 0;  // ← force Livewire à re-render

    public function getTitle(): string
    {
        return 'Tableau de Suivi des Recettes';
    }

    public function mount(): void
    {
        $exercice         = Exercice::getActif();
        $this->exerciceId = $exercice?->id;

        if ($this->exerciceId) {
            $prevision = PrevisionRecette::where('exercice_id', $this->exerciceId)
                ->whereNotIn('statut', ['elaboration'])
                ->first();
            $this->previsionId = $prevision?->id;
        }

        $this->lastRefresh = time();
    }

    // =========================================================
    // DONNÉES — 100% SQL brut, toujours frais
    // =========================================================
    public function getData(): array
    {
        if (!$this->previsionId) return [];

        // ✅ 2 requêtes SQL au lieu de N requêtes Eloquent
        $lignesRaw = DB::table('lignes_previsions_recettes')
            ->where('prevision_recette_id', $this->previsionId)
            ->where('actif', true)
            ->when($this->search, function ($q) {
                $q->where(function ($q) {
                    $q->where('code_nomenclature', 'ilike', "%{$this->search}%")
                        ->orWhere('libelle_nomenclature', 'ilike', "%{$this->search}%");
                });
            })
            ->orderBy('ordre')
            ->select(
                'id',
                'code_nomenclature',
                'libelle_nomenclature',
                DB::raw('CAST(montant_rectifie AS FLOAT) as prevu_annuel')
            )
            ->get();

        if ($lignesRaw->isEmpty()) return [];

        $ligneIds = $lignesRaw->pluck('id')->toArray();

        // ✅ Charger toutes les mensuelles en une seule requête
        $mensuelles = DB::table('previsions_recettes_mensuelles')
            ->whereIn('ligne_prevision_recette_id', $ligneIds)
            ->whereNull('deleted_at')
            ->select(
                'ligne_prevision_recette_id',
                'mois',
                DB::raw('CAST(montant_prevu AS FLOAT) as montant_prevu'),
                DB::raw('CAST(montant_recouvre AS FLOAT) as montant_recouvre')
            )
            ->get()
            ->groupBy('ligne_prevision_recette_id');

        $anneeExercice = DB::table('exercices')
            ->where('id', $this->exerciceId)
            ->value('annee');

        $result = [];

        foreach ($lignesRaw as $ligne) {
            $mensuellesLigne = collect($mensuelles->get($ligne->id, collect()))
                ->keyBy('mois');

            $row = [
                'id'             => $ligne->id,
                'code'           => $ligne->code_nomenclature,
                'libelle'        => $ligne->libelle_nomenclature,
                'prevu_annuel'   => (float) $ligne->prevu_annuel,
                'mois'           => [],
                'cumul_prevu'    => 0,
                'cumul_recouvre' => 0,
                'taux_global'    => 0,
                'ecart'          => 0,
            ];

            $cumulPrevu    = 0;
            $cumulRecouvre = 0;

            for ($m = 1; $m <= 12; $m++) {
                $mensuelle = $mensuellesLigne->get($m);
                $prevu     = $mensuelle ? (float) $mensuelle->montant_prevu    : 0;
                $recouvre  = $mensuelle ? (float) $mensuelle->montant_recouvre : 0;
                $taux      = $prevu > 0 ? round(($recouvre / $prevu) * 100, 1) : null;

                $cumulPrevu    += $prevu;
                $cumulRecouvre += $recouvre;

                $row['mois'][$m] = [
                    'prevu'          => $prevu,
                    'recouvre'       => $recouvre,
                    'taux'           => $taux,
                    'cumul_prevu'    => $cumulPrevu,
                    'cumul_recouvre' => $cumulRecouvre,
                    'taux_cumul'     => $cumulPrevu > 0
                        ? round(($cumulRecouvre / $cumulPrevu) * 100, 1) : null,
                    'est_futur'      => $m > now()->month
                        && now()->year === $anneeExercice,
                ];
            }

            $row['cumul_prevu']    = $cumulPrevu;
            $row['cumul_recouvre'] = $cumulRecouvre;
            $row['ecart']          = $cumulRecouvre - $cumulPrevu;
            $row['taux_global']    = $cumulPrevu > 0
                ? round(($cumulRecouvre / $cumulPrevu) * 100, 1) : 0;

            $result[] = $row;
        }

        return $result;
    }

    /**
     * ✅ Passer les données fraîches à la vue à chaque render Livewire
     */
    protected function getViewData(): array
    {
        $data   = $this->getData();
        $totaux = $this->getTotauxParMois();

        $exercice = \DB::table('exercices')
            ->where('id', $this->exerciceId)
            ->select('id', 'annee')
            ->first();

        $totalPrevu    = collect($data)->sum('cumul_prevu');
        $totalRecouvre = collect($data)->sum('cumul_recouvre');
        $totalEcart    = $totalRecouvre - $totalPrevu;
        $tauxGlobal    = $totalPrevu > 0
            ? round(($totalRecouvre / $totalPrevu) * 100, 1) : 0;

        return [
            'data'          => $data,
            'totaux'        => $totaux,
            'exercice'      => $exercice,
            'exercices'     => $this->getExercices(),
            'previsions'    => $this->getPrevisions(),
            'moisActuel'    => now()->month,
            'anneeActuelle' => now()->year,
            'totalPrevu'    => $totalPrevu,
            'totalRecouvre' => $totalRecouvre,
            'totalEcart'    => $totalEcart,
            'tauxGlobal'    => $tauxGlobal,

            // ✅ AJOUTER — tableau des labels de mois
            'moisLabels'    => [
                'Jan',
                'Fév',
                'Mar',
                'Avr',
                'Mai',
                'Jun',
                'Jul',
                'Aoû',
                'Sep',
                'Oct',
                'Nov',
                'Déc',
            ],
        ];
    }

    public function getTotauxParMois(): array
    {
        $data   = $this->getData();
        $totaux = [];

        for ($m = 1; $m <= 12; $m++) {
            $totalPrevu    = collect($data)->sum(fn($r) => $r['mois'][$m]['prevu']    ?? 0);
            $totalRecouvre = collect($data)->sum(fn($r) => $r['mois'][$m]['recouvre'] ?? 0);
            $totaux[$m]    = [
                'prevu'    => $totalPrevu,
                'recouvre' => $totalRecouvre,
                'taux'     => $totalPrevu > 0
                    ? round(($totalRecouvre / $totalPrevu) * 100, 1) : null,
            ];
        }

        return $totaux;
    }

    // =========================================================
    // FILTRES
    // =========================================================

    public function getExercices(): array
    {
        return Exercice::orderByDesc('annee')->pluck('annee', 'id')->toArray();
    }

    public function getPrevisions(): array
    {
        if (!$this->exerciceId) return [];
        return PrevisionRecette::where('exercice_id', $this->exerciceId)
            ->whereNotIn('statut', ['elaboration'])
            ->pluck('libelle', 'id')
            ->toArray();
    }

    public function changerExercice(int $exerciceId): void
    {
        $this->exerciceId  = $exerciceId;
        $prevision = PrevisionRecette::where('exercice_id', $exerciceId)
            ->whereNotIn('statut', ['elaboration'])
            ->first();
        $this->previsionId = $prevision?->id;
        $this->lastRefresh = time();
    }

    public function changerPrevision(int $previsionId): void
    {
        $this->previsionId = $previsionId;
        $this->lastRefresh = time();
    }

    public function actualiser(): void
    {
        $this->lastRefresh = time();
    }

    // =========================================================
    // ACTIONS HEADER
    // =========================================================
    protected function getHeaderActions(): array
    {
        return [
            Action::make('actualiser')
                ->label('Actualiser')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->outlined()
                ->action(fn() => $this->actualiser()),

            Action::make('nouvelle_recette')
                ->label('Saisir une recette')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->url(fn() => RecetteReelleResource::getUrl('create')),
        ];
    }
}
