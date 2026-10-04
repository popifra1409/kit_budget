<?php

namespace App\Filament\Admin\Pages;

use App\Models\ParametreExecution;
use App\Services\ParametresExecution;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Administration > Paramètres d'exécution budgétaire.
 * Chaque enregistrement crée une version datée (date d'effet + motif) des seuls paramètres modifiés.
 */
class ParametresExecutionPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static ?string $navigationGroup = 'Paramétrage';
    protected static ?string $navigationLabel = "Paramètres d'exécution budgétaire";
    protected static ?string $title = "Paramètres d'exécution budgétaire";
    protected static ?string $slug = 'parametres-execution';
    protected static string $view = 'filament.admin.pages.parametres-execution';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && ($user->hasAnyRole(['super_admin', 'admin']) || $user->can('gerer_parametres_execution'));
    }

    public function mount(): void
    {
        $this->form->fill([
            'valeurs'    => ParametresExecution::valeurs(),
            'date_effet' => now()->toDateString(),
            'motif'      => null,
        ]);
    }

    public function form(Form $form): Form
    {
        $definitions = ParametresExecution::definitions();

        $sections = collect(config('execution.groupes', []))->map(function ($titre, $groupe) use ($definitions) {
            $champs = collect($definitions)
                ->filter(fn($def) => ($def['groupe'] ?? null) === $groupe)
                ->map(fn($def, $cle) => $this->champ($cle, $def))
                ->values()
                ->all();

            return Forms\Components\Section::make($titre)->schema($champs)->columns(2)->collapsible();
        })->values()->all();

        return $form
            ->schema(array_merge($sections, [
                Forms\Components\Section::make('Application des modifications')
                    ->description('Seuls les paramètres modifiés sont enregistrés, avec leur date d\'effet. Les opérations antérieures gardent la règle de leur date.')
                    ->schema([
                        Forms\Components\DatePicker::make('date_effet')
                            ->label("Date d'effet")->required()->native(false)->displayFormat('d/m/Y'),
                        Forms\Components\TextInput::make('motif')
                            ->label('Motif / texte de référence')
                            ->placeholder('Ex. : Instruction du 22 janvier 2026')
                            ->maxLength(255),
                    ])->columns(2),
            ]))
            ->statePath('data');
    }

    protected function champ(string $cle, array $def): Forms\Components\Field
    {
        $nom = "valeurs.{$cle}";

        $champ = match ($def['type']) {
            'booleen' => Forms\Components\Toggle::make($nom),
            'choix'   => Forms\Components\Select::make($nom)->options($def['options'] ?? [])->required()->native(false),
            'decimal' => Forms\Components\TextInput::make($nom)->numeric()->step('0.01')->minValue(0)->required(),
            default   => Forms\Components\TextInput::make($nom)->numeric()->integer()->minValue(0)->required(),
        };

        $champ->label($def['libelle']);

        if (!empty($def['unite']) && method_exists($champ, 'suffix')) {
            $champ->suffix($def['unite']);
        }

        if (!empty($def['aide'])) {
            $champ->helperText($def['aide']);
        }

        return $champ;
    }

    public function enregistrer(): void
    {
        $data = $this->form->getState();

        $modifies = ParametresExecution::enregistrer($data['valeurs'] ?? [], $data['date_effet'], $data['motif'] ?? null);

        if (empty($modifies)) {
            Notification::make()->info()->title('Aucune modification')->body('Toutes les valeurs sont identiques à celles en vigueur à cette date.')->send();
            return;
        }

        $definitions = ParametresExecution::definitions();
        $detail = collect($modifies)->map(fn($v, $cle) => '• ' . $definitions[$cle]['libelle'] . ' : '
            . ParametresExecution::libelleValeur($cle, $v[0]) . ' → ' . ParametresExecution::libelleValeur($cle, $v[1]))
            ->implode("\n");

        Notification::make()->success()->title(count($modifies) . ' paramètre(s) mis à jour')->body($detail)->persistent()->send();

        $this->mount();
    }

    /** Historique des valeurs enregistrées (les plus récentes d'abord). */
    public function getHistorique()
    {
        return ParametreExecution::with('auteur')->orderByDesc('date_effet')->orderByDesc('id')->limit(100)->get();
    }
}
