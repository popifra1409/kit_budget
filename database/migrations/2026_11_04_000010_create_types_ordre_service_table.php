<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Marchés publics — étape E 1/3 : le référentiel des ordres de service.
 *
 * « Ordre de service » figure dans la liste du paramétrage du scan 1/4, à côté des
 * quatre référentiels déjà construits à l'étape A. C'est la cinquième ligne, restée
 * à faire.
 *
 * Structure PLATE, comme `elements_evaluation_dao` : un code, un libellé, un ordre,
 * un drapeau actif. Pas de volet ni de parent — l'ordre de service est un acte
 * unique, il n'a pas de décomposition.
 *
 * `demarre_lexecution` est la seule colonne qui porte une règle. L'effet d'un ordre
 * de service sur le marché — le faire passer d'« homologué » à « en cours
 * d'exécution » — est une propriété du type, pas un code en dur dans le modèle :
 * le semis ne fixe qu'un libellé technique, et l'EP qui paramètre ses types décide
 * lesquels commandent effectivement le démarrage du service. Sans cette colonne, la
 * règle devrait reconnaître « demarrage » par sa chaîne, et un type nommé
 * « Notification de début de prestations » ne démarrerait rien.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('types_ordre_service', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('libelle');
            $table->text('description')->nullable();
            $table->boolean('demarre_lexecution')->default(false)
                ->comment('Vrai quand l\'émission de ce type met le marché en cours d\'exécution');
            $table->integer('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->index(['actif', 'ordre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('types_ordre_service');
    }
};
