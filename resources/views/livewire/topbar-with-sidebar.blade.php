<div>
    {{-- Quick Actions dans le Topbar --}}
    <div class="fi-topbar-actions flex items-center gap-3">

        {{-- Toggle Sidebar (Hide/Show) --}}
        <button type="button" x-data="sidebarToggle()" x-init="init()" @click="toggle()"
            x-bind:title="hidden ? 'Afficher la barre latérale' : 'Masquer la barre latérale'"
            class="fi-icon-btn relative flex items-center justify-center rounded-lg outline-none transition duration-75 hover:bg-gray-50 focus-visible:bg-gray-50 dark:hover:bg-white/5 dark:focus-visible:bg-white/5 -m-2 h-9 w-9 text-gray-400 hover:text-gray-500 focus-visible:text-gray-500 dark:text-gray-500 dark:hover:text-gray-400 dark:focus-visible:text-gray-400">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                stroke="currentColor" class="sidebar-toggle-icon h-5 w-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
        </button>

        {{-- Logo + Nom Structure --}}
        @if ($structure)
            <div
                class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700">
                @if ($structure->logo_url)
                    <img src="{{ $structure->logo_url }}" alt="{{ $structure->sigle ?? 'Logo' }}" class="h-7 w-auto">
                @endif

                <div class="flex flex-col">
                    <span class="text-sm font-semibold text-gray-900 dark:text-white">
                        {{ $structure->sigle ?? $structure->nom_structure }}
                    </span>
                    @if ($structure->sigle && $structure->nom_structure)
                        <span class="text-xs text-gray-500 dark:text-gray-400 hidden xl:block">
                            {{ Str::limit($structure->nom_structure, 35) }}
                        </span>
                    @endif
                </div>
            </div>
        @endif

        {{-- Quick Actions --}}
        <div class="flex items-center gap-2">

            {{-- AJOUTEZ VOS QUICK ACTIONS ICI --}}
            {{-- 
            Exemple:
            <a 
                href="/admin/resources/bon-commande-resource/create"
                class="fi-quick-action"
                title="Nouveau Bon de Commande"
            >
                <svg class="h-5 w-5">...</svg>
                <span class="hidden lg:inline">Nouveau BC</span>
            </a>
            --}}

            {{-- Action: Dashboard --}}
            <a href="/admin" class="fi-quick-action" title="Tableau de bord">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                </svg>
                <span class="hidden lg:inline">Dashboard</span>
            </a>

        </div>
    </div>

    {{-- Styles --}}
    <style>
        .fi-topbar-actions {
            margin-right: auto;
        }

        .fi-quick-action {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            border-radius: 0.5rem;
            background: transparent;
            color: rgb(var(--gray-700));
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }

        .fi-quick-action:hover {
            background: rgb(var(--primary-50));
            color: rgb(var(--primary-600));
            border-color: rgb(var(--primary-100));
        }

        .dark .fi-quick-action {
            color: rgb(var(--gray-400));
        }

        .dark .fi-quick-action:hover {
            background: rgb(var(--primary-500) / 0.1);
            color: rgb(var(--primary-400));
            border-color: rgb(var(--primary-500) / 0.2);
        }

        /* Animation toggle icon */
        body[data-sidebar-hidden="true"] .sidebar-toggle-icon {
            transform: rotate(90deg);
        }
    </style>
</div>
