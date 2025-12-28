<div class="flex items-center w-full gap-4">
    {{-- Logo + Sigle Structure (remplace "Laravel") --}}
    @if ($structure)
        <div class="fi-topbar-brand flex items-center gap-3">
            {{-- @if ($structure->logo_url)
                <img src="{{ $structure->logo_url }}" alt="{{ $structure->sigle ?? 'Logo' }}"
                    class="h-9 w-auto flex-shrink-0">
            @endif --}}

            <div class="flex flex-col min-w-0">
                <span class="text-sm font-bold text-gray-900 dark:text-white whitespace-nowrap">
                    {{ $structure->sigle ?? $structure->nom_structure }}
                </span>
                @if ($structure->sigle && $structure->nom_structure)
                    <span class="text-xs text-gray-500 dark:text-gray-400 hidden xl:block truncate">
                        {{ Str::limit($structure->nom_structure, 50) }}
                    </span>
                @endif
            </div>
        </div>
    @endif

    {{-- Quick Actions (alignées à droite avec ml-auto) --}}
    <div class="fi-topbar-quick-actions ml-auto flex items-center gap-2 flex-shrink-0">

        {{-- ============================================
             PERSONNALISEZ VOS URLs ICI
             ============================================
             
             Pour trouver vos URLs :
             1. Dans Filament, allez sur la liste de votre resource
             2. Cliquez sur "Créer" / "Nouveau"
             3. Copiez l'URL de la barre d'adresse
             4. Remplacez les URLs ci-dessous
             
             Exemples d'URLs Filament :
             - /admin/resources/bon-commande-resource/create
             - /admin/resources/engagement-resource/create
             - /admin/resources/memoire-depense-resource/create
             - /admin (dashboard)
        --}}

        {{-- Action 1: Nouveau Bon de Commande --}}
        {{-- REMPLACEZ L'URL CI-DESSOUS PAR LA VÔTRE --}}
        <a href="/admin" class="fi-quick-action fi-quick-action-primary" title="Nouveau Bon de Commande">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                <path fill-rule="evenodd"
                    d="M4.5 2A1.5 1.5 0 003 3.5v13A1.5 1.5 0 004.5 18h11a1.5 1.5 0 001.5-1.5V7.621a1.5 1.5 0 00-.44-1.06l-4.12-4.122A1.5 1.5 0 0011.378 2H4.5zm2.25 8.5a.75.75 0 000 1.5h6.5a.75.75 0 000-1.5h-6.5zm0 3a.75.75 0 000 1.5h6.5a.75.75 0 000-1.5h-6.5z"
                    clip-rule="evenodd" />
            </svg>
            <span class="hidden lg:inline font-medium">Nouveau BC</span>
        </a>

        {{-- Action 2: Nouvel Engagement --}}
        {{-- REMPLACEZ L'URL CI-DESSOUS PAR LA VÔTRE --}}
        <a href="/admin" class="fi-quick-action fi-quick-action-success" title="Nouvel Engagement">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                <path
                    d="M10.75 10.818v2.614A3.13 3.13 0 0011.888 13c.482-.315.612-.648.612-.875 0-.227-.13-.56-.612-.875a3.13 3.13 0 00-1.138-.432zM8.33 8.62c.053.055.115.11.184.164.208.16.46.284.736.363V6.603a2.45 2.45 0 00-.35.13c-.14.065-.27.143-.386.233-.377.292-.514.627-.514.909 0 .184.058.39.202.592.037.051.08.102.128.152z" />
                <path fill-rule="evenodd"
                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-6a.75.75 0 01.75.75v.316a3.78 3.78 0 011.653.713c.426.33.744.74.925 1.2a.75.75 0 01-1.395.55 1.35 1.35 0 00-.447-.563 2.187 2.187 0 00-.736-.363V9.3c.698.093 1.383.32 1.959.696.787.514 1.29 1.27 1.29 2.13 0 .86-.504 1.616-1.29 2.13-.576.377-1.261.603-1.96.696v.299a.75.75 0 11-1.5 0v-.3c-.697-.092-1.382-.318-1.958-.695-.482-.315-.857-.717-1.078-1.188a.75.75 0 111.359-.636c.08.173.245.376.54.569.313.205.706.353 1.138.432v-2.748a3.782 3.782 0 01-1.653-.713C6.9 9.433 6.5 8.681 6.5 7.875c0-.805.4-1.558 1.097-2.096a3.78 3.78 0 011.653-.713V4.75A.75.75 0 0110 4z"
                    clip-rule="evenodd" />
            </svg>
            <span class="hidden lg:inline font-medium">Engagement</span>
        </a>

        {{-- Action 3: Nouveau Mémoire de Dépense --}}
        {{-- REMPLACEZ L'URL CI-DESSOUS PAR LA VÔTRE --}}
        <a href="/admin" class="fi-quick-action fi-quick-action-warning" title="Nouveau Mémoire de Dépense">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                <path fill-rule="evenodd"
                    d="M4.5 2A1.5 1.5 0 003 3.5v13A1.5 1.5 0 004.5 18h11a1.5 1.5 0 001.5-1.5V7.621a1.5 1.5 0 00-.44-1.06l-4.12-4.122A1.5 1.5 0 0011.378 2H4.5zM8 8a.75.75 0 01.75-.75h2.5a.75.75 0 010 1.5h-2.5A.75.75 0 018 8zm0 2.75a.75.75 0 01.75-.75h2.5a.75.75 0 010 1.5h-2.5a.75.75 0 01-.75-.75zm0 2.75a.75.75 0 01.75-.75h2.5a.75.75 0 010 1.5h-2.5a.75.75 0 01-.75-.75z"
                    clip-rule="evenodd" />
            </svg>
            <span class="hidden lg:inline font-medium">Mémoire</span>
        </a>

        {{-- Nouveau Fournisseur --}}
        <a href="/admin/resources/FournisseurResource/create" class="fi-quick-action fi-quick-action-primary"
            title="Nouveau Fournisseur">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                <path
                    d="M10 9a3 3 0 100-6 3 3 0 000 6zM6 8a2 2 0 11-4 0 2 2 0 014 0zM1.49 15.326a.78.78 0 01-.358-.442 3 3 0 014.308-3.516 6.484 6.484 0 00-1.905 3.959c-.023.222-.014.442.025.654a4.97 4.97 0 01-2.07-.655zM16.44 15.98a4.97 4.97 0 002.07-.654.78.78 0 00.357-.442 3 3 0 00-4.308-3.517 6.484 6.484 0 011.907 3.96 2.32 2.32 0 01-.026.654zM18 8a2 2 0 11-4 0 2 2 0 014 0zM5.304 16.19a.844.844 0 01-.277-.71 5 5 0 019.947 0 .843.843 0 01-.277.71A6.975 6.975 0 0110 18a6.974 6.974 0 01-4.696-1.81z" />
            </svg>
            <span class="hidden lg:inline font-medium">Fournisseur</span>
        </a>

        {{-- Voir Budget --}}
        <a href="/admin/resources/budget-resource" class="fi-quick-action fi-quick-action-secondary"
            title="Voir Budget">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                <path fill-rule="evenodd"
                    d="M1 4a1 1 0 011-1h16a1 1 0 011 1v8a1 1 0 01-1 1H2a1 1 0 01-1-1V4zm12 4a3 3 0 11-6 0 3 3 0 016 0zM4 9a1 1 0 100-2 1 1 0 000 2zm13-1a1 1 0 11-2 0 1 1 0 012 0zM1.75 14.5a.75.75 0 000 1.5c4.417 0 8.693.603 12.749 1.73 1.111.309 2.251-.512 2.251-1.696v-.784a.75.75 0 00-1.5 0v.784a.272.272 0 01-.35.25A49.043 49.043 0 001.75 14.5z"
                    clip-rule="evenodd" />
            </svg>
            <span class="hidden lg:inline font-medium">Budget</span>
        </a>

        {{-- Divider --}}
        <div class="h-6 w-px bg-gray-200 dark:bg-gray-700 hidden md:block"></div>

        {{-- Action 4: Recherche rapide (utilise le Global Search natif Filament) --}}
        <button type="button" class="fi-quick-action fi-quick-action-secondary" title="Recherche rapide" x-data
            @click="$dispatch('open-global-search')">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                <path fill-rule="evenodd"
                    d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z"
                    clip-rule="evenodd" />
            </svg>
            <span class="hidden xl:inline font-medium">Recherche</span>
        </button>

        {{-- Action 5: Dashboard --}}
        <a href="/admin" class="fi-quick-action fi-quick-action-secondary" title="Tableau de bord">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                <path
                    d="M10.75 16.82A7.462 7.462 0 0115 15.5c.71 0 1.396.098 2.046.282A.75.75 0 0018 15.06v-11a.75.75 0 00-.546-.721A9.006 9.006 0 0015 3a8.963 8.963 0 00-4.25 1.065V16.82zM9.25 4.065A8.963 8.963 0 005 3c-.85 0-1.673.118-2.454.339A.75.75 0 002 4.06v11a.75.75 0 00.954.721A7.506 7.506 0 015 15.5c1.579 0 3.042.487 4.25 1.32V4.065z" />
            </svg>
            <span class="hidden xl:inline font-medium">Dashboard</span>
        </a>

        {{-- Divider --}}
        <div class="h-6 w-px bg-gray-200 dark:bg-gray-700 hidden md:block"></div>

        {{-- Badge: Exercice en cours --}}
        <div
            class="hidden lg:flex items-center gap-2 px-3 py-1.5 rounded-lg bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                class="h-4 w-4 text-primary-600 dark:text-primary-400">
                <path fill-rule="evenodd"
                    d="M5.75 2a.75.75 0 01.75.75V4h7V2.75a.75.75 0 011.5 0V4h.25A2.75 2.75 0 0118 6.75v8.5A2.75 2.75 0 0115.25 18H4.75A2.75 2.75 0 012 15.25v-8.5A2.75 2.75 0 014.75 4H5V2.75A.75.75 0 015.75 2zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75z"
                    clip-rule="evenodd" />
            </svg>
            <span class="text-sm font-semibold text-primary-700 dark:text-primary-300">{{ now()->year }}</span>
        </div>
    </div>

    {{-- Styles --}}
    <style>
        /* Container principal en flex */
        .fi-topbar>.fi-topbar-start {
            display: flex;
            align-items: center;
            width: 100%;
            gap: 1rem;
        }

        /* Brand logo - ne prend que l'espace nécessaire */
        .fi-topbar-brand {
            flex-shrink: 0;
        }

        /* Quick actions container - pousse vers la droite */
        .fi-topbar-quick-actions {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-shrink: 0;
        }

        /* Base quick action */
        .fi-quick-action {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
            border: 1px solid transparent;
            cursor: pointer;
            white-space: nowrap;
        }

        /* Primary action (Nouveau BC) */
        .fi-quick-action-primary {
            background: linear-gradient(135deg, rgb(37, 99, 235) 0%, rgb(29, 78, 216) 100%);
            color: white;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }

        .fi-quick-action-primary:hover {
            background: linear-gradient(135deg, rgb(29, 78, 216) 0%, rgb(30, 64, 175) 100%);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transform: translateY(-1px);
        }

        /* Success action (Engagement) */
        .fi-quick-action-success {
            background: rgb(240, 253, 244);
            color: rgb(21, 128, 61);
            border-color: rgb(187, 247, 208);
        }

        .fi-quick-action-success:hover {
            background: rgb(220, 252, 231);
            border-color: rgb(134, 239, 172);
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        }

        .dark .fi-quick-action-success {
            background: rgba(34, 197, 94, 0.1);
            color: rgb(74, 222, 128);
            border-color: rgba(34, 197, 94, 0.2);
        }

        .dark .fi-quick-action-success:hover {
            background: rgba(34, 197, 94, 0.2);
            border-color: rgba(34, 197, 94, 0.3);
        }

        /* Warning action (Mémoire) */
        .fi-quick-action-warning {
            background: rgb(255, 251, 235);
            color: rgb(180, 83, 9);
            border-color: rgb(253, 230, 138);
        }

        .fi-quick-action-warning:hover {
            background: rgb(254, 243, 199);
            border-color: rgb(252, 211, 77);
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        }

        .dark .fi-quick-action-warning {
            background: rgba(251, 191, 36, 0.1);
            color: rgb(251, 191, 36);
            border-color: rgba(251, 191, 36, 0.2);
        }

        .dark .fi-quick-action-warning:hover {
            background: rgba(251, 191, 36, 0.2);
            border-color: rgba(251, 191, 36, 0.3);
        }

        /* Secondary actions (Recherche, Dashboard) */
        .fi-quick-action-secondary {
            background: transparent;
            color: rgb(55, 65, 81);
            border-color: transparent;
        }

        .fi-quick-action-secondary:hover {
            background: rgb(243, 244, 246);
            color: rgb(17, 24, 39);
        }

        .dark .fi-quick-action-secondary {
            color: rgb(156, 163, 175);
        }

        .dark .fi-quick-action-secondary:hover {
            background: rgb(31, 41, 55);
            color: rgb(229, 231, 235);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .fi-topbar-brand {
                margin-right: 0;
            }

            .fi-quick-action {
                padding: 0.5rem 0.75rem;
            }
        }

        @media (max-width: 768px) {
            .fi-topbar-brand span:last-child {
                display: none;
            }
        }
    </style>
</div>
