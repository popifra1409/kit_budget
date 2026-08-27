<x-filament-panels::page>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap');

        /* ── Reset & conteneur principal ──────────────────────────── */
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        .portal-wrap {
            font-family: 'Sora', sans-serif;
            min-height: calc(100vh - 130px);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: .75rem;
            padding: 1rem 1.5rem;
        }

        /* ── En-tête ───────────────────────────────────────────────── */
        .portal-head {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: .45rem;
            text-align: center;
        }

        .portal-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            font-family: 'JetBrains Mono', monospace;
            font-size: .68rem;
            font-weight: 500;
            letter-spacing: .13em;
            text-transform: uppercase;
            color: #0ea5e9;
            background: rgba(14, 165, 233, .08);
            border: 1px solid rgba(14, 165, 233, .22);
            padding: .25rem .9rem;
            border-radius: 999px;
        }

        .portal-title {
            font-size: clamp(1.45rem, 2.8vw, 2.1rem);
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -.02em;
            color: #0f172a;
            margin: 0;
        }

        .dark .portal-title {
            color: #f8fafc;
        }

        .portal-title span {
            background: linear-gradient(135deg, #0ea5e9, #7c3aed);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .portal-sub {
            font-size: .85rem;
            color: #64748b;
            max-width: 440px;
            margin: 0;
            line-height: 1.5;
        }

        .dark .portal-sub {
            color: #94a3b8;
        }

        /* ── Grille ────────────────────────────────────────────────── */
        /* Adaptative : 4 cartes sur une ligne en grand ecran,
           2x2 sur tablette, 1 colonne sur mobile. S'ajuste
           automatiquement si un futur module est ajoute. */
        .modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: .875rem;
            width: 100%;
            max-width: 1100px;
            margin: 0 auto;
        }

        @media (max-width: 900px) {
            .modules-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 560px) {
            .modules-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ── Carte active ──────────────────────────────────────────── */
        .mod-card {
            display: flex;
            flex-direction: column;
            background: rgba(255, 255, 255, .88);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(226, 232, 240, .8);
            border-radius: 1.1rem;
            padding: 1.1rem;
            text-decoration: none;
            transition: all .26s cubic-bezier(.34, 1.56, .64, 1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .dark .mod-card {
            background: rgba(30, 41, 59, .78);
            border-color: rgba(71, 85, 105, .5);
        }

        .mod-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2.5px;
            background: var(--c);
            border-radius: 1.1rem 1.1rem 0 0;
            opacity: 0;
            transition: opacity .22s;
        }

        .mod-card:hover {
            transform: translateY(-4px) scale(1.008);
            box-shadow: 0 14px 36px -8px var(--s);
        }

        .mod-card:hover::before {
            opacity: 1;
        }

        /* ── Carte verrouillée ─────────────────────────────────────── */
        .mod-card-locked {
            display: flex;
            flex-direction: column;
            background: rgba(248, 250, 252, .5);
            border: 1px dashed rgba(148, 163, 184, .35);
            border-radius: 1.1rem;
            padding: 1.1rem;
            opacity: .58;
            cursor: not-allowed;
            filter: grayscale(.35);
        }

        .dark .mod-card-locked {
            background: rgba(15, 23, 42, .35);
            border-color: rgba(71, 85, 105, .3);
        }

        /* ── Éléments internes ─────────────────────────────────────── */
        .mod-header {
            display: flex;
            align-items: center;
            gap: .7rem;
            margin-bottom: .6rem;
        }

        .mod-icon {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: .7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
            background: var(--ib, rgba(148, 163, 184, .08));
            border: 1px solid var(--is, rgba(148, 163, 184, .2));
        }

        .mod-num {
            font-family: 'JetBrains Mono', monospace;
            font-size: .62rem;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--cc, #94a3b8);
            margin-bottom: .18rem;
        }

        .mod-title {
            font-size: .97rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
            line-height: 1.2;
        }

        .dark .mod-title {
            color: #f1f5f9;
        }

        .mod-title-locked {
            color: #94a3b8 !important;
        }

        .mod-desc {
            font-size: .8rem;
            color: #64748b;
            line-height: 1.5;
            flex-grow: 1;
            margin-bottom: .55rem;
        }

        .dark .mod-desc {
            color: #94a3b8;
        }

        .mod-tags {
            display: flex;
            flex-wrap: wrap;
            gap: .25rem;
            margin-bottom: .55rem;
        }

        .mod-tag {
            font-size: .63rem;
            font-weight: 500;
            padding: .15rem .5rem;
            border-radius: 999px;
            background: var(--tb);
            color: var(--tc);
            border: 1px solid var(--tbo);
        }

        .mod-cta {
            display: flex;
            align-items: center;
            gap: .4rem;
            font-size: .78rem;
            font-weight: 600;
            color: var(--cc);
            margin-top: auto;
        }

        .mod-card:hover .mod-cta-arrow {
            transform: translateX(3px);
        }

        .mod-cta-arrow {
            transition: transform .18s;
        }

        .mod-locked-msg {
            display: flex;
            align-items: center;
            gap: .35rem;
            font-size: .75rem;
            color: #94a3b8;
            margin-top: auto;
        }

        /* ── Footer ────────────────────────────────────────────────── */
        .portal-footer {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1.5rem;
            flex-wrap: wrap;
            padding-top: .6rem;
            border-top: 1px solid rgba(226, 232, 240, .6);
            width: 100%;
            max-width: 900px;
            margin: 0 auto;
        }

        .dark .portal-footer {
            border-top-color: rgba(71, 85, 105, .4);
        }

        .pf-stat {
            display: flex;
            align-items: center;
            gap: .35rem;
            font-size: .73rem;
            color: #94a3b8;
        }

        .pf-dot {
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: #22c55e;
            box-shadow: 0 0 4px #22c55e;
            animation: pd 2s ease infinite;
        }

        @keyframes pd {

            0%,
            100% {
                opacity: 1;
                transform: scale(1);
            }

            50% {
                opacity: .7;
                transform: scale(1.3);
            }
        }

        /* ── Toast ─────────────────────────────────────────────────── */
        .portal-toast {
            position: fixed;
            top: 1.25rem;
            right: 1.25rem;
            z-index: 9999;
            background: rgba(220, 38, 38, .95);
            color: #fff;
            padding: .7rem 1.1rem;
            border-radius: .75rem;
            font-size: .82rem;
            font-weight: 500;
            box-shadow: 0 8px 24px rgba(220, 38, 38, .3);
            display: flex;
            align-items: center;
            gap: .5rem;
            animation: toastIn .3s ease;
            max-width: 360px;
        }

        @keyframes toastIn {
            from {
                opacity: 0;
                transform: translateX(20px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
    </style>

    @php
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole(['super_admin', 'admin']);
        $canPlanification = $isSuperAdmin || $user->can('access_module_planification');
        $canBudget = $isSuperAdmin || $user->can('access_module_budget');
        $canComptable = $isSuperAdmin || $user->can('access_module_comptable');
        $canMarches = $isSuperAdmin || $user->can('access_module_marches');
        $modulesActifs = collect([$canPlanification, $canBudget, $canComptable, $canMarches])->filter()->count();
    @endphp

    {{-- Toast accès refusé --}}
    @if(session('module_access_denied'))
        <div class="portal-toast">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
            </svg>
            {{ session('module_access_denied') }}
        </div>
        <script>
            setTimeout(() => {
                const t = document.querySelector('.portal-toast');
                if (t) { t.style.transition = 'opacity .4s'; t.style.opacity = '0'; setTimeout(() => t.remove(), 400); }
            }, 4500);
        </script>
    @endif

    <div class="portal-wrap">

        {{-- En-tête --}}
        <div class="portal-head">
            <div class="portal-eyebrow">
                <svg width="8" height="8" viewBox="0 0 8 8" fill="currentColor">
                    <circle cx="4" cy="4" r="3" />
                </svg>
                SIGB &mdash; Portail applicatif
            </div>
            <h1 class="portal-title">Choisissez votre <span>module de gestion</span></h1>
            <p class="portal-sub">Chaque module dispose de sa propre interface. Votre session est partagée entre les
                modules.</p>
        </div>

        {{-- Grille --}}
        <div class="modules-grid">

            {{-- MODULE 01 : Planification Stratégique --}}
            @if($canPlanification)
                <a href="/planification" class="mod-card"
                    style="--c:linear-gradient(90deg,#028090,#02c39a);--cc:#028090;--s:rgba(2,128,144,.22);--ib:rgba(2,128,144,.1);--is:rgba(2,128,144,.2);--tb:rgba(2,128,144,.08);--tc:#014e57;--tbo:rgba(2,128,144,.2)">
                    <div class="mod-header">
                        <div class="mod-icon">🎯</div>
                        <div>
                            <div class="mod-num">Module 01</div>
                            <div class="mod-title">Planification Stratégique</div>
                        </div>
                    </div>
                    <div class="mod-desc">Cadrage stratégique du secteur santé, CSP ministériel, plans stratégiques et
                        sous-programmes de l'établissement.</div>
                    <div class="mod-tags">
                        <span class="mod-tag">CSP</span>
                        <span class="mod-tag">Plans Stratégiques</span>
                        <span class="mod-tag">Sous-Programmes</span>
                        <span class="mod-tag">Activités</span>
                    </div>
                    <div class="mod-cta">
                        Accéder au module
                        <svg class="mod-cta-arrow" width="14" height="14" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                d="M17 8l4 4m0 0l-4 4m4-4H3" />
                        </svg>
                    </div>
                </a>
            @else
                <div class="mod-card-locked" title="Accès non autorisé — contactez votre administrateur">
                    <div class="mod-header">
                        <div class="mod-icon" style="opacity:.4;">🎯</div>
                        <div>
                            <div class="mod-num" style="color:#94a3b8;">Module 01</div>
                            <div class="mod-title mod-title-locked">Planification Stratégique</div>
                        </div>
                    </div>
                    <div class="mod-desc" style="color:#94a3b8;">Accès restreint à ce module.</div>
                    <div class="mod-tags">
                        <span class="mod-tag"
                            style="background:rgba(148,163,184,.08);color:#94a3b8;border-color:rgba(148,163,184,.2);">Accès
                            restreint</span>
                    </div>
                    <div class="mod-locked-msg">
                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        Contacter l'administrateur
                    </div>
                </div>
            @endif

            {{-- MODULE 02 : Budget --}}
            @if($canBudget)
                <a href="/budget" class="mod-card"
                    style="--c:linear-gradient(90deg,#0ea5e9,#38bdf8);--cc:#0ea5e9;--s:rgba(14,165,233,.22);--ib:rgba(14,165,233,.1);--is:rgba(14,165,233,.2);--tb:rgba(14,165,233,.08);--tc:#0369a1;--tbo:rgba(14,165,233,.2)">
                    <div class="mod-header">
                        <div class="mod-icon">💰</div>
                        <div>
                            <div class="mod-num">Module 02</div>
                            <div class="mod-title">Gestion Budgétaire</div>
                        </div>
                    </div>
                    <div class="mod-desc">Bons de commande, engagements, mémoires de dépenses, lignes budgétaires et
                        tableaux de bord.</div>
                    <div class="mod-tags">
                        <span class="mod-tag">Engagements</span>
                        <span class="mod-tag">Bons de commande</span>
                        <span class="mod-tag">Mémoires</span>
                        <span class="mod-tag">Cadre logique</span>
                    </div>
                    <div class="mod-cta">
                        Accéder au module
                        <svg class="mod-cta-arrow" width="14" height="14" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                d="M17 8l4 4m0 0l-4 4m4-4H3" />
                        </svg>
                    </div>
                </a>
            @else
                <div class="mod-card-locked" title="Accès non autorisé — contactez votre administrateur">
                    <div class="mod-header">
                        <div class="mod-icon" style="opacity:.4;">💰</div>
                        <div>
                            <div class="mod-num" style="color:#94a3b8;">Module 02</div>
                            <div class="mod-title mod-title-locked">Gestion Budgétaire</div>
                        </div>
                    </div>
                    <div class="mod-desc" style="color:#94a3b8;">Accès restreint à ce module.</div>
                    <div class="mod-tags">
                        <span class="mod-tag"
                            style="background:rgba(148,163,184,.08);color:#94a3b8;border-color:rgba(148,163,184,.2);">Accès
                            restreint</span>
                    </div>
                    <div class="mod-locked-msg">
                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        Contacter l'administrateur
                    </div>
                </div>
            @endif

            {{-- MODULE 03 : Comptabilité Matières --}}
            @if($canComptable)
                <a href="/comptable" class="mod-card"
                    style="--c:linear-gradient(90deg,#059669,#34d399);--cc:#059669;--s:rgba(5,150,105,.22);--ib:rgba(5,150,105,.1);--is:rgba(5,150,105,.2);--tb:rgba(5,150,105,.08);--tc:#065f46;--tbo:rgba(5,150,105,.2)">
                    <div class="mod-header">
                        <div class="mod-icon">📦</div>
                        <div>
                            <div class="mod-num">Module 03</div>
                            <div class="mod-title">Comptabilité Matières</div>
                        </div>
                    </div>
                    <div class="mod-desc">Acquisition, affectation, suivi des mouvements, entretien et aliénation des biens.
                    </div>
                    <div class="mod-tags">
                        <span class="mod-tag">Acquisition</span>
                        <span class="mod-tag">Affectation</span>
                        <span class="mod-tag">Inventaire</span>
                        <span class="mod-tag">Aliénation</span>
                    </div>
                    <div class="mod-cta">
                        Accéder au module
                        <svg class="mod-cta-arrow" width="14" height="14" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                d="M17 8l4 4m0 0l-4 4m4-4H3" />
                        </svg>
                    </div>
                </a>
            @else
                <div class="mod-card-locked" title="Accès non autorisé — contactez votre administrateur">
                    <div class="mod-header">
                        <div class="mod-icon" style="opacity:.4;">📦</div>
                        <div>
                            <div class="mod-num" style="color:#94a3b8;">Module 03</div>
                            <div class="mod-title mod-title-locked">Comptabilité Matières</div>
                        </div>
                    </div>
                    <div class="mod-desc" style="color:#94a3b8;">Accès restreint à ce module.</div>
                    <div class="mod-tags">
                        <span class="mod-tag"
                            style="background:rgba(148,163,184,.08);color:#94a3b8;border-color:rgba(148,163,184,.2);">Accès
                            restreint</span>
                    </div>
                    <div class="mod-locked-msg">
                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        Contacter l'administrateur
                    </div>
                </div>
            @endif

            {{-- MODULE 04 : Marchés Publics --}}
            @if($canMarches)
                <a href="/marches" class="mod-card"
                    style="--c:linear-gradient(90deg,#7c3aed,#a78bfa);--cc:#7c3aed;--s:rgba(124,58,237,.22);--ib:rgba(124,58,237,.1);--is:rgba(124,58,237,.2);--tb:rgba(124,58,237,.08);--tc:#4c1d95;--tbo:rgba(124,58,237,.2)">
                    <div class="mod-header">
                        <div class="mod-icon">📋</div>
                        <div>
                            <div class="mod-num">Module 04</div>
                            <div class="mod-title">Marchés Publics</div>
                        </div>
                    </div>
                    <div class="mod-desc">Planification, appels d'offres, attribution, contrats, avenants et suivi
                        d'exécution.</div>
                    <div class="mod-tags">
                        <span class="mod-tag">Appels d'offres</span>
                        <span class="mod-tag">Attribution</span>
                        <span class="mod-tag">Contrats</span>
                        <span class="mod-tag">Exécution</span>
                    </div>
                    <div class="mod-cta">
                        Accéder au module
                        <svg class="mod-cta-arrow" width="14" height="14" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                d="M17 8l4 4m0 0l-4 4m4-4H3" />
                        </svg>
                    </div>
                </a>
            @else
                <div class="mod-card-locked" title="Accès non autorisé — contactez votre administrateur">
                    <div class="mod-header">
                        <div class="mod-icon" style="opacity:.4;">📋</div>
                        <div>
                            <div class="mod-num" style="color:#94a3b8;">Module 04</div>
                            <div class="mod-title mod-title-locked">Marchés Publics</div>
                        </div>
                    </div>
                    <div class="mod-desc" style="color:#94a3b8;">Accès restreint à ce module.</div>
                    <div class="mod-tags">
                        <span class="mod-tag"
                            style="background:rgba(148,163,184,.08);color:#94a3b8;border-color:rgba(148,163,184,.2);">Accès
                            restreint</span>
                    </div>
                    <div class="mod-locked-msg">
                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        Contacter l'administrateur
                    </div>
                </div>
            @endif

        </div>{{-- /.modules-grid --}}

        {{-- Footer --}}
        <div class="portal-footer">
            <div class="pf-stat">
                <div class="pf-dot"></div>
                {{ $modulesActifs }} module{{ $modulesActifs > 1 ? 's' : '' }}
                accessible{{ $modulesActifs > 1 ? 's' : '' }}
            </div>
            <div class="pf-stat">
                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                {{ auth()->user()?->name }}
            </div>
            <div class="pf-stat">Exercice {{ now()->year }}</div>
        </div>

    </div>{{-- /.portal-wrap --}}
</x-filament-panels::page>