<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::create('types_decision', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('libelle');
            $table->text('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->integer('ordre')->default(0);
            $table->timestamps();
        });

        // Insérer des types par défaut
        DB::table('types_decision')->insert([
            [
                'code' => 'ARRETE',
                'libelle' => 'Arrêté',
                'description' => 'Décision administrative sous forme d\'arrêté',
                'actif' => true,
                'ordre' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'DECISION',
                'libelle' => 'Décision',
                'description' => 'Décision administrative simple',
                'actif' => true,
                'ordre' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'NOTE_SERVICE',
                'libelle' => 'Note de service',
                'description' => 'Note de service interne',
                'actif' => true,
                'ordre' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'CIRCULAIRE',
                'libelle' => 'Circulaire',
                'description' => 'Circulaire administrative',
                'actif' => true,
                'ordre' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'ORDRE_MISSION',
                'libelle' => 'Ordre de mission',
                'description' => 'Ordre de mission pour déplacement',
                'actif' => true,
                'ordre' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('types_decision');
    }
};
