<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fiche de Contrôle des Engagements</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        @page {
            size: A4 landscape;
            margin: 15mm 15mm 20mm 15mm; /* haut | droite | bas (footer) | gauche */
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 7pt;
            line-height: 1.2;
            color: #000;
            width: 100%;
        }

        /* ── Règle universelle DomPDF ─────────────────────────────
           table-layout:fixed + word-wrap:break-word = colonnes strictes
           et texte qui se coupe proprement sans débordement.
        ──────────────────────────────────────────────────────────── */
        table {
            table-layout: fixed;
            border-collapse: collapse;
            width: 100%;
        }
        td, th {
            word-wrap: break-word;
            overflow-wrap: break-word;
            overflow: hidden;
        }

        /* ── En-tête ─────────────────────────────────────────────── */
        .title h1 {
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        .title .subtitle { font-size: 7pt; font-style: italic; }

        .info-box { border: 1px solid #000; padding: 2px 3px; }
        .info-box label { font-weight: bold; font-size: 5.5pt; display: block; margin-bottom: 1px; }
        .info-box .value { font-size: 6.5pt; }

        /* ── Hiérarchie ───────────────────────────────────────────── */
        .hierarchie { background-color: #f0f0f0; padding: 4px; border: 1px solid #000; margin: 5px 0; }
        .hierarchie h2 { font-size: 8pt; margin-bottom: 3px; text-decoration: underline; }
        .hierarchie td { padding: 1px 3px; font-size: 6pt; }
        .hierarchie td:first-child { font-weight: bold; width: 80px; }

        /* ── Résumé — 7 boîtes dans 1 ligne ──────────────────────── */
        .resume-table { width: 100%; border-collapse: collapse; margin: 5px 0; }
        .resume-table td { padding: 2px; vertical-align: top; }
        .resume-box {
            border: 2px solid #000;
            padding: 3px 2px;
            text-align: center;
        }
        .resume-box .lbl {
            font-size: 5.5pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 2px;
            line-height: 1.1;
        }
        .resume-box .val { font-size: 8pt; font-weight: bold; }

        /* ── Tableau engagements ──────────────────────────────────── */
        table.eng { width: 100%; margin: 5px 0; font-size: 5.5pt; }
        table.eng th {
            background-color: #333;
            color: #fff;
            padding: 2px 2px;
            text-align: center;
            border: 1px solid #000;
            font-size: 5.5pt;
            line-height: 1.1;
            vertical-align: middle;
        }
        table.eng td {
            padding: 2px 2px;
            border: 1px solid #000;
            vertical-align: top;
            font-size: 5.5pt;
            line-height: 1.1;
        }
        table.eng tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.eng tbody tr:nth-child(odd)  { background-color: #fff; }
        table.eng .num { text-align: right; white-space: nowrap; }
        table.eng .ctr { text-align: center; }
        table.eng .neg { color: #cc0000; }
        table.eng .pos { color: #009900; }
        table.eng tfoot { background-color: #e0e0e0; }
        table.eng tfoot td { padding: 3px 2px; border: 2px solid #000; font-size: 6pt; font-weight: bold; }

        /* ── Signatures ───────────────────────────────────────────── */
        .sig-box { text-align: center; border-top: 1px solid #000; padding-top: 3px; }
        .sig-box .titre { font-weight: bold; font-size: 6.5pt; margin-bottom: 12px; }
        .sig-box .nom   { font-style: italic; font-size: 6.5pt; margin-top: 12px; }

        /* ── Légende ──────────────────────────────────────────────── */
        .legende { font-size: 5pt; font-style: italic; color: #666; margin: 3px 0; }

        /* ── Footer fixe ──────────────────────────────────────────── */
        .footer {
            position: fixed;
            bottom: -15mm;  /* remonte dans la marge bas */
            left: 0; right: 0;
            height: 15mm;
            text-align: center;
            font-size: 5.5pt;
            border-top: 1px solid #ccc;
            padding-top: 3px;
            background: #fff;
        }
    </style>
</head>
<body>

{{-- ══════════════════════════════════════════════════════════
     PAGINATION DomPDF — au niveau racine
     ══════════════════════════════════════════════════════════ --}}
<script type="text/php">
if (isset($pdf)) {
    $font = $fontMetrics->getFont("Arial");
    $pdf->page_text(
        $pdf->get_width() / 2 - 20,
        $pdf->get_height() - 12,
        "Page {PAGE_NUM} / {PAGE_COUNT}",
        $font, 7
    );
}
</script>

{{-- Footer fixe --}}
<div class="footer">
    Document généré le {{ $date_generation ?? now()->format('d/m/Y à H:i') }}
    &nbsp;|&nbsp; {{ $generePar ?? 'Système' }}
    &nbsp;|&nbsp; Nomenclature : {{ $nomenclature?->code ?? 'N/A' }}
</div>

{{-- ══════════════════════════════════════════════════════════
     EN-TÊTE
     ✅ <table> au lieu de flex — colonnes strictes avec widths fixes
     ══════════════════════════════════════════════════════════ --}}
<table style="margin-bottom:4px;border:none;">
    <colgroup>
        <col style="width:40px;">
        <col>
        <col style="width:40px;">
    </colgroup>
    <tr>
        <td style="vertical-align:middle;text-align:center;border:none;">
            @if(!empty($logo))
                <img src="{{ $logo }}" style="width:35px;height:35px;">
            @else
                <div style="font-size:14pt;font-weight:bold;color:#0066cc;">
                    {{ strtoupper(substr($nomStructure ?? 'H', 0, 1)) }}
                </div>
            @endif
        </td>
        <td style="text-align:center;vertical-align:middle;border:none;" class="title">
            <h1>Fiche de Contrôle des Engagements des Crédits</h1>
            <div class="subtitle">Suivi de la Consommation Budgétaire</div>
            <div style="margin-top:4px;font-size:8pt;font-weight:bold;">
                GESTIONNAIRE DE CRÉDITS : {{ strtoupper($gestionnaireCredits ?? 'Non défini') }}
            </div>
        </td>
        <td style="border:none;"></td>
    </tr>
</table>

{{-- Infos exercice / budget / date
     ✅ 3 colonnes avec width strictes --}}
<table style="margin-bottom:5px;border-bottom:2px solid #000;padding-bottom:4px;border-left:none;border-right:none;border-top:none;">
    <colgroup>
        <col style="width:33%;">
        <col style="width:34%;">
        <col style="width:33%;">
    </colgroup>
    <tr>
        <td style="padding-right:3px;border:none;">
            <div class="info-box">
                <label>EXERCICE BUDGÉTAIRE</label>
                <div class="value">
                    {{ $ligneBudgetaire->budget?->code ?? 'N/A' }}
                    @if($ligneBudgetaire->budget?->libelle)
                        — {{ $ligneBudgetaire->budget->libelle }}
                    @endif
                </div>
            </div>
        </td>
        <td style="padding:0 3px;border:none;">
            <div class="info-box">
                <label>BUDGET</label>
                <div class="value">
                    {{ $ligneBudgetaire->budget?->code ?? 'N/A' }}
                    @if($ligneBudgetaire->budget?->libelle)
                        — {{ $ligneBudgetaire->budget->libelle }}
                    @endif
                </div>
            </div>
        </td>
        <td style="padding-left:3px;border:none;">
            <div class="info-box">
                <label>DATE DE GÉNÉRATION</label>
                <div class="value">{{ $date_generation ?? now()->format('d/m/Y à H:i') }}</div>
            </div>
        </td>
    </tr>
</table>

{{-- ══════════════════════════════════════════════════════════
     HIÉRARCHIE BUDGÉTAIRE
     ══════════════════════════════════════════════════════════ --}}
@if(!empty($hierarchie))
<div class="hierarchie">
    <h2>IMPUTATION</h2>
    <table style="border:none;">
        <colgroup>
            <col style="width:40%;">
            <col style="width:60%;">
        </colgroup>
        <tr>
            <td style="vertical-align:top;padding-right:8px;border:none;">
                <strong>PARAGRAPHE :</strong><br>
                {{ $nomenclature?->code ?? 'N/A' }} — {{ $nomenclature?->libelle ?? 'N/A' }}
            </td>
            <td style="vertical-align:top;border:none;">
                @foreach(['programme','action','activite','tache','article','paragraphe'] as $niveau)
                    @if(!empty($hierarchie[$niveau]))
                        <strong>{{ strtoupper($niveau) }} :</strong> {{ $hierarchie[$niveau] }}<br>
                    @endif
                @endforeach
            </td>
        </tr>
    </table>
</div>
@endif

{{-- ══════════════════════════════════════════════════════════
     RÉSUMÉ BUDGÉTAIRE — 7 boîtes
     ✅ table-layout:fixed + 7 colonnes à 14.28% chacune
     Pas de border-spacing (cause de débordement)
     ══════════════════════════════════════════════════════════ --}}
<table class="resume-table" style="border-collapse:collapse;margin:5px 0;">
    <colgroup>
        <col style="width:14.28%;">
        <col style="width:14.28%;">
        <col style="width:14.28%;">
        <col style="width:14.28%;">
        <col style="width:14.28%;">
        <col style="width:14.28%;">
        <col style="width:14.32%;">
    </colgroup>
    <tr>
        <td style="padding:0 2px 0 0;">
            <div class="resume-box" style="border-color:#0066cc;">
                <div class="lbl">Dotation<br>Initiale</div>
                <div class="val" style="color:#0066cc;">
                    {{ number_format($dotation_initiale ?? 0, 0, ',', ' ') }}
                </div>
            </div>
        </td>
        <td style="padding:0 2px;">
            <div class="resume-box" style="border-color:#00aa00;">
                <div class="lbl">Vir.<br>Entrants (+)</div>
                <div class="val" style="color:#00aa00;">
                    {{ number_format($virements_entrants ?? 0, 0, ',', ' ') }}
                </div>
            </div>
        </td>
        <td style="padding:0 2px;">
            <div class="resume-box" style="border-color:#cc0000;">
                <div class="lbl">Vir.<br>Sortants (-)</div>
                <div class="val" style="color:#cc0000;">
                    {{ number_format($virements_sortants ?? 0, 0, ',', ' ') }}
                </div>
            </div>
        </td>
        <td style="padding:0 2px;">
            <div class="resume-box" style="border-color:#0066cc;">
                <div class="lbl">Budget<br>Rectifié</div>
                <div class="val" style="color:#0066cc;">
                    {{ number_format($budget_rectifie ?? 0, 0, ',', ' ') }}
                </div>
            </div>
        </td>
        <td style="padding:0 2px;">
            <div class="resume-box" style="border-color:#ff6600;">
                <div class="lbl">Total<br>Engagé</div>
                <div class="val" style="color:#ff6600;">
                    {{ number_format($total_engage ?? 0, 0, ',', ' ') }}
                </div>
            </div>
        </td>
        <td style="padding:0 2px;">
            @php $dispColor = ($disponible ?? 0) < 0 ? '#cc0000' : '#009900'; @endphp
            <div class="resume-box" style="border-color:{{ $dispColor }};">
                <div class="lbl">Crédits<br>Disponibles</div>
                <div class="val" style="color:{{ $dispColor }};">
                    {{ number_format($disponible ?? 0, 0, ',', ' ') }}
                </div>
            </div>
        </td>
        <td style="padding:0 0 0 2px;">
            <div class="resume-box" style="border-color:#333;">
                <div class="lbl">Taux de<br>Consommation</div>
                <div class="val">{{ number_format($taux_consommation ?? 0, 1) }}%</div>
            </div>
        </td>
    </tr>
</table>

{{-- ══════════════════════════════════════════════════════════
     TABLEAU DES ENGAGEMENTS
     ✅ table-layout:fixed + colgroup avec widths % stricts
     ══════════════════════════════════════════════════════════ --}}
<table class="eng">
    <colgroup>
        <col style="width:7%;">   {{-- N° Engagement --}}
        <col style="width:13%;">  {{-- Bénéficiaire --}}
        <col style="width:17%;">  {{-- Objet --}}
        <col style="width:6%;">   {{-- Référence --}}
        <col style="width:6%;">   {{-- Date --}}
        <col style="width:9%;">   {{-- Montant engagé --}}
        <col style="width:9%;">   {{-- Disponible après --}}
        <col style="width:7%;">   {{-- N° OP --}}
        <col style="width:9%;">   {{-- Montant OP --}}
        <col style="width:9%;">   {{-- Montant OPT --}}
        <col style="width:8%;">   {{-- Observations --}}
    </colgroup>
    <thead>
        <tr>
            <th>N° ENG.</th>
            <th>BÉNÉFICIAIRE</th>
            <th>OBJET</th>
            <th>RÉF.</th>
            <th>DATE</th>
            <th>MT ENGAGÉ<br>(FCFA)</th>
            <th>DISPON.<br>APRÈS</th>
            <th>N° OP</th>
            <th>MT OP<br>(FCFA)</th>
            <th>MT OPT<br>(FCFA)</th>
            <th>OBS.</th>
        </tr>
    </thead>
    <tbody>
        @forelse($engagements ?? [] as $engagement)
            <tr>
                <td class="ctr">{{ $engagement['numero_engagement'] ?? '-' }}</td>
                <td>{{ $engagement['beneficiaire'] ?? '-' }}</td>
                <td>{{ $engagement['objet'] ?? '-' }}</td>
                <td class="ctr">{{ $engagement['reference'] ?? '-' }}</td>
                <td class="ctr">{{ $engagement['date_engagement'] ?? '-' }}</td>
                <td class="num">{{ number_format($engagement['montant_engage'] ?? 0, 0, ',', ' ') }}</td>
                @php $da = $engagement['disponible_apres'] ?? 0; @endphp
                <td class="num {{ $da < 0 ? 'neg' : 'pos' }}">{{ number_format($da, 0, ',', ' ') }}</td>
                <td class="ctr">{{ $engagement['numero_op'] ?? '-' }}</td>
                <td class="num">{{ number_format($engagement['montant_op'] ?? 0, 0, ',', ' ') }}</td>
                <td class="num">{{ number_format($engagement['montant_opt'] ?? 0, 0, ',', ' ') }}</td>
                <td style="font-size:5pt;">{{ $engagement['observations'] ?? '' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="11" style="text-align:center;padding:10px;font-style:italic;color:#999;">
                    Aucun engagement enregistré sur cette ligne budgétaire
                </td>
            </tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5" style="text-align:right;">TOTAL GÉNÉRAL :</td>
            <td class="num">{{ number_format($total_engage ?? 0, 0, ',', ' ') }}</td>
            @php $dispTotal = $disponible ?? 0; @endphp
            <td class="num {{ $dispTotal < 0 ? 'neg' : 'pos' }}">
                {{ number_format($dispTotal, 0, ',', ' ') }}
            </td>
            <td></td>
            <td class="num">{{ number_format($total_op ?? 0, 0, ',', ' ') }}</td>
            <td class="num">{{ number_format($total_opt ?? 0, 0, ',', ' ') }}</td>
            <td></td>
        </tr>
    </tfoot>
</table>

<div class="legende">
    * Montants en FCFA &nbsp;|&nbsp;
    * Disponible calculé progressivement après chaque engagement &nbsp;|&nbsp;
    * <span style="color:#cc0000;">Rouge</span> = dépassement de crédits
</div>

{{-- ══════════════════════════════════════════════════════════
     SIGNATURES — 3 colonnes
     ✅ <table> strict sans border-spacing
     ══════════════════════════════════════════════════════════ --}}
<table style="margin-top:8px;border:none;">
    <colgroup>
        <col style="width:33%;">
        <col style="width:34%;">
        <col style="width:33%;">
    </colgroup>
    <tr>
        <td style="padding-right:5px;border:none;vertical-align:top;">
            <div class="sig-box">
                <div class="titre">LE CONTRÔLEUR FINANCIER</div>
                <div class="nom">Nom et Signature</div>
            </div>
        </td>
        <td style="padding:0 5px;border:none;vertical-align:top;">
            <div class="sig-box">
                <div class="titre">
                    {{ strtoupper($sousDirection ?? 'DIRECTEUR DES AFFAIRES ADMINISTRATIVES ET FINANCIÈRES') }}
                </div>
                <div class="nom">Nom et Signature</div>
            </div>
        </td>
        <td style="padding-left:5px;border:none;vertical-align:top;">
            <div class="sig-box">
                <div class="titre">
                    {{ strtoupper($fonctionOrdonnateur ?? 'LE DIRECTEUR GÉNÉRAL') }}
                </div>
                <div class="nom">Nom et Signature</div>
            </div>
        </td>
    </tr>
</table>

</body>
</html>