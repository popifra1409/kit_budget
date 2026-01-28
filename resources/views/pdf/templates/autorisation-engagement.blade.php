@extends('pdf.layouts.master')

@php
    $engagement = $donnees['_raw'];
    $nomenclature = $engagement->nomenclaturePrincipale;
    $tache = $nomenclature->tache ?? null;
    $activite = $tache->activite ?? null;
    $action = $activite->action ?? null;
    $programme = $action->programme ?? null;
@endphp

@section('title', 'Autorisation d\'Engagement')

@section('montant_lettres')
    {{ \App\Helpers\NombreEnLettres::montantCFA($engagement->montant_engage ?? 0) }}
@endsection

@section('additional_styles')
    <style>
        .doc-title {
            text-align: center;
            font-size: 11pt;
            font-weight: bold;
            margin: 15px 0 20px 0;
            text-decoration: underline;
        }

        .type-etat {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            font-size: 9pt;
        }

        .info-line {
            margin: 6px 0;
            font-size: 9pt;
            line-height: 1.4;
        }

        .info-line strong {
            font-weight: bold;
        }

        .section-title {
            font-weight: bold;
            font-size: 9pt;
            margin: 12px 0 6px 0;
        }

        .hierarchie-table {
            width: 100%;
            margin: 15px 0;
            border-collapse: collapse;
            font-size: 8.5pt;
        }

        .hierarchie-table th,
        .hierarchie-table td {
            border: 1px solid #000;
            padding: 6px;
            text-align: left;
            vertical-align: top;
        }

        .hierarchie-table th {
            background-color: #f0f0f0;
            font-weight: bold;
            width: 20%;
        }

        .visa-section {
            margin-top: 40px;
            text-align: right;
            font-weight: bold;
            font-size: 9pt;
        }
    </style>
@endsection

@section('content')
    {{-- Titre --}}
    <div class="doc-title">
        AUTORISATION D'ENGAGEMENT
    </div>

    {{-- Type et État --}}
    <table style="width: 100%; margin: 10px 0; border-collapse: collapse;">
        <tr>
            <td style="width: 60%; border: none; padding: 0; font-size: 9pt;">
                <strong>Type Autorisation Engagement:</strong> {{ strtoupper($engagement->type_engagement) }}
            </td>
            <td style="width: 40%; border: none; padding: 0; text-align: right; font-size: 9pt;">
                <strong>État: Annuel</strong>
            </td>
        </tr>
    </table>

    {{-- Montant --}}
    <div class="info-line">
        <strong>Montant en chiffres:</strong> {{ number_format($engagement->montant_engage, 0, ',', ' ') }} F cfa
    </div>

    <div class="info-line">
        <strong>(En lettres):</strong> @yield('montant_lettres')
    </div>

    {{-- Exercice --}}
    <div class="info-line">
        <strong>A été contractée au titre de l'exercice:</strong> {{ $engagement->exercice ?? now()->year }}
    </div>

    {{-- Référence et signature --}}
    <div class="section-title">Référence</div>

    <div class="info-line">
        <strong>Date de signature:</strong>
        {{ $engagement->date_engagement ? \Carbon\Carbon::parse($engagement->date_engagement)->format('d/m/Y') : '................................' }}
    </div>

    <div class="info-line">
        <strong>Signataire:</strong> {{ $parametres->nom_ordonnateur ?? 'Pr. ESSOMBA NOEL EMMANUEL' }}
    </div>

    <div class="info-line">
        <strong>Objet:</strong> {{ $engagement->objet }}
    </div>

    <div class="info-line">
        <strong>Bénéficiaire:</strong>
        {{ $engagement->beneficiaire->raison_sociale ?? ($engagement->beneficiaire->name ?? '') }}
    </div>

    {{-- Imputation --}}
    <div class="section-title">
        Cette autorisation sera imputée de la manière suivante:
    </div>

    <div class="info-line">
        <strong>Chapitre:</strong> {{ substr($nomenclature->code, 0, 2) }}
    </div>

    <div class="info-line">
        <strong>Article:</strong> {{ substr($nomenclature->code, 0, 3) }}
    </div>

    <div class="info-line">
        <strong>Paragraphe:</strong> {{ $nomenclature->code }}
    </div>

    {{-- Tableau hiérarchique --}}
    <table class="hierarchie-table">
        <tr>
            <th>PROGRAMME:</th>
            <td>{{ $programme->libelle ?? 'GOUVERNANCE ET PILOTAGE STRATÉGIQUE DU SYSTÈME' }}</td>
        </tr>
        <tr>
            <th>OBJECTIF:</th>
            <td>{{ $action->objectif ?? 'Améliorer la coordination des services et assurer la bonne mise en œuvre des programmes au ministère' }}
            </td>
        </tr>
        <tr>
            <th>ACTION:</th>
            <td>{{ $action->libelle ?? 'Gestion budgétaire et financière' }}</td>
        </tr>
        <tr>
            <th>ACTIVITÉ:</th>
            <td>{{ $activite->libelle ?? 'Appuyer les services en consommables médicaux' }}</td>
        </tr>
        <tr>
            <th>TACHE:</th>
            <td>{{ $tache->libelle ?? $nomenclature->libelle }}</td>
        </tr>
    </table>

    {{-- Visa --}}
    <div class="visa-section">
        VISA DE L'ORDONNATEUR.
    </div>
@endsection
