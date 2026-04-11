<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->newLine();
        $this->command->info('╔══════════════════════════════════════════════════════╗');
        $this->command->info('║        INITIALISATION GESTION BUDGET                ║');
        $this->command->info('╚══════════════════════════════════════════════════════╝');
        $this->command->newLine();

        // ========================================
        // 1. PARAMÈTRES DE STRUCTURE
        // ========================================
        $this->command->info('📦 1. Paramètres de la structure...');
        $this->call(ParametresStructureSeeder::class);
        $this->command->info('   ✓ Paramètres créés');
        $this->command->newLine();

        // ========================================
        // 2. ÉTATS ET CONFIGURATIONS
        // ========================================
        $this->command->info('📦 2. États et configurations système...');
        $this->call(EtatConfigSeeder::class);
        $this->command->info('   ✓ Configurations créées');
        $this->command->newLine();

        // ========================================
        // 3. RÔLES ET PERMISSIONS
        // ========================================
        $this->command->info('📦 3. Rôles et permissions...');
        $this->call(RolePermissionSeeder::class);
        $this->command->info('   ✓ Rôles et permissions créés');
        $this->command->newLine();

        // ========================================
        // 4. SUPER ADMINISTRATEUR
        // ========================================
        $this->command->info('📦 4. Création du super administrateur...');
        $this->createSuperAdmin();
        $this->command->newLine();

        // ========================================
        // 5. EXERCICES BUDGÉTAIRES
        // ========================================
        // $this->command->info('📦 5. Exercices budgétaires...');
        // $this->call(ExerciceSeeder::class);
        // $this->command->info('   ✓ Exercices créés (2024, 2025, 2026)');
        // $this->command->newLine();

        // ========================================
        // RÉCAPITULATIF FINAL
        // ========================================
        $this->showSummary();
    }

    /**
     * Créer le super administrateur
     */
    private function createSuperAdmin(): void
    {
        // Vérifier si l'utilisateur existe déjà
        $existingAdmin = User::where('email', 'kit@kisinit237.com')->first();

        if ($existingAdmin) {
            $this->command->warn('   ⚠ Super admin existe déjà, pas de création');
            return;
        }

        // Créer le super admin
        $admin = User::create([
            'name' => 'Super Administrateur',
            'email' => 'kit@kisinit237.com',
            'password' => Hash::make('Admin@1977'),
            'email_verified_at' => now(),
        ]);

        // Assigner le rôle super_admin (si Spatie est installé)
        if (class_exists('Spatie\Permission\Models\Role')) {
            try {
                $admin->assignRole('super_admin');
                $this->command->info('   ✓ Super admin créé avec le rôle');
            } catch (\Exception $e) {
                $this->command->warn("   ⚠ Rôle super_admin non assigné : {$e->getMessage()}");
            }
        } else {
            $this->command->info('   ✓ Super admin créé (sans rôle Spatie)');
        }

        $this->command->info('   📧 Email    : kit@kisinit237.com');
        $this->command->info('   🔒 Password : Admin@1977');
    }

    /**
     * Afficher le récapitulatif
     */
    private function showSummary(): void
    {
        $this->command->info('╔══════════════════════════════════════════════════════╗');
        $this->command->info('║        ✅ INITIALISATION TERMINÉE                   ║');
        $this->command->info('╚══════════════════════════════════════════════════════╝');
        $this->command->newLine();

        // Statistiques
        $this->command->info('📊 RÉSUMÉ :');
        $this->command->info('   • Utilisateurs      : ' . User::count());
        $this->command->info('   • Exercices         : ' . \App\Models\Exercice::count());
        $this->command->info('   • Paramètres struct : ' . \DB::table('parametres_structure')->count());

        // Rôles (si Spatie)
        if (class_exists('Spatie\Permission\Models\Role')) {
            $this->command->info('   • Rôles             : ' . \Spatie\Permission\Models\Role::count());
        }

        $this->command->newLine();

        // Informations de connexion
        $this->command->info('🔐 CONNEXION :');
        $this->command->info('   📧 Email    : kit@kisinit237.com');
        $this->command->info('   🔒 Password : Admin@1977');
        $this->command->newLine();

        // URL de l'application
        $appUrl = config('app.url');
        $this->command->info('🌐 URL : ' . $appUrl);
        $this->command->newLine();

        // Prochaines étapes
        $this->command->info('💡 PROCHAINES ÉTAPES :');
        $this->command->info('   1. Démarrer le serveur : php artisan serve --host=0.0.0.0');
        $this->command->info('   2. Ouvrir : ' . $appUrl);
        $this->command->info('   3. Se connecter avec les identifiants ci-dessus');
        $this->command->info('   4. Créer les autres utilisateurs depuis l\'interface');
        $this->command->newLine();
    }
}
