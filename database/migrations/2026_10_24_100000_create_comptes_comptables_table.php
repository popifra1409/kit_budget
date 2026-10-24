<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module « Gestion Comptable et Traitement des Flux Financiers » — prérequis 1/3.
 *
 * Référentiel comptable SYSCOHADA, volontairement distinct de
 * nomenclature_budgetaire : ce dernier reste l'autorité budgétaire (classes 6
 * et 7, lignes de dépense/recette, versions et dates de validité) et ne doit
 * pas être détourné pour porter des comptes de tiers. La liaison se fait dans
 * l'autre sens, par comptes_comptables.nomenclature_budgetaire_id, pour
 * rattacher un compte de charge ou de produit à sa ligne budgétaire existante.
 *
 * Semis : classe 4 — comptes de tiers (40 à 49), telle que fournie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comptes_comptables', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('intitule');
            $table->char('classe', 1)->comment('1 à 9 (SYSCOHADA)');
            $table->enum('niveau', ['classe', 'groupe', 'compte', 'sous_compte'])
                ->default('compte');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->foreign('parent_id')
                ->references('id')->on('comptes_comptables')
                ->restrictOnDelete();

            // Nullable : les lignes de regroupement (classe, groupe) n'ont pas
            // de catégorie bilan, seules les feuilles en portent une.
            $table->enum('categorie', [
                'actif', 'passif', 'charge', 'produit', 'resultat',
                'contre_actif', 'contre_passif',
            ])->nullable();
            $table->enum('sens_normal', ['debit', 'credit'])->nullable();

            $table->string('type_tiers', 30)->nullable()
                ->comment('fournisseur|client|personnel|organisme_social|etat|associe|divers|hao|depreciation — sert au rattachement automatique des tiers du module');
            $table->boolean('imputable')->default(true)
                ->comment('false pour une classe ou un groupe : non saisissable dans une écriture');
            $table->foreignId('nomenclature_budgetaire_id')
                ->nullable()
                ->constrained('nomenclature_budgetaire', 'id')
                ->nullOnDelete()
                ->comment('Ligne de nomenclature budgétaire équivalente (classes 6 et 7) — lien, pas duplication');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('classe');
            $table->index('categorie');
            $table->index('type_tiers');
        });

        $this->semerClasseQuatre();
    }

    public function down(): void
    {
        Schema::dropIfExists('comptes_comptables');
    }

    /**
     * [code, intitule, niveau, parent, categorie, sens, type_tiers, note]
     */
    private function planQuatre(): array
    {
        return [
            ['4',   'COMPTES DE TIERS',                                'classe',  null,  null,   null,   null,
             'Créances et dettes hors trésorerie (classe 4 SYSCOHADA).'],

            ['40',  'Fournisseurs et comptes rattachés',               'groupe',  '4',   null,   null,   'fournisseur', null],
            ['401', 'Fournisseurs — dettes en compte',                 'compte',  '40',  'passif', 'credit', 'fournisseur', null],
            ['402', 'Fournisseurs — effets à payer',                   'compte',  '40',  'passif', 'credit', 'fournisseur', null],
            ['408', 'Factures non parvenues',                          'compte',  '40',  'passif', 'credit', 'fournisseur',
             'Charges courues non encore facturées (rattachements).'],
            ['409', 'Fournisseurs débiteurs',                          'compte',  '40',  'actif',  'debit',  'fournisseur',
             'Acomptes versés, avances, trop-perçus.'],

            ['41',  'Clients et comptes rattachés',                    'groupe',  '4',   null,   null,   'client', null],
            ['411', 'Clients',                                         'compte',  '41',  'actif',  'debit',  'client', null],
            ['412', 'Clients — effets à recevoir',                     'compte',  '41',  'actif',  'debit',  'client', null],
            ['416', 'Clients — créances douteuses',                    'compte',  '41',  'actif',  'debit',  'client',
             'Valeur brute ; la dépréciation est portée au compte 491.'],
            ['418', 'Clients — produits non encore facturés',          'compte',  '41',  'actif',  'debit',  'client', null],
            ['419', 'Clients créditeurs',                              'compte',  '41',  'passif', 'credit', 'client', null],

            ['42',  'Personnel',                                       'groupe',  '4',   null,   null,   'personnel', null],
            ['421', 'Rémunérations dues au personnel',                 'compte',  '42',  'passif', 'credit', 'personnel', null],
            ['422', 'Personnel — avances et acomptes',                 'compte',  '42',  'actif',  'debit',  'personnel', null],
            ['423', 'Personnel — dépôts',                              'compte',  '42',  'passif', 'credit', 'personnel', null],
            ['424', 'Personnel — œuvres sociales',                     'compte',  '42',  'passif', 'credit', 'personnel', null],
            ['427', 'Personnel — oppositions',                         'compte',  '42',  'passif', 'credit', 'personnel', null],
            ['428', 'Personnel — charges à payer',                     'compte',  '42',  'passif', 'credit', 'personnel', null],

            ['43',  'Organismes sociaux',                              'groupe',  '4',   null,   null,   'organisme_social', null],
            ['431', 'Sécurité sociale',                                'compte',  '43',  'passif', 'credit', 'organisme_social', null],
            ['432', 'Caisses de retraite',                             'compte',  '43',  'passif', 'credit', 'organisme_social', null],
            ['438', 'Charges sociales à payer',                        'compte',  '43',  'passif', 'credit', 'organisme_social', null],

            ['44',  "État et collectivités publiques",                 'groupe',  '4',   null,   null,   'etat', null],
            ['441', 'Impôt sur les bénéfices',                         'compte',  '44',  'passif', 'credit', 'etat', null],
            ['442', 'Autres impôts et taxes',                          'compte',  '44',  'passif', 'credit', 'etat', null],
            ['443', 'TVA facturée',                                    'compte',  '44',  'passif', 'credit', 'etat', null],
            ['444', 'TVA due',                                         'compte',  '44',  'passif', 'credit', 'etat', null],
            ['445', 'TVA récupérable',                                 'compte',  '44',  'actif',  'debit',  'etat', null],
            ['446', 'Autres taxes',                                    'compte',  '44',  'passif', 'credit', 'etat', null],
            ['447', 'Impôts retenus à la source',                      'compte',  '44',  'passif', 'credit', 'etat',
             "IR et retenues à la source collectées pour le compte de l'État."],

            ['45',  'Organismes internationaux',                       'groupe',  '4',   'passif', 'credit', 'divers',
             'Feuille du groupe en l\'absence de sous-comptes détaillés — à ventiler selon le paramétrage retenu.'],

            ['46',  'Associés et groupe',                              'groupe',  '4',   null,   null,   'associe', null],
            ['461', "Associés — apports en nature",                    'compte',  '46',  'actif',  'debit',  'associe', null],
            ['462', 'Capital appelé non versé',                        'compte',  '46',  'actif',  'debit',  'associe', null],
            ['465', 'Associés — dividendes à payer',                   'compte',  '46',  'passif', 'credit', 'associe', null],
            ['467', 'Groupe',                                          'compte',  '46',  'passif', 'credit', 'associe', null],

            ['47',  'Débiteurs et créditeurs divers',                  'groupe',  '4',   null,   null,   'divers', null],
            ['471', 'Débiteurs et créditeurs divers',                  'compte',  '47',  'actif',  'debit',  'divers',
             'Compte à solde mixte : débiteur = créance, créditeur = dette.'],
            ['476', 'Charges constatées d\'avance',                    'compte',  '47',  'actif',  'debit',  'divers', null],
            ['477', 'Produits constatés d\'avance',                    'compte',  '47',  'passif', 'credit', 'divers', null],
            ['478', 'Écarts de conversion — actif',                    'compte',  '47',  'actif',  'debit',  'divers', null],
            ['479', 'Écarts de conversion — passif',                   'compte',  '47',  'passif', 'credit', 'divers', null],

            ['48',  'Créances et dettes hors activités ordinaires',    'groupe',  '4',   null,   null,   'hao', 'HAO.'],
            ['481', "Fournisseurs d'investissements",                  'compte',  '48',  'passif', 'credit', 'hao', null],
            ['482', "Fournisseurs d'investissements — effets à payer", 'compte',  '48',  'passif', 'credit', 'hao', null],
            ['485', 'Créances sur cessions d\'immobilisations',        'compte',  '48',  'actif',  'debit',  'hao', null],
            ['488', 'Autres créances HAO',                             'compte',  '48',  'actif',  'debit',  'hao', null],

            ['49',  'Dépréciations et risques provisionnés (tiers)',   'groupe',  '4',   null,   null,   'depreciation', null],
            ['490', 'Dépréciations des comptes fournisseurs',           'compte',  '49',  'contre_passif', 'credit', 'depreciation', null],
            ['491', 'Dépréciations des comptes clients',               'compte',  '49',  'contre_actif',  'credit', 'depreciation', null],
            ['492', 'Dépréciations des comptes du personnel',          'compte',  '49',  'contre_actif',  'credit', 'depreciation', null],
            ['494', "Dépréciations des comptes de l'État",             'compte',  '49',  'contre_actif',  'credit', 'depreciation', null],
            ['496', 'Dépréciations des comptes des associés',          'compte',  '49',  'contre_actif',  'credit', 'depreciation', null],
            ['497', 'Dépréciations des comptes débiteurs divers',      'compte',  '49',  'contre_actif',  'credit', 'depreciation', null],
            ['499', 'Risques provisionnés (comptes de tiers)',         'compte',  '49',  'contre_passif', 'credit', 'depreciation', null],
        ];
    }

    private function semerClasseQuatre(): void
    {
        if (DB::table('comptes_comptables')->exists()) {
            return;
        }

        $plan     = $this->planQuatre();
        $parents  = array_column(array_filter($plan, fn($l) => $l[3] !== null), 3);
        $ids      = [];
        $maintenant = now();

        foreach ($plan as [$code, $intitule, $niveau, $parent, $categorie, $sens, $typeTiers, $note]) {
            // Une feuille du plan est saisible : un groupe qui n'a pas été
            // détaillé (45) sert donc directement de compte.
            $imputable = !in_array($code, $parents, true);

            DB::table('comptes_comptables')->insert([
                'code'        => $code,
                'intitule'    => $intitule,
                'classe'      => substr($code, 0, 1),
                'niveau'      => $niveau,
                'parent_id'   => $parent === null ? null : ($ids[$parent] ?? null),
                'categorie'   => $categorie,
                'sens_normal' => $sens,
                'type_tiers'  => $typeTiers,
                'imputable'   => $imputable,
                'note'        => $note,
                'created_at'  => $maintenant,
                'updated_at'  => $maintenant,
            ]);

            $ids[$code] = (int) DB::table('comptes_comptables')->where('code', $code)->value('id');
        }
    }
};
