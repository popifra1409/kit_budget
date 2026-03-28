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
    </style>

    <div class="md-wrap">

        {{-- En-tête --}}
        <div style="display:flex; justify-content:space-between; align-items:flex-start;
            margin-bottom:1rem; padding-bottom:.75rem; border-bottom:1px solid var(--color-border-tertiary);">
            <div>
                <h2 style="font-size:1.05rem; font-weight:700; margin:0 0 .2rem;">
                    Mémoire de Dépense — {{ $memoire->numero }}
                </h2>
                <p style="font-size:.8rem; color:var(--color-text-secondary); margin:0;">
                    {{ $memoire->objet }}
                </p>
            </div>
            <div style="display:flex; align-items:center; gap:.75rem; flex-shrink:0;">
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
                <span style="font-size:.75rem; color:var(--color-text-secondary);">
                    {{ $memoire->date_memoire?->format('d/m/Y') }}
                </span>
            </div>
        </div>

        {{-- Informations --}}
        <div class="sec-title">Informations générales</div>
        <div class="info-grid">
            <div class="info-item">
                <label>Exercice</label>
                <span>{{ $memoire->exercice }}</span>
            </div>
            <div class="info-item">
                <label>N° Décision</label>
                <span>{{ $memoire->numero_decision ?? '—' }}</span>
            </div>
            <div class="info-item">
                <label>N° CE</label>
                <span>{{ $memoire->numero_ce ?? '—' }}</span>
            </div>
            <div class="info-item">
                <label>Signataire</label>
                <span>{{ $memoire->signataire_nom ?? '—' }}</span>
            </div>
            <div class="info-item">
                <label>Fonction</label>
                <span>{{ $memoire->signataire_fonction ?? '—' }}</span>
            </div>
            <div class="info-item">
                <label>Lieu</label>
                <span>{{ $memoire->lieu_signature ?? '—' }}</span>
            </div>
        </div>

        {{-- Lignes --}}
        <div class="sec-title" style="margin-top:.75rem;">
            Lignes de dépenses
            <span style="background:var(--color-background-tertiary); color:var(--color-text-secondary);
                 padding:.1rem .45rem; border-radius:999px; font-size:.63rem; margin-left:.4rem;">
                {{ $memoire->lignes->count() }} ligne{{ $memoire->lignes->count() > 1 ? 's' : '' }}
            </span>
        </div>

        @if($memoire->lignes->isEmpty())
            <div style="text-align:center; padding:2rem; color:var(--color-text-tertiary); font-size:.82rem;
                    border:1px dashed var(--color-border-tertiary); border-radius:.5rem;">
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
                            <th style="text-align:right;">P.U</th>
                            <th style="text-align:right;">MHT</th>
                            <th style="text-align:right;">TVA ({{ $memoire->lignes->first()?->taux_tva }}%)</th>
                            <th style="text-align:right;">TTC</th>
                            <th style="text-align:right;">IR</th>
                            <th style="text-align:right; color:#166534;">NAP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($memoire->lignes->sortBy('numero_ligne') as $ligne)
                            <tr>
                                <td style="text-align:center; color:var(--color-text-tertiary); font-size:.72rem;">
                                    {{ $ligne->numero_ligne }}
                                </td>
                                <td style="font-weight:500; font-size:.82rem;">{{ $ligne->nature_depense }}</td>
                                <td style="text-align:center;">{{ number_format($ligne->quantite, 0, ',', ' ') }}</td>
                                <td class="num" style="color:var(--color-text-secondary);">
                                    {{ number_format($ligne->prix_unitaire, 0, ',', ' ') }}
                                </td>
                                <td class="num">{{ number_format($ligne->montant_ht, 0, ',', ' ') }}</td>
                                <td class="num" style="color:#854d0e;">{{ number_format($ligne->montant_tva, 0, ',', ' ') }}
                                </td>
                                <td class="num" style="color:#1e40af; font-weight:600;">
                                    {{ number_format($ligne->montant_ttc, 0, ',', ' ') }}</td>
                                <td class="num" style="color:#9f1239;">{{ number_format($ligne->montant_ir, 0, ',', ' ') }}</td>
                                <td class="num" style="color:#166534; font-weight:700;">
                                    {{ number_format($ligne->net_a_payer, 0, ',', ' ') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" style="font-size:.7rem; color:var(--color-text-secondary);">
                                TOTAUX ({{ $memoire->lignes->count() }} lignes)
                            </td>
                            <td class="num">{{ number_format($memoire->lignes->sum('montant_ht'), 0, ',', ' ') }}</td>
                            <td class="num" style="color:#854d0e;">
                                {{ number_format($memoire->lignes->sum('montant_tva'), 0, ',', ' ') }}</td>
                            <td class="num" style="color:#1e40af;">
                                {{ number_format($memoire->lignes->sum('montant_ttc'), 0, ',', ' ') }}</td>
                            <td class="num" style="color:#9f1239;">
                                {{ number_format($memoire->lignes->sum('montant_ir'), 0, ',', ' ') }}</td>
                            <td class="num" style="color:#166534;">
                                {{ number_format($memoire->lignes->sum('net_a_payer'), 0, ',', ' ') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif

        {{-- Récapitulatif --}}
        <div class="totaux-bloc">
            <div class="totaux-inner">
                <div class="sec-title">Récapitulatif financier</div>

                <div class="total-row t-ht">
                    <span>Montant HT</span>
                    <span
                        class="num-val">{{ number_format($memoire->montant_ht ?? $memoire->lignes->sum('montant_ht'), 0, ',', ' ') }}
                        FCFA</span>
                </div>
                <div class="total-row t-tva">
                    <span>TVA</span>
                    <span class="num-val">+
                        {{ number_format($memoire->montant_tva ?? $memoire->lignes->sum('montant_tva'), 0, ',', ' ') }}
                        FCFA</span>
                </div>
                <div class="total-row t-ttc">
                    <span>Montant TTC</span>
                    <span
                        class="num-val">{{ number_format($memoire->montant_ttc ?? $memoire->lignes->sum('montant_ttc'), 0, ',', ' ') }}
                        FCFA</span>
                </div>
                <div class="total-row t-ir">
                    <span>Retenue IR</span>
                    <span class="num-val">−
                        {{ number_format($memoire->montant_ir ?? $memoire->lignes->sum('montant_ir'), 0, ',', ' ') }}
                        FCFA</span>
                </div>
                <div class="total-row t-nap">
                    <span>💰 Net à Payer (NAP)</span>
                    <span
                        class="num-val">{{ number_format($memoire->montant_net ?? $memoire->lignes->sum('net_a_payer'), 0, ',', ' ') }}
                        FCFA</span>
                </div>

                @if($memoire->montant_lettres)
                    <div style="margin-top:.5rem; padding:.4rem .7rem; background:var(--color-background-tertiary);
                        border-radius:.4rem; font-size:.72rem; color:var(--color-text-secondary);
                        font-style:italic;">
                        {{ $memoire->montant_lettres }}
                    </div>
                @endif
            </div>
        </div>

        {{-- Note --}}
        <div style="margin-top:.75rem; padding:.4rem .7rem; background:var(--color-background-tertiary);
            border-radius:.4rem; font-size:.7rem; color:var(--color-text-tertiary);
            display:flex; align-items:center; gap:.4rem;">
            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Aperçu des lignes <strong>enregistrées</strong> — sauvegardez avant de rouvrir.
        </div>

    </div>{{-- /.md-wrap --}}
</div>