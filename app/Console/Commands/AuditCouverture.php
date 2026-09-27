<?php
// app/Console/Commands/AuditCouverture.php

namespace App\Console\Commands;

use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Liste tous les modeles de app/Models et indique comment chacun est audite.
 * Code retour 1 s'il existe des modeles non couverts (utilisable en controle avant deploiement).
 *
 *   php artisan audit:couverture
 */
class AuditCouverture extends Command
{
    protected $signature = 'audit:couverture';

    protected $description = "Vérifie que chaque modèle de l'application est couvert par le journal d'audit";

    public function handle(): int
    {
        $lignes = [];
        $nonCouverts = 0;

        foreach (glob(app_path('Models/*.php')) as $fichier) {
            $classe = 'App\\Models\\' . pathinfo($fichier, PATHINFO_FILENAME);

            // Fichier illisible (erreur de syntaxe...) : signale au lieu de faire planter la commande
            if (!Audit::classeChargeable($classe)) {
                $erreur = null;
                try {
                    class_exists($classe);
                } catch (\Throwable $e) {
                    $erreur = $e->getMessage() . ' (ligne ' . $e->getLine() . ')';
                }

                if ($erreur) {
                    $nonCouverts++;
                    $lignes[] = [class_basename($classe), '—', "❌ FICHIER EN ERREUR : {$erreur}"];
                }
                continue;
            }

            if (!is_subclass_of($classe, Model::class)) {
                continue;
            }

            $module = Audit::moduleDe($classe);

            $horsMorphMap = !Audit::estExclu($classe) && !Audit::estDansMorphMap($classe);

            // L'exclusion volontaire prime : un modele exclu n'a pas besoin d'etre rattache a un module
            $statut = match (true) {
                Audit::estExclu($classe)                 => '⏭️  exclu volontairement',
                $module === null                         => '❌ NON COUVERT (absent de config/audit.php)',
                $horsMorphMap                            => '❌ ABSENT DE LA MORPH MAP (journalisation impossible)',
                Audit::estJournaliseNativement($classe)  => '✅ natif (LogsActivity)',
                default                                  => '✅ observateur',
            };

            if (($module === null && !Audit::estExclu($classe)) || $horsMorphMap) {
                $nonCouverts++;
            }

            $lignes[] = [class_basename($classe), Audit::libelleModule($module) ?? '—', $statut];
        }

        usort($lignes, fn($a, $b) => [$a[1], $a[0]] <=> [$b[1], $b[0]]);

        $this->table(['Modèle', 'Module', 'Audit'], $lignes);

        if ($nonCouverts > 0) {
            $this->error("{$nonCouverts} modèle(s) non couvert(s) : ajoutez-les dans config/audit.php "
                . "et/ou dans la morph map de AppServiceProvider.");
            return self::FAILURE;
        }

        $this->info('Tous les modèles sont couverts par l\'audit.');
        return self::SUCCESS;
    }
}
