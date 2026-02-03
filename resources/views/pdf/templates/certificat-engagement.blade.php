@extends('pdf.layouts.master', ['typeFooter' => 'engagement'])

@section('footer_override')
    @include('pdf.partials.footer-engagement')
@endsection

@php
    $engagement = $donnees['_raw'];

    $engagement->load(['nomenclaturePrincipale', 'beneficiaire', 'exercice']);

    $nomenclature = $engagement->nomenclaturePrincipale;

    $tache = null;
    $activite = null;
    $action = null;
    $programme = null;
    $objectif = null;

    if ($nomenclature) {
        $tache = $nomenclature->tache ?? $nomenclature->taches()->first();

        if ($tache) {
            $tache->load('activite.action.programme');

            $activite = $tache->activite;
            $action = $activite?->action;
            $programme = $action?->programme;

            if ($programme) {
                // ✅ Essayer différentes façons de récupérer l'objectif
            try {
                // Tentative 1 : Relation HasOne au singulier
                if (method_exists($programme, 'objectifPrincipal')) {
                    $objectif = $programme->objectifPrincipal;
                }
                // Tentative 2 : Relation HasMany au pluriel, prendre le premier
                elseif (method_exists($programme, 'objectifsPrincipaux')) {
                    $objectifs = $programme->objectifsPrincipaux;
                    // Si c'est une collection
                        if ($objectifs instanceof \Illuminate\Support\Collection) {
                            $objectif = $objectifs->first();
                        }
                        // Si c'est déjà un modèle unique
                    else {
                        $objectif = $objectifs;
                    }
                }
            } catch (\Exception $e) {
                \Log::warning('Erreur récupération objectif', [
                    'programme_id' => $programme->id,
                    'error' => $e->getMessage(),
                    ]);
                    $objectif = null;
                }
            }
        }
    }
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
            display: inline-block;
            text-decoration: none;
        }

        .section-title {
            font-weight: bold;
            font-size: 9pt;
            margin: 10px 0 6px 0;
        }

        .info-line {
            margin: 6px 0;
            font-size: 11pt;
            line-height: 1.3;
        }

        .info-line strong {
            font-weight: bold;
        }

        .hierarchie-table {
            width: 100%;
            margin: 10px 0;
            border-collapse: collapse;
            font-size: 9.5pt;
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
        <strong>Date d'émission:</strong>
        {{ $engagement->date_engagement ? \Carbon\Carbon::parse($engagement->date_engagement)->format('d/m/Y') : '.....................' }}
    </div>

    <div class="info-line">
        <strong>Signataire:</strong> {{ $parametres->nom_ordonnateur ?? 'N/A' }}
    </div>

    <div class="info-line">
        <strong>Objet:</strong> {{ $engagement->objet }}
    </div>

    <div class="info-line">
        <strong>Bénéficiaire:</strong>
        {{ $engagement->beneficiaire->raison_sociale ?? ($engagement->beneficiaire->name ?? 'N/A') }}
    </div>

    {{-- Imputation --}}
    <div class="section-title">
        Cette autorisation d'Engagement est imputée de la manière suivante:
    </div>

    @if ($nomenclature)
        <div class="info-line">
            <strong>Chapitre:</strong> {{ substr($nomenclature->code, 0, 2) }}
        </div>

        <div class="info-line">
            <strong>Article:</strong> {{ substr($nomenclature->code, 0, 3) }}
        </div>

        <div class="info-line">
            <strong>Paragraphe:</strong> {{ $nomenclature->code }} - {{ $nomenclature->libelle }}
        </div>
    @endif

    {{-- Tableau hiérarchique --}}
    <table class="hierarchie-table">
        <tr>
            <th>PROGRAMME:</th>
            <td>{{ $programme?->libelle ?? 'N/A' }}</td>
        </tr>
        <tr>
            <th>OBJECTIF:</th>
            <td>{{ $objectif?->libelle ?? 'N/A' }}
            </td>
        </tr>
        <tr>
            <th>ACTION:</th>
            <td>{{ $action?->libelle ?? 'N/A' }}</td>
        </tr>
        <tr>
            <th>ACTIVITÉ:</th>
            <td>{{ $activite?->libelle ?? 'N/A' }}</td>
        </tr>
        <tr>
            <th>TACHE:</th>
            <td>{{ $tache?->libelle ?? ($nomenclature?->libelle ?? 'N/A') }}</td>
        </tr>
    </table>
@endsection
