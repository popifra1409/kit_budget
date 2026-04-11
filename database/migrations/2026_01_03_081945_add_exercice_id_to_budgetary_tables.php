<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. PROGRAMMES
        if (Schema::hasTable('programmes') && !Schema::hasColumn('programmes', 'exercice_id')) {
            Schema::table('programmes', function (Blueprint $table) {
                $table->foreignId('exercice_id')->nullable()->after('id')->constrained('exercices')->onDelete('cascade');
                $table->index('exercice_id');
            });

            // Migrer les données existantes : utiliser 'annee' pour lier à l'exercice
            DB::statement("
                UPDATE programmes p
                SET exercice_id = (
                    SELECT id FROM exercices e WHERE e.annee = p.annee LIMIT 1
                )
                WHERE p.exercice_id IS NULL
            ");
        }

        // 2. ACTIONS
        if (Schema::hasTable('actions') && !Schema::hasColumn('actions', 'exercice_id')) {
            Schema::table('actions', function (Blueprint $table) {
                $table->foreignId('exercice_id')->nullable()->after('id')->constrained('exercices')->onDelete('cascade');
                $table->index('exercice_id');
            });

            // Migrer via le programme parent
            DB::statement("
                UPDATE actions a
                SET exercice_id = (
                    SELECT p.exercice_id FROM programmes p WHERE p.id = a.programme_id LIMIT 1
                )
                WHERE a.exercice_id IS NULL
            ");
        }

        // 3. ACTIVITES
        if (Schema::hasTable('activites') && !Schema::hasColumn('activites', 'exercice_id')) {
            Schema::table('activites', function (Blueprint $table) {
                $table->foreignId('exercice_id')->nullable()->after('id')->constrained('exercices')->onDelete('cascade');
                $table->index('exercice_id');
            });

            // Migrer via l'action parent
            DB::statement("
                UPDATE activites a
                SET exercice_id = (
                    SELECT ac.exercice_id FROM actions ac WHERE ac.id = a.action_id LIMIT 1
                )
                WHERE a.exercice_id IS NULL
            ");
        }

        // 4. TACHES
        if (Schema::hasTable('taches') && !Schema::hasColumn('taches', 'exercice_id')) {
            Schema::table('taches', function (Blueprint $table) {
                $table->foreignId('exercice_id')->nullable()->after('id')->constrained('exercices')->onDelete('cascade');
                $table->index('exercice_id');
            });

            // Migrer via l'activité parent
            DB::statement("
                UPDATE taches t
                SET exercice_id = (
                    SELECT a.exercice_id FROM activites a WHERE a.id = t.activite_id LIMIT 1
                )
                WHERE t.exercice_id IS NULL
            ");
        }

        // 5. BUDGETS
        if (Schema::hasTable('budgets') && !Schema::hasColumn('budgets', 'exercice_id')) {
            Schema::table('budgets', function (Blueprint $table) {
                $table->foreignId('exercice_id')->nullable()->after('id')->constrained('exercices')->onDelete('cascade');
                $table->index('exercice_id');
            });

            // Migrer via le champ 'exercice' existant
            DB::statement("
                UPDATE budgets b
                SET exercice_id = (
                    SELECT id FROM exercices e WHERE e.annee = b.exercice LIMIT 1
                )
                WHERE b.exercice_id IS NULL AND b.exercice IS NOT NULL
            ");
        }

        // 6. BORDEREAUX D'ENGAGEMENT
        if (Schema::hasTable('bordereaux_engagement') && !Schema::hasColumn('bordereaux_engagement', 'exercice_id')) {
            Schema::table('bordereaux_engagement', function (Blueprint $table) {
                $table->foreignId('exercice_id')->nullable()->after('id')->constrained('exercices')->onDelete('cascade');
                $table->index('exercice_id');
            });

            // Migrer via le champ 'exercice' existant
            DB::statement("
                UPDATE bordereaux_engagement b
                SET exercice_id = (
                    SELECT id FROM exercices e WHERE e.annee = b.exercice LIMIT 1
                )
                WHERE b.exercice_id IS NULL AND b.exercice IS NOT NULL
            ");
        }

        // 7. ENGAGEMENTS
        if (Schema::hasTable('engagements') && !Schema::hasColumn('engagements', 'exercice_id')) {
            Schema::table('engagements', function (Blueprint $table) {
                $table->foreignId('exercice_id')->nullable()->after('id')->constrained('exercices')->onDelete('cascade');
                $table->index('exercice_id');
            });

            // Migrer via le champ 'exercice' existant
            DB::statement("
                UPDATE engagements e
                SET exercice_id = (
                    SELECT id FROM exercices ex WHERE ex.annee = e.exercice LIMIT 1
                )
                WHERE e.exercice_id IS NULL AND e.exercice IS NOT NULL
            ");
        }

        // 8. BONS DE COMMANDE
        if (Schema::hasTable('bons_commande') && !Schema::hasColumn('bons_commande', 'exercice_id')) {
            Schema::table('bons_commande', function (Blueprint $table) {
                $table->foreignId('exercice_id')->nullable()->after('id')->constrained('exercices')->onDelete('cascade');
                $table->index('exercice_id');
            });

            // Migrer via la date d'émission (année)
            DB::statement("
                UPDATE bons_commande b
                SET exercice_id = (
                    SELECT id FROM exercices e WHERE e.annee = EXTRACT(YEAR FROM b.date_emission) LIMIT 1
                )
                WHERE b.exercice_id IS NULL AND b.date_emission IS NOT NULL
            ");
        }

        // 9. DECISIONS ADMINISTRATIVES (si la table existe)
        if (Schema::hasTable('decisions_administratives') && !Schema::hasColumn('decisions_administratives', 'exercice_id')) {
            Schema::table('decisions_administratives', function (Blueprint $table) {
                $table->foreignId('exercice_id')->nullable()->after('id')->constrained('exercices')->onDelete('cascade');
                $table->index('exercice_id');
            });
        }
    }

    public function down(): void
    {
        // Supprimer les colonnes dans l'ordre inverse
        $tables = [
            'decisions_administratives',
            'bons_commande',
            'engagements',
            'bordereaux_engagement',
            'budgets',
            'taches',
            'activites',
            'actions',
            'programmes',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'exercice_id')) {
                Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    $blueprint->dropForeign(["{$table}_exercice_id_foreign"]);
                    $blueprint->dropColumn('exercice_id');
                });
            }
        }
    }
};
