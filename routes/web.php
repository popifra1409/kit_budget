<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CadreLogiqueController;
use App\Http\Controllers\MemoireDepenseController;
use App\Http\Controllers\PdfTestController;
use App\Http\Controllers\PdfDownloadController;
use App\Http\Controllers\WelcomeController;
use App\Http\Controllers\CompteDesactiveController;
use App\Http\Controllers\SecureLogoutController;
use App\Services\BonCommandePdfService;
use App\Services\DecisionAdministrativePdfService;
use App\Models\BonCommande;
use App\Models\DecisionAdministrative;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Page de Bienvenue (Racine du Site)
|--------------------------------------------------------------------------
| Affiche une page de démarrage élégante avec logo et informations
| avant de rediriger vers la page de connexion
*/

Route::get('/', [WelcomeController::class, 'index'])->name('welcome');
Route::get('/compte-desactive', [CompteDesactiveController::class, 'index'])
    ->name('compte.desactive');

/*
| Déconnexion sécurisée
*/
Route::post('/secure-logout', [SecureLogoutController::class, 'logout'])
    ->name('secure.logout')
    ->middleware('auth');
/*
|--------------------------------------------------------------------------
| Routes authentifiées (nécessitent connexion)
|--------------------------------------------------------------------------
*/

Route::middleware(['web', 'auth'])->group(function () {

    // Cadre Logique - Téléchargement
    Route::get('/cadre-logique/telecharger', [CadreLogiqueController::class, 'telecharger'])
        ->name('cadre-logique.telecharger');

    // Mémoires de Dépenses - PDF
    Route::prefix('memoire-depense')->group(function () {
        Route::get('/{memoire}/pdf', [MemoireDepenseController::class, 'genererPdf'])
            ->name('memoire-depense.pdf');

        Route::get('/{memoire}/preview', [MemoireDepenseController::class, 'afficherPdf'])
            ->name('memoire-depense.preview');
    });

    // PDF - Téléchargement et Affichage
    Route::prefix('pdf')->group(function () {
        Route::get('/telecharger/{etat}/{id}', [PdfDownloadController::class, 'telecharger'])
            ->name('pdf.telecharger');

        Route::get('/afficher/{etat}/{id}', [PdfDownloadController::class, 'afficher'])
            ->name('pdf.afficher');
    });

    // Bons de commande
    Route::get('/bons-commande/{bonCommande}/pdf/preview', function (BonCommande $bonCommande) {
        return BonCommandePdfService::apercu($bonCommande);
    })->name('bons-commande.pdf.preview');
    // Téléchargement direct (AJOUTER)
    Route::get('/bons-commande/{bonCommande}/pdf/download', function (BonCommande $bonCommande) {
        return BonCommandePdfService::telecharger($bonCommande);
    })->name('bons-commande.pdf.download');

    // Décisions administratives
    Route::get('/decisions-administratives/{decision}/pdf/preview', function (DecisionAdministrative $decision) {
        return DecisionAdministrativePdfService::apercu($decision);
    })->name('decisions-administratives.pdf.preview');
    // Téléchargement direct (AJOUTER)
    Route::get('/decisions-administratives/{decision}/pdf/download', function (DecisionAdministrative $decision) {
        return DecisionAdministrativePdfService::telecharger($decision);
    })->name('decisions-administratives.pdf.download');
});

/*
|--------------------------------------------------------------------------
| Routes de test (à désactiver en production)
|--------------------------------------------------------------------------
*/

if (config('app.env') !== 'production') {
    Route::prefix('test-pdf')->group(function () {
        Route::get('/certificat', [PdfTestController::class, 'certificat']);
        Route::get('/bon-commande', [PdfTestController::class, 'bonCommande']);
    });
}
