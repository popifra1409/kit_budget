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
use App\Models\ParametresStructure;
use App\Http\Controllers\FicheControleEngagementsController;

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

    // BC Simple
    Route::get('/bons-commande/{bonCommande}/pdf/preview-simple', function (BonCommande $bonCommande) {
        return BonCommandePdfService::apercu($bonCommande, 'simple');
    })->name('bons-commande.pdf.preview.simple');

    Route::get('/bons-commande/{bonCommande}/pdf/download-simple', function (BonCommande $bonCommande) {
        return BonCommandePdfService::telecharger($bonCommande, 'simple');
    })->name('bons-commande.pdf.download.simple');

    // BC Complet
    Route::get('/bons-commande/{bonCommande}/pdf/preview-complet', function (BonCommande $bonCommande) {
        return BonCommandePdfService::apercu($bonCommande, 'complet');
    })->name('bons-commande.pdf.preview.complet');

    Route::get('/bons-commande/{bonCommande}/pdf/download-complet', function (BonCommande $bonCommande) {
        return BonCommandePdfService::telecharger($bonCommande, 'complet');
    })->name('bons-commande.pdf.download.complet');

    // BC pour le papier préimprimé
    Route::get('/bons-commande/{bonCommande}/pdf/preview-simple-preimprime', function (BonCommande $bonCommande) {
        return BonCommandePdfService::apercu($bonCommande, 'simple_preimprime');
    })->name('bons-commande.pdf.preview.simple-preimprime');

    Route::get('/bons-commande/{bonCommande}/pdf/download/simple-preimprime', function (BonCommande $bonCommande) {
        return BonCommandePdfService::telecharger($bonCommande, 'simple_preimprime');
    })->name('bons-commande.pdf.download.simple-preimprime');

    // Décisions administratives
    Route::get('/decisions-administratives/{decision}/pdf/preview', function (DecisionAdministrative $decision) {
        return DecisionAdministrativePdfService::apercu($decision);
    })->name('decisions-administratives.pdf.preview');
    // Téléchargement direct (AJOUTER)
    Route::get('/decisions-administratives/{decision}/pdf/download', function (DecisionAdministrative $decision) {
        return DecisionAdministrativePdfService::telecharger($decision);
    })->name('decisions-administratives.pdf.download');

    Route::get('/fiche-controle-engagements/{id}/preview', [FicheControleEngagementsController::class, 'preview'])->name('fiche-controle-engagements.preview');
    Route::get('/fiche-controle-engagements/{id}/pdf', [FicheControleEngagementsController::class, 'telechargerPdf'])->name('fiche-controle-engagements.pdf');
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
Route::redirect('/', '/portal');
