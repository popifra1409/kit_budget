<?php

namespace App\Filament\Budget\Resources\ProgrammeResource\Pages;

use App\Filament\Budget\Resources\ProgrammeResource;
use App\Models\Programme;
use Filament\Resources\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;

class GenerationCadreLogique extends Page
{
    protected static string $resource = ProgrammeResource::class;

    protected static string $view = 'filament.resources.programme-resource.pages.generation-cadre-logique';

    protected static ?string $title = 'Générer le Cadre Logique';

    protected static ?string $navigationLabel = 'Générer Cadre Logique';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'annee' => now()->year,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Paramètres de génération')
                    ->schema([
                        Forms\Components\Select::make('programme_id')
                            ->label('Programme')
                            ->options(Programme::where('actif', true)->pluck('libelle', 'id'))
                            ->searchable()
                            ->placeholder('Tous les programmes')
                            ->helperText('Laissez vide pour tous les programmes'),

                        Forms\Components\TextInput::make('annee')
                            ->label('Année budgétaire')
                            ->required()
                            ->numeric()
                            ->default(now()->year),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Format de sortie')
                    ->schema([
                        Forms\Components\Radio::make('format')
                            ->label('Format')
                            ->options([
                                'excel' => 'Excel (.xlsx)',
                                'pdf' => 'PDF (.pdf)',
                            ])
                            ->default('excel')
                            ->required()
                            ->inline(),
                    ]),
            ])
            ->statePath('data');
    }

    public function generer()
    {
        $data = $this->form->getState();

        $params = [
            'programme_id' => $data['programme_id'] ?? null,
            'annee' => $data['annee'],
            'format' => $data['format'],
        ];

        // Rediriger vers la route de téléchargement
        return redirect()->route('cadre-logique.telecharger', $params);
    }

    protected function genererExcel($programmeId, $annee)
    {
        $export = new \App\Exports\CadreLogiqueExport($programmeId, $annee);

        $filename = 'cadre_logique_' . $annee;
        if ($programmeId) {
            $programme = \App\Models\Programme::find($programmeId);
            $filename .= '_' . $programme->code;
        }
        $filename .= '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download($export, $filename);
    }

    protected function genererPdf($programmeId, $annee)
    {
        $export = new \App\Exports\CadreLogiquePdf($programmeId, $annee);

        return $export->download();
    }

    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('generer')
                ->label('Générer le Cadre Logique')
                ->action('generer')
                ->color('primary')
                ->icon('heroicon-o-document-arrow-down'),
        ];
    }

    protected function getActions(): array
    {
        return [
            \Filament\Actions\Action::make('generer')
                ->label('Générer le Cadre Logique')
                ->action('generer')
                ->color('primary')
                ->icon('heroicon-o-document-arrow-down'),
        ];
    }
}
