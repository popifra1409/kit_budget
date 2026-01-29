@extends('pdf.layouts.master', ['typeHeader' => 'op', 'typeFooter' => 'op'])

@php
    $ordonnance = $donnees['_raw'];
    $engagement = $ordonnance->engagement ?? null;
@endphp

@section('title', 'Ordonnance de Paiement - Impôt')

@section('montant_lettres')
    {{ \App\Helpers\NombreEnLettres::montantCFA($ordonnance->montant_impot ?? 0) }}
@endsection

@section('additional_styles')
    <style>
        .section-title {
            font-weight: bold;
            font-size: 9pt;
            margin: 12px 0 6px 0;
        }

        .info-grid {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 8.5pt;
        }

        .info-grid td {
            border: 1px solid #000;
            padding: 6px;
        }

        .info-grid td.label {
            font-weight: bold;
            background-color: #f5f5f5;
        }
    </style>
@endsection

@section('content')
    {{-- Objet de la dépense --}}
    <div class="section-title">OBJET DE LA DEPENSE:</div>
    <div style="border: 1px solid #000; padding: 8px; margin-bottom: 15px;">
        ACHAT DES CONSOMMABLES MEDICAUX
    </div>

    {{-- Tableau récapitulatif --}}
    <table class="info-grid">
        <tr>
            <td class="label" style="width: 35%;">N° du bon de caisse:</td>
            <td style="width: 65%;">{{ $ordonnance->numero_bon ?? '' }}</td>
        </tr>
        <tr>
            <td class="label">N° d'émission:</td>
            <td>{{ $ordonnance->numero_emission ?? '' }}</td>
        </tr>
        <tr>
            <td class="label">N° OP:</td>
            <td>{{ $ordonnance->numero_op ?? '' }}</td>
        </tr>
        <tr>
            <td class="label">Montant brut de l'ordonnancement:</td>
            <td style="text-align: right;">{{ number_format($ordonnance->montant_brut ?? 0, 0, ',', ' ') }} Fcfa</td>
        </tr>
        <tr>
            <td class="label">A PERCEVOIR:</td>
            <td style="text-align: right;">{{ number_format($ordonnance->montant_net ?? 0, 0, ',', ' ') }} Fcfa</td>
        </tr>
    </table>

    {{-- Désignation et Imputation --}}
    <table style="width: 100%; margin: 20px 0; border-collapse: collapse;">
        <tr>
            <td style="width: 50%; vertical-align: top;">
                <div class="section-title">DESIGNATION OU ORGANISME(S):</div>
                <table class="info-grid">
                    <tr>
                        <td class="label">PEC MEDICAL</td>
                        <td style="text-align: right;">{{ number_format($ordonnance->montant_pec ?? 0, 0, ',', ' ') }}</td>
                    </tr>
                    <tr>
                        <td class="label">A PERCEVOIR:</td>
                        <td style="text-align: right;">{{ number_format($ordonnance->montant_net ?? 0, 0, ',', ' ') }}</td>
                    </tr>
                    <tr>
                        <td colspan="2" style="text-align: center; font-style: italic; font-size: 7.5pt;">
                            Somme nette à payer ou à virer(A)<br>
                            Net sum to be paid or transferred
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" style="min-height: 40px;"></td>
                    </tr>
                </table>
            </td>
            <td style="width: 50%; vertical-align: top; padding-left: 10px;">
                <div class="section-title" style="text-align: center;">Imputation</div>
                <table class="info-grid">
                    <tr>
                        <td class="label">Période</td>
                        <td>{{ $ordonnance->periode ?? now()->format('m/Y') }}</td>
                    </tr>
                    <tr>
                        <td class="label">Montant</td>
                        <td style="text-align: right;">{{ number_format($ordonnance->montant_impot ?? 0, 0, ',', ' ') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Pièces justificatives --}}
    <div class="section-title">PIECES JUSTIFICATIVES DE LA DEPENSE(S):</div>
    <div style="border: 1px solid #000; padding: 8px; min-height: 50px;">
        Arrêté par nous le présent ordre de paiement à la somme de:<br>
        Stopped by us the present order or payment at the sum of
    </div>
@endsection