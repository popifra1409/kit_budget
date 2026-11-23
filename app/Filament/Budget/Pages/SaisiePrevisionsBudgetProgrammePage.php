<?php

namespace App\Filament\Budget\Pages;

use App\Models\Exercice;
use App\Models\NomenclatureBudgetaire;
use App\Models\PrevisionBudgetProgramme;
use App\Services\Budget\ControlePlafondCbmt;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SaisiePrevisionsBudgetProgrammePage extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-pencil-square';
    protected static ?string $navigationLabel = 'Saisie Prévisions';
    protected static ?string $navigationGroup = 'Budget & crédits';
    protected static ?int $navigationSort = 5;
protected static bool    $shouldRegisterNavigation = false; // Accessible via BudgetProgrammePage
    protected static string  $view = 'filament.pages.saisie-previsions-budget-programme';

    public int    $anneeRef;
    public int    $anneeN1;
    public int    $anneeN2;
    public string $type        = 'depense';   // 'depense' ou 'recette'
    public array  $previsions  = [];    // [nomenclature_id => ['n1' => montant, 'n2' => montant]]
    public bool   $isLoading   = false;

    public function mount(): void
    {
        $this->anneeRef = (int) request('annee', Exercice::getActif()?->annee ?? now()->year);
        $this->anneeN1  = $this->anneeRef + 1;
        $this->anneeN2  = $this->anneeRef + 2;
        $this->type     = in_array(request('type'), ['depense', 'recette']) ? request('type') : 'depense';
        $this->chargerPrevisions();
    }

    /**
     * Basculer entre saisie des dépenses et des recettes
     */
    public function changerType(string $type): void
    {
        if (!in_array($type, ['depense', 'recette'])) {
            return;
        }

        $this->type = $type;
        $this->chargerPrevisions();
    }

    public function chargerPrevisions(): void
    {
        // Charger les prévisions déjà saisies pour N+1 et N+2.
        // La nature (dépense/recette) est portée par la nomenclature, PAS par `categorie`, dont
        // la contrainte CHECK ne connaît que 'fonctionnement' et 'investissement'.
        $existantes = PrevisionBudgetProgramme::whereIn('annee', [$this->anneeN1, $this->anneeN2])
            ->where('type', 'prevision')
            ->whereHas('nomenclature', fn($q) => $q->where('type', $this->type))
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

        // Un lot par année visée : le plafond CBMT s'oppose année par année.
        $lots = [];
        $negatives = 0;
        foreach ($this->previsions as $nomenclatureId => $montants) {
            foreach ($montants as $annee => $montant) {
                if ($montant === null || $montant === '') {
                    continue; // champ vidé : rien à écrire pour cette cellule
                }
                $montant = (float) $montant;
                if ($montant < 0) {
                    $negatives++;
                    continue;
                }
                $lots[(int) $annee][] = ['nomenclature_id' => (int) $nomenclatureId, 'montant' => $montant];
            }
        }

        $controlle = app(ControlePlafondCbmt::class);
        $count = 0;
        $refusees = 0;

        foreach ($lots as $annee => $lot) {
            $decision = $controlle->decider($this->type, (int) $annee, $lot, $exercice->id);

            foreach ($decision['acceptees'] as $ligne) {
                PrevisionBudgetProgramme::sauvegarder(
                    $exercice->id,
                    (int) $ligne['nomenclature_id'],
                    (int) $annee,
                    'prevision',
                    (float) $ligne['montant']
                );
                $count++;
            }

            foreach ($decision['refus'] as $refus) {
                $refusees += (int) $refus['nb_lignes'];
                Notification::make()->danger()
                    ->title('Plafond CBMT — ' . ($refus['libelle'] ?? 'titre indéterminé') . " · {$annee}")
                    ->body($controlle->messageRefus($refus))
                    ->send();
            }
        }

        if ($negatives > 0) {
            Notification::make()->warning()
                ->title("{$negatives} valeur(s) négative(s) ignorée(s)")
                ->body('Une prévision négative ne peut pas être enregistrée.')
                ->send();
        }

        if ($count === 0) {
            if ($refusees === 0 && $negatives === 0) {
                Notification::make()->warning()
                    ->title('Aucune prévision à enregistrer')
                    ->body("Saisissez au moins un montant pour {$this->anneeN1} ou {$this->anneeN2}.")
                    ->send();
            } else {
                Notification::make()->danger()
                    ->title("0 prévision enregistrée, {$refusees} refusée(s)")
                    ->body('Aucune ligne n’a passé le contrôle des plafonds du CBMT.')
                    ->send();
            }
            return;
        }

        Notification::make()->success()
            ->title("✅ {$count} prévision(s) sauvegardée(s)")
            ->body($refusees === 0
                ? "Prévisions {$this->type} pour {$this->anneeN1} et {$this->anneeN2} enregistrées."
                : "{$refusees} ligne(s) refusée(s) par le plafond CBMT — voir les notices ci-dessus.")
            ->send();
    }

    public function setPrevision(int $nomenclatureId, int $annee, float $montant): void
    {
        $this->previsions[$nomenclatureId][$annee] = $montant;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('changer_type')
                ->label($this->type === 'depense' ? '💸 Dépenses' : '💰 Recettes')
                ->color($this->type === 'depense' ? 'danger' : 'success')
                ->icon('heroicon-o-arrows-right-left')
                ->action(fn() => $this->changerType($this->type === 'depense' ? 'recette' : 'depense')),

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

    /**
     * Nomenclatures du type courant (depense/recette), triées et groupées
     * par groupe de nomenclature (avec fallback "Non classées / Hors groupe").
     */
    public function getNomenclaturesProperty()
    {
        return NomenclatureBudgetaire::where('type', $this->type)
            ->where('actif', true)
            ->with('groupe')
            ->orderBy('code')
            ->get()
            ->sortBy(fn($n) => sprintf('%04d-%s', $n->groupe?->ordre ?? 9999, $n->code))
            ->groupBy(fn($n) => $n->groupe?->libelle ?? 'Non classées / Hors groupe');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_budget_programme') ?? false;
    }
}
