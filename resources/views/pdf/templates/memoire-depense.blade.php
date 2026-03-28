@php
    $disableFooter = true;

    $memoire = $donnees['_raw'] ?? $memoire ?? null;
    if (!$memoire)
        abort(404);

    $parametres = \App\Models\ParametresStructure::where('actif', true)->first();

    // ✅ Charger les relations DA et engagement
    $memoire->load([
        'lignes',
        'decisionAdministrative.typeDecision',
        'decisionAdministrative.engagement',
    ]);

    $lignes = $memoire->lignes->sortBy('numero_ligne');

    // ── DA et engagement liés ─────────────────────────────────
    $da = $memoire->decisionAdministrative;
    $engagement = $da?->engagement;

    // ── Pagination ────────────────────────────────────────────
    $lignesPage1 = 12;
    $lignesPagesSuivantes = 20;

    $totalLignes = $lignes->count();
    $lignesChunked = collect();
    $lignesRest = $lignes;

    if ($totalLignes > 0) {
        $lignesChunked->push($lignesRest->take($lignesPage1));
        $lignesRest = $lignesRest->skip($lignesPage1);
        while ($lignesRest->count() > 0) {
            $lignesChunked->push($lignesRest->take($lignesPagesSuivantes));
            $lignesRest = $lignesRest->skip($lignesPagesSuivantes);
        }
    } else {
        $lignesChunked->push(collect());
    }

    $nombrePages = $lignesChunked->count();

    $montantLettres = $donnees['montant_lettres']
        ?? $memoire->montant_lettres
        ?? \App\Services\NombreEnLettres::convertir($memoire->montant_ttc ?? 0);
@endphp

@extends('pdf.layouts.master', ['orientation' => 'landscape'])

@section('title', 'Mémoire de Dépense N° ' . $memoire->numero)

@section('montant_lettres')
    {{ $montantLettres }}
@endsection

@push('styles')
    <style>
        @page {
            size: A4 landscape;
            margin-top: 2cm;
            margin-bottom: 2cm;
            margin-left: 1.5cm;
            margin-right: 1.5cm;
        }

        .content-wrapper {
            padding-top: 1cm;
        }

        .md-header-row {
            display: table;
            width: 100%;
            margin-bottom: 6px;
        }

        .md-header-cell {
            display: table-cell;
            vertical-align: middle;
            font-size: 9pt;
        }

        .md-header-cell.left {
            width: 50%;
            text-align: left;
        }

        .md-header-cell.right {
            width: 50%;
            text-align: right;
        }

        .md-numero-box {
            display: inline-block;
            border: 2px solid #000;
            padding: 5px 14px;
            font-weight: bold;
            font-size: 11pt;
        }

        .md-titre {
            text-align: center;
            font-weight: bold;
            font-size: 11pt;
            text-decoration: underline;
            text-transform: uppercase;
            margin: 6px 0 3px;
        }

        .md-objet {
            text-align: center;
            font-size: 9pt;
            font-style: italic;
            margin-bottom: 4px;
        }

        /* ── Références ─────────────────────────────────────── */
        .md-refs {
            display: table;
            width: 100%;
            font-size: 8.5pt;
            margin-bottom: 4px;
        }

        .md-refs td {
            padding: 2px 4px;
        }

        /* ── Bandeau DA / Engagement ─────────────────────────── */
        .md-da-band {
            display: table;
            width: 100%;
            background: #f0f4ff;
            border: 1px solid #b0c0e8;
            border-radius: 3px;
            padding: 4px 8px;
            font-size: 8pt;
            margin-bottom: 5px;
        }

        .md-da-band td {
            border: none;
            padding: 2px 6px;
        }

        .md-da-label {
            font-weight: bold;
            color: #1e3a8a;
        }

        /* ── Tableau lignes ───────────────────────────────────── */
        .md-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
            margin: 6px 0;
        }

        .md-table th {
            background: #e8e8e8;
            border: 1px solid #000;
            padding: 5px 6px;
            text-align: center;
            font-weight: bold;
            font-size: 8pt;
            text-transform: uppercase;
        }

        .md-table td {
            border: 1px solid #000;
            padding: 4px 6px;
            vertical-align: middle;
            font-size: 8.5pt;
            line-height: 1.2;
        }

        .md-table td.num {
            text-align: right;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .md-table td.center {
            text-align: center;
        }

        .md-table tr.total-row td {
            background: #d8d8d8;
            font-weight: bold;
            border-top: 2px solid #000;
        }

        .md-lettres {
            border: 1px solid #000;
            padding: 5px 10px;
            font-size: 8.5pt;
            text-align: center;
            margin: 8px 0;
            page-break-inside: avoid;
        }

        .md-signature {
            margin-top: 20px;
            page-break-inside: avoid;
        }

        .md-signature-right {
            float: right;
            width: 30%;
            text-align: center;
            font-size: 8.5pt;
        }

        .page-break {
            page-break-after: always;
            break-after: page;
        }

        .page-number {
            position: fixed;
            bottom: 1cm;
            right: 1.5cm;
            font-size: 8pt;
            color: #666;
        }

        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }
    </style>
@endpush

@section('content')

    @foreach($lignesChunked as $pageIndex => $lignesPage)

        @if($pageIndex === 0)

            {{-- ── Numéro + Exercice ────────────────────────────── --}}
            <div class="md-header-row">
                <div class="md-header-cell left" style="font-size:8pt;">
                    Exercice : <strong>{{ $memoire->exercice }}</strong>
                </div>
                <div class="md-header-cell right">
                    <div class="md-numero-box">N° {{ $memoire->numero }}</div>
                </div>
            </div>

            <div style="text-align:right; font-size:7.5pt; color:#666; margin-bottom:4px;">
                Imprimé le {{ now()->format('d/m/Y à H:i') }}
            </div>

            <div class="md-titre">MÉMOIRE DE DÉPENSE</div>
            <div class="md-objet">{{ strtoupper($memoire->objet ?? '') }}</div>

            {{-- ── Références ──────────────────────────────────── --}}
            <table class="md-refs">
                <tr>
                    @if($memoire->numero_decision)
                        <td>
                            <strong>Décision N° :</strong> {{ $memoire->numero_decision }}
                            @if($memoire->date_decision)
                                du {{ $memoire->date_decision->format('d/m/Y') }}
                            @endif
                        </td>
                    @endif
                    @if($memoire->numero_ce)
                        <td>
                            <strong>CE N° :</strong> {{ $memoire->numero_ce }}
                            @if($memoire->date_ce)
                                du {{ $memoire->date_ce->format('d/m/Y') }}
                            @endif
                        </td>
                    @endif
                    <td style="text-align:right;">
                        <strong>Date :</strong>
                        {{ $memoire->date_memoire?->format('d/m/Y') ?? now()->format('d/m/Y') }}
                    </td>
                </tr>
            </table>

            {{-- ✅ NOUVEAU — Bandeau DA + Engagement (si transformé) ──── --}}
            {{-- @if($da || $engagement)
            <table class="md-da-band">
                <tr>
                    @if($da)
                    <td>
                        <span class="md-da-label">N° Décision :</span>
                        {{ $da->numero }}
                        @if($da->typeDecision)
                        <span style="color:#475569;">({{ $da->typeDecision->libelle }})</span>
                        @endif
                        @if($da->date_decision)
                        — du {{ \Carbon\Carbon::parse($da->date_decision)->format('d/m/Y') }}
                        @endif
                    </td>
                    @endif
                    @if($engagement)
                    <td>
                        <span class="md-da-label">N° Engagement :</span>
                        {{ $engagement->numero }}
                        @if($engagement->date_engagement)
                        — du {{ \Carbon\Carbon::parse($engagement->date_engagement)->format('d/m/Y') }}
                        @endif
                        <span style="color:#166534;">
                            ({{ number_format((float) $engagement->montant_engage, 0, ',', ' ') }} FCFA)
                        </span>
                    </td>
                    @endif
                </tr>
            </table>
            @endif --}}

        @else
            {{-- ── En-tête pages suivantes ─────────────────────── --}}
            <div style="margin-bottom:8px; display:table; width:100%;">
                <span style="display:table-cell; font-size:9pt; font-weight:bold; vertical-align:middle;">
                    Suite — Page {{ $pageIndex + 1 }}
                </span>
                <span style="display:table-cell; text-align:right; vertical-align:middle;">
                    <div class="md-numero-box" style="font-size:10pt;">N° {{ $memoire->numero }}</div>
                </span>
            </div>
        @endif

        {{-- ── Tableau des lignes ───────────────────────────────── --}}
        <table class="md-table">
            <thead>
                <tr>
                    <th style="width:35%;">NATURE DE LA DÉPENSE</th>
                    <th style="width:6%;">QTÉ</th>
                    <th style="width:9%;">P.U</th>
                    <th style="width:9%;">NAP</th>
                    <th style="width:9%;">MHT</th>
                    <th style="width:9%;">TVA (19,25%)</th>
                    <th style="width:8%;">IR (5,5%)</th>
                    <th style="width:9%;">MONTANT TTC</th>
                </tr>
            </thead>
            <tbody>
                @forelse($lignesPage as $ligne)
                    <tr>
                        <td>{{ $ligne->nature_depense }}</td>
                        <td class="center">{{ number_format($ligne->quantite, 0, ',', ' ') }}</td>
                        <td class="num">{{ number_format($ligne->prix_unitaire, 0, ',', ' ') }}</td>
                        <td class="num" style="font-weight:600;">{{ number_format($ligne->net_a_payer, 0, ',', ' ') }}</td>
                        <td class="num">{{ number_format($ligne->montant_ht, 0, ',', ' ') }}</td>
                        <td class="num">{{ number_format($ligne->montant_tva, 0, ',', ' ') }}</td>
                        <td class="num">{{ number_format($ligne->montant_ir, 0, ',', ' ') }}</td>
                        <td class="num" style="font-weight:600;">{{ number_format($ligne->montant_ttc, 0, ',', ' ') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align:center; font-style:italic; color:#666; height:8mm;">
                            Aucune ligne enregistrée
                        </td>
                    </tr>
                @endforelse

                {{-- @if($loop->last)
                @php $nbVides = max(0, 5 - $lignesPage->count()); @endphp
                @for($v = 0; $v < $nbVides; $v++) <tr style="height:7mm;">
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    </tr>
                    @endfor
                    @endif --}}
            </tbody>

            @if($loop->last)
                <tfoot>
                    <tr class="total-row">
                        <td colspan="2" style="text-align:right; font-size:9pt; text-transform:uppercase;">TOTAL</td>
                        <td></td>
                        <td class="num">{{ number_format($memoire->montant_net ?? $lignes->sum('net_a_payer'), 0, ',', ' ') }}</td>
                        <td class="num">{{ number_format($memoire->montant_ht ?? $lignes->sum('montant_ht'), 0, ',', ' ') }}</td>
                        <td class="num">{{ number_format($memoire->montant_tva ?? $lignes->sum('montant_tva'), 0, ',', ' ') }}</td>
                        <td class="num">{{ number_format($memoire->montant_ir ?? $lignes->sum('montant_ir'), 0, ',', ' ') }}</td>
                        <td class="num">{{ number_format($memoire->montant_ttc ?? $lignes->sum('montant_ttc'), 0, ',', ' ') }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>

        @if($loop->last)

            <div class="md-lettres" style="page-break-inside:avoid;">
                Arrêté le présent mémoire de dépense à la somme TTC de :
                <strong style="text-transform:uppercase;">@yield('montant_lettres')</strong>
            </div>

            <div class="md-signature clearfix" style="page-break-inside:avoid;">
                <div class="md-signature-right">
                    <div style="margin-bottom:4px; font-size:8pt;">
                        {{ $memoire->lieu_signature ?? 'Yaoundé' }}, le ________________________________
                    </div>
                    <div style="font-weight:bold; font-size:9pt; text-transform:uppercase;">
                        {{ $memoire->signataire_fonction
                        ?? ($parametres?->fonction_ordonnateur ?? 'LE DIRECTEUR GÉNÉRAL') }}
                    </div>
                    <div style="margin-top:18mm; font-size:8.5pt;">
                        @if($memoire->signataire_nom)
                            <span style="text-decoration:underline; font-weight:bold;">
                                {{ strtoupper($memoire->signataire_nom) }}
                            </span>
                        @else
                            <span style="color:#aaa;">____________________</span>
                        @endif
                    </div>
                </div>
            </div>

        @endif

        <div class="page-number">Page {{ $pageIndex + 1 }} / {{ $nombrePages }}</div>

        @if(!$loop->last)
            <div class="page-break"></div>
        @endif

    @endforeach

@endsection