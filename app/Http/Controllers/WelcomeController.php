<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WelcomeController extends Controller
{
    /**
     * Afficher la page de bienvenue
     * 
     * Si l'utilisateur est déjà connecté, le rediriger vers le dashboard
     */
    public function index()
    {
        // Si l'utilisateur est déjà connecté, le rediriger vers le dashboard
        if (auth()->check()) {
            return redirect('/admin');
        }

        // Sinon, afficher la page de bienvenue
        return view('welcome');
    }
}
