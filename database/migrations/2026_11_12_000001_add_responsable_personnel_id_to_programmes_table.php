<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le responsable d'un programme / sous-programme.
 *
 * La hiérarchie budgétaire (programme → sous-programme → action → activité → tâche) est
 * la seule du SIGB à ne porter aucun visage : `actions`, `activites` et
 * `sous_programmes_ep` ont tous un `responsable_id`, `programmes` n'en a pas. Le cadre
 * logique étendu qui descend jusqu'aux tâches doit afficher un service et un agent
 * responsables par ligne ; sans colonne, le rapport ne pourrait nommer personne au
 * niveau qu'il affiche en premier.
 *
 * Pourquoi `personnels` et non `users`, alors que les trois autres `responsable_id` du
 * module pointent vers `users` :
 *  - un responsable est un agent, pas un compte. `Personnel::user()` est une BelongsTo :
 *    la moitié du personnel n'a jamais eu de login, et la nommer responsable d'un
 *    sous-programme ne devrait pas dépendre de la délivrance d'un accès ;
 *  - `personnels` porte déjà le nom, le matricule, la fonction et le `service_id` que le
 *    rapport imprimera. Depuis `users`, il faudrait traverser la même table pour afficher
 *    un service ;
 *  - le précédent existe : `decisions_administratives.personnel_id` et le bénéficiaire
 *    d'un engagement (`Engagement::beneficiairePersonnel()`) nomment déjà des agents.
 *
 * Le nom est donc explicite — `responsable_personnel_id` — et non `responsable_id`. Deux
 * sens du mot « responsable » cohabitant dans le module, une colonne qui dit vers quoi
 * elle pointe est le seul moyen d'éviter qu'un futur lecteur joigne `users` dessus.
 *
 * `nullOnDelete()` ne protège pas ce qu'on croirait : `Personnel` est en SoftDeletes, donc
 * `$personnel->delete()` est un UPDATE de `deleted_at` et PostgreSQL n'évalue jamais la
 * contrainte. Le responsable supprimé continue de pointer sa ligne, et c'est la lecture
 * qui décide : la `belongsTo` de `Programme::responsablePersonnel()` est déclarée
 * `withTrashed()` pour que le nom reste imprimable quand l'agent est retiré — un cadre
 * logique qui perd son responsable sans rien dire serait un rapport faux, pas un rapport
 * propre. La contrainte ne mord donc que sur une purge réelle (`forceDelete()`, nettoyage
 * en SQL) ; elle garantit alors qu'aucun programme ne reste accroché à un identifiant mort.
 *
 * Pas d'héritage : un sous-programme sans responsable affiche le vide. Remonter au
 * programme parent ferait apparaître comme comptable d'un sous-programme quelqu'un qui
 * ne l'est pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programmes', function (Blueprint $table) {
            // Pas de `->after()` : PostgreSQL ne sait pas ordonner une colonne, et le
            // sucre de Laravel y devient un mensonge que la table dément à l'ouverture.
            $table->foreignId('responsable_personnel_id')
                ->nullable()
                ->comment('Agent responsable du programme ou du sous-programme — table personnels, pas users')
                ->constrained('personnels')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('programmes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsable_personnel_id');
        });
    }
};
