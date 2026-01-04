<?php

namespace App\Services;

use App\Models\Exercice;
use App\Models\Programme;
use App\Models\Action;
use App\Models\Activite;
use App\Models\Tache;
use App\Models\NomenclatureBudgetaire;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReconductionExerciceService
{
    protected array $mapping = [];
    protected array $stats = [
        'programmes' => 0,
        'actions' => 0,
        'activites' => 0,
        'taches' => 0,
        'nomenclatures' => 0,
    ];

    /**
     * Reconduire un exercice vers un nouvel exercice
     */
    public function reconduire(
        Exercice $exerciceSource,
        Exercice $exerciceCible,
        array $options = []
    ): array {
        // Options par défaut
        $options = array_merge([
            'reconduire_programmes' => true,
            'reconduire_nomenclature' => false,
            'ajuster_montants' => false,
            'pourcentage_ajustement' => 0,
        ], $options);

        // Réinitialiser le mapping et stats
        $this->mapping = [];
        $this->stats = [
            'programmes' => 0,
            'actions' => 0,
            'activites' => 0,
            'taches' => 0,
            'nomenclatures' => 0,
        ];

        DB::beginTransaction();

        try {
            // 1. Reconduire la nomenclature (optionnel)
            if ($options['reconduire_nomenclature']) {
                $this->reconduireNomenclature($exerciceSource, $exerciceCible);
            }

            // 2. Reconduire les programmes
            if ($options['reconduire_programmes']) {
                $this->reconduireProgrammes(
                    $exerciceSource,
                    $exerciceCible,
                    $options
                );
            }

            // 3. Marquer la reconduction comme effectuée
            $exerciceCible->reconduction_effectuee = true;
            $exerciceCible->exercice_source_id = $exerciceSource->id;
            $exerciceCible->save();

            DB::commit();

            return [
                'success' => true,
                'stats' => $this->stats,
                'message' => $this->getMessageSucces(),
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'stats' => $this->stats,
            ];
        }
    }

    /**
     * Reconduire la nomenclature budgétaire
     */
    protected function reconduireNomenclature(Exercice $exerciceSource, Exercice $exerciceCible): void
    {
        $nomenclatures = NomenclatureBudgetaire::where('exercice_id', $exerciceSource->id)
            ->orderBy('niveau')
            ->orderBy('code')
            ->get();

        foreach ($nomenclatures as $nomenclature) {
            $nouvelle = $nomenclature->replicate();
            $nouvelle->exercice_id = $exerciceCible->id;
            $nouvelle->exercice = $exerciceCible->annee;
            $nouvelle->version = 1;

            // Gérer le parent (mapping)
            if ($nomenclature->parent_id) {
                $nouvelle->parent_id = $this->mapping['nomenclatures'][$nomenclature->parent_id] ?? null;
            }

            $nouvelle->save();

            // Stocker le mapping
            $this->mapping['nomenclatures'][$nomenclature->id] = $nouvelle->id;
            $this->stats['nomenclatures']++;
        }
    }

    /**
     * Reconduire les programmes (et toute la hiérarchie)
     */
    protected function reconduireProgrammes(
        Exercice $exerciceSource,
        Exercice $exerciceCible,
        array $options
    ): void {
        // Récupérer les programmes principaux uniquement
        $programmes = Programme::where('exercice_id', $exerciceSource->id)
            ->whereNull('parent_id')
            ->where('niveau', 'programme')
            ->orderBy('code')
            ->get();

        foreach ($programmes as $programme) {
            $this->reconduireProgramme($programme, $exerciceCible, $options);
        }
    }

    /**
     * Reconduire un programme (et ses sous-programmes)
     */
    protected function reconduireProgramme(
        Programme $programme,
        Exercice $exerciceCible,
        array $options,
        ?int $parentId = null
    ): void {
        // Dupliquer le programme
        $nouveau = $programme->replicate();
        $nouveau->exercice_id = $exerciceCible->id;
        $nouveau->annee = $exerciceCible->annee;
        $nouveau->parent_id = $parentId;
        $nouveau->save();

        // Stocker le mapping
        $this->mapping['programmes'][$programme->id] = $nouveau->id;
        $this->stats['programmes']++;

        // Reconduire les sous-programmes
        if ($programme->sousProgrammes) {
            foreach ($programme->sousProgrammes as $sousProgramme) {
                $this->reconduireProgramme($sousProgramme, $exerciceCible, $options, $nouveau->id);
            }
        }

        // Reconduire les actions de ce programme
        foreach ($programme->actions as $action) {
            $this->reconduireAction($action, $exerciceCible, $nouveau->id, $options);
        }
    }

    /**
     * Reconduire une action
     */
    protected function reconduireAction(
        Action $action,
        Exercice $exerciceCible,
        int $programmeId,
        array $options
    ): void {
        $nouvelle = $action->replicate();
        $nouvelle->exercice_id = $exerciceCible->id;
        $nouvelle->programme_id = $programmeId;
        $nouvelle->save();

        // Stocker le mapping
        $this->mapping['actions'][$action->id] = $nouvelle->id;
        $this->stats['actions']++;

        // Reconduire les activités
        foreach ($action->activites as $activite) {
            $this->reconduireActivite($activite, $exerciceCible, $nouvelle->id, $options);
        }
    }

    /**
     * Reconduire une activité
     */
    protected function reconduireActivite(
        Activite $activite,
        Exercice $exerciceCible,
        int $actionId,
        array $options
    ): void {
        $nouvelle = $activite->replicate();
        $nouvelle->exercice_id = $exerciceCible->id;
        $nouvelle->action_id = $actionId;
        $nouvelle->save();

        // Stocker le mapping
        $this->mapping['activites'][$activite->id] = $nouvelle->id;
        $this->stats['activites']++;

        // Reconduire les tâches
        foreach ($activite->taches as $tache) {
            $this->reconduireTache($tache, $exerciceCible, $nouvelle->id, $options);
        }
    }

    /**
     * Reconduire une tâche (et ses sous-tâches)
     */
    protected function reconduireTache(
        Tache $tache,
        Exercice $exerciceCible,
        int $activiteId,
        array $options,
        ?int $parentId = null
    ): void {
        $nouvelle = $tache->replicate();
        $nouvelle->exercice_id = $exerciceCible->id;
        $nouvelle->activite_id = $activiteId;
        $nouvelle->parent_id = $parentId;

        // Ajuster les montants si demandé
        if ($options['ajuster_montants'] && $options['pourcentage_ajustement'] != 0) {
            $facteur = 1 + ($options['pourcentage_ajustement'] / 100);
            $nouvelle->ae = $tache->ae * $facteur;
            $nouvelle->cp = $tache->cp * $facteur;
        }

        // Mapper la nomenclature si elle a été reconduite
        if ($tache->nomenclature_id && isset($this->mapping['nomenclatures'][$tache->nomenclature_id])) {
            $nouvelle->nomenclature_id = $this->mapping['nomenclatures'][$tache->nomenclature_id];
        }

        $nouvelle->save();

        // Stocker le mapping
        $this->mapping['taches'][$tache->id] = $nouvelle->id;
        $this->stats['taches']++;

        // Reconduire les sous-tâches
        if ($tache->sousTaches) {
            foreach ($tache->sousTaches as $sousTache) {
                $this->reconduireTache($sousTache, $exerciceCible, $activiteId, $options, $nouvelle->id);
            }
        }
    }

    /**
     * Obtenir le message de succès
     */
    protected function getMessageSucces(): string
    {
        $message = "Reconduction effectuée avec succès :\n";
        $message .= "- {$this->stats['programmes']} programme(s)\n";
        $message .= "- {$this->stats['actions']} action(s)\n";
        $message .= "- {$this->stats['activites']} activité(s)\n";
        $message .= "- {$this->stats['taches']} tâche(s)\n";

        if ($this->stats['nomenclatures'] > 0) {
            $message .= "- {$this->stats['nomenclatures']} nomenclature(s)\n";
        }

        return $message;
    }

    /**
     * Obtenir les statistiques
     */
    public function getStats(): array
    {
        return $this->stats;
    }
}
