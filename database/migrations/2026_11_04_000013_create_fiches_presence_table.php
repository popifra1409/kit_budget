<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module Marchés publics — étape E 4/4 : la fiche de présence de la commission.
 *
 * Le scan du 27/10 17.53 demande, après la composition de la commission, « la
 * génération de la fiche de présence avec possibilité de Visa ». La fiche est donc
 * l'acte qui atteste que la commission s'est rassemblée : dressée depuis le référentiel
 * (nom, qualité, N° téléphone du membre), émargée membre par membre, et visée par
 * chacun de ceux qui étaient présents.
 *
 * Quatre partis pris, et ils se discutent tous :
 *
 * 1. La fiche est une table, pas une colonne du marché. Un marché peut tenir plusieurs
 *    séances (ouverture des plis, puis reprise après levée d'attribution) ; une seule
 *    colonne « date de la séance » aurait effacé la première au profit de la seconde,
 *    et le dossier n'aurait plus la trace de ce qui s'est réellement passé.
 *
 * 2. Les membres sont **recopiés** dans `fiche_presence_membres`, avec leur nom, leur
 *    qualité et leur téléphone, au moment du dressage. Le référentiel est pérenne — ses
 *    lignes vivent d'une année sur l'autre — alors que la fiche atteste une séance
 *    d'un jour donné : si elle lisait `commission_membres` en direct, une modification
 *    du paramétrage le mois suivant réécrirait rétroactivement qui siégeait. La colonne
 *    `commission_membre_id` garde la provenance, elle ne sert pas à relire le référentiel.
 *
 * 3. Le visa est **par membre**, pas par fiche. « Possibilité de Visa » sur une fiche
 *    de présence, c'est la case que chaque présent remplit en signant ; un visa unique
 *    sur l'en-tête attesterait l'absence des autres. C'est pourquoi `visa` et
 *    `date_visa` sont sur la ligne, et protégés par une contrainte : on ne vise pas
 *    quelqu'un qui n'était pas là.
 *
 * 4. `present` est nullable, et son vide est un état. Une fiche dressée le matin n'est
 *    pas émargée le matin : « pas encore porté à la fiche » n'est ni présent ni absent,
 *    et l'écran qui réclame le visa doit pouvoir dire combien de cases manquent encore.
 *
 * Aucune de ces deux tables ne porte de statut : la machine à états reste celle du
 * marché (config/marches.php). La fiche constate une séance, elle ne décide pas du
 * cycle — pas plus que l'ordre de service à l'étape E 3/3.
 *
 * `marche_id` et `commission_id` sont en `restrictOnDelete` : une fiche visée est une
 * pièce de la trace, elle ne se retrouve pas orpheline parce qu'un agent a rangé un
 * référentiel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiches_presence', function (Blueprint $table) {
            $table->id();

            $table->string('numero', 30)->unique()
                ->comment('Référence SIGB, générée par FichePresence::genererNumero() — FP-2026-0001');

            $table->foreignId('marche_id')->constrained('marches')->restrictOnDelete()
                ->comment('La séance tenue pour ce marché');
            $table->foreignId('commission_id')->constrained('commissions')->restrictOnDelete()
                ->comment('La commission réunie, telle qu\'épinglée au marché');

            $table->date('date_seance')
                ->comment('Le jour où la commission s\'est rassemblée ; jamais celui de la saisie');
            $table->string('lieu')->nullable();
            $table->text('objet')
                ->comment('Ce à quoi la séance était consacrée, en une phrase lisible au recours');

            $table->string('fichier')->nullable()
                ->comment('Chemin sur le disque public, dossier marches/fiches-presence — la fiche émargée et visée');

            $table->foreignId('etablie_par_id')->nullable()->constrained('users')->nullOnDelete()
                ->comment('Qui dresse la fiche — le secrétaire de séance, pas un membre qu\'on signerait à sa place');

            $table->text('observations')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Une séance d'un même jour pour un même marché ne se dresse pas deux fois :
            // deux fiches concurrentes laisseraient le dossier sans savoir laquelle a
            // été signée. Le modèle porte le message, la base tient le fait.
            $table->unique(['marche_id', 'date_seance']);
            $table->index('commission_id');
            $table->index('date_seance');
        });

        Schema::create('fiche_presence_membres', function (Blueprint $table) {
            $table->id();

            $table->foreignId('fiche_presence_id')->constrained('fiches_presence')->cascadeOnDelete()
                ->comment('Les lignes n\'existent que pour leur fiche : rien à conserver séparément');

            // La provenance, pas la relecture : ces deux colonnes survivent en vide
            // quand le référentiel est nettoyé, parce que la fiche, elle, est déjà écrite.
            $table->foreignId('commission_membre_id')->nullable()
                ->constrained('commission_membres')->nullOnDelete();
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete()
                ->comment('Membre interne, quand il en est un : le nom recopié reste la référence');

            $table->string('nom');
            $table->enum('titre', array_keys(config('marches.titres_membre')))
                ->comment('Qualité dans la commission, recopiée du référentiel — elle ordonne les signatures');
            $table->string('telephone', 30)->nullable();
            $table->integer('ordre')->default(0);

            $table->boolean('present')->nullable()
                ->comment('NULL = case encore vide à la fiche ; ce n\'est ni présent, ni absent');
            $table->text('motif_absence')->nullable();

            $table->boolean('visa')->default(false);
            $table->timestamp('date_visa')->nullable();

            $table->timestamps();

            $table->index(['fiche_presence_id', 'ordre']);
        });

        // Un visa attesté pour une personne absente ( ou pour une case vide ) n'est plus
        // une signature, c'est une affirmation sur quelqu'un. La contrainte tient ce
        // chemin-là fermé même sous DB::raw(), où les hooks du modèle ne parlent pas.
        DB::statement("
            ALTER TABLE fiche_presence_membres
                ADD CONSTRAINT fiche_presence_membres_visa_check
                CHECK (NOT visa OR (present IS TRUE AND date_visa IS NOT NULL))
        ");

        // Une absence sans motif est une ligne muette : la fiche dit alors qu'un membre
        // n'est pas venu sans rien pouvoir en relire.
        DB::statement("
            ALTER TABLE fiche_presence_membres
                ADD CONSTRAINT fiche_presence_membres_absence_check
                CHECK (present IS DISTINCT FROM FALSE OR btrim(coalesce(motif_absence, '')) <> '')
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE fiche_presence_membres DROP CONSTRAINT IF EXISTS fiche_presence_membres_visa_check');
        DB::statement('ALTER TABLE fiche_presence_membres DROP CONSTRAINT IF EXISTS fiche_presence_membres_absence_check');

        Schema::dropIfExists('fiche_presence_membres');
        Schema::dropIfExists('fiches_presence');
    }
};
