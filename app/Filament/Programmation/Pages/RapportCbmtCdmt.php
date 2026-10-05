<?php

namespace App\Filament\Programmation\Pages;

use App\Models\CbmtExercice;
use App\Models\CdmtExercice;
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

    /** CBMT affiché (obligatoire) : suffit pour les ressources et dépenses par titres. */
    public ?int $cbmt_exercice_id = null;

    /** CDMT de ce CBMT (facultatif) : ajoute le test de cohérence et l'Annexe B. */
    public ?int $cdmt_exercice_id = null;

    public function mount(): void
    {
        // Par défaut : le CBMT le plus récent
        $this->form->fill([
            'cbmt_exercice_id' => CbmtExercice::latest('id')->value('id'),
        ]);
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\Select::make('cbmt_exercice_id')
                    ->label('CBMT')
                    ->options(fn() => CbmtExercice::with(['planStrategiqueEp', 'exerciceReference'])
                        ->latest('id')
                        ->get()
                        ->mapWithKeys(fn($c) => [
                            $c->id => "{$c->numero} — exercice {$c->exerciceReference?->annee} ({$c->planStrategiqueEp?->libelle})",
                        ]))
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn() => $this->cdmt_exercice_id = null)
                    ->required(),

                Forms\Components\Select::make('cdmt_exercice_id')
                    ->label('CDMT (facultatif)')
                    ->placeholder('Aucun : CBMT seul')
                    ->options(fn() => CdmtExercice::where('cbmt_exercice_id', $this->cbmt_exercice_id)
                        ->latest('id')
                        ->get()
                        ->mapWithKeys(fn($c) => [$c->id => "{$c->numero} — {$c->version}"]))
                    ->live()
                    ->disabled(fn() => !$this->cbmt_exercice_id),
            ]),
        ];
    }

    public function getCbmt(): ?CbmtExercice
    {
        return $this->cbmt_exercice_id
            ? CbmtExercice::with(['planStrategiqueEp', 'exerciceReference'])->find($this->cbmt_exercice_id)
            : null;
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
        ])
            ->where('cbmt_exercice_id', $this->cbmt_exercice_id)
            ->find($this->cdmt_exercice_id);
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

    /**
     * Exports PDF et Excel, toujours disponibles dès qu'un CBMT est choisi :
     *  - CBMT seul        → exports du CBMT (ressources, dépenses par titres, équilibre) ;
     *  - CBMT + CDMT      → exports CBMT/CDMT (avec test de cohérence et annexes).
     */
    protected function getHeaderActions(): array
    {
        $url = function (string $format): ?string {
            if ($this->cdmt_exercice_id) {
                return route("programmation.rapports.cbmt-cdmt.{$format}", ['cdmt' => $this->cdmt_exercice_id]);
            }

            return $this->cbmt_exercice_id
                ? route("programmation.rapports.cbmt.{$format}", ['cbmt' => $this->cbmt_exercice_id])
                : null;
        };

        return [
            Action::make('exportPdf')
                ->label(fn() => $this->cdmt_exercice_id ? 'PDF (CBMT et CDMT)' : 'PDF (CBMT)')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->visible(fn() => $this->cbmt_exercice_id !== null)
                ->url(fn() => $url('pdf'))
                ->openUrlInNewTab(),

            Action::make('exportExcel')
                ->label(fn() => $this->cdmt_exercice_id ? 'Excel (CBMT et CDMT)' : 'Excel (CBMT)')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->visible(fn() => $this->cbmt_exercice_id !== null)
                ->url(fn() => $url('excel'))
                ->openUrlInNewTab(),
        ];
    }
}
