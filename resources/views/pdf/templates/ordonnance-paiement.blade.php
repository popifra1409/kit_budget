@extends('pdf.layouts.master', ['typeHeader' => 'op', 'typeFooter' => 'op'])

@php
    $ordonnance = $donnees['_raw'];
    $engagement = $ordonnance->engagement ?? null;
    $beneficiaire = $ordonnance->beneficiaire ?? null;
@endphp

@section('title', 'Ordonnance de Paiement')

@section('montant_lettres')
    {{ \App\Helpers\NombreEnLettres::montantCFA($ordonnance->montant ?? 0) }}
@endsection

@section('additional_styles')
    <style>
        .section-title {
            font-weight: bold;
            font-size: 9pt;
            margin: 12px 0 6px 0;
            text-transform: uppercase;
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
            vertical-align: top;
        }

        .info-grid td.label {
            width: 40%;
            font-weight: bold;
            background-color: #f5f5f5;
        }

        .info-grid td.value {
            width: 60%;
        }

        .montant-box {
            text-align: center;
            font-weight: bold;
            font-size: 10pt;
            border: 2px solid #000;
            padding: 10px;
            margin: 15px 0;
        }
    </style>
@endsection

@section('content')
    {{-- Objet de la dépense --}}
    <div class="section-title">OBJET DE LA DEPENSE:</div>
    <div style="border: 1px solid #000; padding: 8px; margin-bottom: 15px; font-size: 9pt;">
        {{ $ordonnance->objet ?? 'ACHAT DES CONSOMMABLES MEDICAUX' }}
    </div>

    {{-- Désignation du créancier --}}
    <table class="info-grid">
        <tr>
            <td class="label">DESIGNATION DU CREANCIER(S):<br><i style="font-weight: normal; font-size: 7.5pt;">(DESIGNATE
                    THE CREDITOR(S))</i></td>
            <td class="value">{{ $beneficiaire->raison_sociale ?? ($beneficiaire->name ?? '') }}</td>
        </tr>
        <tr>
            <td class="label">A PERCEVOIR:<br><i style="font-weight: normal; font-size: 7.5pt;">(TO BE CREDITED)</i></td>
            <td class="value">{{ number_format($ordonnance->montant_net ?? 0, 0, ',', ' ') }} Fcfa</td>
        </tr>
    </table>

    {{-- Désignation et Imputation --}}
    <table style="width: 100%; margin: 10px 0; border-collapse: collapse;">
        <tr>
            <td style="width: 50%; vertical-align: top; padding-right: 10px;">
                <div class="section-title">LE DIRECTEUR DES IMPOTS</div>
                <div style="border: 1px solid #000; padding: 15px; min-height: 80px; font-size: 8.5pt;">
                    <!-- Espace pour visa -->
                </div>
            </td>
            <td style="width: 50%; vertical-align: top; padding-left: 10px;">
                <div class="section-title" style="text-align: center;">Imputation</div>
                <table class="info-grid">
                    <tr>
                        <td class="label">Période</td>
                        <td class="value">
                            {{ $ordonnance->mois_emission ?? now()->format('m') }}/{{ $ordonnance->exercice ?? now()->year }}
                        </td>
                    </tr>
                    <tr>
                        <td class="label">Montant</td>
                        <td class="value">{{ number_format($ordonnance->montant ?? 0, 0, ',', ' ') }} Fcfa</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Pièces justificatives --}}
    <div class="section-title">PIECES JUSTIFICATIVES DE LA DEPENSE(S):</div>
    <div style="border: 1px solid #000; padding: 8px; min-height: 60px; font-size: 8.5pt;">
        RELEVE DES CREANCIER(S)
    </div>

    {{-- Montant en lettres (géré par le footer) --}}
@endsection
