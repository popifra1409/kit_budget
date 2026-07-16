<?php

namespace App\Filament\Budget\Pages;

use App\Models\Exercice;
use App\Models\NomenclatureBudgetaire;
use App\Models\PrevisionBudgetProgramme;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SaisiePrevisionsBudgetProgrammePage extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-pencil-square';
    protected static ?string $navigationLabel = 'Saisie Prévisions';
    protected static ?string $navigationGroup = 'Gestion Budgétaire';
    protected static bool    $shouldRegisterNavigation = false; // Accessible via BudgetProgrammePage
    protected static string  $view = 'filament.pages.saisie-previsions-budget-programme';

    public int    $anneeRef;
    public int    $anneeN1;
    public int    $anneeN2;
    public string $categorie   = 'fonctionnement';
    public array  $previsions  = [];    // [nomenclature_id => ['n1' => montant, 'n2' => montant]]
    public bool   $isLoading   = false;

    public function mount(): void
    {
        $this->anneeRef = (int) request('annee', Exercice::getActif()?->annee ?? now()->year);
        $this->anneeN1  = $this->anneeRef + 1;
        $this->anneeN2  = $this->anneeRef + 2;
        $this->chargerPrevisions();
    }

    public function chargerPrevisions(): void
    {
        // Charger les prévisions déjà saisies pour N+1 et N+2
        $existantes = PrevisionBudgetProgramme::whereIn('annee', [$this->anneeN1, $this->anneeN2])
            ->where('type', 'prevision')
            ->where('categorie', $this->categorie)
            ->get();

        $this->previsions = [];
        foreach ($existantes as $prev) {
            $this->previsions[$prev->nomenclature_id][$prev->annee] = (float) $prev->montant;
        }
    }

    public function sauvegarder(): void
    {
        $exercice = Exercice::where('annee', $this->anneeRef)->first();
        if (!$exercice) {
            Notification::make()->danger()
                ->title("Exercice {$this->anneeRef} introuvable")
                ->send();
            return;
        }

        $count = 0;
        foreach ($this->previsions as $nomenclatureId => $montants) {
            foreach ($montants as $annee => $montant) {
                if ($montant >= 0) {
                    PrevisionBudgetProgramme::sauvegarder(
                        $exercice->id,
                        (int) $nomenclatureId,
                        (int) $annee,
                        'prevision',
                        (float) $montant,
                        $this->categorie
                    );
                    $count++;
                }
            }
        }

        Notification::make()->success()
            ->title("✅ {$count} prévisions sauvegardées")
            ->body("Prévisions {$this->anneeN1} et {$this->anneeN2} enregistrées.")
            ->send();
    }

    public function setPrevision(int $nomenclatureId, int $annee, float $montant): void
    {
        $this->previsions[$nomenclatureId][$annee] = $montant;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sauvegarder')
                ->label('💾 Sauvegarder')
                ->color('success')
                ->icon('heroicon-o-check')
                ->action(fn() => $this->sauvegarder()),

            Action::make('retour')
                ->label('← Retour')
                ->color('gray')
                ->url(fn() => route('filament.budget.pages.budget-programme-page')),
        ];
    }

    public function getNomenclaturesProperty()
    {
        return NomenclatureBudgetaire::where('code', 'like', '6%')
            ->orderBy('code')
            ->get()
            ->groupBy(fn($n) => substr($n->code, 0, 3));
    }

    public static function getUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?\Illuminate\Database\Eloquent\Model $tenant = null): string
    {
        return route('filament.budget.pages.saisie-previsions-budget-programme', $parameters);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_budget_programme') ?? false;
    }
}
