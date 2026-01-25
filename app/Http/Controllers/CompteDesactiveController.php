<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CompteDesactiveController extends Controller
{
    /**
     * Afficher la page de compte désactivé
     */
    public function index()
    {
        // Déconnecter l'utilisateur s'il est encore connecté
        if (auth()->check()) {
            auth()->logout();
        }

        return view('compte-desactive');
    }
}
