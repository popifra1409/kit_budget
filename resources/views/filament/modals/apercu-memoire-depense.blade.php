{{-- resources/views/filament/modals/apercu-memoire-depense.blade.php --}}
<div class="p-1">
    <style>
        .md-wrap {
            font-family: inherit;
        }

        .md-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .78rem;
            white-space: nowrap;
        }

        .md-table th {
            background: var(--color-background-secondary);
            padding: 6px 8px;
            text-align: left;
            font-weight: 700;
            font-size: .68rem;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--color-text-secondary);
            border-bottom: 2px solid var(--color-border-secondary);
        }

        .md-table td {
            padding: 6px 8px;
            border-bottom: 1px solid var(--color-border-tertiary);
            vertical-align: middle;
        }

        .md-table tbody tr:hover td {
            background: var(--color-background-tertiary);
        }

        .md-table td.num {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .md-table tfoot td {
            font-weight: 700;
            background: var(--color-background-secondary);
            border-top: 2px solid var(--color-border-secondary);
        }

        .sec-title {
            font-size: .67rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: var(--color-text-secondary);
            margin-bottom: .5rem;
            padding-bottom: .3rem;
            border-bottom: 1px solid var(--color-border-tertiary);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: .5rem 1.5rem;
            margin-bottom: 1rem;
        }

        .info-item label {
            display: block;
            font-size: .67rem;
            color: var(--color-text-secondary);
            margin-bottom: .1rem;
        }

        .info-item span {
            font-size: .82rem;
            font-weight: 500;
            color: var(--color-text-primary);
        }

        .totaux-bloc {
            display: flex;
            justify-content: flex-end;
            margin-top: .75rem;
        }

        .totaux-inner {
            min-width: 280px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: .3rem .7rem;
            border-radius: .4rem;
            font-size: .8rem;
            margin-bottom: .2rem;
        }

        .t-ht {
            background: var(--color-background-tertiary);
        }

        .t-tva {
            background: #fefce8;
            color: #854d0e;
        }

        .t-ttc {
            background: #eff6ff;
            color: #1e40af;
            font-weight: 600;
        }

        .t-ir {
            background: #fff1f2;
            color: #9f1239;
        }

        .t-nap {
            background: #f0fdf4;
            color: #166534;
            font-weight: 700;
            font-size: .88rem;
        }

        .dark .t-ht {
            background: #1e293b;
        }

        .dark .t-tva {
            background: #422006;
            color: #fcd34d;
        }

        .dark .t-ttc {
            background: #1e3a5f;
            color: #93c5fd;
        }

        .dark .t-ir {
            background: #4c0519;
            color: #fca5a5;
        }

        .dark .t-nap {
            background: #052e16;
            color: #86efac;
        }

        .num-val {
            font-variant-numeric: tabular-nums;
        }

        .badge-statut {
            display: inline-flex;
            padding: .2rem .7rem;
            border-radius: 999px;
            font-size: .7rem;
            font-weight: 600;
        }

        .bs-brouillon {
            background: #f1f5f9;
            color: #475569;
        }

        .bs-valide {
            background: #dcfce7;
            color: #166534;
        }

        .bs-transmis {
            background: #dbeafe;
            color: #1e40af;
        }

        .mode-badge {
            display: inline-block;
            font-size: .6rem;
            padding: .1rem .35rem;
            border-radius: 999px;
            font-weight: 600;
            vertical-align: middle;
        }

        .mode-nap {
            background: #f0fdf4;
            color: #166534;
        }

        .mode-pu {
            background: #eff6ff;
            color: #1e40af;
        }
    </style>

    @php
        $lignes = $memoire->lignes->sortBy('numero_ligne');
        $premiere = $lignes->first();
        $tauxTva = (float) ($premiere?->taux_tva ?? 19.25);
        $tauxIr = (float) ($premiere?->taux_ir ?? 5.5);
        $modeSaisie = $memoire->mode_saisie ?? 'montant_nap';

        /**
         * ✅ CORRIGÉ — NAP/unité et NAP total
         *
         * Priorité des sources pour NAP d'une ligne :
         *   1. montant_net       (stocké par preparerDonneesLigne — nouveaux enregistrements)
         *   2. net_a_payer       (alias possible selon le modèle)
         *   3. montant_ht - montant_ir  (calculé depuis MHT et IR si NAP non stocké)
         *
         * Cela couvre les anciens enregistrements où montant_net était 0 ou null.
         */
        $totalHt  = 0;
        $totalTva = 0;
        $totalIr  = 0;
        $totalNap = 0;

        foreach ($lignes as $l) {
            $mht = (float) ($l->montant_ht ?? 0);
            $tva = (float) ($l->montant_tva ?? 0);
            $ir = (float) ($l->montant_ir ?? 0);
            $ttc = (float) ($l->montant_ttc ?? 0);

            // ✅ NAP = montant_net si dispo et > 0, sinon calculé depuis MHT - IR
            $nap = (float) ($l->montant_net ?? $l->net_a_payer ?? 0);
            if ($nap <= 0 && $mht > 0) {
                $nap = $mht - $ir;  // fallback : MHT - IR
            }

            // ✅ Accumuler les valeurs DÉJÀ arrondies
            //    pour que Σ colonnes = total affiché (cohérence visuelle)
            $totalHt  += (int) round($mht);
            $totalTva += (int) round($tva);
            $totalIr  += (int) round($ir);
            // $totalTtc calculé après le foreach : HT + TVA
            $totalNap += (int) round($nap);
        }

        // Arrondi final (une seule fois)
        // ✅ Déjà arrondis dans le foreach — TTC = HT + TVA
        //    Σ colonnes = total (cohérence visuelle garantie)
        $totalTtc = $totalHt + $totalTva;
        $totalNap = (int) round($totalNap, 0);
    @endphp

    <div class="md-wrap">

        {{-- En-tête --}}
        <div style="display:flex;justify-content:space-between;align-items:flex-start;
                    margin-bottom:1rem;padding-bottom:.75rem;
                    border-bottom:1px solid var(--color-border-tertiary);">
            <div>
                <h2 style="font-size:1.05rem;font-weight:700;margin:0 0 .2rem;">
                    Mémoire de Dépense — {{ $memoire->numero }}
                </h2>
                <p style="font-size:.8rem;color:var(--color-text-secondary);margin:0;">
                    {{ $memoire->objet }}
                </p>
            </div>
            <div style="display:flex;align-items:center;gap:.75rem;flex-shrink:0;">
                <span class="badge-statut bs-{{ $memoire->statut }}">
                    {{ match ($memoire->statut) {
    'brouillon' => '✏️ Brouillon',
    'valide' => '✅ Validé',
    'transmis' => '📤 Transmis',
    'approuve' => '🏆 Approuvé',
    'annule' => '❌ Annulé',
    default => ucfirst($memoire->statut),
} }}
                </span>
                <span style="font-size:.75rem;color:var(--color-text-secondary);">
                    {{ $memoire->date_memoire?->format('d/m/Y') }}
                </span>
            </div>
        </div>

        {{-- Informations générales --}}
        <div class="sec-title">Informations générales</div>
        <div class="info-grid">
            <div class="info-item"><label>Exercice</label><span>{{ $memoire->exercice }}</span></div>
            <div class="info-item"><label>N° Décision</label><span>{{ $memoire->numero_decision ?? '—' }}</span></div>
            <div class="info-item"><label>N° CE</label><span>{{ $memoire->numero_ce ?? '—' }}</span></div>
            <div class="info-item"><label>Signataire</label><span>{{ $memoire->signataire_nom ?? '—' }}</span></div>
            <div class="info-item"><label>Fonction</label><span>{{ $memoire->signataire_fonction ?? '—' }}</span></div>
            <div class="info-item"><label>Lieu</label><span>{{ $memoire->lieu_signature ?? '—' }}</span></div>
        </div>

        {{-- Lignes de dépenses --}}
        <div class="sec-title" style="margin-top:.75rem;">
            Lignes de dépenses
            <span style="background:var(--color-background-tertiary);color:var(--color-text-secondary);
                         padding:.1rem .45rem;border-radius:999px;font-size:.63rem;margin-left:.4rem;">
                {{ $lignes->count() }} ligne{{ $lignes->count() > 1 ? 's' : '' }}
            </span>
            <span class="mode-badge {{ $modeSaisie === 'montant_nap' ? 'mode-nap' : 'mode-pu' }}"
                style="margin-left:.5rem;">
                {{ $modeSaisie === 'montant_nap' ? 'Mode NAP' : 'Mode P.U HT' }}
            </span>
        </div>

        @if($lignes->isEmpty())
            <div style="text-align:center;padding:2rem;color:var(--color-text-tertiary);font-size:.82rem;
                            border:1px dashed var(--color-border-tertiary);border-radius:.5rem;">
                ⚠️ Aucune ligne enregistrée — sauvegardez le mémoire d'abord
            </div>
        @else
            <div style="overflow-x:auto;">
                <table class="md-table">
                    <thead>
                        <tr>
                            <th style="width:36px;">#</th>
                            <th>Nature de la dépense</th>
                            <th style="text-align:center;">Qté</th>
                            <th style="text-align:right;">
                                NAP/unité
                                <span style="font-size:.58rem;opacity:.7;display:block;font-weight:400;">
                                    Net à Percevoir / unité
                                </span>
                            </th>
                            <th style="text-align:right;color:#166634;">NAP total</th>
                            <th style="text-align:right;">MHT</th>
                            <th style="text-align:right;">TVA ({{ $tauxTva }}%)</th>
                            <th style="text-align:right;">IR ({{ $tauxIr }}%)</th>
                            <th style="text-align:right;">TTC</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lignes as $ligne)
                            @php
                                $qte = max(1, (float) $ligne->quantite);
                                $mht = (float) ($ligne->montant_ht ?? 0);
                                $tva = (float) ($ligne->montant_tva ?? 0);
                                $ir = (float) ($ligne->montant_ir ?? 0);
                                $ttc = (float) ($ligne->montant_ttc ?? 0);

                                // ✅ NAP — avec fallback si montant_net absent/nul
                                $napTotal = (float) ($ligne->montant_net ?? $ligne->net_a_payer ?? 0);
                                if ($napTotal <= 0 && $mht > 0) {
                                    $napTotal = $mht - $ir;  // MHT - IR
                                }

                                // NAP/unité = NAP total ÷ quantité
                                $napUnitaire = $qte > 0 ? $napTotal / $qte : 0;

                                // Mode détecté via le champ mode_saisie du mémoire
                                $estModeNap = $modeSaisie === 'montant_nap';
                            @endphp
                            <tr>
                                <td style="text-align:center;color:var(--color-text-tertiary);font-size:.72rem;">
                                    {{ $ligne->numero_ligne }}
                                </td>
                                <td style="font-weight:500;font-size:.82rem;">
                                    {{ $ligne->nature_depense }}
                                    <span class="mode-badge {{ $estModeNap ? 'mode-nap' : 'mode-pu' }}">
                                        {{ $estModeNap ? 'NAP' : 'P.U' }}
                                    </span>
                                </td>
                                <td style="text-align:center;">{{ number_format($qte, 0, ',', ' ') }}</td>

                                {{-- ✅ NAP/unité — jamais 0 si MHT est renseigné --}}
                                <td class="num" style="color:#166534;font-weight:600;">
                                    {{ number_format($napUnitaire, 0, ',', ' ') }}
                                </td>
                                {{-- ✅ NAP total --}}
                                <td class="num" style="color:#166534;font-weight:700;">
                                    {{ number_format($napTotal, 0, ',', ' ') }}
                                </td>
                                <td class="num">{{ number_format($mht, 0, ',', ' ') }}</td>
                                <td class="num" style="color:#854d0e;">{{ number_format($tva, 0, ',', ' ') }}</td>
                                <td class="num" style="color:#9f1239;">{{ number_format($ir, 0, ',', ' ') }}</td>
                                <td class="num" style="color:#1e40af;font-weight:600;">{{ number_format($ttc, 0, ',', ' ') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" style="font-size:.7rem;color:var(--color-text-secondary);">
                                TOTAUX ({{ $lignes->count() }} ligne{{ $lignes->count() > 1 ? 's' : '' }})
                            </td>
                            <td class="num" style="color:var(--color-text-secondary);font-size:.7rem;font-style:italic;">—
                            </td>
                            <td class="num" style="color:#166534;">{{ number_format($totalNap, 0, ',', ' ') }}</td>
                            <td class="num">{{ number_format($totalHt, 0, ',', ' ') }}</td>
                            <td class="num" style="color:#854d0e;">{{ number_format($totalTva, 0, ',', ' ') }}</td>
                            <td class="num" style="color:#9f1239;">{{ number_format($totalIr, 0, ',', ' ') }}</td>
                            <td class="num" style="color:#1e40af;">{{ number_format($totalTtc, 0, ',', ' ') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif

        {{-- Récapitulatif financier --}}
        <div class="totaux-bloc">
            <div class="totaux-inner">
                <div class="sec-title">Récapitulatif financier</div>
                <div class="total-row t-ht">
                    <span>Montant HT (MHT)</span>
                    <span class="num-val">{{ number_format($totalHt, 0, ',', ' ') }} FCFA</span>
                </div>
                <div class="total-row t-tva">
                    <span>TVA ({{ $tauxTva }}%)</span>
                    <span class="num-val">+ {{ number_format($totalTva, 0, ',', ' ') }} FCFA</span>
                </div>
                <div class="total-row t-ttc">
                    <span>Montant TTC</span>
                    <span class="num-val">{{ number_format($totalTtc, 0, ',', ' ') }} FCFA</span>
                </div>
                <div class="total-row t-ir">
                    <span>Retenue IR ({{ $tauxIr }}%)</span>
                    <span class="num-val">− {{ number_format($totalIr, 0, ',', ' ') }} FCFA</span>
                </div>
                <div class="total-row t-nap">
                    <span>💰 Net à Payer (NAP)</span>
                    <span class="num-val">{{ number_format($totalNap, 0, ',', ' ') }} FCFA</span>
                </div>

                @if($memoire->montant_lettres)
                    <div style="margin-top:.5rem;padding:.4rem .7rem;
                                    background:var(--color-background-tertiary);border-radius:.4rem;
                                    font-size:.72rem;color:var(--color-text-secondary);font-style:italic;">
                        {{ $memoire->montant_lettres }}
                    </div>
                @endif

                {{-- Indicateur de formule --}}
                <div style="margin-top:.4rem;padding:.3rem .6rem;
                            background:var(--color-background-tertiary);border-radius:.3rem;
                            font-size:.65rem;color:var(--color-text-tertiary);">
                    Formule : MHT = NAP ÷ (1 − {{ $tauxIr }}%) =
                    NAP ÷ {{ number_format(1 - $tauxIr / 100, 3, ',', '') }}
                </div>
            </div>
        </div>

        {{-- Note --}}
        <div style="margin-top:.75rem;padding:.4rem .7rem;
                    background:var(--color-background-tertiary);border-radius:.4rem;
                    font-size:.7rem;color:var(--color-text-tertiary);
                    display:flex;align-items:center;gap:.4rem;">
            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Valeurs lues depuis la base — sauvegardez avant de rouvrir.
            NAP = montant_net stocké, ou MHT−IR si non renseigné (anciens enregistrements).
        </div>
    </div>
</div>