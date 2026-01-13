<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CadreLogiqueController;
use App\Http\Controllers\MemoireDepenseController;
use App\Services\PdfGenerator\PdfGenerator;
use App\Models\BordereauEngagement;

use App\Http\Controllers\PdfTestController;
use App\Http\Controllers\PdfDownloadController;

Route::get('/', function () {
    return view('/admin');
});

// Route POST pour le login (contournement)
// Route POST pour le login Filament (contournement)
Route::post('/admin/login', function (Request $request) {
    // Valider les credentials
    $credentials = $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    // Tenter l'authentification
    if (auth()->attempt($credentials, $request->boolean('remember'))) {
        $request->session()->regenerate();
        return redirect()->intended('/admin');
    }

    return back()->withErrors([
        'email' => 'Les identifiants fournis sont incorrects.',
    ])->onlyInput('email');
})->middleware(['web'])->name('filament.admin.auth.login.fallback');

Route::get('/cadre-logique/telecharger', [CadreLogiqueController::class, 'telecharger'])
    ->name('cadre-logique.telecharger')
    ->middleware('auth');

// Routes pour les mémoires de dépenses
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/memoire-depense/{memoire}/pdf', [MemoireDepenseController::class, 'genererPdf'])
        ->name('memoire-depense.pdf');

    Route::get('/memoire-depense/{memoire}/preview', [MemoireDepenseController::class, 'afficherPdf'])
        ->name('memoire-depense.preview');
});

Route::get('/test-pdf/certificat', [PdfTestController::class, 'certificat']);
Route::get('/test-pdf/bon-commande', [PdfTestController::class, 'bonCommande']);

Route::get('/pdf/telecharger/{etat}/{id}', function ($etat, $id, PdfGenerator $generator) {
    // Adapter selon votre modèle
    $record = BordereauEngagement::findOrFail($id);

    return $generator->telecharger($etat, $record);
})->name('pdf.telecharger')->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::get('/pdf/telecharger/{etat}/{id}', [PdfDownloadController::class, 'telecharger'])
        ->name('pdf.telecharger');

    Route::get('/pdf/afficher/{etat}/{id}', [PdfDownloadController::class, 'afficher'])
        ->name('pdf.afficher');
});
