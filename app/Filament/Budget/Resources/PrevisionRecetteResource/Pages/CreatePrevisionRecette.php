<?php

namespace App\Filament\Budget\Resources\PrevisionRecetteResource\Pages;

use App\Filament\Budget\Resources\PrevisionRecetteResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class CreatePrevisionRecette extends CreateRecord
{
    protected static string $resource = PrevisionRecetteResource::class;

    // ── État de la génération ────────────────────────────────
    public int  $offset             = 0;
    public int  $batchSize          = 10;
    public int  $totalLignes        = 0;
    public int  $totalGenere        = 0;
    public bool $generationEnCours  = false;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    // =========================================================
    // APRÈS CRÉATION — lancer le premier batch
    // =========================================================
    protected function afterCreate(): void
    {
        set_time_limit(60);
        ini_set('memory_limit', '256M');

        $this->totalLignes = DB::table('lignes_previsions_recettes')
            ->where('prevision_recette_id', $this->record->id)
            ->count();

        if ($this->totalLignes === 0) {
            Notification::make()
                ->title('⚠️ Aucune ligne trouvée')
                ->warning()
                ->body('Ajoutez des lignes à la prévision avant de générer les mensuelles.')
                ->send();
            return;
        }

        $this->offset             = 0;
        $this->totalGenere        = 0;
        $this->generationEnCours  = true;

        // Premier batch automatique
        $this->genererBatch();
    }

    // =========================================================
    // BOUTON "Continuer la génération"
    // =========================================================
    protected function getHeaderActions(): array
    {
        return [
            Action::make('continuer_generation')
                ->label(function () {
                    $restant = $this->totalLignes - $this->totalGenere;
                    return "▶ Continuer ({$this->totalGenere}/{$this->totalLignes} — {$restant} restantes)";
                })
                ->icon('heroicon-o-arrow-right-circle')
                ->color('warning')
                ->size('lg')
                ->visible(fn() => $this->generationEnCours && $this->totalGenere < $this->totalLignes)
                ->action(function () {
                    set_time_limit(60);
                    ini_set('memory_limit', '256M');
                    $this->genererBatch();
                }),
        ];
    }

    // =========================================================
    // GÉNÉRATION D'UN BATCH DE 10 LIGNES × 12 MOIS
    // =========================================================
    public function genererBatch(): void
    {
        $previsionId = $this->record->id;

        // ✅ 100% SQL brut — aucun Eloquent, aucun cast decimal
        $prevision = DB::table('previsions_recettes')
            ->where('id', $previsionId)
            ->select('exercice_id')
            ->first();

        if (!$prevision) {
            Notification::make()->title('❌ Prévision introuvable')->danger()->send();
            $this->generationEnCours = false;
            return;
        }

        $exercice = DB::table('exercices')
            ->where('id', $prevision->exercice_id)
            ->select('id', 'annee')
            ->first();

        if (!$exercice) {
            Notification::make()->title('❌ Exercice introuvable')->danger()->send();
            $this->generationEnCours = false;
            return;
        }

        // Récupérer 10 lignes à partir de l'offset
        $lignes = DB::table('lignes_previsions_recettes')
            ->where('prevision_recette_id', $previsionId)
            ->selectRaw('id, CAST(montant_rectifie AS FLOAT) as montant')
            ->orderBy('id')
            ->offset($this->offset)
            ->limit($this->batchSize)
            ->get();

        if ($lignes->isEmpty()) {
            $this->generationEnCours = false;
            Notification::make()
                ->title('✅ Génération terminée')
                ->success()
                ->body("Toutes les {$this->totalLignes} lignes traitées — " . ($this->totalLignes * 12) . " prévisions créées.")
                ->send();
            return;
        }

        // Construire INSERT
        $now      = now()->toDateTimeString();
        $values   = [];
        $bindings = [];

        foreach ($lignes as $ligne) {
            $mensuel = round((float)$ligne->montant / 12, 2);

            for ($mois = 1; $mois <= 12; $mois++) {
                $values[]   = '(?,?,?,?,?,0,?,0,0,0,0,true,?,?)';
                $bindings[] = $ligne->id;       // ligne_prevision_recette_id
                $bindings[] = $exercice->id;    // exercice_id
                $bindings[] = $mois;            // mois
                $bindings[] = $exercice->annee; // annee
                $bindings[] = $mensuel;         // montant_prevu
                $bindings[] = -$mensuel;        // ecart
                $bindings[] = $now;             // created_at
                $bindings[] = $now;             // updated_at
            }
        }

        // INSERT 10 × 12 = 120 lignes en une requête
        DB::statement("
            INSERT INTO previsions_recettes_mensuelles
                (ligne_prevision_recette_id, exercice_id, mois, annee,
                 montant_prevu, montant_recouvre, ecart,
                 taux_realisation, montant_cumule_prevu,
                 montant_cumule_recouvre, taux_realisation_cumule,
                 actif, created_at, updated_at)
            VALUES " . implode(',', $values) . "
            ON CONFLICT ON CONSTRAINT unique_ligne_mois
            DO UPDATE SET
                montant_prevu = EXCLUDED.montant_prevu,
                exercice_id   = EXCLUDED.exercice_id,
                updated_at    = EXCLUDED.updated_at
        ", $bindings);

        // Avancer l'offset
        $this->offset      += $lignes->count();
        $this->totalGenere += $lignes->count();
        $restant            = $this->totalLignes - $this->totalGenere;

        if ($this->totalGenere >= $this->totalLignes) {
            // ✅ Terminé
            $this->generationEnCours = false;
            Notification::make()
                ->title('✅ Génération terminée')
                ->success()
                ->body("Toutes les {$this->totalLignes} lignes traitées — " . ($this->totalLignes * 12) . " prévisions créées.")
                ->persistent()
                ->send();
        } else {
            // ⏳ Batch suivant disponible
            Notification::make()
                ->title("⏳ Batch {$this->totalGenere}/{$this->totalLignes}")
                ->info()
                ->body("{$this->totalGenere} lignes traitées, {$restant} restantes. Cliquez sur « Continuer ».")
                ->send();
        }
    }
}
