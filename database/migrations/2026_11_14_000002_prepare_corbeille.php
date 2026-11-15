<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * C2a — le socle de colonnes de la corbeille, sans aucun comportement.
 *
 * La corbeille demandée (« les éléments supprimés dans tout SIGB, les restaurer, sauf suppression
 * définitive ») ne peut pas s'écrire sur l'existant pour une raison précise : `deleted_at` seul ne
 * raconte rien. Il dit qu'une ligne est morte, pas QUI l'a tuée, ni AVEC QUOI elle est morte. Or
 * un document sans ses lignes est un document vide, et une corbeille qui ne sait pas dire qui a
 * supprimé ne peut pas être purgée en toute confiance.
 *
 * Trois colonnes, deux familles de tables :
 *  - `deleted_by` (bigint, nullable, → users) : l'auteur du geste. `nullOnDelete()` et non
 *    RESTRICT, parce que `users` est hors corbeille par décision : un compte supprimé ne doit pas
 *    rendre 76 tables impurigeables, et la colonne n'est qu'une signature.
 *  - `corbeille_group` (uuid, nullable) : le jeton du GESTE, pas de la ligne. Parent et lignes
 *    supprimés ensemble portent le même, `restaurer()` remonte par le jeton — ce qui laisse
 *    délibérément sur place une ligne jetée à la main avant son parent (Étape C4).
 *  - `deleted_at` : absent sur 24 tables d'enfants, parce qu'elles mouraient en CASCADE avec leur
 *    parent et n'ont jamais eu besoin de leur propre mort. Une corbeille qui n'a pas la colonne ne
 *    voit rien en `onlyTrashed()` : le `delete()` du modèle resterait une suppression physique
 *    silencieuse.
 *
 * Le périmètre (décisions du 2026-11-14) : les trois panneaux de planification + les documents de
 * gestion (DA → BC → engagement → ordonnance, régies, marchés) + les référentiels déjà souples qui
 * les portent (fournisseurs, offres, personnes, services). EXCLUS, et ces tables ne sont pas
 * touchées : `Comptable` (journaux, écritures), `activity_log`, `users`, `roles`, les référentiels
 * techniques durs, et le fil de l'eau — `mouvement_bordereaux`, `bordereau_mouvements`,
 * `mouvements_collectifs`, `liquidation_preuves` — qui reste DUR volontairement : une écriture
 * comptable qui disparaît avec son bordereau n'est plus un journal. Elle survivra donc au parent
 * en corbeille ; c'est Étape C2b (et son refus affiché) qui tranche, pas la colonne.
 *
 * `if not exists` sur l'index partiel, rien de tel sur les colonnes : rejouer cette migration doit
 * échouer plutôt que masquer un état à moitié écrit.
 */
return new class extends Migration
{
    /** Tables déjà souples : elles reçoivent les deux colonnes du geste. */
    private const DEJA_SOUPLES = [
        'actions', 'activites', 'avenants', 'bons_commande', 'bons_commande_regies',
        'bordereaux_engagement', 'budgets', 'cbmt_exercices', 'cdmt_exercices',
        'csp_ministere_sante', 'decisions_administratives', 'depenses_regies',
        'dossiers_fournisseurs', 'engagements', 'etat_configs', 'exercices', 'extrants',
        'factures_proforma', 'fiches_presence', 'fournisseurs', 'indicateurs',
        'lignes_bon_commande', 'lignes_budgetaires', 'lignes_previsions_recettes', 'liquidations',
        'marches', 'memoires_depense', 'modes_paiement', 'nomenclature_budgetaire', 'offres',
        'ordonnances_paiement', 'ordres_service', 'paiements_exceptionnels',
        'parametres_fournisseur', 'parametres_structure', 'personnels', 'pieces_dossier',
        'plans_strategiques_ep', 'ppa_exercices', 'previsions_recettes',
        'previsions_recettes_mensuelles', 'programmes', 'rapports_activites_periodiques',
        'rapports_annuels_performance', 'recettes_reelles', 'regies_avances', 'services',
        'sous_programmes_ep', 'taches', 'tiers_recettes', 'types_engagement',
        'virements_budgetaires',
    ];

    /** Parents de la corbeille qui n'avaient jamais eu leur propre mort : lignes, pivots, et les
     *  trois documents à recouvrer. Trois colonnes, dont `deleted_at`. */
    private const SANS_DELETED_AT = [
        'bordereau_engagement_lignes', 'budget_collectif', 'cbmt_lignes', 'cdmt_lignes',
        'collectifs_budgetaires', 'commission_membres', 'commissions', 'decaissements_regies',
        'evaluations', 'fiche_presence_membres', 'lignes_bons_commande_regies',
        'lignes_depenses_regies', 'lignes_engagement', 'lignes_factures_proforma',
        'lignes_memoire_depense', 'lignes_regies_avances', 'menu_depense_decisions',
        'nomenclature_cadre_logique', 'objectifs_principaux', 'objectifs_specifiques',
        'prevision_recette_collectif', 'provision_consommations', 'rapport_activite_lignes',
        'valeurs_indicateurs',
    ];

    public function up(): void
    {
        foreach (self::SANS_DELETED_AT as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->softDeletes();
                $this->colonnesGeste($t);
            });
        }

        foreach (self::DEJA_SOUPLES as $table) {
            Schema::table($table, fn(Blueprint $t) => $this->colonnesGeste($t));
        }

        // La corbeille listerait `deleted_at is not null` sur 76 tables : un index complet paierait
        // 76 index sur la colonne d'une table où l'immense majorité des lignes est vivante.
        foreach ($this->tables() as $table) {
            DB::statement("create index if not exists corbeille_{$table}_deleted_at
                           on {$table} (deleted_at) where deleted_at is not null");
        }
    }

    public function down(): void
    {
        foreach ($this->tables() as $table) {
            DB::statement("drop index if exists corbeille_{$table}_deleted_at");

            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropForeign(['deleted_by']);
                $t->dropColumn(['deleted_by', 'corbeille_group']);
                // `deleted_at` ne se retire QUE là où cette migration l'a posé : le rendre
                // nullable-absent sur une table déjà souple ferait de toutes ses suppressions des
                // meurtres physiques silencieux.
                if (in_array($table, self::SANS_DELETED_AT, true)) {
                    $t->dropColumn('deleted_at');
                }
            });
        }
    }

    private function colonnesGeste(Blueprint $t): void
    {
        $t->foreignId('deleted_by')
            ->nullable()
            ->comment('Auteur du geste de suppression — null = supprimé avant la corbeille, ou par un script sans utilisateur connecté')
            ->constrained('users')
            ->nullOnDelete();

        $t->uuid('corbeille_group')
            ->nullable()
            ->comment('Jeton du geste : parent et lignes partis ensemble portent le même, et reviennent ensemble');

        $t->index('corbeille_group');
    }

    /** @return string[] */
    private function tables(): array
    {
        return array_merge(self::SANS_DELETED_AT, self::DEJA_SOUPLES);
    }
};
