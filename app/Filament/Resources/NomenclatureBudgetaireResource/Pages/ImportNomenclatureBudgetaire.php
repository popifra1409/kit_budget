<?php

namespace App\Filament\Resources\NomenclatureBudgetaireResource\Pages;

use App\Filament\Resources\NomenclatureBudgetaireResource;
use App\Imports\NomenclatureImport;
use Filament\Resources\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class ImportNomenclatureBudgetaire extends Page
{
    protected static string $resource = NomenclatureBudgetaireResource::class;

    protected static string $view = 'filament.resources.nomenclature-budgetaire-resource.pages.import-nomenclature-budgetaire';

    protected static ?string $title = 'Importer la Nomenclature';

    protected static ?string $navigationLabel = 'Importer';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'date_debut_validite' => now()->startOfYear(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Importer depuis Excel')
                    ->description('Importez votre nomenclature budgétaire depuis un fichier Excel (.xlsx)')
                    ->schema([
                        Forms\Components\FileUpload::make('file')
                            ->label('Fichier Excel')
                            ->required()
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel'
                            ])
                            ->maxSize(10240)
                            ->helperText('Formats acceptés : .xlsx, .xls (Max 10 Mo)')
                            ->columnSpanFull(),

                        Forms\Components\Select::make('sheet')
                            ->label('Feuille à importer')
                            ->options([
                                'DEPENSES' => 'DEPENSES - Classe 6',
                                'RECETTES' => 'RECETTES - Classe 7',
                            ])
                            ->required()
                            ->helperText('Sélectionnez la feuille du fichier Excel à importer'),

                        Forms\Components\DatePicker::make('date_debut_validite')
                            ->label('Date de début de validité')
                            ->required()
                            ->default(now()->startOfYear())
                            ->helperText('Date à partir de laquelle cette nomenclature sera valide'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Instructions')
                    ->schema([
                        Forms\Components\Placeholder::make('instructions')
                            ->label('')
                            ->content('
                                **Format du fichier Excel :**
                                
                                Le fichier doit contenir les colonnes suivantes :
                                - **code** : Code de la nomenclature (ex: 601, 601300)
                                - **libelle** : Libellé de la ligne budgétaire
                                - **ordre** (optionnel) : Ordre d\'affichage
                                
                                **Notes importantes :**
                                - La première ligne doit contenir les en-têtes
                                - Les codes doivent commencer par 6 (dépenses) ou 7 (recettes)
                                - La hiérarchie sera automatiquement détectée selon la longueur du code
                            ')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ])
            ->statePath('data');
    }

    public function import()
    {
        $data = $this->form->getState();

        try {
            $filePath = storage_path('app/public/' . $data['file']);

            if (!file_exists($filePath)) {
                Notification::make()
                    ->title('Fichier introuvable')
                    ->danger()
                    ->send();
                return;
            }

            $dateDebut = Carbon::parse($data['date_debut_validite']);
            $import = new NomenclatureImport($dateDebut);

            Excel::import($import, $filePath);

            Notification::make()
                ->title('Importation réussie')
                ->success()
                ->body('La nomenclature budgétaire a été importée avec succès.')
                ->send();

            return redirect()->route('filament.admin.resources.nomenclature-budgetaires.index');
        } catch (\Exception $e) {
            Notification::make()
                ->title('Erreur lors de l\'importation')
                ->danger()
                ->body($e->getMessage())
                ->send();
        }
    }

    protected function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('import')
                ->label('Importer')
                ->action('import')
                ->color('primary')
                ->icon('heroicon-o-arrow-down-tray'),
        ];
    }
}
