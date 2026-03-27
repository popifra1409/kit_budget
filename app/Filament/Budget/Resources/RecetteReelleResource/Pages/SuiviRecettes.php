<?php

namespace App\Filament\Budget\Resources\RecetteReelleResource\Pages;

use App\Filament\Budget\Resources\RecetteReelleResource;
use Filament\Resources\Pages\Page;
use Filament\Actions\Action;
use App\Models\Exercice;
use App\Models\PrevisionRecette;
use App\Models\LignePrevisionRecette;
use App\Models\PrevisionRecetteMensuelle;

class SuiviRecettes extends Page
{
    protected static string $resource = RecetteReelleResource::class;
    protected static string $view     = 'filament.pages.suivi-recettes';

    public string $search = '';

    public function getTitle(): string
    {
        return 'Tableau de Suivi des Recettes';
    }

    public ?int $exerciceId  = null;
    public ?int $previsionId = null;

    public function mount(): void
    {
        $exercice          = Exercice::getActif();
        $this->exerciceId  = $exercice?->id;

        if ($this->exerciceId) {
            $prevision         = PrevisionRecette::where('exercice_id', $this->exerciceId)
                ->whereNotIn('statut', ['elaboration'])
                ->first();
            $this->previsionId = $prevision?->id;
        }
    }

    public function getData(): array
    {
        if (!$this->previsionId) return [];

        $lignes = LignePrevisionRecette::with(['previsionsMensuelles.recettesReelles'])
            ->where('prevision_recette_id', $this->previsionId)
            ->where('actif', true)
            ->when($this->search, function ($q) {
                $q->where(function ($q) {
                    $q->where('code_nomenclature', 'ilike', "%{$this->search}%")
                        ->orWhere('libelle_nomenclature', 'ilike', "%{$this->search}%");
                });
            })
            ->orderBy('ordre')
            ->get();

        $result = [];

        foreach ($lignes as $ligne) {
            $row = [
                'id'             => $ligne->id,
                'code'           => $ligne->code_nomenclature,
                'libelle'        => $ligne->libelle_nomenclature,
                'prevu_annuel'   => (float) $ligne->montant_rectifie,
                'mois'           => [],
                'cumul_prevu'    => 0,
                'cumul_recouvre' => 0,
                'taux_global'    => 0,
                'ecart'          => 0,
            ];

            $mensuellesParMois = $ligne->previsionsMensuelles->keyBy('mois');
            $cumulPrevu        = 0;
            $cumulRecouvre     = 0;
            $anneeExercice     = Exercice::find($this->exerciceId)?->annee;

            for ($m = 1; $m <= 12; $m++) {
                $mensuelle = $mensuellesParMois->get($m);
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
        $prevision         = PrevisionRecette::where('exercice_id', $exerciceId)
            ->whereNotIn('statut', ['elaboration'])
            ->first();
        $this->previsionId = $prevision?->id;
    }

    public function changerPrevision(int $previsionId): void
    {
        $this->previsionId = $previsionId;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('nouvelle_recette')
                ->label('Saisir une recette')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->url(RecetteReelleResource::getUrl('create')),
        ];
    }
}
