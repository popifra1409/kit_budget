<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenue - Système de Gestion Budgétaire</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.8s ease-out forwards;
        }
        
        .animate-float {
            animation: float 3s ease-in-out infinite;
        }
        
        .delay-100 { animation-delay: 0.1s; }
        .delay-200 { animation-delay: 0.2s; }
        .delay-300 { animation-delay: 0.3s; }
        .delay-400 { animation-delay: 0.4s; }
        .delay-500 { animation-delay: 0.5s; }
        
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center p-4">
    
    <div class="max-w-5xl w-full">
        
        <!-- Carte Principale -->
        <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">
            
            <!-- En-tête avec Logo -->
            <div class="bg-gradient-to-r from-purple-600 to-indigo-600 p-12 text-center text-white opacity-0 animate-fade-in">
                
                <!-- Logo -->
                <div class="mb-6 animate-float">
                    @if(file_exists(public_path('images/logo.png')))
                        <img src="{{ asset('images/logo.png') }}" alt="Logo" class="h-32 mx-auto">
                    @else
                        <svg class="h-32 w-32 mx-auto text-white" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2L2 7v10c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-10-5zm0 2.18l8 3.6v8.72c0 4.35-3 8.38-7.5 9.45-.37-.09-.74-.18-1.10-.29-3.59-1.04-6.4-4.52-6.4-9.16V7.78l7-3.14V4.18z"/>
                            <path d="M9.5 11.5l2 2 4-4 1.5 1.5-5.5 5.5-3.5-3.5z"/>
                        </svg>
                    @endif
                </div>
                
                <h1 class="text-4xl md:text-5xl font-bold mb-4">
                    Bienvenue
                </h1>
                <p class="text-xl text-purple-100">
                    Système de Gestion Budgétaire
                </p>
            </div>
            
            <!-- Contenu -->
            <div class="p-8 md:p-12">
                
                <!-- Description -->
                <div class="text-center mb-10 opacity-0 animate-fade-in delay-200">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">
                        Solution Complète de Pilotage Financier
                    </h2>
                    <p class="text-gray-600 max-w-2xl mx-auto">
                        Gérez vos budgets, suivez vos recettes et dépenses, et pilotez votre performance financière avec des outils puissants et intuitifs.
                    </p>
                </div>
                
                <!-- Fonctionnalités en Grille -->
                <div class="grid md:grid-cols-3 gap-8 mb-10">
                    
                    <!-- Feature 1 -->
                    <div class="text-center opacity-0 animate-fade-in delay-300">
                        <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                        <h3 class="font-bold text-lg text-gray-800 mb-2">Budgets Annuels</h3>
                        <p class="text-sm text-gray-600">
                            Élaboration, suivi et analyse de vos budgets prévisionnels
                        </p>
                    </div>
                    
                    <!-- Feature 2 -->
                    <div class="text-center opacity-0 animate-fade-in delay-400">
                        <div class="w-16 h-16 bg-gradient-to-br from-green-500 to-teal-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <h3 class="font-bold text-lg text-gray-800 mb-2">Recettes Réelles</h3>
                        <p class="text-sm text-gray-600">
                            Suivi en temps réel de vos encaissements et taux de réalisation
                        </p>
                    </div>
                    
                    <!-- Feature 3 -->
                    <div class="text-center opacity-0 animate-fade-in delay-500">
                        <div class="w-16 h-16 bg-gradient-to-br from-orange-500 to-red-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/>
                            </svg>
                        </div>
                        <h3 class="font-bold text-lg text-gray-800 mb-2">Tableaux de Bord</h3>
                        <p class="text-sm text-gray-600">
                            Visualisez vos indicateurs clés et prenez les bonnes décisions
                        </p>
                    </div>
                    
                </div>
                
                <!-- Statistiques -->
                <div class="bg-gradient-to-r from-purple-50 to-indigo-50 rounded-2xl p-6 mb-10 opacity-0 animate-fade-in delay-300">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
                        <div>
                            <div class="text-3xl font-bold text-purple-600">{{ date('Y') }}</div>
                            <div class="text-sm text-gray-600">Exercice en cours</div>
                        </div>
                        <div>
                            <div class="text-3xl font-bold text-indigo-600">4.0</div>
                            <div class="text-sm text-gray-600">Version</div>
                        </div>
                        <div>
                            <div class="text-3xl font-bold text-green-600">24/7</div>
                            <div class="text-sm text-gray-600">Support</div>
                        </div>
                        <div>
                            <div class="text-3xl font-bold text-orange-600">SSL</div>
                            <div class="text-sm text-gray-600">Sécurisé</div>
                        </div>
                    </div>
                </div>
                
                <!-- Bouton Principal -->
                <div class="text-center opacity-0 animate-fade-in delay-400">
                    <a href="{{ route('filament.admin.auth.login') }}" 
                       class="inline-flex items-center px-10 py-4 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-full font-semibold text-lg shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200">
                        <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                        </svg>
                        <span>Se Connecter</span>
                    </a>
                    
                    <p class="mt-6 text-sm text-gray-500">
                        Nouveau sur la plateforme ? Contactez votre administrateur pour obtenir un compte.
                    </p>
                </div>
                
            </div>
            
            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-6 text-center text-sm text-gray-600 border-t opacity-0 animate-fade-in delay-500">
                <p>&copy; {{ date('Y') }} Système de Gestion Budgétaire. Tous droits réservés.</p>
                <p class="mt-2 text-xs">Développé avec ❤️ par Votre Équipe</p>
            </div>
            
        </div>
        
    </div>
    
</body>
</html>