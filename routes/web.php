<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CadreLogiqueController;
use App\Http\Controllers\MemoireDepenseController;

Route::get('/', function () {
    return view('welcome');
});

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
