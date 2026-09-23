<?php

namespace App\Filament\Programmation\Pages;

use App\Models\CdmtExercice;
use App\Models\CbmtLigne;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms;
use Filament\Actions\Action;

class RapportCbmtCdmt extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationGroup = 'Rapports';

    protected static ?string $navigationLabel = 'Rapport CBMT/CDMT';

    protected static string $view = 'filament.programmation.pages.rapport-cbmt-cdmt';

    public ?int $cdmt_exercice_id = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('cdmt_exercice_id')
                ->label('CDMT')
                ->options(
                    CdmtExercice::with('cbmtExercice.planStrategiqueEp')
                        ->get()
                        ->mapWithKeys(fn($c) => [
                            $c->id => "{$c->numero} — {$c->version} ({$c->cbmtExercice?->planStrategiqueEp?->libelle})",
                        ])
                )
                ->searchable()
                ->live()
                ->required(),
        ];
    }

    public function getCdmt(): ?CdmtExercice
    {
        if (!$this->cdmt_exercice_id) {
            return null;
        }

        return CdmtExercice::with([
            'cbmtExercice.planStrategiqueEp',
            'cbmtExercice.exerciceReference',
            'lignes.sousProgrammeEp',
            'lignes.action',
            'lignes.activite',
        ])->find($this->cdmt_exercice_id);
    }

    public function getTableau9(): \Illuminate\Support\Collection
    {
        return $this->getCdmt()?->cbmtExercice->lignesRessources()->get()
            ->groupBy('titre')
            ->map(fn($lignes, $titre) => [
                'titre' => $titre,
                'libelle' => CbmtLigne::TITRES_RESSOURCES[$titre] ?? $lignes->first()->libelle_titre,
                'lignes' => $lignes,
                'total_n_moins_1' => $lignes->sum('montant_n_moins_1'),
                'total_n' => $lignes->sum('montant_n'),
                'total_n_plus_1' => $lignes->sum('montant_n_plus_1'),
                'total_n_plus_2' => $lignes->sum('montant_n_plus_2'),
                'total_n_plus_3' => $lignes->sum('montant_n_plus_3'),
            ])->values() ?? collect();
    }

    public function getTableau10(): \Illuminate\Support\Collection
    {
        return $this->getCdmt()?->cbmtExercice->lignesDepenses()->get()
            ->groupBy('titre')
            ->map(fn($lignes, $titre) => [
                'titre' => $titre,
                'libelle' => CbmtLigne::TITRES_DEPENSES[$titre] ?? $lignes->first()->libelle_titre,
                'total_n_moins_1' => $lignes->sum('montant_n_moins_1'),
                'total_n' => $lignes->sum('montant_n'),
                'total_n_plus_1' => $lignes->sum('montant_n_plus_1'),
                'total_n_plus_2' => $lignes->sum('montant_n_plus_2'),
                'total_n_plus_3' => $lignes->sum('montant_n_plus_3'),
            ])->values() ?? collect();
    }

    /**
     * Annexe B - memo de programmation financiere du triennat,
     * groupe par Sous-Programme > Action.
     */
    public function getAnnexeB(): \Illuminate\Support\Collection
    {
        $cdmt = $this->getCdmt();
        if (!$cdmt) return collect();

        return $cdmt->lignes->groupBy('sous_programme_ep_id')->map(function ($lignesSp) {
            $parAction = $lignesSp->groupBy('action_id')->map(function ($lignesAction) {
                return [
                    'action' => $lignesAction->first()->action,
                    'lignes' => $lignesAction->map(fn($l) => array_merge($l->toArray(), [
                        'cout_total' => $l->avant_n_moins_1 + $l->n_ae + $l->n_plus_1_ae + $l->n_plus_2_ae + $l->n_plus_3_ae,
                    ])),
                    'total_action_ae' => $lignesAction->sum(fn($l) => $l->avant_n_moins_1 + $l->n_ae + $l->n_plus_1_ae + $l->n_plus_2_ae + $l->n_plus_3_ae),
                ];
            });

            return [
                'sous_programme' => $lignesSp->first()->sousProgrammeEp,
                'actions' => $parAction,
                'total_sp' => $lignesSp->sum(fn($l) => $l->avant_n_moins_1 + $l->n_ae + $l->n_plus_1_ae + $l->n_plus_2_ae + $l->n_plus_3_ae),
            ];
        })->values();
    }

    /**
     * Annexe C - programmation des depenses par activites (N+1 a N+3, AE/CP).
     */
    public function getAnnexeC(): \Illuminate\Support\Collection
    {
        $cdmt = $this->getCdmt();
        if (!$cdmt) return collect();

        return $cdmt->lignes->groupBy('sous_programme_ep_id')->map(function ($lignesSp) {
            return [
                'sous_programme' => $lignesSp->first()->sousProgrammeEp,
                'actions' => $lignesSp->groupBy('action_id')->map(fn($l) => [
                    'action' => $l->first()->action,
                    'lignes' => $l,
                ]),
            ];
        })->values();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportPdf')
                ->label('Télécharger PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->visible(fn() => $this->cdmt_exercice_id !== null)
                ->url(fn() => route('programmation.rapports.cbmt-cdmt.pdf', ['cdmt' => $this->cdmt_exercice_id]))
                ->openUrlInNewTab(),

            Action::make('exportExcel')
                ->label('Télécharger Excel')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->visible(fn() => $this->cdmt_exercice_id !== null)
                ->url(fn() => route('programmation.rapports.cbmt-cdmt.excel', ['cdmt' => $this->cdmt_exercice_id]))
                ->openUrlInNewTab(),
        ];
    }
}
