<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Traçage — la date de dernière connexion.
 *
 * `WelcomeWidget` calculait `$user->last_login_at?->diffForHumans()` depuis toujours, et
 * la charte des droits inscrivait ce champ au nombre de ce que le logiciel ne sait pas
 * dire : la colonne n'existait pas dans `users`, donc la ligne restait vide à l'écran
 * comme au PDF.
 *
 * La colonne se pose à l'authentification (`LogSuccessfulLogin`), pas à la demande de
 * page : elle répond à « quand ce compte s'est-il connecté pour la dernière fois », ce
 * qui est un fait d'authentification, pas un fait de navigation.
 *
 * Nullable sans défaut : les 22 comptes déjà en base restent à NULL jusqu'à leur première
 * connexion après ce déploiement. Remplir la colonne avec la date du déploiement serait
 * affirmer une connexion qui n'a pas eu lieu — le NULL est la réponse honnête, et
 * l'écran le dit.
 *
 * Une seule colonne, pas de `previous_login_at` : l'historique complet des connexions est
 * déjà dans `activity_log` (event « login », avec IP et agent utilisateur), et c'est là
 * que l'écran d'accueil lit la connexion précédente. Deux copies du même fait finiraient
 * par se contredire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_login_at')->nullable()
                ->comment('Horodatage de la dernière authentification réussie ; NULL tant que le compte ne s\'est pas connecté depuis ce déploiement');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_login_at');
        });
    }
};
