<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cdmt_exercices', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();
            $table->foreignId('cbmt_exercice_id')->constrained('cbmt_exercices')->restrictOnDelete();

            // Cycle initial -> arbitrages -> final (V.1 du guide)
            $table->string('version')->default('initial'); // initial, final
            $table->date('date_cdmt_initial')->nullable(); // limite : 31 mars
            $table->date('date_cdmt_final')->nullable();   // limite : 20 novembre

            $table->text('note_arbitrages')->nullable(); // ce qui a change entre initial et final

            $table->string('statut')->default('brouillon'); // brouillon, en_transmission, valide
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['cbmt_exercice_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cdmt_exercices');
    }
};
