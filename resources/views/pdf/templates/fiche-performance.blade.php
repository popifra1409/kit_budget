@extends('pdf.layouts.master', ['typeFooter' => 'engagement'])

@php
    $engagement = $donnees['_raw'];
    $nomenclature = $engagement->nomenclaturePrincipale;
    $tache = $nomenclature->tache ?? null;
    $activite = $tache->activite ?? null;
    $action = $activite->action ?? null;
    $programme = $action->programme ?? null;
@endphp

@section('title', 'Fiche de Performance')

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
            margin: 15px 0 8px 0;
            text-transform: uppercase;
        }

        .moyens-table {
            width: 100%;
            margin: 10px 0;
            border-collapse: collapse;
            font-size: 8.5pt;
        }

        .moyens-table td {
            border: 1px solid #000;
            padding: 6px;
            vertical-align: top;
        }

        .moyens-table td.label {
            width: 35%;
            font-weight: bold;
            background-color: #f0f0f0;
        }
    </style>
@endsection

@section('content')
    {{-- Titre --}}
    <div class="doc-title">
        FICHE DE PERFORMANCE
    </div>

    {{-- Exercice --}}
    <div class="info-line">
        <strong>EXERCICE:</strong> {{ $engagement->exercice ?? now()->year }}
    </div>

    {{-- Programme --}}
    <div class="info-line">
        <strong>PROGRAMME:</strong> {{ $programme->libelle ?? 'GOUVERNANCE ET PILOTAGE STRATÉGIQUE DU SYSTÈME' }}
    </div>

    {{-- Objectif --}}
    <div class="info-line">
        <strong>OBJECTIF:</strong>
        {{ $action->objectif ?? 'Améliorer la coordination des services et assurer la bonne mise en œuvre des programmes au ministère' }}
    </div>

    {{-- Action --}}
    <div class="info-line">
        <strong>ACTION:</strong> {{ $action->libelle ?? 'Gestion budgétaire et financière' }}
    </div>

    {{-- Activité --}}
    <div class="info-line">
        <strong>ACTIVITÉ:</strong> {{ $activite->libelle ?? 'Appuyer les services en consommables médicaux' }}
    </div>

    {{-- Tâche --}}
    <div class="info-line">
        <strong>TACHE:</strong> {{ $tache->libelle ?? $nomenclature->libelle }}
    </div>

    {{-- Indicateur de résultats --}}
    <div class="info-line">
        <strong>INDICATEUR DE RESULTATS:</strong> Disponibilité des consommables dans les services
    </div>

    {{-- Valeur de référence --}}
    <div class="info-line">
        <strong>VALEUR DE REFERENCE:</strong> {{ $tache->valeur_reference ?? 'Fourniture d\'Anatomopathologie' }}
    </div>

    {{-- Niveau actuel d'avancement --}}
    <div class="info-line">
        <strong>NIVEAU ACTUEL D'AVANCEMENT:</strong>
    </div>

    {{-- Section : Mise à disposition des moyens --}}
    <div class="section-title">
        MISE A DISPOSITION DES MOYENS
    </div>

    <table class="moyens-table">
        <tr>
            <td class="label">Autorisation de dépenses</td>
            <td></td>
        </tr>
        <tr>
            <td class="label">Imputation:</td>
            <td>{{ $nomenclature->code }}</td>
        </tr>
        <tr>
            <td class="label">N°BON:</td>
            <td>{{ $engagement->reference_document ?? '' }}</td>
        </tr>
        <tr>
            <td class="label">Montant:</td>
            <td>{{ number_format($engagement->montant_engage, 0, ',', ' ') }} F cfa</td>
        </tr>
        <tr>
            <td class="label">Date:</td>
            <td>{{ $engagement->date_engagement ? \Carbon\Carbon::parse($engagement->date_engagement)->format('d/m/Y') : '' }}
            </td>
        </tr>
        <tr>
            <td class="label">Objet de la dépense:</td>
            <td>{{ $engagement->objet }}</td>
        </tr>
    </table>
@endsection
