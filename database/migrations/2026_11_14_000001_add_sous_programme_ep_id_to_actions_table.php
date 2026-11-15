<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B2b — la clé étrangère du pont sous-programme de l'EP ↔ actions.
 *
 * Aujourd'hui le lien n'existe pas : il se déduit. `SousProgrammeEp::actions()` est une
 * `HasManyThrough` qui joint `actions.programme_id → programmes.id` puis compare
 * `programmes.code` au `code_programme_ep` saisi sur le sous-programme. Deux conséquences que
 * les écrans payent déjà :
 *  - le lien dépend d'une colonne texte recopiée à la main, donc deux sous-programmes peuvent
 *    porter le même code et afficher les MÊMES actions comme leurs propres actions ;
 *  - changer le code d'un programme du MINSANTE dans la classification casse le pont sans
 *    aucun message — les actions ne disparaissent pas de la base, elles disparaissent des états.
 *
 * Une colonne nullable, pas une refonte : le backfill (`actions:relier-sous-programmes`) remplit
 * ce que le rapprochement par code permettait de déduire sans ambiguïté, et laissons
 * `actions.programme_id` en place — elle porte la classification budgétaire de l'action, qui
 * reste vraie même pour une action qu'aucun sous-programme de l'hôpital ne décompose.
 *
 * `restrictOnDelete()`, et non `cascade` ni `nullOnDelete` : une action financée, budgétée et
 * déjà relayée dans un PPA ne se laisse pas emporter par la purge de son sous-programme, et ne
 * se retrouve pas orpheline sans que personne le sache. RESTRICT refuse la purge et nomme la
 * ligne — c'est la seule des trois règles qui rende l'accident visible.
 *
 * `SousProgrammeEp` est en SoftDeletes, donc `$sp->delete()` reste un UPDATE de `deleted_at`
 * que PostgreSQL ne contrôle pas : la contrainte ne mord que sur `forceDelete()` ou une
 * suppression en SQL. Le SoftDeletes est là pour ça — une ligne clôturée reste consultable.
 *
 * Index explicite : PostgreSQL n'indexe pas les clés étrangères d'elles-mêmes (contrairement à
 * MySQL), et toute la lecture du pont — `actions()` du sous-programme, les états par
 * sous-programme, le cadre logique — descend par cette colonne.
 *
 * Cette migration n'écrit AUCUNE donnée : le peuplement est une commande relisible, qui refuse
 * ce qu'elle ne peut pas trancher.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actions', function (Blueprint $table) {
            $table->foreignId('sous_programme_ep_id')
                ->nullable()
                ->comment('Sous-programme de l\'EP (hôpital) qui décompose l\'action — null = action que l\'hôpital ne décompose pas')
                ->constrained('sous_programmes_ep')
                ->restrictOnDelete();

            $table->index('sous_programme_ep_id');
        });
    }

    public function down(): void
    {
        Schema::table('actions', function (Blueprint $table) {
            // `dropConstrainedForeignId` emporte contrainte, index et colonne : le retour
            // arrière ne doit laisser aucune trace, sinon B2e recréerait une colonne fantôme.
            $table->dropConstrainedForeignId('sous_programme_ep_id');
        });
    }
};
