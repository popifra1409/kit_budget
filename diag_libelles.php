<?php
// diag_libelles.php  (fichier temporaire, a supprimer apres usage)
// ⚠️ Remplacez par l'email du compte avec lequel vous vous connectez
$email = 'votre.email@exemple.com';

use App\Models\Action;
use App\Models\Exercice;
use App\Models\PlanStrategiqueEp;
use App\Models\Programme;
use App\Models\SousProgrammeEp;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

$ok = fn ($c) => $c ? '✅' : '❌';

echo "\n=== 1. STRUCTURE DE LA BASE (migrations) ===\n";
$table = (new SousProgrammeEp)->getTable();
echo $ok(Schema::hasColumn($table, 'code_programme_ep')) . " Colonne {$table}.code_programme_ep\n";
echo $ok(Schema::hasColumn('activites', 'responsable_id')) . " Colonne activites.responsable_id\n";
echo $ok(Schema::hasColumn('rapport_activite_lignes', 'source_realisation')) . " Colonne rapport_activite_lignes.source_realisation\n";

echo "\n=== 2. PERMISSIONS (seeder) ===\n";
foreach (['access_module_planification', 'view_arborescence_libelles', 'view_all_arborescence_libelles', 'exporter_arborescence_libelles'] as $p) {
    echo $ok(Permission::where('name', $p)->exists()) . " {$p}\n";
}

echo "\n=== 3. VOTRE UTILISATEUR ({$email}) ===\n";
$user = User::where('email', $email)->first();
if (!$user) {
    echo "❌ Utilisateur introuvable : corrigez \$email en haut du script\n";
} else {
    echo "Rôles : " . ($user->getRoleNames()->implode(', ') ?: 'AUCUN') . "\n";
    foreach (['access_module_planification', 'view_arborescence_libelles', 'view_all_arborescence_libelles'] as $p) {
        try {
            echo $ok($user->can($p)) . " peut {$p}\n";
        } catch (\Throwable $e) {
            echo "❌ {$p} : permission absente de la base\n";
        }
    }
}

echo "\n=== 4. EXERCICE ACTIF ===\n";
$actif = Exercice::getActif();
echo $ok($actif) . ' Exercice actif : ' . ($actif ? "#{$actif->id} ({$actif->annee})" : 'AUCUN') . "\n";

echo "\n=== 5. DONNEES DE PLANIFICATION ===\n";
echo 'PSP en base : ' . PlanStrategiqueEp::count() . "\n";
foreach (SousProgrammeEp::orderBy('code')->get() as $sp) {
    $nbActions = (Schema::hasColumn($table, 'code_programme_ep') && $actif)
        ? $sp->actionsPourExercice($actif->id)->count()
        : 0;
    printf("  %s %s | PSP #%s | code_programme_ep=%s | responsable_id=%s | actions=%d\n",
        $ok($nbActions > 0), $sp->code, $sp->plan_strategique_ep_id,
        $sp->code_programme_ep ?? 'NULL', $sp->responsable_id ?? 'NULL', $nbActions);
}

echo "\n=== 6. PROGRAMMES DE L'EXERCICE ACTIF ===\n";
if ($actif) {
    foreach (Programme::withoutGlobalScope('exercice')->where('exercice_id', $actif->id)->orderBy('code')->get() as $p) {
        printf("  %s | niveau=%s | actions=%d | %s\n", $p->code, $p->niveau ?? '—',
            Action::withoutGlobalScope('exercice')->where('programme_id', $p->id)->count(), mb_substr($p->libelle, 0, 45));
    }
}

echo "\n=== 7. CONFIGURATION ===\n";
echo $ok(config('planification.libelles')) . " config/planification.php chargé\n";
echo $ok(\Illuminate\Support\Facades\Route::has('planification.libelles.pdf')) . " Route planification.libelles.pdf\n";