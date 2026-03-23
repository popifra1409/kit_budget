<x-filament-panels::page>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap');

        .portal-wrap {
            font-family: 'Sora', sans-serif;
            height: calc(100vh - 140px);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            padding: .5rem 1rem;
            overflow: hidden;
        }

        .portal-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            font-family: 'JetBrains Mono', monospace;
            font-size: .6rem;
            font-weight: 500;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: #0ea5e9;
            background: rgba(14, 165, 233, .08);
            border: 1px solid rgba(14, 165, 233, .2);
            padding: .2rem .75rem;
            border-radius: 999px;
            margin-bottom: 0;
            /* supprimé — géré par gap du parent */
        }

        .portal-title {
            font-size: clamp(1.3rem, 2.5vw, 1.9rem);
            /* réduit */
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -.02em;
            color: #0f172a;
            margin: 0;
            /* supprimé */
            text-align: center;
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
            font-size: .78rem;
            /* réduit */
            color: #64748b;
            max-width: 420px;
            margin: 0;
            /* supprimé */
            line-height: 1.45;
            text-align: center;
        }

        .dark .portal-sub {
            color: #94a3b8;
        }

        .modules-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            /* fixe 3 colonnes */
            gap: .75rem;
            /* réduit */
            max-width: 860px;
            width: 100%;
            margin: 0;
            /* supprimé */
        }

        @media (max-width: 640px) {
            .modules-grid {
                grid-template-columns: 1fr;
            }
        }

        .mod-card {
            display: flex;
            flex-direction: column;
            background: rgba(255, 255, 255, .85);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(226, 232, 240, .8);
            border-radius: 1rem;
            /* réduit */
            padding: .875rem;
            /* réduit */
            text-decoration: none;
            transition: all .25s cubic-bezier(.34, 1.56, .64, 1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .dark .mod-card {
            background: rgba(30, 41, 59, .75);
            border-color: rgba(71, 85, 105, .5);
        }

        .mod-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--c);
            border-radius: 1rem 1rem 0 0;
            opacity: 0;
            transition: opacity .25s;
        }

        .mod-card:hover {
            transform: translateY(-3px) scale(1.005);
            box-shadow: 0 12px 32px -8px var(--s);
        }

        .mod-card:hover::before {
            opacity: 1;
        }

        /* Icône + numéro/titre sur la même ligne → économise de la hauteur */
        .mod-header {
            display: flex;
            align-items: center;
            gap: .6rem;
            margin-bottom: .5rem;
        }

        .mod-icon {
            width: 2.25rem;
            /* réduit */
            height: 2.25rem;
            border-radius: .6rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
            background: var(--ib);
            border: 1px solid var(--is);
        }

        .mod-num {
            font-family: 'JetBrains Mono', monospace;
            font-size: .58rem;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--cc);
            margin-bottom: .15rem;
        }

        .mod-title {
            font-size: .9rem;
            /* réduit */
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }

        .dark .mod-title {
            color: #f1f5f9;
        }

        .mod-desc {
            font-size: .75rem;
            /* réduit */
            color: #64748b;
            line-height: 1.45;
            flex-grow: 1;
            margin-bottom: .5rem;
            /* réduit */
        }

        .dark .mod-desc {
            color: #94a3b8;
        }

        .mod-tags {
            display: flex;
            flex-wrap: wrap;
            gap: .2rem;
            margin-bottom: .5rem;
            /* réduit */
        }

        .mod-tag {
            font-size: .6rem;
            font-weight: 500;
            padding: .1rem .45rem;
            border-radius: 999px;
            background: var(--tb);
            color: var(--tc);
            border: 1px solid var(--tbo);
        }

        .mod-cta {
            display: flex;
            align-items: center;
            gap: .35rem;
            font-size: .75rem;
            font-weight: 600;
            color: var(--cc);
        }

        .mod-card:hover .mod-cta-arrow {
            transform: translateX(3px);
        }

        .mod-cta-arrow {
            transition: transform .18s;
        }

        .portal-footer {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1.25rem;
            flex-wrap: wrap;
            padding-top: .5rem;
            /* réduit */
            border-top: 1px solid rgba(226, 232, 240, .6);
            max-width: 860px;
            width: 100%;
        }

        .dark .portal-footer {
            border-top-color: rgba(71, 85, 105, .4);
        }

        .pf-stat {
            display: flex;
            align-items: center;
            gap: .35rem;
            font-size: .7rem;
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
    </style>
    <div class="portal-wrap">
        <div style="text-align:center">
            <div class="portal-eyebrow">
                <svg width="8" height="8" viewBox="0 0 8 8" fill="currentColor">
                    <circle cx="4" cy="4" r="3" />
                </svg>
                Budget Suite &mdash; Portail applicatif
            </div>
            <h1 class="portal-title">Choisissez votre<br><span>module de gestion</span></h1>
            <p class="portal-sub">Chaque module dispose de sa propre interface. Votre session est partagée entre les
                modules.</p>
        </div>

        <div class="modules-grid">

            {{-- Budget --}}
            <a href="/budget" class="mod-card"
                style="--c:linear-gradient(90deg,#0ea5e9,#38bdf8);--cc:#0ea5e9;--s:rgba(14,165,233,.2);--ib:rgba(14,165,233,.1);--is:rgba(14,165,233,.2);--tb:rgba(14,165,233,.08);--tc:#0369a1;--tbo:rgba(14,165,233,.2)">
                <div class="mod-icon">💰</div>
                <div class="mod-num">Module 01</div>
                <div class="mod-title">Gestion Budgétaire</div>
                <div class="mod-desc">Bons de commande, engagements, mémoires de dépenses, lignes budgétaires et
                    tableaux de bord.</div>
                <div class="mod-tags">
                    <span class="mod-tag">Engagements</span>
                    <span class="mod-tag">Bons de commande</span>
                    <span class="mod-tag">Mémoires</span>
                    <span class="mod-tag">Cadre logique</span>
                </div>
                <div class="mod-cta">Accéder au module <svg class="mod-cta-arrow" width="16" height="16" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                            d="M17 8l4 4m0 0l-4 4m4-4H3" />
                    </svg></div>
            </a>

            {{-- Comptable --}}
            <a href="/comptable" class="mod-card"
                style="--c:linear-gradient(90deg,#059669,#34d399);--cc:#059669;--s:rgba(5,150,105,.2);--ib:rgba(5,150,105,.1);--is:rgba(5,150,105,.2);--tb:rgba(5,150,105,.08);--tc:#065f46;--tbo:rgba(5,150,105,.2)">
                <div class="mod-icon">📒</div>
                <div class="mod-num">Module 02</div>
                <div class="mod-title">Comptabilité Matières</div>
                <div class="mod-desc">Acquisition, journaux de saisie, grand livre, balance des comptes et états
                    financiers.</div>
                <div class="mod-tags">
                    <span class="mod-tag">Acquisition</span>
                    <span class="mod-tag">Affectation</span>
                    <span class="mod-tag">Inventaire</span>
                    <span class="mod-tag">Aliénation</span>
                </div>
                <div class="mod-cta">Accéder au module <svg class="mod-cta-arrow" width="16" height="16" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                            d="M17 8l4 4m0 0l-4 4m4-4H3" />
                    </svg></div>
            </a>

            {{-- Marchés --}}
            <a href="/marches" class="mod-card"
                style="--c:linear-gradient(90deg,#7c3aed,#a78bfa);--cc:#7c3aed;--s:rgba(124,58,237,.2);--ib:rgba(124,58,237,.1);--is:rgba(124,58,237,.2);--tb:rgba(124,58,237,.08);--tc:#4c1d95;--tbo:rgba(124,58,237,.2)">
                <div class="mod-icon">📋</div>
                <div class="mod-num">Module 03</div>
                <div class="mod-title">Marchés Publics</div>
                <div class="mod-desc">Planification, appels d'offres, attribution, contrats, avenants et suivi
                    d'exécution.</div>
                <div class="mod-tags">
                    <span class="mod-tag">Appels d'offres</span>
                    <span class="mod-tag">Attribution</span>
                    <span class="mod-tag">Contrats</span>
                    <span class="mod-tag">Exécution</span>
                </div>
                <div class="mod-cta">Accéder au module <svg class="mod-cta-arrow" width="16" height="16" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                            d="M17 8l4 4m0 0l-4 4m4-4H3" />
                    </svg></div>
            </a>

        </div>

        <div class="portal-footer">
            <div class="pf-stat">
                <div class="pf-dot"></div> 3 modules actifs
            </div>
            <div class="pf-stat">
                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                {{ auth()->user()?->name }}
            </div>
            <div class="pf-stat">Exercice {{ now()->year }}</div>
        </div>
    </div>
</x-filament-panels::page>