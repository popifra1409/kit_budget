@extends('pdf.layouts.master', ['typeFooter' => 'engagement'])

@section('footer_override')
    @include('pdf.partials.footer-engagement')
@endsection

@php
    $engagement = $donnees['_raw'];
    $nomenclature = $engagement->nomenclaturePrincipale;
    $tache = $nomenclature->tache ?? null;
    $activite = $tache->activite ?? null;
    $action = $activite->action ?? null;
    $programme = $action->programme ?? null;
@endphp

@section('title', 'Certificat d\'Engagement')

@section('montant_lettres')
    {{ \App\Helpers\NombreEnLettres::montantCFA($engagement->montant_engage ?? 0) }}
@endsection

@section('additional_styles')
    <style>
        .doc-title {
            text-align: center;
            font-size: 11pt;
            font-weight: bold;
            margin: 10px auto 20px auto;
            padding: 6px 12px;

            border: 1px solid #000;
            /* encadrement */
            display: inline-block;
            /* encadre seulement le texte */
            text-decoration: none;
            /* on enlève le soulignement */
        }

        .section-title {
            font-weight: bold;
            font-size: 9pt;
            margin: 10px 0 6px 0;
        }

        .info-line {
            margin: 6px 0;
            font-size: 11pt;
            line-height: 2.0;
        }

        .info-line strong {
            font-weight: bold;
        }

        .hierarchie-table {
            width: 100%;
            margin: 10px 0;
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
            margin-top: 20px;
            text-align: right;
            font-weight: bold;
            font-size: 9pt;
        }
    </style>
@endsection

@section('content')
    {{-- Titre --}}
    <div class="doc-title doc-title-wrapper">
        CERTIFICAT D'ENGAGEMENT
    </div>

    {{-- Introduction --}}
    <div class="info-line">
        Une Autorisation d'Engagement d'un montant de:
    </div>

    <div class="info-line">
        <strong>Montant en chiffres:</strong> {{ number_format($engagement->montant_engage, 0, ',', ' ') }} F cfa
    </div>

    <div class="info-line">
        <strong>En lettres:</strong> @yield('montant_lettres')
    </div>

    {{-- Réservation --}}
    <div class="section-title">
        Est réservée pour l'acte Administratif ci-après:
    </div>

    <div class="info-line">
        <strong>Référence:</strong> {{ $engagement->reference_document ?? 'BON DE COMMANDE' }}
    </div>

    <div class="info-line">
        <strong>Date de Signature:</strong>
        {{ $engagement->date_engagement ? \Carbon\Carbon::parse($engagement->date_engagement)->format('d/m/Y') : '.....................' }}
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
        Cette autorisation d'Engagement est imputée de la manière suivante:
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
            <td>{{ $programme->objectifsPrincipaux->libelle ?? ' la coordination des services et assurer la bonne mise en œuvre des programmes au ministère' }}
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
@endsection
