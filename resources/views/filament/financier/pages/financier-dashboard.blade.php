<x-filament-panels::page>
    @php $socle = $this->etatSocle(); @endphp

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:1rem;">
        <div style="border:1px solid rgba(148,163,184,.3); border-radius:.75rem; padding:1rem;">
            <div style="font-weight:700; margin-bottom:.35rem;">⚙️ Automatisation de la saisie</div>
            <div style="font-size:.85rem; opacity:.8;">
                Une pièce du budget devient une écriture, sans ressaisie : le document d'origine
                reste la source, l'écriture ne fait que le référencer.
            </div>
        </div>
        <div style="border:1px solid rgba(148,163,184,.3); border-radius:.75rem; padding:1rem;">
            <div style="font-weight:700; margin-bottom:.35rem;">🧾 Facturation et paiements</div>
            <div style="font-size:.85rem; opacity:.8;">
                Suivi de la liasse indispensable au paiement définitif : bon de commande, facture
                du fournisseur, service fait, ordonnance, mise en paiement.
            </div>
        </div>
        <div style="border:1px solid rgba(148,163,184,.3); border-radius:.75rem; padding:1rem;">
            <div style="font-weight:700; margin-bottom:.35rem;">📊 États financiers</div>
            <div style="font-size:.85rem; opacity:.8;">
                Journaux, balance des comptes et compte administratif produits à partir des
                écritures, pour la consolidation nationale.
            </div>
        </div>
    </div>

    <div style="border:1px solid rgba(148,163,184,.3); border-radius:.75rem; padding:1rem;">
        <div style="font-weight:700; margin-bottom:.75rem;">
            Socle comptable
            @if($socle['pret'])
                <span style="color:#059669; font-size:.8rem;">— en place</span>
            @else
                <span style="color:#f59e0b; font-size:.8rem;">— tables non créées</span>
            @endif
        </div>

        @if(!$socle['pret'])
            <div style="font-size:.85rem; opacity:.85;">
                Les migrations du module n'ont pas encore été jouées. Exécute
                <code>php artisan migrate</code> puis
                <code>php artisan db:seed --class=RolePermissionSeeder</code> pour créer la permission
                <code>access_module_financier</code>.
            </div>
        @else
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:.75rem;">
                <div>
                    <div style="font-size:1.4rem; font-weight:700;">{{ $socle['comptes'] }}</div>
                    <div style="font-size:.8rem; opacity:.75;">comptes du plan</div>
                </div>
                <div>
                    <div style="font-size:1.4rem; font-weight:700;">{{ $socle['tiers'] }}</div>
                    <div style="font-size:.8rem; opacity:.75;">comptes de tiers imputables (classe 4)</div>
                </div>
                <div>
                    <div style="font-size:1.4rem; font-weight:700;">{{ $socle['journaux'] }}</div>
                    <div style="font-size:.8rem; opacity:.75;">journaux</div>
                </div>
                <div>
                    <div style="font-size:1.4rem; font-weight:700;">{{ $socle['ecritures'] }}</div>
                    <div style="font-size:.8rem; opacity:.75;">écritures comptabilisées</div>
                </div>
            </div>
        @endif
    </div>

    <div style="border:1px solid rgba(148,163,184,.3); border-radius:.75rem; padding:1rem;">
        <div style="font-weight:700; margin-bottom:.5rem;">Sources déjà présentes dans SIGB, liées et non réécrites</div>
        <ul style="font-size:.85rem; opacity:.85; padding-left:1.1rem; margin:0; line-height:1.7;">
            <li><strong>nomenclature_budgetaire</strong> : la nomenclature budgétaire existante (chapitres,
                articles, types dépense et recette), rattachée aux comptes comptables par
                <code>comptes_comptables.nomenclature_budgetaire_id</code>.</li>
            <li><strong>engagements, liquidations, mémoires de dépense, ordonnances de paiement</strong> :
                la chaîne du paiement définitif, référencée par les écritures.</li>
            <li><strong>recettes_reelles</strong> : encaissements, déjà porteurs d'une
                <code>date_comptabilisation</code>.</li>
            <li><strong>depenses_regies, bons_commande_regies</strong> : dépenses et factures des régies.</li>
            <li><strong>fournisseurs, personnels, tiers_recettes, dossiers_fournisseurs</strong> : les tiers,
                raccordés aux comptes 4xx par <code>rattachements_tiers</code>.</li>
            <li><strong>compte administratif et clôture d'exercice</strong> : états budgétaires existants,
                appelés à être alimentés par la balance plutôt que remplacés.</li>
        </ul>
    </div>

    <div style="border:1px dashed rgba(148,163,184,.45); border-radius:.75rem; padding:1rem;">
        <div style="font-weight:700; margin-bottom:.5rem;">À ouvrir dans les prochaines conversations</div>
        <ul style="font-size:.85rem; opacity:.85; padding-left:1.1rem; margin:0; line-height:1.7;">
            <li>Écrans du plan comptable, des journaux et des écritures.</li>
            <li>Génération automatique des écritures depuis les ordonnances et les encaissements.</li>
            <li>Balance des comptes, grand livre, lettrage et âges de créances et dettes.</li>
            <li>Détail des classes 1 à 7, puis rapprochement avec le compte administratif.</li>
        </ul>
    </div>
</x-filament-panels::page>
