<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Exercice;
use App\Services\ReconductionExerciceService;

class ReconduireExerciceCommand extends Command
{
    protected $signature = 'exercice:reconduire 
                            {annee-source? : Année de l\'exercice source}
                            {annee-cible? : Année de l\'exercice cible}
                            {--nomenclature : Reconduire aussi la nomenclature}
                            {--ajuster= : Pourcentage d\'ajustement des montants (ex: 5 pour +5%)}
                            {--auto : Mode automatique (source=actif/clôturé, cible=brouillon suivant)}';

    protected $description = 'Reconduire un exercice budgétaire vers un nouvel exercice';

    protected ReconductionExerciceService $reconductionService;

    public function __construct(ReconductionExerciceService $reconductionService)
    {
        parent::__construct();
        $this->reconductionService = $reconductionService;
    }

    public function handle(): int
    {
        $this->info('╔══════════════════════════════════════════════════╗');
        $this->info('║   RECONDUCTION D\'EXERCICE BUDGÉTAIRE            ║');
        $this->info('╚══════════════════════════════════════════════════╝');
        $this->newLine();

        // 1. Récupérer les exercices source et cible
        $exercices = $this->getExercices();

        if (!$exercices) {
            return Command::FAILURE;
        }

        [$exerciceSource, $exerciceCible] = $exercices;

        // 2. Afficher les informations
        $this->afficherInformations($exerciceSource, $exerciceCible);

        // 3. Préparer les options
        $options = $this->prepareOptions();

        // 4. Afficher l'aperçu
        $this->afficherApercu($exerciceSource, $options);

        // 5. Demander confirmation (sauf en mode auto)
        if (!$this->option('auto')) {
            if (!$this->confirm('Confirmer la reconduction ?', true)) {
                $this->info('Reconduction annulée.');
                return Command::SUCCESS;
            }
        }

        // 6. Effectuer la reconduction
        $this->newLine();
        $this->info('🔄 Reconduction en cours...');
        $this->newLine();

        $bar = $this->output->createProgressBar(5);
        $bar->start();

        try {
            $bar->advance();

            $resultat = $this->reconductionService->reconduire(
                $exerciceSource,
                $exerciceCible,
                $options
            );

            $bar->finish();
            $this->newLine(2);

            if ($resultat['success']) {
                $this->afficherResultat($resultat, $exerciceSource, $exerciceCible);
                return Command::SUCCESS;
            } else {
                $this->error('❌ Erreur lors de la reconduction :');
                $this->error($resultat['error']);
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $bar->finish();
            $this->newLine(2);
            $this->error('❌ Erreur inattendue : ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Récupérer les exercices source et cible
     */
    protected function getExercices(): ?array
    {
        $anneeSource = $this->argument('annee-source');
        $anneeCible = $this->argument('annee-cible');

        // Mode automatique
        if ($this->option('auto')) {
            return $this->getExercicesAuto();
        }

        // Mode manuel
        if (!$anneeSource || !$anneeCible) {
            $this->error('❌ Veuillez spécifier les années source et cible.');
            $this->info('Usage : php artisan exercice:reconduire 2025 2026');
            $this->info('   ou : php artisan exercice:reconduire --auto');
            return null;
        }

        $exerciceSource = Exercice::where('annee', $anneeSource)->first();
        $exerciceCible = Exercice::where('annee', $anneeCible)->first();

        if (!$exerciceSource) {
            $this->error("❌ Exercice source {$anneeSource} introuvable.");
            return null;
        }

        if (!$exerciceCible) {
            $this->error("❌ Exercice cible {$anneeCible} introuvable.");
            return null;
        }

        // Vérifications
        if ($exerciceCible->statut !== 'brouillon') {
            $this->error("❌ L'exercice cible doit être en brouillon (statut actuel : {$exerciceCible->statut})");
            return null;
        }

        if ($anneeCible <= $anneeSource) {
            $this->warn("⚠️  L'exercice cible ({$anneeCible}) devrait être ultérieur à la source ({$anneeSource})");
        }

        return [$exerciceSource, $exerciceCible];
    }

    /**
     * Mode automatique : source=actif/clôturé, cible=brouillon suivant
     */
    protected function getExercicesAuto(): ?array
    {
        $this->info('🤖 Mode automatique activé');
        $this->newLine();

        // Source : exercice actif ou clôturé le plus récent
        $exerciceSource = Exercice::whereIn('statut', ['actif', 'cloture'])
            ->orderBy('annee', 'desc')
            ->first();

        if (!$exerciceSource) {
            $this->error('❌ Aucun exercice actif ou clôturé trouvé.');
            return null;
        }

        // Cible : premier exercice brouillon avec année > source
        $exerciceCible = Exercice::where('statut', 'brouillon')
            ->where('annee', '>', $exerciceSource->annee)
            ->orderBy('annee')
            ->first();

        if (!$exerciceCible) {
            $this->error("❌ Aucun exercice brouillon trouvé après {$exerciceSource->annee}");
            return null;
        }

        $this->info("✓ Source : {$exerciceSource->annee} ({$exerciceSource->statut})");
        $this->info("✓ Cible : {$exerciceCible->annee} ({$exerciceCible->statut})");
        $this->newLine();

        return [$exerciceSource, $exerciceCible];
    }

    /**
     * Préparer les options de reconduction
     */
    protected function prepareOptions(): array
    {
        $options = [
            'reconduire_programmes' => true,
            'reconduire_nomenclature' => $this->option('nomenclature'),
            'ajuster_montants' => false,
            'pourcentage_ajustement' => 0,
        ];

        if ($this->option('ajuster')) {
            $options['ajuster_montants'] = true;
            $options['pourcentage_ajustement'] = (float) $this->option('ajuster');
        }

        return $options;
    }

    /**
     * Afficher les informations sur les exercices
     */
    protected function afficherInformations(Exercice $source, Exercice $cible): void
    {
        $this->info('📋 EXERCICES :');
        $this->table(
            ['Type', 'Année', 'Libellé', 'Statut'],
            [
                ['Source', $source->annee, $source->libelle, $source->getBadgeStatut()],
                ['Cible', $cible->annee, $cible->libelle, $cible->getBadgeStatut()],
            ]
        );
        $this->newLine();
    }

    /**
     * Afficher l'aperçu de ce qui sera reconduit
     */
    protected function afficherApercu(Exercice $source, array $options): void
    {
        $this->info('📊 APERÇU DE LA RECONDUCTION :');
        $this->newLine();

        // Compter les éléments
        $stats = [
            'Programmes' => \App\Models\Programme::where('exercice_id', $source->id)->count(),
            'Actions' => \App\Models\Action::where('exercice_id', $source->id)->count(),
            'Activités' => \App\Models\Activite::where('exercice_id', $source->id)->count(),
            'Tâches' => \App\Models\Tache::where('exercice_id', $source->id)->count(),
        ];

        if ($options['reconduire_nomenclature']) {
            $stats['Nomenclatures'] = \App\Models\NomenclatureBudgetaire::where('exercice_id', $source->id)->count();
        }

        foreach ($stats as $type => $count) {
            $this->line("  • {$type} : {$count}");
        }

        $this->newLine();

        // Options
        $this->info('⚙️  OPTIONS :');
        $this->line('  • Reconduire nomenclature : ' . ($options['reconduire_nomenclature'] ? '✓ Oui' : '✗ Non'));
        $this->line('  • Ajuster montants : ' . ($options['ajuster_montants'] ? "✓ Oui ({$options['pourcentage_ajustement']}%)" : '✗ Non'));
        $this->newLine();
    }

    /**
     * Afficher le résultat de la reconduction
     */
    protected function afficherResultat(array $resultat, Exercice $source, Exercice $cible): void
    {
        $this->info('╔══════════════════════════════════════════════════╗');
        $this->info('║   ✅ RECONDUCTION RÉUSSIE !                     ║');
        $this->info('╚══════════════════════════════════════════════════╝');
        $this->newLine();

        $this->info("Exercice {$source->annee} → {$cible->annee}");
        $this->newLine();

        $stats = $resultat['stats'];
        $this->info('📊 ÉLÉMENTS RECONDUITS :');
        $this->line("  • Programmes : {$stats['programmes']}");
        $this->line("  • Actions : {$stats['actions']}");
        $this->line("  • Activités : {$stats['activites']}");
        $this->line("  • Tâches : {$stats['taches']}");

        if ($stats['nomenclatures'] > 0) {
            $this->line("  • Nomenclatures : {$stats['nomenclatures']}");
        }

        $this->newLine();
        $this->info("✓ L'exercice {$cible->annee} est maintenant prêt à être utilisé.");
        $this->info("✓ Pensez à l'activer quand vous serez prêt.");
    }
}
