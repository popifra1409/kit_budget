<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compte Désactivé</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            max-width: 500px;
            width: 100%;
        }

        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            text-align: center;
        }

        .icon-container {
            margin-bottom: 24px;
        }

        .icon {
            width: 80px;
            height: 80px;
            margin: 0 auto;
            background: #FEF3C7;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .icon svg {
            width: 40px;
            height: 40px;
            color: #F59E0B;
        }

        h1 {
            font-size: 24px;
            font-weight: 700;
            color: #1F2937;
            margin-bottom: 16px;
        }

        .message {
            font-size: 16px;
            color: #6B7280;
            line-height: 1.6;
            margin-bottom: 24px;
        }

        .info-box {
            background: #F3F4F6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 24px;
        }

        .info-box h2 {
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 12px;
        }

        .info-box p {
            font-size: 14px;
            color: #6B7280;
            line-height: 1.5;
        }

        .contact-info {
            background: #EEF2FF;
            border-left: 4px solid #6366F1;
            padding: 16px;
            border-radius: 4px;
            margin-bottom: 24px;
            text-align: left;
        }

        .contact-info strong {
            display: block;
            color: #4338CA;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .contact-info p {
            font-size: 14px;
            color: #4B5563;
            margin: 4px 0;
        }

        .contact-info a {
            color: #6366F1;
            text-decoration: none;
            font-weight: 500;
        }

        .contact-info a:hover {
            text-decoration: underline;
        }

        .actions {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #6366F1;
            color: white;
        }

        .btn-primary:hover {
            background: #4F46E5;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .btn-secondary {
            background: #F3F4F6;
            color: #374151;
        }

        .btn-secondary:hover {
            background: #E5E7EB;
        }

        .footer {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #E5E7EB;
        }

        .footer p {
            font-size: 12px;
            color: #9CA3AF;
        }

        @media (max-width: 640px) {
            .card {
                padding: 24px;
            }

            h1 {
                font-size: 20px;
            }

            .actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="card">
            <div class="icon-container">
                <div class="icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
            </div>

            <h1>Accès Désactivé</h1>

            <p class="message">
                Votre compte a été désactivé par un administrateur et vous ne pouvez plus accéder à l'application pour
                le moment.
            </p>

            <div class="info-box">
                <h2>Pourquoi mon compte est-il désactivé ?</h2>
                <p>
                    Votre compte peut avoir été temporairement désactivé pour diverses raisons (fin de contrat,
                    changement de fonction, maintenance, etc.).
                    Seul un administrateur peut réactiver votre accès.
                </p>
            </div>

            <div class="contact-info">
                <strong>📞 Contactez l'administration</strong>
                <p>Pour réactiver votre compte, veuillez contacter :</p>
                <p>Email : <a href="mailto:admin@exemple.com">admin@exemple.com</a></p>
                <p>Téléphone : +237 XXX XXX XXX</p>
            </div>

            <div class="actions">
                <a href="{{ route('filament.budget.auth.login') }}" class="btn btn-primary">
                    Retour à la connexion
                </a>
                <a href="mailto:admin@exemple.com?subject=Réactivation de compte&body=Bonjour,%0D%0A%0D%0AJe souhaiterais réactiver mon compte.%0D%0A%0D%0AEmail : {{ auth()->user()->email ?? '' }}%0D%0A%0D%0AMerci."
                    class="btn btn-secondary">
                    Envoyer un email
                </a>
            </div>

            <div class="footer">
                <p>© {{ date('Y') }} Gestion Budget - Tous droits réservés</p>
            </div>
        </div>
    </div>
</body>

</html>
