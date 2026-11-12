<?php

namespace App\Filament\Planification\Pages;

use App\Models\Exercice;
use App\Models\SousProgrammeEp;
use App\Services\Planification\CadreLogiqueEtendu;
use Filament\Actions\Action as PageAction;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;

class CadreLogiqueModeleEtendu extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationGroup = 'Rapports';

    protected static ?string $navigationLabel = 'Cadre logique — modèle étendu';

    protected static ?string $title = 'Cadre logique étendu — nomenclature, AE/CP et tâches';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.planification.pages.cadre-logique-modele-etendu';

    public ?int $sous_programme_ep_id = null;

    public ?int $exercice_id = null;

    public function mount(): void
    {
        $this->form->fill([
            'exercice_id' => Exercice::getActif()?->id,
        ]);
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('sous_programme_ep_id')
                ->label('Sous-programme')
                ->options(SousProgrammeEp::orderBy('code')->pluck('libelle', 'id'))
                ->searchable()->live()->required(),

            Forms\Components\Select::make('exercice_id')
                ->label('Exercice de référence')
                ->helperText('Année N : les AE/CP des tâches portent l\'exercice N, les agrégats remontent de la tâche à l\'action.')
                ->options(Exercice::orderByDesc('annee')->pluck('annee', 'id'))
                ->searchable()->live()->required(),
        ];
    }

    public function getRapport(): ?CadreLogiqueEtendu
    {
        if (! $this->sous_programme_ep_id || ! $this->exercice_id) {
            return null;
        }

        $sousProgramme = SousProgrammeEp::find($this->sous_programme_ep_id);
        $exercice = Exercice::find($this->exercice_id);

        if (! $sousProgramme || ! $exercice) {
            return null;
        }

        return new CadreLogiqueEtendu($sousProgramme, $exercice);
    }

    protected function getHeaderActions(): array
    {
        $url = fn(string $suffix) => fn() => $this->sous_programme_ep_id && $this->exercice_id
            ? route("planification.rapports.cadre-logique-etendu.{$suffix}", [
                'sousProgramme' => $this->sous_programme_ep_id,
                'exercice' => $this->exercice_id,
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
