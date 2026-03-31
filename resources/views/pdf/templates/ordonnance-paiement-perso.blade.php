{{-- resources/views/pdf/templates/ordonnance-paiement-perso.blade.php --}}
@php
    $disableFooter = true;
    $op = $donnees['_raw'];
    $params = \App\Models\ParametresStructure::where('actif', true)->first();

    // Relations
    if (!$op->relationLoaded('engagement'))
        $op->load(['engagement.engageable', 'exercice']);

    $engagement = $op->engagement;
    $engageable = $engagement?->engageable;
    $nomStructure = $params?->nom_complet ?? 'HOPITAL GENERAL DE YAOUNDE';
    $sigle = $params?->sigle ?? 'HGY';
    $ville = $params?->ville ?? 'Yaoundé';
    $bp = $params?->bp ?? 'B.P 5408 YAOUNDE';
    $tel = $params?->telephone ?? '(237) 222 21 20 18';
    $fax = $params?->fax ?? '(237) 222 21 20 15';

    $montantBrut = (float) ($op->montant_brut ?? $op->montant_net ?? 0);
    $aPrecompter = (float) ($op->montant_ir ?? 0);
    $sommeNette = (float) ($op->montant_net ?? 0);
    $imputation = $engagement?->nomenclaturePrincipale?->code ?? '';
    $beneficiaire = $op->beneficiaire_nom
        ?? $engagement?->beneficiaire?->raison_sociale
        ?? $engagement?->beneficiaire?->nom_complet
        ?? '—';
    $objet = $op->objet ?? $engageable?->objet ?? '—';
    $refBC = $engageable?->numero ?? $op->reference_bc ?? '—';
    $annee = $op->exercice?->annee ?? now()->year;
    $moisEmission = $op->date_emission ? $op->date_emission->format('m/Y') : now()->format('m/Y');
    $dateEmission = $op->date_emission ? $op->date_emission->format('d/m/Y') : now()->format('d/m/Y');
    $montantLettres = $donnees['montant_net_lettres']
        ?? $donnees['montant_brut_lettres']
        ?? \App\Services\NombreEnLettres::convertir($sommeNette);

    // Numéros
    $numOP = $op->numero ?? '—';
    $numEM = $op->numero_emission ?? $numOP;
    $numBC = $op->numero_bon_caisse ?? $refBC;
@endphp

@extends('pdf.layouts.master', ['orientation' => 'portrait'])

@section('title', 'Ordonnance de Paiement N° ' . $numOP)

@push('styles')
    <style>
        @page {
            size: A4 portrait;
            margin: 8mm 8mm 8mm 8mm;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 8.5pt;
            color: #000;
        }

        /* ── En-tête ───────────────────────────────────────── */
        .op-header {
            display: table;
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2mm;
        }

        .op-header-left {
            display: table-cell;
            width: 22%;
            vertical-align: top;
            font-size: 7.5pt;
            border: 1px solid #000;
            padding: 3mm;
        }

        .op-header-center {
            display: table-cell;
            width: 44%;
            vertical-align: middle;
            text-align: center;
            padding: 2mm;
        }

        .op-header-right {
            display: table-cell;
            width: 34%;
            vertical-align: top;
        }

        .op-title {
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .op-subtitle {
            font-size: 8pt;
            margin-top: 1mm;
        }

        .op-auth {
            font-size: 7.5pt;
            font-style: italic;
            margin-top: 2mm;
            border-top: 1px solid #000;
            padding-top: 1mm;
        }

        .op-struct {
            font-size: 9.5pt;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.3;
        }

        .op-struct-en {
            font-size: 8pt;
            font-style: italic;
        }

        .op-adresse {
            font-size: 7pt;
            margin-top: 1mm;
        }

        /* Boxes droite */
        .boxes-table {
            width: 100%;
            border-collapse: collapse;
        }

        .boxes-table td {
            border: 1px solid #000;
            padding: 1mm 2mm;
            font-size: 7.5pt;
        }

        .boxes-table .box-label {
            background: #f0f0f0;
            font-size: 6.5pt;
        }

        .boxes-table .box-value {
            font-weight: bold;
            font-size: 8pt;
            text-align: right;
        }

        .boxes-table .box-en {
            font-size: 6pt;
            font-style: italic;
            color: #555;
        }

        /* ── Corps principal ───────────────────────────────── */
        .op-body {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1mm;
        }

        .op-body td,
        .op-body th {
            border: 1px solid #000;
            padding: 1.5mm 2mm;
            vertical-align: top;
            font-size: 8pt;
        }

        .op-body th {
            background: #f0f0f0;
            font-size: 7.5pt;
            font-weight: bold;
        }

        .label-fr {
            font-weight: bold;
            font-size: 8pt;
        }

        .label-en {
            font-size: 6.5pt;
            font-style: italic;
            color: #333;
        }

        .val-bold {
            font-weight: bold;
        }

        .val-box {
            border: 1.5px solid #000;
            display: inline-block;
            padding: 1mm 3mm;
            min-width: 25mm;
            text-align: right;
            font-weight: bold;
            font-size: 9pt;
        }

        .val-right {
            text-align: right;
        }

        .montant-lettres {
            font-weight: bold;
            font-size: 10pt;
            text-align: center;
            padding: 3mm;
        }

        .section-label {
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        /* ── Signatures ────────────────────────────────────── */
        .sig-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2mm;
        }

        .sig-table td {
            border: 1px solid #000;
            padding: 2mm;
            vertical-align: top;
            font-size: 7.5pt;
        }

        .sig-h {
            height: 18mm;
        }

        /* ── Footer ────────────────────────────────────────── */
        .op-footer {
            font-size: 6.5pt;
            margin-top: 2mm;
            border-top: 1px solid #000;
            padding-top: 1mm;
        }
    </style>
@endpush

@section('content')

    {{-- ═══════════════ EN-TÊTE ═══════════════ --}}
    <div class="op-header">

        {{-- Gauche : Visa DAAF --}}
        <div class="op-header-left">
            <div class="label-fr">Visa du Directeur des<br>Affaires Administratives<br>et Financières</div>
            <div style="height:16mm;"></div>
        </div>

        {{-- Centre : Titre --}}
        <div class="op-header-center">
            <div class="op-struct">{{ $nomStructure }}</div>
            <div class="op-struct-en">{{ strtoupper($params?->nom_court_en ?? $sigle . ' GENERAL HOSPITAL') }}</div>
            <div class="op-adresse">{{ $bp }} &nbsp; Tél. {{ $tel }} &nbsp; Fax {{ $fax }}</div>
            <div style="margin:2mm 0; border-top:1px solid #000; border-bottom:1px solid #000; padding:1mm 0;">
                <div class="op-title">ORDONNANCE DE PAIEMENT</div>
                <div class="op-subtitle"><em>PAYMENT ORDER</em></div>
            </div>
            <div class="op-auth">
                L'Agent comptable de l'{{ $sigle }} est autorisé à payer la<br>
                <em>The accounting officer of the {{ $sigle }} is hereby autorized to pay the debit</em>
            </div>
        </div>

        {{-- Droite : Numéros --}}
        <div class="op-header-right">
            <table class="boxes-table">
                <tr>
                    <td class="box-label">Mois et exercice d'émission<br><span class="box-en">Month and budgetary</span>
                    </td>
                    <td class="box-value">{{ $moisEmission }}</td>
                </tr>
                <tr>
                    <td class="box-label">Exercice budgétaire<br><span class="box-en">Budgetary year</span></td>
                    <td class="box-value">{{ $annee }}</td>
                </tr>
                <tr>
                    <td class="box-label">N° de bon de caisse<br><span class="box-en">N° of the cash voucher</span></td>
                    <td class="box-value">{{ $numBC }}</td>
                </tr>
                <tr>
                    <td class="box-label">N° Emission<br><span class="box-en">N° of emission</span></td>
                    <td class="box-value">{{ $numEM }}</td>
                </tr>
                <tr>
                    <td class="box-label">N° OP<br><span class="box-en">N° of OP</span></td>
                    <td class="box-value">{{ $numOP }}</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- ═══════════════ CORPS ═══════════════ --}}
    <table class="op-body">

        {{-- Objet + Imputation + Montant --}}
        <tr>
            <td style="width:55%;">
                <div class="label-fr">OBJET DE LA DEPENSE:</div>
                <div class="label-en">SUBJECT OF EXPENDITURE:</div>
                <div class="val-bold" style="margin-top:1mm;">{{ strtoupper($objet) }}</div>
            </td>
            <th style="width:15%; text-align:center;">
                Imputation<br><em style="font-size:6.5pt;">Imputation</em>
            </th>
            <th style="width:30%; text-align:center;">
                Montant:<br><em style="font-size:6.5pt;">Amount</em>
            </th>
        </tr>

        {{-- Référence BC --}}
        <tr>
            <td>
                <span style="font-size:8pt;">Paiement selon le bon</span>
                <strong style="margin-left:5mm;">{{ $refBC }}</strong>
            </td>
            <td class="val-right">{{ $imputation }}</td>
            <td class="val-right val-bold">{{ number_format($montantBrut, 0, ',', ' ') }}</td>
        </tr>

        {{-- Bénéficiaire + Montants --}}
        <tr>
            <td rowspan="3" style="vertical-align:top;">
                <div class="label-fr">DESIGNATION DU CREANCIER(1):</div>
                <div class="label-en">DESIGNATION OF THE CREDITOR(1):</div>
                <div class="val-bold" style="margin-top:3mm; font-size:10pt;">{{ strtoupper($beneficiaire) }}</div>
                <div style="margin-top:5mm;">
                    <div class="label-fr" style="font-size:7.5pt;">PIECES JUSTIFICATIVES DE LA DEPENSE(1)</div>
                    <div class="label-en">RELEVANT OF THE CREDITOR(1)</div>
                    <div style="height:12mm;"></div>
                </div>
            </td>
            <td colspan="2" style="padding:1mm 2mm;">
                <div style="display:table; width:100%;">
                    <div style="display:table-cell; font-size:7.5pt; vertical-align:middle; width:55%;">
                        Montant brut de l'ordonnance<br>
                        <em style="font-size:6.5pt;">Gross amount of the orther</em>
                    </div>
                    <div style="display:table-cell; text-align:right; vertical-align:middle;">
                        <span class="val-box">{{ number_format($montantBrut, 0, ',', ' ') }}</span>
                    </div>
                </div>
            </td>
        </tr>
        <tr>
            <td colspan="2" style="padding:1mm 2mm;">
                <div style="display:table; width:100%;">
                    <div style="display:table-cell; font-size:7.5pt; vertical-align:middle; width:55%;">
                        A PRECOMPTER<br>
                        <em style="font-size:6.5pt;">TO BE DEDUCTED</em>
                    </div>
                    <div style="display:table-cell; text-align:right; vertical-align:middle;">
                        <span class="val-box">{{ number_format($aPrecompter, 0, ',', ' ') }}</span>
                    </div>
                </div>
            </td>
        </tr>
        <tr>
            <td colspan="2" style="padding:1mm 2mm;">
                <div style="display:table; width:100%;">
                    <div style="display:table-cell; font-size:7.5pt; vertical-align:middle; width:55%;">
                        Somme nette à payer ou à virer(A)<br>
                        <em style="font-size:6.5pt;">Net sum to be paid or tranfered(A)</em>
                    </div>
                    <div style="display:table-cell; text-align:right; vertical-align:middle;">
                        <span class="val-box">{{ number_format($sommeNette, 0, ',', ' ') }}</span>
                    </div>
                </div>
            </td>
        </tr>

        {{-- Montant en lettres --}}
        <tr>
            <td colspan="3" style="text-align:center; padding:1mm 3mm;">
                <div style="font-size:7.5pt; font-style:italic;">
                    Arrêté par nous le présent ordre de payment à la somme de:<br>
                    <em style="font-size:6.5pt;">We hereby write up this order at the amount of:</em>
                </div>
                <div class="montant-lettres" style="margin-top:1mm;">
                    {{ ucfirst($montantLettres) }}
                </div>
            </td>
        </tr>

        {{-- Agent comptable + Émis le + Signature ordonnateur --}}
        <tr>
            <td style="width:30%; vertical-align:top;">
                <div class="label-fr">L'AGENT COMPTABLE:</div>
                <div class="label-en">THE ACCOUNTING OFFICER</div>
                <div style="height:12mm;"></div>
            </td>
            <td colspan="2" style="vertical-align:top;">
                <div style="display:table; width:100%;">
                    <div style="display:table-cell; width:50%; vertical-align:top;">
                        <div style="font-size:7.5pt; font-style:italic;">
                            émis à {{ $ville }} le<br>
                            <em style="font-size:6.5pt;">Issued at {{ $ville }} on</em>
                        </div>
                        <div style="font-weight:bold; margin-top:1mm;">{{ $dateEmission }}</div>
                        <div style="height:14mm;"></div>
                    </div>
                    <div
                        style="display:table-cell; width:50%; vertical-align:top; text-align:center; border-left:1px solid #ccc; padding-left:2mm;">
                        <div style="font-size:6.5pt; font-style:italic; margin-bottom:1mm;">
                            (Signature et timbre de l'ordonnateur)<br>
                            <em>(Signature and stamp of the Vote Holder</em>
                        </div>
                        <div style="height:14mm;"></div>
                    </div>
                </div>
            </td>
        </tr>

        {{-- Paiement par + Compte à créditer --}}
        <tr>
            <td style="vertical-align:top;">
                <div class="label-fr">PAIEMENT PAR:</div>
                <div class="label-en">We hereby make up this order at</div>
                <div style="height:8mm;"></div>
                <div style="font-size:7.5pt;">A {{ $ville }}, le ______________</div>
                <div style="font-size:6.5pt; font-style:italic;">At yaounde, on the</div>
                <div style="height:8mm;"></div>
            </td>
            <td colspan="2" style="vertical-align:top;">
                <div class="label-fr">COMPTE A CREDITER</div>
                <div class="label-en">ACCOUNT TO BE CREDITED</div>
                <div style="height:20mm;"></div>
            </td>
        </tr>
    </table>

    {{-- ═══════════════ FOOTER ═══════════════ --}}
    <div class="op-footer">
        <p>1)Nom, Prénom, adresse complète. Pour les sociétés: Raisons sociales exactes.</p>
        <p>1)Surname, name and full adress. Precise company name</p>
    </div>

@endsection