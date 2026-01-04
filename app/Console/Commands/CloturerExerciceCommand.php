<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Exercice;
use App\Models\User;
use App\Services\ValidationExerciceService;

class CloturerExerciceCommand extends Command
{
    protected $signature = 'exercice:cloturer 
                            {annee? : Année de l\'exercice à clôturer (défaut: exercice actif)}
                            {--force : Forcer la clôture même avec des avertissements}
                            {--user-id=1 : ID de l\'utilisateur qui clôture}';

    protected $description = 'Clôturer un exercice budgétaire après validation';

    protected ValidationExerciceService $validationService;

    public function __construct(ValidationExerciceService $validationService)
    {
        parent::__construct();
        $this->validationService = $validationService;
    }

    public function handle(): int
    {
        $this->info('╔══════════════════════════════════════════════════╗');
        $this->info('║   CLÔTURE D\'EXERCICE BUDGÉTAIRE                 ║');
        $this->info('╚══════════════════════════════════════════════════╝');
        $this->newLine();

        // 1. Récupérer l'exercice
        $exercice = $this->getExercice();
        if (!$exercice) {
            return Command::FAILURE;
        }

        $this->info("Exercice sélectionné : {$exercice->annee} ({$exercice->libelle})");
        $this->info("Statut actuel : {$exercice->getBadgeStatut()}");
        $this->newLine();

        // 2. Valider l'exercice
        $this->info('🔍 Validation en cours...');
        $this->newLine();

        $validation = $this->validationService->validerCloture($exercice);

        // 3. Afficher les résultats de validation
        $this->afficherValidation($validation);

        // 4. Vérifier si la clôture est possible
        if (!$validation['valid']) {
            $this->error('❌ CLÔTURE IMPOSSIBLE - Corrigez les erreurs ci-dessus.');
            return Command::FAILURE;
        }

        // 5. Vérifier les avertissements
        if (!empty($validation['avertissements']) && !$this->option('force')) {
            $this->warn('⚠️  Des avertissements ont été détectés.');

            if (!$this->confirm('Voulez-vous continuer malgré les avertissements ?', false)) {
                $this->info('Clôture annulée par l\'utilisateur.');
                return Command::FAILURE;
            }
        }

        // 6. Demander confirmation finale
        $this->newLine();
        $this->warn('⚠️  ATTENTION : Cette action est importante !');
        $this->warn('   Une fois clôturé, l\'exercice ne pourra plus être modifié (sauf admin).');
        $this->newLine();

        if (!$this->confirm("Confirmer la clôture de l'exercice {$exercice->annee} ?", false)) {
            $this->info('Clôture annulée par l\'utilisateur.');
            return Command::FAILURE;
        }

        // 7. Clôturer l'exercice
        $this->newLine();
        $this->info('🔒 Clôture en cours...');

        try {
            $user = User::find($this->option('user-id'));
            if (!$user) {
                $this->error("Utilisateur ID {$this->option('user-id')} introuvable.");
                return Command::FAILURE;
            }

            // Mettre à jour les statistiques avant clôture
            $exercice->mettreAJourStatistiques();
            $this->info('   ✓ Statistiques mises à jour');

            // Clôturer
            $exercice->cloturer($user);
            $this->info('   ✓ Exercice clôturé');

            // Afficher le récapitulatif
            $this->newLine();
            $this->afficherRecapitulatif($exercice, $validation);

            $this->newLine();
            $this->info('✅ Exercice clôturé avec succès !');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Erreur lors de la clôture : ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Récupérer l'exercice à clôturer
     */
    protected function getExercice(): ?Exercice
    {
        $annee = $this->argument('annee');

        if ($annee) {
            $exercice = Exercice::where('annee', $annee)->first();

            if (!$exercice) {
                $this->error("Exercice {$annee} introuvable.");
                return null;
            }

            return $exercice;
        }

        // Par défaut : exercice actif
        $exercice = Exercice::getActif();

        if (!$exercice) {
            $this->error('Aucun exercice actif trouvé.');
            $this->info('Spécifiez une année : php artisan exercice:cloturer 2025');
            return null;
        }

        return $exercice;
    }

    /**
     * Afficher les résultats de validation
     */
    protected function afficherValidation(array $validation): void
    {
        // Erreurs
        if (!empty($validation['erreurs'])) {
            $this->error('❌ ERREURS BLOQUANTES :');
            foreach ($validation['erreurs'] as $erreur) {
                $this->line("   • {$erreur}");
            }
            $this->newLine();
        }

        // Avertissements
        if (!empty($validation['avertissements'])) {
            $this->warn('⚠️  AVERTISSEMENTS :');
            foreach ($validation['avertissements'] as $avertissement) {
                $this->line("   • {$avertissement}");
            }
            $this->newLine();
        }

        // Informations
        $this->info('📊 INFORMATIONS :');
        foreach ($validation['infos'] as $info) {
            $this->line("   • {$info}");
        }
        $this->newLine();

        // Statistiques budgétaires
        if (isset($validation['stats'])) {
            $stats = $validation['stats'];
            $this->info('💰 BUDGET :');
            $this->line("   • Total : " . number_format($stats['budget_total'], 0, ',', ' ') . " FCFA");
            $this->line("   • Engagé : " . number_format($stats['engage_total'], 0, ',', ' ') . " FCFA");
            $this->line("   • Disponible : " . number_format($stats['disponible'], 0, ',', ' ') . " FCFA");
            $this->line("   • Taux d'exécution : " . number_format($stats['taux_execution'], 2) . "%");
            $this->newLine();
        }

        // Verdict
        if ($validation['valid']) {
            $this->info('✅ L\'exercice peut être clôturé.');
        } else {
            $this->error('❌ L\'exercice ne peut PAS être clôturé.');
        }
        $this->newLine();
    }

    /**
     * Afficher le récapitulatif final
     */
    protected function afficherRecapitulatif(Exercice $exercice, array $validation): void
    {
        $this->info('╔══════════════════════════════════════════════════╗');
        $this->info('║   RÉCAPITULATIF DE CLÔTURE                      ║');
        $this->info('╚══════════════════════════════════════════════════╝');
        $this->newLine();

        $this->line("Exercice : {$exercice->annee}");
        $this->line("Statut : {$exercice->getBadgeStatut()}");
        $this->line("Date de clôture : {$exercice->date_cloture->format('d/m/Y H:i')}");
        $this->line("Clôturé par : {$exercice->cloturePar->name}");
        $this->newLine();

        $stats = $exercice->statistiques;
        $this->line("Programmes : {$stats['nb_programmes']}");
        $this->line("Budgets : {$stats['nb_budgets']}");
        $this->line("Bordereaux : {$stats['nb_bordereaux']}");
        $this->line("AE total : " . number_format($stats['montant_total_ae'], 0, ',', ' ') . " FCFA");
        $this->line("CP total : " . number_format($stats['montant_total_cp'], 0, ',', ' ') . " FCFA");

        if (isset($validation['stats'])) {
            $this->line("Taux d'exécution : " . number_format($validation['stats']['taux_execution'], 2) . "%");
        }
    }
}
