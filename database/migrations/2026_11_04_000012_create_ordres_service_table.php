<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Marchés publics — étape E 3/3 : les ordres de service.
 *
 * L'ordre de service est l'acte par lequel le maître d'ouvrage commande l'exécution
 * au titulaire : démarrage, arrêt, reprise. Il ne se décide pas — il s'émet, se date
 * et se verse au dossier. D'où une table de pièces jointes au marché, sans machine à
 * états propre : contrairement à l'offre (étape C), personne n'a à statuer sur un
 * ordre de service, il est ou il n'est pas.
 *
 * Trois partis pris :
 *
 * 1. `date_emission` est saisie, `emis_par_id` ne l'est jamais. La date est celle
 *    portée sur le document — elle commande le délai d'exécution du marché, elle
 *    appartient au papier ; la main qui enregistre l'acte est reprise de la session,
 *    comme `evalue_par_id` à l'étape D. Un champ « émis par » libre serait une
 *    signature tapée par un autre.
 *
 * 2. Pas de colonne `created_by` : l'auteur de la ligne et l'auteur de l'acte sont
 *    la même personne ici, et deux colonnes sur un seul fait finissent par se
 *    contredire. `created_at` garde l'horodatage de l'enregistrement.
 *
 * 3. `type_ordre_service_id` est une référence au référentiel, pas un texte : c'est
 *    la colonne qui porte l'effet sur le marché (`demarre_lexecution`), donc une
 *    valeur tapée à la main rendrait l'effet invisible.
 *
 * Le fichier de l'ordre de service tient dans une colonne de cette table, comme la
 * quittance de l'offre à l'étape C : `pieces_dossier` est clef sur le dossier
 * fournisseur, et une table polymorphe n'aurait encore qu'un seul type d'usage.
 *
 * `marche_id` et `type_ordre_service_id` sont en `restrictOnDelete` : un ordre de
 * service est une pièce de la trace du marché, il ne se retrouve pas orphelin parce
 * qu'un agent a nettoyé un référentiel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordres_service', function (Blueprint $table) {
            $table->id();

            $table->string('numero', 30)->unique()
                ->comment('Référence SIGB, générée par OrdreService::genererNumero() — OS-2026-0001');

            $table->foreignId('marche_id')->constrained('marches')->restrictOnDelete()
                ->comment('Le marché dont l\'exécution est commandée');
            $table->foreignId('type_ordre_service_id')->constrained('types_ordre_service')->restrictOnDelete()
                ->comment('Démarrage, arrêt, reprise — le type porte l\'effet sur le marché');

            $table->date('date_emission')
                ->comment('Date portée sur le document ; c\'elle qui court, pas la date de saisie');
            $table->text('objet')
                ->comment('Le service commandé, tel que l\'ordre de service l\'énonce');

            $table->string('fichier_os')->nullable()
                ->comment('Chemin sur le disque public, dossier marches/ordres-service — pas une URL');

            $table->foreignId('emis_par_id')->nullable()->constrained('users')->nullOnDelete();

            $table->text('observations')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('marche_id');
            $table->index('type_ordre_service_id');
            $table->index('date_emission');
            $table->index(['marche_id', 'date_emission']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordres_service');
    }
};
