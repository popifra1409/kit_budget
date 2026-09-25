<?php

namespace App\Filament\SuiviEvaluation\Pages;

use App\Models\Exercice;
use App\Models\PlanStrategiqueEp;
use App\Services\SuiviEvaluation\MatriceArrimageService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class MatriceArrimage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationGroup = 'Tableaux de Bord';
    protected static ?string $navigationLabel = "Matrice d'arrimage stratégique";
    protected static ?string $title = "Matrice d'arrimage stratégique";
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.suivi-evaluation.pages.matrice-arrimage';

    // Filtres synchronises avec l'URL : lien partageable avec le responsable
    #[Url(as: 'psp')]
    public $plan_strategique_ep_id = null;

    #[Url(as: 'exercice')]
    public $exercice_id = null;

    #[Url(as: 'sp')]
    public $sous_programme_ep_id = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_matrice_arrimage') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'plan_strategique_ep_id' => $this->plan_strategique_ep_id ?? PlanStrategiqueEp::query()->latest('id')->value('id'),
            'exercice_id'            => $this->exercice_id ?? Exercice::getActif()?->id,
            'sous_programme_ep_id'   => $this->sous_programme_ep_id,
        ]);
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\Select::make('plan_strategique_ep_id')
                    ->label('Plan stratégique (PSP)')
                    ->options(fn() => PlanStrategiqueEp::pluck('libelle', 'id'))
                    ->live()
                    ->afterStateUpdated(fn(Forms\Set $set) => $set('sous_programme_ep_id', null))
                    ->required(),

                Forms\Components\Select::make('exercice_id')
                    ->label('Exercice')
                    ->options(fn() => Exercice::orderByDesc('annee')->pluck('annee', 'id'))
                    ->live()
                    ->required(),

                Forms\Components\Select::make('sous_programme_ep_id')
                    ->label('Sous-programme')
                    ->placeholder('Tous les sous-programmes visibles')
                    ->options(function (Forms\Get $get) {
                        $psp = PlanStrategiqueEp::find($get('plan_strategique_ep_id'));

                        return $psp
                            ? app(MatriceArrimageService::class)->sousProgrammesVisibles($psp, auth()->user())->pluck('libelle', 'id')
                            : [];
                    })
                    ->live(),
            ]),
        ];
    }

    public function getMatrice(): ?array
    {
        $psp = $this->plan_strategique_ep_id ? PlanStrategiqueEp::find((int) $this->plan_strategique_ep_id) : null;

        if (!$psp || !$this->exercice_id) {
            return null;
        }

        return app(MatriceArrimageService::class)->construire(
            $psp,
            (int) $this->exercice_id,
            auth()->user(),
            $this->sous_programme_ep_id ? (int) $this->sous_programme_ep_id : null
        );
    }

    protected function getHeaderActions(): array
    {
        $params = fn() => array_filter([
            'psp'            => $this->plan_strategique_ep_id,
            'exercice'       => $this->exercice_id,
            'sous_programme' => $this->sous_programme_ep_id,
        ]);

        $visible = fn() => $this->plan_strategique_ep_id && $this->exercice_id
            && auth()->user()->can('exporter_matrice_arrimage');

        return [
            Action::make('pdf')
                ->label('PDF (A3)')->icon('heroicon-o-document-arrow-down')->color('danger')
                ->visible($visible)
                ->url(fn() => route('suivi-evaluation.rapports.matrice.pdf', $params()))
                ->openUrlInNewTab(),

            Action::make('excel')
                ->label('Excel')->icon('heroicon-o-table-cells')->color('success')
                ->visible($visible)
                ->url(fn() => route('suivi-evaluation.rapports.matrice.excel', $params()))
                ->openUrlInNewTab(),
        ];
    }
}
