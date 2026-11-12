<?php

namespace App\Filament\Planification\Pages;

use App\Models\Exercice;
use App\Models\SousProgrammeEp;
use App\Services\Planification\EtatsActionsSousProgramme;
use Filament\Actions\Action as PageAction;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;

class RapportEtatsActions extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-right-on-rectangle';

    protected static ?string $navigationGroup = 'Rapports';

    protected static ?string $navigationLabel = 'États isolés des actions';

    protected static ?string $title = 'États isolés des actions d\'un sous-programme';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.planification.pages.etats-actions-sous-programme';

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
                ->helperText('Un état par action du sous-programme : une page dans le PDF, un onglet dans le classeur.')
                ->options(Exercice::orderByDesc('annee')->pluck('annee', 'id'))
                ->searchable()->live()->required(),
        ];
    }

    public function getRapport(): ?EtatsActionsSousProgramme
    {
        if (! $this->sous_programme_ep_id || ! $this->exercice_id) {
            return null;
        }

        $sousProgramme = SousProgrammeEp::find($this->sous_programme_ep_id);
        $exercice = Exercice::find($this->exercice_id);

        if (! $sousProgramme || ! $exercice) {
            return null;
        }

        return new EtatsActionsSousProgramme($sousProgramme, $exercice);
    }

    protected function getHeaderActions(): array
    {
        $url = fn(string $suffix) => fn() => $this->sous_programme_ep_id && $this->exercice_id
            ? route("planification.rapports.etats-actions.{$suffix}", [
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
