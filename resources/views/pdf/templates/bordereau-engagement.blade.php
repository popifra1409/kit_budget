@extends('pdf.layouts.master')

@php
    $bordereau = $donnees['_raw'];

    $parametres = \App\Models\ParametresStructure::where('actif', true)->first();

    // Charger les relations si nécessaire
    if (!$bordereau->relationLoaded('engagements')) {
        $bordereau->load(['engagements.beneficiaire', 'lignes']);
    }

    $lignes = $bordereau->lignes()->with('engagement.beneficiaire')->orderBy('numero_ligne')->get();
@endphp

@section('title', 'Bordereau d\'Engagement')

@section('additional_styles')
    <style>
        @page {
            size: A4 portrait;
            margin: 20mm 15mm;
        }

        body {
            font-family: "Times New Roman", serif;
            font-size: 10pt;
            line-height: 1.3;
        }

        .header-section {
            text-align: center;
            margin-bottom: 20px;
        }

        .header-section h1 {
            font-size: 16pt;
            font-weight: bold;
            margin: 10px 0;
        }

        .info-box {
            border: 1px solid #000;
            padding: 10px;
            margin: 10px 0;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
        }

        .table-engagements {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 9pt;
        }

        .table-engagements th {
            background-color: #f0f0f0;
            border: 1px solid #000;
            padding: 8px 5px;
            font-weight: bold;
            text-align: left;
        }

        .table-engagements td {
            border: 1px solid #000;
            padding: 6px 5px;
            vertical-align: top;
        }

        .table-engagements .col-numero {
            width: 12%;
            text-align: center;
        }

        .table-engagements .col-beneficiaire {
            width: 20%;
        }

        .table-engagements .col-objet {
            width: 30%;
        }

        .table-engagements .col-montant {
            width: 15%;
            text-align: right;
        }

        .table-engagements .col-observations {
            width: 23%;
        }

        .total-row {
            font-weight: bold;
            background-color: #e8f5e9;
        }

        .signature-section {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
        }

        .signature-box {
            width: 45%;
            text-align: center;
        }

        .signature-line {
            border-top: 1px solid #000;
            margin-top: 60px;
            padding-top: 5px;
        }
    </style>
@endsection

@section('content')
    {{-- En-tête --}}
    <div class="header-section">

        <h1>BORDEREAU D'ENGAGEMENT</h1>
        <div style="font-size: 11pt; font-weight: bold; margin-top: 10px;">
            N° {{ $bordereau->numero }}
        </div>
    </div>

    {{-- Informations générales --}}
    <div class="info-box">
        <div class="info-row">
            <div><strong>Date d'émission :</strong> {{ $bordereau->date_emission?->format('d/m/Y') ?? 'N/A' }}</div>
            <div><strong>Exercice :</strong> {{ $bordereau->exercice }}</div>
        </div>
        <div class="info-row">
            <div><strong>Budget :</strong> {{ $bordereau->budget?->libelle ?? 'N/A' }}</div>
            <div><strong>Statut :</strong>
                <span style="text-transform: uppercase;">
                    {{ str_replace('_', ' ', $bordereau->statut) }}
                </span>
            </div>
        </div>
        @if ($bordereau->objet)
            <div style="margin-top: 5px;">
                <strong>Objet :</strong> {{ $bordereau->objet }}
            </div>
        @endif
    </div>

    {{-- Tableau des engagements --}}
    <table class="table-engagements">
        <thead>
            <tr>
                <th class="col-numero">N° Engagement</th>
                <th class="col-beneficiaire">Bénéficiaire</th>
                <th class="col-objet">Objet</th>
                <th class="col-montant">Montant Engagement</th>
                <th class="col-observations">Observations</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalMontant = 0;
            @endphp

            @foreach ($lignes as $ligne)
                @php
                    $engagement = $ligne->engagement;
                    $beneficiaire = $engagement->beneficiaire;
                    $nomBeneficiaire =
                        $beneficiaire->raison_sociale ?? ($beneficiaire->nom_complet ?? ($beneficiaire->name ?? 'N/A'));
                    $totalMontant += $engagement->montant_engage;
                @endphp
                <tr>
                    <td class="col-numero">{{ $engagement->numero }}</td>
                    <td class="col-beneficiaire">{{ $nomBeneficiaire }}</td>
                    <td class="col-objet">{{ $engagement->objet }}</td>
                    <td class="col-montant">{{ number_format($engagement->montant_engage, 0, ',', ' ') }} FCFA</td>
                    <td class="col-observations">
                        @if ($ligne->observations)
                            {{ $ligne->observations }}
                        @endif
                        @if ($ligne->statut_ligne === 'rejete' && $ligne->motif_rejet)
                            <span style="color: #d32f2f; font-weight: bold;">
                                REJETÉ: {{ $ligne->motif_rejet }}
                            </span>
                        @endif
                    </td>
                </tr>
            @endforeach

            {{-- Ligne de total --}}
            <tr class="total-row">
                <td colspan="3" style="text-align: right; padding-right: 10px;">
                    <strong>TOTAL ({{ $bordereau->nombre_engagements }} engagement(s))</strong>
                </td>
                <td class="col-montant">
                    <strong>{{ number_format($totalMontant, 0, ',', ' ') }} FCFA</strong>
                </td>
                <td></td>
            </tr>
        </tbody>
    </table>

    {{-- Observations générales --}}
    @if ($bordereau->observations)
        <div class="info-box">
            <strong>Observations générales :</strong>
            <div style="margin-top: 5px;">{{ $bordereau->observations }}</div>
        </div>
    @endif

    {{-- Section signatures --}}
    <div class="signature-section">
        <div class="signature-box">
            <div style="font-weight: bold; margin-bottom: 10px;">L'Ordonnateur</div>
            <div style="font-size: 9pt;">
                @if ($bordereau->emetteur)
                    {{ $bordereau->emetteur->name }}
                @endif
            </div>
            <div class="signature-line">
                Signature et cachet
            </div>
        </div>

        @if ($bordereau->valide_par)
            <div class="signature-box">
                <div style="font-weight: bold; margin-bottom: 10px;">Le Contrôleur Financier</div>
                <div style="font-size: 9pt;">
                    {{ $bordereau->validateur->name }}
                </div>
                <div class="signature-line">
                    Signature et cachet
                </div>
                <div style="font-size: 8pt; margin-top: 5px;">
                    Validé le {{ $bordereau->date_validation?->format('d/m/Y à H:i') }}
                </div>
            </div>
        @else
            <div class="signature-box">
                <div style="font-weight: bold; margin-bottom: 10px;">Le Contrôleur Financier</div>
                <div class="signature-line">
                    Signature et cachet
                </div>
            </div>
        @endif
    </div>

    {{-- Pied de page avec informations de transmission --}}
    @if ($bordereau->date_transmission)
        <div style="margin-top: 20px; font-size: 8pt; border-top: 1px solid #ccc; padding-top: 10px;">
            <strong>Transmission :</strong>
            Transmis le {{ $bordereau->date_transmission?->format('d/m/Y à H:i') }}
            @if ($bordereau->instance_destinataire)
                vers {{ str_replace('_', ' ', $bordereau->instance_destinataire) }}
            @endif
        </div>
    @endif
@endsection
