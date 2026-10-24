<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module « Gestion Comptable et Traitement des Flux Financiers » — prérequis 2/3.
 *
 * Le socle de l'écriture comptable en partie double : un journal, une écriture
 * (en-tête daté et rattaché à l'exercice), ses lignes débit/crédit.
 *
 * Rien n'est recopié depuis le budget : l'écriture pointe le document SIGB qui
 * l'a fait naître (source_type/source_id polymorphes — bon de commande, dépense
 * de régie, mémoire de dépense, liquidation, ordonnance de paiement, recette
 * réelle, virement…) et recopie seulement son numéro, comme preuve de date.
 * L'index unique (source_type, source_id, code_objet) est la garantie que la
 * production automatisée des écritures ne comptabilise jamais deux fois la même
 * pièce ; une écriture annulée libère la place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journaux_comptables', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('libelle');
            $table->enum('type', [
                'achats', 'vente', 'tresorerie', 'recettes', 'paiements',
                'engagement', 'operations_diverses',
            ])->index();
            $table->string('prefixe_numero', 10)->default('EC')
                ->comment('Tête du numéro d\'écriture produit par ce journal (EC-2026-0001).');
            $table->text('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('ecritures_comptables', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 50)->unique();
            $table->foreignId('journal_id')->constrained('journaux_comptables', 'id')->restrictOnDelete();
            $table->foreignId('exercice_id')->nullable()
                ->constrained('exercices', 'id')->nullOnDelete();
            $table->date('date_ecriture')->index();
            $table->string('libelle');

            // Document SIGB d'origine : aucune ressaisie, on ne stocke que la
            // référence imprimée pour que l'état reste lisible après purge.
            $table->string('source_type', 255)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_numero', 100)->nullable();
            $table->string('code_objet', 50)->nullable()
                ->comment('Nature de la comptabilisation (engagement, paiement, encaissement…) — borne l’idempotence.');

            $table->enum('statut', ['brouillon', 'validee', 'comptabilisee', 'annulee'])
                ->default('brouillon')->index();
            $table->decimal('total_debit',  15, 2)->default(0);
            $table->decimal('total_credit', 15, 2)->default(0);
            $table->boolean('equilibree')->default(false);
            $table->foreignId('saisie_par')->nullable()->constrained('users', 'id')->nullOnDelete();
            $table->timestamp('comptabilisee_at')->nullable();
            $table->text('motif_annulation')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['source_type', 'source_id']);
        });

        Schema::create('lignes_ecritures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ecriture_comptable_id')
                ->constrained('ecritures_comptables', 'id')->cascadeOnDelete();
            $table->foreignId('compte_comptable_id')
                ->constrained('comptes_comptables', 'id')->restrictOnDelete();
            $table->string('compte_code', 20)
                ->comment('Code figé à l\'émission : le grand livre reste correct même si le plan évolue.');
            $table->string('libelle')->nullable();
            $table->decimal('debit',  15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);

            // Tiers concerné (fournisseur, personne, tiers de recette) : porte
            // le lettrage et l'aging quand l'écran arrivera.
            $table->string('tierable_type', 255)->nullable();
            $table->unsignedBigInteger('tierable_id')->nullable();
            $table->date('date_echeance')->nullable();
            $table->timestamp('lettree_at')->nullable();
            $table->timestamps();

            $table->index(['ecriture_comptable_id']);
            $table->index(['compte_comptable_id']);
            $table->index(['tierable_type', 'tierable_id']);
            $table->index(['lettree_at']);
        });

        // Une écriture doit être à la fois équilibrée et non négative ; la
        // balance et le compte administratif ne peuvent pas se construire sur
        // des journaux bancales.
        DB::statement("alter table lignes_ecritures
            add constraint lignes_ecritures_montants_positifs check (debit >= 0 and credit >= 0)");

        DB::statement("create unique index ecritures_source_objet_unique
            on ecritures_comptables (source_type, source_id, code_objet)
            where source_type is not null and source_id is not null
              and code_objet is not null and statut <> 'annulee' and deleted_at is null");

        $this->semerJournaux();
    }

    public function down(): void
    {
        Schema::dropIfExists('lignes_ecritures');
        Schema::dropIfExists('ecritures_comptables');
        Schema::dropIfExists('journaux_comptables');
    }

    /**
     * [code, libellé, type, préfixe, description]
     */
    private function journaux(): array
    {
        return [
            ['ENG', 'Engagements et créances',     'engagement',          'EG',
             'Constatation du service fait, des engagements et des restes à payer.'],
            ['ACH', 'Achats et charges',           'achats',              'AC',
             'Réception facturée et charges de fonctionnement, hors trésorerie.'],
            ['PAY', 'Paiements',                   'paiements',           'PA',
             'Ordonnances et décaissements mis en paiement.'],
            ['REC', 'Recettes encaissées',         'recettes',            'RE',
             'Encaissements droits et recouvrements des régies.'],
            ['BAN', 'Banque',                      'tresorerie',          'BA',
             'Mouvements des comptes bancaires de l\'organisme.'],
            ['CAI', 'Caisse et régies',            'tresorerie',          'CI',
             'Mouvements d\'espèces, y compris les avances de régie (RAV).'],
            ['OD',  'Opérations diverses',         'operations_diverses', 'OD',
             'Écritures manuelles : à nouveaux, régularisations, rattachements.'],
        ];
    }

    private function semerJournaux(): void
    {
        if (DB::table('journaux_comptables')->exists()) {
            return;
        }

        $maintenant = now();

        DB::table('journaux_comptables')->insert(array_map(
            fn(array $j) => [
                'code'           => $j[0],
                'libelle'        => $j[1],
                'type'           => $j[2],
                'prefixe_numero' => $j[3],
                'description'    => $j[4],
                'actif'          => true,
                'created_at'     => $maintenant,
                'updated_at'     => $maintenant,
            ],
            $this->journaux()
        ));
    }
};
