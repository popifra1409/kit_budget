<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenue - Système de Gestion Budgétaire</title>

    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }

        .animate-fade-in {
            animation: fadeIn 0.8s ease-out forwards;
        }

        .animate-slide-in {
            animation: slideIn 0.6s ease-out forwards;
        }

        .animate-pulse-slow {
            animation: pulse 2s ease-in-out infinite;
        }

        .delay-100 {
            animation-delay: 0.1s;
        }

        .delay-200 {
            animation-delay: 0.2s;
        }

        .delay-300 {
            animation-delay: 0.3s;
        }

        .delay-400 {
            animation-delay: 0.4s;
        }

        .delay-500 {
            animation-delay: 0.5s;
        }

        .delay-600 {
            animation-delay: 0.6s;
        }

        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* Auto-redirect countdown */
        @keyframes countdown {
            from {
                stroke-dashoffset: 0;
            }

            to {
                stroke-dashoffset: 283;
            }
        }

        .countdown-circle {
            animation: countdown 5s linear forwards;
        }
    </style>
</head>

<body class="gradient-bg min-h-screen flex items-center justify-center p-4">

    <!-- Particules d'arrière-plan (optionnel) -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div
            class="absolute top-10 left-10 w-72 h-72 bg-purple-300 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-pulse-slow">
        </div>
        <div
            class="absolute top-40 right-10 w-72 h-72 bg-yellow-300 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-pulse-slow delay-200">
        </div>
        <div
            class="absolute bottom-10 left-40 w-72 h-72 bg-pink-300 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-pulse-slow delay-400">
        </div>
    </div>

    <!-- Contenu Principal -->
    <div class="relative z-10 max-w-4xl w-full">

        <!-- Carte Principale -->
        <div class="glass-effect rounded-3xl shadow-2xl p-8 md:p-12 text-white opacity-0 animate-fade-in">

            <!-- Logo et Titre -->
            <div class="text-center mb-8 opacity-0 animate-fade-in delay-100">
                <!-- Logo -->
                <div class="mb-6">
                    @if (file_exists(public_path('images/logo.png')))
                        <img src="{{ asset('images/logo.png') }}" alt="Logo" class="h-32 mx-auto">
                    @else
                        <!-- Logo par défaut SVG -->
                        <svg class="h-32 w-32 mx-auto text-white" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M12 2L2 7v10c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-10-5zm0 2.18l8 3.6v8.72c0 4.35-3 8.38-7.5 9.45-.37-.09-.74-.18-1.1-.29-3.59-1.04-6.4-4.52-6.4-9.16V7.78l7-3.14V4.18z" />
                            <path d="M9.5 11.5l2 2 4-4 1.5 1.5-5.5 5.5-3.5-3.5z" />
                        </svg>
                    @endif
                </div>

                <!-- Titre Principal -->
                <h1 class="text-4xl md:text-5xl font-bold mb-3">
                    Système de Gestion Budgétaire
                </h1>

                <!-- Sous-titre -->
                <p class="text-xl md:text-2xl font-light text-purple-100">
                    Solution Complète de Pilotage Financier
                </p>
            </div>

            <!-- Séparateur -->
            <div
                class="w-24 h-1 bg-gradient-to-r from-purple-400 to-pink-400 mx-auto mb-8 rounded-full opacity-0 animate-fade-in delay-200">
            </div>

            <!-- Informations Utiles -->
            <div class="grid md:grid-cols-3 gap-6 mb-10">

                <!-- Feature 1 -->
                <div class="text-center opacity-0 animate-slide-in delay-300">
                    <div
                        class="w-16 h-16 bg-white bg-opacity-20 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <h3 class="font-semibold text-lg mb-2">Gestion des Budgets</h3>
                    <p class="text-sm text-purple-100">
                        Élaboration et suivi des budgets annuels
                    </p>
                </div>

                <!-- Feature 2 -->
                <div class="text-center opacity-0 animate-slide-in delay-400">
                    <div
                        class="w-16 h-16 bg-white bg-opacity-20 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 class="font-semibold text-lg mb-2">Suivi des Recettes</h3>
                    <p class="text-sm text-purple-100">
                        Prévisions et réalisation en temps réel
                    </p>
                </div>

                <!-- Feature 3 -->
                <div class="text-center opacity-0 animate-slide-in delay-500">
                    <div
                        class="w-16 h-16 bg-white bg-opacity-20 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <h3 class="font-semibold text-lg mb-2">Tableaux de Bord</h3>
                    <p class="text-sm text-purple-100">
                        Indicateurs et analytics avancés
                    </p>
                </div>

            </div>

            <!-- Informations Système -->
            <div class="bg-white bg-opacity-10 rounded-xl p-6 mb-8 opacity-0 animate-fade-in delay-600">
                <div class="grid md:grid-cols-2 gap-4 text-sm">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2 text-green-300" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd" />
                        </svg>
                        <span>Version 4.0 - Production</span>
                    </div>
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-300" fill="currentColor" viewBox="0 0 20 20">
                            <path
                                d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z" />
                        </svg>
                        <span>Exercice {{ date('Y') }}</span>
                    </div>
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2 text-yellow-300" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"
                                clip-rule="evenodd" />
                        </svg>
                        <span>Connexion Sécurisée SSL</span>
                    </div>
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2 text-purple-300" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                            <path fill-rule="evenodd"
                                d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z"
                                clip-rule="evenodd" />
                        </svg>
                        <span>Support 24/7</span>
                    </div>
                </div>
            </div>

            <!-- Bouton d'Action -->
            <div class="text-center opacity-0 animate-fade-in delay-600">
                <a href="{{ url('/admin/login') }}"
                    class="inline-flex items-center px-8 py-4 bg-white text-purple-700 rounded-full font-semibold text-lg shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200">
                    <span>Accéder au Système</span>
                    <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                </a>

                <!-- Ou redirection automatique -->
                <p class="mt-4 text-sm text-purple-200">
                    <span id="countdown-text">Redirection automatique dans <span id="countdown">5</span>
                        secondes...</span>
                </p>
            </div>

            <!-- Footer -->
            <div
                class="mt-10 pt-6 border-t border-white border-opacity-20 text-center text-sm text-purple-100 opacity-0 animate-fade-in delay-600">
                <p>
                    &copy; {{ date('Y') }} Système de Gestion Budgétaire. Tous droits réservés.
                </p>
                <p class="mt-2">
                    Développé avec ❤️ par l'Equipe de GEC-INFORMATIQUE
                </p>
            </div>

        </div>

    </div>

    <!-- Script de redirection automatique -->
    <script>
        // Redirection automatique après 5 secondes
        let countdown = 5;
        const countdownElement = document.getElementById('countdown');
        const countdownText = document.getElementById('countdown-text');

        const timer = setInterval(() => {
            countdown--;
            countdownElement.textContent = countdown;

            if (countdown <= 0) {
                clearInterval(timer);
                countdownText.textContent = 'Redirection en cours...';
                window.location.href = "{{ url('/admin/login') }}";
            }
        }, 1000);

        // Optionnel: Annuler la redirection si l'utilisateur clique sur le bouton
        document.querySelector('a[href*="login"]').addEventListener('click', () => {
            clearInterval(timer);
        });
    </script>

</body>

</html>
