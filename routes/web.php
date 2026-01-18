<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CadreLogiqueController;
use App\Http\Controllers\MemoireDepenseController;
use App\Http\Controllers\PdfTestController;
use App\Http\Controllers\PdfDownloadController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Redirection de la racine vers le panneau admin Filament
Route::get('/', function () {
    return redirect('/admin');
});

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
