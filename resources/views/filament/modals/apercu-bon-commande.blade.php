@php
$modeArrondi = $bc->mode_arrondi ?? true;
$decimales   = $modeArrondi ? 0 : 2;
@endphp
<div class="p-1">
    <style>
        .apercu-wrap {
            font-family: inherit;
        }

        .apercu-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .8rem;
        }

        .apercu-table th {
            background: #f1f5f9;
            padding: 6px 10px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
            font-size: .74rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #475569;
        }

        .apercu-table td {
            padding: 6px 10px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .apercu-table tbody tr:hover td {
            background: #f8fafc;
        }

        .apercu-table td.num {
            text-align: right;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .apercu-table td.center {
            text-align: center;
        }

        .apercu-table tfoot td {
            font-weight: 700;
            border-top: 2px solid #cbd5e1;
            background: #f8fafc;
            font-size: .82rem;
        }

        .dark .apercu-table th {
            background: #1e293b;
            color: #94a3b8;
            border-color: #334155;
        }

        .dark .apercu-table td {
            border-color: #1e293b;
        }

        .dark .apercu-table tfoot td {
            background: #0f172a;
            border-color: #334155;
        }

        .dark .apercu-table tbody tr:hover td {
            background: #1e293b;
        }

        .badge-statut {
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            padding: .2rem .7rem;
            border-radius: 999px;
            font-size: .72rem;
            font-weight: 600;
        }

        .badge-brouillon {
            background: #fef9c3;
            color: #854d0e;
        }

        .badge-valide {
            background: #dcfce7;
            color: #166534;
        }

        .badge-engage {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-annule {
            background: #fee2e2;
            color: #991b1b;
        }

        .section-title {
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: #94a3b8;
            margin-bottom: .5rem;
            padding-bottom: .3rem;
            border-bottom: 1px solid #f1f5f9;
        }

        .dark .section-title {
            border-color: #1e293b;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: .6rem 1.5rem;
            margin-bottom: 1rem;
        }

        @media(max-width:600px) {
            .info-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .info-item label {
            display: block;
            font-size: .68rem;
            color: #94a3b8;
            margin-bottom: .1rem;
            font-weight: 500;
        }

        .info-item span {
            font-size: .82rem;
            font-weight: 500;
            color: #0f172a;
        }

        .dark .info-item span {
            color: #f1f5f9;
        }

        .total-bloc {
            min-width: 300px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: .35rem .75rem;
            border-radius: .4rem;
            font-size: .82rem;
            margin-bottom: .25rem;
        }

        .total-row.ht {
            background: #f1f5f9;
            color: #475569;
        }

        .total-row.tva {
            background: #fefce8;
            color: #854d0e;
        }

        .total-row.ttc {
            background: #eff6ff;
            color: #1e40af;
            font-weight: 600;
        }

        .total-row.ir {
            background: #fff1f2;
            color: #9f1239;
        }

        .total-row.tsr {
            background: #fff7ed;
            color: #9a3412;
        }

        .total-row.net {
            background: #f0fdf4;
            color: #166534;
            font-weight: 700;
            font-size: .9rem;
        }

        .dark .total-row.ht {
            background: #1e293b;
            color: #94a3b8;
        }

        .dark .total-row.tva {
            background: #422006;
            color: #fcd34d;
        }

        .dark .total-row.ttc {
            background: #1e3a5f;
            color: #93c5fd;
        }

        .dark .total-row.ir {
            background: #4c0519;
            color: #fca5a5;
        }

        .dark .total-row.net {
            background: #052e16;
            color: #86efac;
        }

        .montant {
            font-variant-numeric: tabular-nums;
        }

        .no-lignes {
            text-align: center;
            padding: 2rem 1rem;
            color: #94a3b8;
            font-size: .82rem;
            border: 1px dashed #e2e8f0;
            border-radius: .5rem;
        }

        .dark .no-lignes {
            border-color: #334155;
        }

        .ligne-badge {
            display: inline-flex;
            padding: .1rem .4rem;
            border-radius: 4px;
            font-size: .68rem;
            font-weight: 600;
        }

        .badge-ref {
            background: #dcfce7;
            color: #166534;
        }

        .badge-perso {
            background: #dbeafe;
            color: #1e40af;
        }

        .dark .badge-ref {
            background: #052e16;
            color: #86efac;
        }

        .dark .badge-perso {
            background: #1e3a5f;
            color: #93c5fd;
        }
    </style>

    <div class="apercu-wrap">

        {{-- ── En-tête ─────────────────────────────────────────────── --}}
        <div style="display:flex; justify-content:space-between; align-items:flex-start;
            margin-bottom:1rem; padding-bottom:.75rem; border-bottom:1px solid #e2e8f0;">
            <div>
                <h2 style="font-size:1.05rem; font-weight:700; color:#0f172a; margin:0 0 .2rem;">
                    Bon de Commande — {{ $bc->numero }}
                </h2>
                <p style="font-size:.8rem; color:#64748b; margin:0;">
                    {{ $bc->objet ?? 'Sans objet' }}
                </p>
            </div>
            <div style="display:flex; align-items:center; gap:.75rem; flex-shrink:0;">
                <span class="badge-statut badge-{{ $bc->statut }}">
                    {{ match ($bc->statut) {
    'brouillon' => '✏️ Brouillon',
    'valide' => '✅ Validé',
    'engage' => '💰 Engagé',
    'annule' => '❌ Annulé',
    default => ucfirst($bc->statut),
} }}
                </span>
                <span style="font-size:.75rem; color:#94a3b8;">
                    {{ $bc->date_bc
    ? \Carbon\Carbon::parse($bc->date_bc)->format('d/m/Y')
    : now()->format('d/m/Y') }}
                </span>
            </div>
        </div>

        {{-- ── Informations générales ─────────────────────────────── --}}
        <div class="section-title">Informations générales</div>
        <div class="info-grid" style="margin-bottom:1.25rem;">
            <div class="info-item">
                <label>Fournisseur</label>
                <span>{{ $bc->fournisseur?->raison_sociale ?? '—' }}</span>
            </div>
            <div class="info-item">
                <label>Exercice</label>
                <span>{{ $bc->exercice?->annee ?? now()->year }}</span>
            </div>
            <div class="info-item">
                <label>Type d'engagement</label>
                <span>{{ $bc->typeEngagement?->libelle ?? '—' }}</span>
            </div>
            <div class="info-item">
                <label>Budget</label>
                <span>{{ $bc->budget?->libelle ?? '—' }}</span>
            </div>
            <div class="info-item">
                <label>TVA</label>
                <span>
                    @if($bc->exonere_tva)
                        <span style="color:#854d0e;">⚠️ Exonéré</span>
                    @elseif($bc->tva_commune)
                        {{ $bc->tva_commune }}%
                    @else
                        Variable par ligne
                    @endif
                </span>
            </div>
            <div class="info-item">
                <label>IR</label>
                <span>
                    @if($bc->exonere_ir)
                        <span style="color:#854d0e;">⚠️ Exonéré</span>
                    @elseif($bc->ir_commun)
                        {{ $bc->ir_commun }}%
                    @else
                        Variable par ligne
                    @endif
                </span>
            </div>
        </div>

        {{-- ── Lignes ──────────────────────────────────────────────── --}}
        <div class="section-title">
            Lignes du bon de commande
            <span style="background:#e2e8f0; color:#475569; padding:.1rem .5rem;
                 border-radius:999px; font-size:.65rem; margin-left:.4rem; font-weight:600;">
                {{ $bc->lignes->count() }} ligne{{ $bc->lignes->count() > 1 ? 's' : '' }}
            </span>
        </div>

        @if($bc->lignes->isEmpty())
            <div class="no-lignes">
                ⚠️ Aucune ligne enregistrée — sauvegardez d'abord le BC pour ajouter des lignes
            </div>
        @else
            <div style="overflow-x:auto; margin-bottom:1.25rem;">
                <table class="apercu-table">
                    <thead>
                        <tr>
                            <th style="width:36px;">#</th>
                            <th>Désignation</th>
                            <th>Réf.</th>
                            <th style="text-align:center;">Qté</th>
                            <th style="text-align:center;">Unité</th>
                            <th style="text-align:right;">P.U HT</th>
                            <th style="text-align:right;">Montant HT</th>
                            <th style="text-align:right;">TVA ({{ $bc->exonere_tva ? '0%' : '' }})</th>
                            <th style="text-align:right;">TTC</th>
                            <th style="text-align:right;">IR</th>
                            <th style="text-align:right; color:#166534;">Net à payer</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($bc->lignes->sortBy('numero_ligne') as $ligne)
                            <tr>
                                <td class="center" style="color:#94a3b8; font-size:.72rem;">
                                    {{ $ligne->numero_ligne }}
                                </td>
                                <td>
                                    <div style="font-weight:500; font-size:.82rem;">{{ $ligne->designation }}</div>
                                    @if($ligne->observations)
                                        <div style="font-size:.7rem; color:#94a3b8; margin-top:.1rem; font-style:italic;">
                                            {{ $ligne->observations }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if($ligne->referenceMercuriale)
                                        <span class="ligne-badge badge-ref">
                                            {{ $ligne->referenceMercuriale->code_reference }}
                                        </span>
                                    @elseif($ligne->reference_personnalisee)
                                        <span class="ligne-badge badge-perso">
                                            {{ $ligne->reference_personnalisee }}
                                        </span>
                                    @else
                                        <span style="color:#cbd5e1; font-size:.72rem;">—</span>
                                    @endif
                                </td>
                                <td class="center">{{ number_format($ligne->quantite, $decimales, ',', ' ') }}</td>
                                <td class="center" style="color:#64748b; font-size:.75rem;">{{ $ligne->unite }}</td>
                                <td class="num" style="color:#64748b;">
                                    {{ number_format($ligne->prix_unitaire_ht, $decimales, ',', ' ') }}
                                </td>
                                <td class="num">{{ number_format($ligne->montant_ht, $decimales, ',', ' ') }}</td>
                                <td class="num" style="color:#854d0e;">
                                    {{ number_format($ligne->montant_tva, $decimales, ',', ' ') }}
                                    @if($ligne->taux_tva > 0)
                                        <span style="font-size:.65rem; color:#94a3b8;">({{ $ligne->taux_tva }}%)</span>
                                    @endif
                                </td>
                                <td class="num" style="color:#1e40af; font-weight:600;">
                                    {{ number_format($ligne->montant_ttc, $decimales, ',', ' ') }}
                                </td>
                                <td class="num" style="color:#9f1239;">
                                    {{ number_format($ligne->montant_ir, $decimales, ',', ' ') }}
                                    @if($ligne->taux_ir > 0)
                                        <span style="font-size:.65rem; color:#94a3b8;">({{ $ligne->taux_ir }}%)</span>
                                    @endif
                                </td>
                                <td class="num" style="color:#166534; font-weight:700;">
                                    {{ number_format($ligne->net_a_payer, $decimales, ',', ' ') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            @php
                                $tfHt = 0;
                                $tfTva = 0;
                                $tfTtc = 0;
                                $tfIr = 0;
                                $tfNet = 0;
                                foreach ($bc->lignes->sortBy('numero_ligne') as $l) {
                                    $tfHt += round((float) ($l->montant_ht ?? 0), $decimales);
                                    $tfTva += round((float) ($l->montant_tva ?? 0), $decimales);
                                    $tfTtc += round((float) ($l->montant_ttc ?? 0), $decimales);
                                    $tfIr += round((float) ($l->montant_ir ?? 0), $decimales);
                                    $tfNet += round((float) ($l->net_a_payer ?? 0), $decimales);
                                }
                            @endphp
                            <td colspan="6" style="text-align:right; font-size:.72rem; color:#94a3b8; padding-right:1rem;">
                                TOTAUX ({{ $bc->lignes->count() }} ligne{{ $bc->lignes->count() > 1 ? 's' : '' }})
                            </td>
                            <td class="num">{{ number_format($tfHt, $decimales, ',', ' ') }}</td>
                            <td class="num" style="color:#854d0e;">{{ number_format($tfTva, $decimales, ',', ' ') }}</td>
                            <td class="num" style="color:#1e40af;">{{ number_format($tfTtc, $decimales, ',', ' ') }}</td>
                            <td class="num" style="color:#9f1239;">{{ number_format($tfIr, $decimales, ',', ' ') }}</td>
                            <td class="num" style="color:#166534;">{{ number_format($tfNet, $decimales, ',', ' ') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif

        {{-- ── Récapitulatif ───────────────────────────────────────── --}}
        @php
            // ✅ Totaux lus depuis les colonnes DB du BC (source de vérité)
            // calculerMontants() calcule depuis HT total → cohérence garantie
            // Ne pas resommer les lignes individuelles (accumule les erreurs d'arrondi)
            $totalHT  = (float)($bc->montant_ht      ?? 0);
            $totalTVA = (float)($bc->montant_tva     ?? 0);
            $totalTTC = (float)($bc->montant_ttc     ?? 0);
            $totalIR  = (float)($bc->montant_ir      ?? 0);
            $totalTSR = (float)($bc->montant_tsr     ?? 0);
            $totalNet = (float)($bc->net_a_percevoir ?? $bc->net_a_payer ?? ($totalHT - $totalIR - $totalTSR));
        @endphp

        <div style="display:flex; justify-content:flex-end; margin-top:.5rem;">
            <div class="total-bloc">
                <div class="section-title">Récapitulatif financier</div>

                <div class="total-row ht">
                    <span>Montant HT</span>
                    <span class="montant">{{ number_format($totalHT, $decimales, ',', ' ') }} FCFA</span>
                </div>

                <div class="total-row tva">
                    <span>TVA{{ $bc->exonere_tva ? ' (exonéré)' : '' }}</span>
                    <span class="montant">+ {{ number_format($totalTVA, $decimales, ',', ' ') }} FCFA</span>
                </div>

                <div class="total-row ttc">
                    <span>Montant TTC</span>
                    <span class="montant">{{ number_format($totalTTC, $decimales, ',', ' ') }} FCFA</span>
                </div>

                <div class="total-row ir">
                    <span>Retenue IR{{ $bc->exonere_ir ? ' (exonéré)' : '' }}</span>
                    <span class="montant">− {{ number_format($totalIR, $decimales, ',', ' ') }} FCFA</span>
                </div>

                @if($totalTSR > 0)
                    <div class="total-row tsr">
                        <span>Retenue TSR</span>
                        <span class="montant">− {{ number_format($totalTSR, $decimales, ',', ' ') }} FCFA</span>
                    </div>
                @endif

                <div class="total-row net">
                    <span>💰 Net à payer</span>
                    <span class="montant">{{ number_format($totalNet, $decimales, ',', ' ') }} FCFA</span>
                </div>
            </div>
        </div>

        {{-- ── Note d'actualisation ────────────────────────────────── --}}
        <div style="margin-top:1rem; padding:.5rem .75rem; background:#f8fafc;
            border-radius:.5rem; border:1px solid #e2e8f0; font-size:.72rem; color:#94a3b8;
            display:flex; align-items:center; gap:.4rem;">
            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Les données affichées correspondent aux lignes <strong>enregistrées</strong>.
            Sauvegardez vos modifications avant de rouvrir l'aperçu.
        </div>

    </div>{{-- /.apercu-wrap --}}
</div>