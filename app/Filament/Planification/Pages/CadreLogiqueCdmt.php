<?php

namespace App\Filament\Planification\Pages;

use App\Models\Exercice;
use App\Models\PlanStrategiqueEp;
use App\Models\SousProgrammeEp;
use App\Services\Planification\CadreLogiqueSousProgrammes;
use Filament\Actions\Action as PageAction;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;

class CadreLogiqueCdmt extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationGroup = 'Rapports';

    protected static ?string $navigationLabel = 'Cadre logique des sous-programmes (CDMT)';

    protected static ?string $title = 'Cadre stratégique et matrices du cadre logique (CDMT)';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.planification.pages.cadre-logique-cdmt';

    public ?int $plan_strategique_ep_id = null;

    public ?int $exercice_id = null;

    public ?int $sous_programme_ep_id = null;

    public function mount(): void
    {
        $this->form->fill([
            'exercice_id' => Exercice::getActif()?->id,
        ]);
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('plan_strategique_ep_id')
                ->label('Plan Stratégique de Performance (PSP)')
                ->options(PlanStrategiqueEp::pluck('libelle', 'id'))
                ->searchable()->live()->required(),

            Forms\Components\Select::make('exercice_id')
                ->label('Exercice de référence')
                ->helperText('Année de référence N : réalisation N-1, estimation N, valeurs cibles N+1 à N+3.')
                ->options(Exercice::orderByDesc('annee')->pluck('annee', 'id'))
                ->searchable()->live()->required(),

            Forms\Components\Select::make('sous_programme_ep_id')
                ->label('Sous-programme')
                ->helperText('Laisser vide pour ressortir le tableau 1 et toutes les matrices.')
                ->options(SousProgrammeEp::orderBy('code')->pluck('libelle', 'id'))
                ->searchable()->live()->placeholder('Tous les sous-programmes'),
        ];
    }

    public function getRapport(): ?CadreLogiqueSousProgrammes
    {
        if (! $this->plan_strategique_ep_id || ! $this->exercice_id) {
            return null;
        }

        $psp = PlanStrategiqueEp::find($this->plan_strategique_ep_id);
        $exercice = Exercice::find($this->exercice_id);

        if (! $psp || ! $exercice) {
            return null;
        }

        return new CadreLogiqueSousProgrammes(
            $psp,
            $exercice,
            $this->sous_programme_ep_id ? SousProgrammeEp::find($this->sous_programme_ep_id) : null,
        );
    }

    protected function getHeaderActions(): array
    {
        $url = fn(string $suffix) => fn() => $this->plan_strategique_ep_id && $this->exercice_id
            ? route("planification.rapports.cadre-logique.{$suffix}", [
                'psp' => $this->plan_strategique_ep_id,
                'exercice' => $this->exercice_id,
                'sousProgramme' => $this->sous_programme_ep_id,
            ])
            : null;

        return [
            PageAction::make('exportPdf')
                ->label('Télécharger PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->url($url('pdf'))
                ->openUrlInNewTab(),

            PageAction::make('exportExcel')
                ->label('Télécharger Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->url($url('excel'))
                ->openUrlInNewTab(),
        ];
    }
}
