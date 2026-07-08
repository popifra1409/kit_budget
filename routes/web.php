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

Route::get('/', [WelcomeController::class, 'index'])->name('welcome');

Route::get('/compte-desactive', [CompteDesactiveController::class, 'index'])
    ->name('compte.desactive');

Route::post('/secure-logout', [SecureLogoutController::class, 'logout'])
    ->name('secure.logout')
    ->middleware('auth');

Route::middleware(['web', 'auth'])->group(function () {

    // ── Cadre Logique ─────────────────────────────────────────
    Route::get('/cadre-logique/telecharger', [CadreLogiqueController::class, 'telecharger'])
        ->name('cadre-logique.telecharger');

    // ── Mémoires de Dépenses ──────────────────────────────────
    Route::prefix('memoire-depense')
        ->middleware(['module.access:budget'])
        ->group(function () {
            Route::get('/{memoire}/pdf', [MemoireDepenseController::class, 'genererPdf'])
                ->name('memoire-depense.pdf');
            Route::get('/{memoire}/preview', [MemoireDepenseController::class, 'afficherPdf'])
                ->name('memoire-depense.preview');
        });

    // ── PDF Générique ─────────────────────────────────────────
    Route::prefix('pdf')->group(function () {
        Route::get('/telecharger/{etat}/{id}', [PdfDownloadController::class, 'telecharger'])
            ->name('pdf.telecharger');
        Route::get('/afficher/{etat}/{id}', [PdfDownloadController::class, 'afficher'])
            ->name('pdf.afficher');
    });

    // ── Bons de Commande PDF ──────────────────────────────────
    Route::get('/bons-commande/{id}/pdf/preview-simple', function (int $id) {
        $bonCommande = BonCommande::withoutGlobalScope('exercice')
            ->with(['fournisseur', 'serviceDemandeur', 'lignes.nomenclature', 'engagement'])
            ->findOrFail($id);
        return BonCommandePdfService::apercu($bonCommande, 'simple');
    })->name('bons-commande.pdf.preview.simple');

    Route::get('/bons-commande/{id}/pdf/download-simple', function (int $id) {
        $bonCommande = BonCommande::withoutGlobalScope('exercice')
            ->with(['fournisseur', 'serviceDemandeur', 'lignes.nomenclature', 'engagement'])
            ->findOrFail($id);
        return BonCommandePdfService::telecharger($bonCommande, 'simple');
    })->name('bons-commande.pdf.download.simple');

    Route::get('/bons-commande/{id}/pdf/preview-complet', function (int $id) {
        $bonCommande = BonCommande::withoutGlobalScope('exercice')
            ->with(['fournisseur', 'serviceDemandeur', 'lignes.nomenclature', 'engagement'])
            ->findOrFail($id);
        return BonCommandePdfService::apercu($bonCommande, 'complet');
    })->name('bons-commande.pdf.preview.complet');

    Route::get('/bons-commande/{id}/pdf/download-complet', function (int $id) {
        $bonCommande = BonCommande::withoutGlobalScope('exercice')
            ->with(['fournisseur', 'serviceDemandeur', 'lignes.nomenclature', 'engagement'])
            ->findOrFail($id);
        return BonCommandePdfService::telecharger($bonCommande, 'complet');
    })->name('bons-commande.pdf.download.complet');

    Route::get('/bons-commande/{id}/pdf/preview-simple-preimprime', function (int $id) {
        $bonCommande = BonCommande::withoutGlobalScope('exercice')
            ->with(['fournisseur', 'serviceDemandeur', 'lignes.nomenclature', 'engagement'])
            ->findOrFail($id);
        return BonCommandePdfService::apercu($bonCommande, 'simple_preimprime');
    })->name('bons-commande.pdf.preview.simple-preimprime');

    Route::get('/bons-commande/{id}/pdf/download/simple-preimprime', function (int $id) {
        $bonCommande = BonCommande::withoutGlobalScope('exercice')
            ->with(['fournisseur', 'serviceDemandeur', 'lignes.nomenclature', 'engagement'])
            ->findOrFail($id);
        return BonCommandePdfService::telecharger($bonCommande, 'simple_preimprime');
    })->name('bons-commande.pdf.download.simple-preimprime');

    // ── Décisions Administratives PDF ────────────────────────
    Route::get('/decisions-administratives/{id}/pdf/preview', function (int $id) {
        $decision = DecisionAdministrative::withoutGlobalScope('exercice')
            ->with(['personnel', 'fournisseur', 'typeDecision', 'exercice', 'budget', 'engagement'])
            ->findOrFail($id);
        return DecisionAdministrativePdfService::apercu($decision);
    })->name('decisions-administratives.pdf.preview');

    Route::get('/decisions-administratives/{id}/pdf/download', function (int $id) {
        $decision = DecisionAdministrative::withoutGlobalScope('exercice')
            ->with(['personnel', 'fournisseur', 'typeDecision', 'exercice', 'budget', 'engagement'])
            ->findOrFail($id);
        return DecisionAdministrativePdfService::telecharger($decision);
    })->name('decisions-administratives.pdf.download');

    // ── Expressions de Besoins PDF ────────────────────────────
    Route::get('/expressions-besoins/{id}/pdf/preview', function (int $id) {
        $expressionBesoin = \App\Models\ExpressionBesoin::with([
            'serviceDemandeur', 'responsableService', 'comptableMatieres',
            'ordonnateur', 'lignes.article.uniteMesure', 'lignes.conditionnement',
        ])->findOrFail($id);
        return \App\Services\ExpressionBesoinPdfService::apercu($expressionBesoin);
    })->name('expressions-besoins.pdf.preview');

    Route::get('/expressions-besoins/{id}/pdf/download', function (int $id) {
        $expressionBesoin = \App\Models\ExpressionBesoin::with([
            'serviceDemandeur', 'responsableService', 'comptableMatieres',
            'ordonnateur', 'lignes.article.uniteMesure', 'lignes.conditionnement',
        ])->findOrFail($id);
        return \App\Services\ExpressionBesoinPdfService::telecharger($expressionBesoin);
    })->name('expressions-besoins.pdf.download');

    // ── Fiche Contrôle Engagements ────────────────────────────
    Route::get('/fiche-controle-engagements/{id}/preview', [FicheControleEngagementsController::class, 'preview'])
        ->name('fiche-controle-engagements.preview');

    Route::get('/fiche-controle-engagements/{id}/pdf', [FicheControleEngagementsController::class, 'telechargerPdf'])
        ->name('fiche-controle-engagements.pdf');

    // ── Bons de Commande Régie ────────────────────────────────
    Route::get('/bcr/{bcr}/apercu', [App\Http\Controllers\BonCommandeRegiePdfController::class, 'apercu'])
        ->name('bcr.pdf.apercu')
        ->middleware(['auth']);

    Route::get('/bcr/{bcr}/telecharger', [App\Http\Controllers\BonCommandeRegiePdfController::class, 'telecharger'])
        ->name('bcr.pdf.telecharger')
        ->middleware(['auth']);

    // ── Décaissements Régie d'Avance ──────────────────────────
    Route::middleware(['auth'])->group(function () {
        Route::get(
            '/regie/{regie}/decaissement/{decaissement}/mandat/apercu',
            [App\Http\Controllers\MandatDecaissementPdfController::class, 'apercu']
        )->name('mandat.decaissement.apercu');

        Route::get(
            '/regie/{regie}/decaissement/{decaissement}/mandat/telecharger',
            [App\Http\Controllers\MandatDecaissementPdfController::class, 'telecharger']
        )->name('mandat.decaissement.telecharger');
    });

    // ── Prévisions de Recettes — Export Excel & PDF ───────────
    // ✅ Dans le groupe middleware web+auth pour sécuriser les téléchargements
    // Ces routes sont appelées via ->url() depuis PrevisionRecetteResource
    // (évite le problème de streaming dans Livewire/Filament actions)
    Route::get(
        '/prevision-recette/{id}/export/excel',
        [App\Http\Controllers\PrevisionRecetteExportController::class, 'excel']
    )->name('prevision-recette.export.excel');

    Route::get(
        '/prevision-recette/{id}/export/pdf',
        [App\Http\Controllers\PrevisionRecetteExportController::class, 'pdf']
    )->name('prevision-recette.export.pdf');
});

/*
|--------------------------------------------------------------------------
| Routes de test (désactivées en production)
|--------------------------------------------------------------------------
*/
if (config('app.env') !== 'production') {
    Route::prefix('test-pdf')->group(function () {
        Route::get('/certificat', [PdfTestController::class, 'certificat']);
        Route::get('/bon-commande', [PdfTestController::class, 'bonCommande']);
    });
}

Route::redirect('/', '/portal');