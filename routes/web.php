<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CadreLogiqueController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/cadre-logique/telecharger', [CadreLogiqueController::class, 'telecharger'])
    ->name('cadre-logique.telecharger')
    ->middleware('auth');
