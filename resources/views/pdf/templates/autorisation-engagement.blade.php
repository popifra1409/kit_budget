@extends('pdf.layouts.master', ['typeFooter' => 'engagement'])

@section('footer_override')
    @include('pdf.partials.footer-engagement')
@endsection

@php
    $engagement = $donnees['_raw'];

    $engagement->load(['nomenclaturePrincipale', 'beneficiaire', 'exercice']);

    $nomenclature = $engagement->nomenclaturePrincipale;

    // ✅ Essayer de trouver une tâche liée à cette nomenclature
    $tache = null;
    $activite = null;
    $action = null;
    $programme = null;
    $objectif = null;

    if ($nomenclature) {
        // Priorité : sous-tâche, sinon première tâche disponible
        $tache = $nomenclature->tache ?? $nomenclature->taches()->first();

        if ($tache) {
            $tache->load('activite.action.programme.objectifsPrincipaux');

            $activite = $tache->activite;
            $action = $activite?->action;
            $programme = $action?->programme;
            $objectif = $programme?->objectifsPrincipaux;
        } else {
            // ✅ FALLBACK : Logger l'absence de tâche
        \Log::warning('Aucune tâche liée à la nomenclature (Autorisation)', [
            'nomenclature_id' => $nomenclature->id,
            'nomenclature_code' => $nomenclature->code,
            'engagement_id' => $engagement->id,
        ]);
    }
}

// ✅ DEBUG (à retirer après)
\Log::info('Hiérarchie Autorisation', [
    'nomenclature' => $nomenclature?->code,
    'tache' => $tache?->libelle,
    'activite' => $activite?->libelle,
    'action' => $action?->libelle,
    'programme' => $programme?->libelle,
    'objectif' => $objectif?->libelle,
    ]);
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

        .warning-box {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            padding: 8px;
            margin: 10px 0;
            font-size: 8pt;
            color: #856404;
        }
    </style>
@endsection

@section('content')
    {{-- Titre --}}
    <div class="doc-title">
        AUTORISATION D'ENGAGEMENT
    </div>

    {{-- ✅ Avertissement si données incomplètes --}}
    @if (!$tache)
        <div class="warning-box">
            ⚠️ Attention: La hiérarchie budgétaire complète n'est pas disponible pour cette nomenclature.
            Veuillez compléter les données dans le module de gestion budgétaire.
        </div>
    @endif

    {{-- Type et État --}}
    <table style="width: 100%; margin: 10px 0; border-collapse: collapse;">
        <tr>
            <td style="width: 60%; border: none; padding: 0; font-size: 9pt;">
                <strong>Type Autorisation Engagement:</strong> {{ strtoupper($engagement->type_engagement ?? 'Standard') }}
            </td>
            <td style="width: 40%; border: none; padding: 0; text-align: right; font-size: 9pt;">
                <strong>État: Annuel</strong>
            </td>
        </tr>
    </table>

    {{-- Montant --}}
    <div class="info-line">
        <strong>Montant en chiffres:</strong> {{ number_format($engagement->montant_engage ?? 0, 0, ',', ' ') }} F cfa
    </div>

    <div class="info-line">
        <strong>(En lettres):</strong> @yield('montant_lettres')
    </div>

    {{-- Exercice --}}
    <div class="info-line">
        <strong>A été contractée au titre de l'exercice:</strong>
        {{ $engagement->exercice?->annee ?? now()->year }}
    </div>

    {{-- Référence et signature --}}
    <div class="section-title">Référence</div>

    <div class="info-line">
        <strong>Date d'émission:</strong>
        {{ $engagement->date_engagement ? \Carbon\Carbon::parse($engagement->date_engagement)->format('d/m/Y') : '................................' }}
    </div>

    <div class="info-line">
        <strong>Signataire:</strong> {{ $parametres->nom_ordonnateur ?? 'Pr. ESSOMBA NOEL EMMANUEL' }}
    </div>

    <div class="info-line">
        <strong>Objet:</strong> {{ $engagement->objet ?? 'N/A' }}
    </div>

    <div class="info-line">
        <strong>Bénéficiaire:</strong>
        {{ $engagement->beneficiaire->raison_sociale ?? ($engagement->beneficiaire->name ?? 'N/A') }}
    </div>

    {{-- Imputation --}}
    <div class="section-title">
        Cette autorisation sera imputée de la manière suivante:
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
    @else
        <div class="info-line">
            <strong>Nomenclature:</strong> Non définie
        </div>
    @endif

    {{-- Tableau hiérarchique --}}
    <table class="hierarchie-table">
        <tr>
            <th>PROGRAMME:</th>
            <td>{{ $programme?->libelle ?? 'GOUVERNANCE ET PILOTAGE STRATÉGIQUE DU SYSTÈME' }}</td>
        </tr>
        <tr>
            <th>OBJECTIF:</th>
            <td>{{ $objectif?->libelle ?? 'Améliorer la coordination des services et assurer la bonne mise en œuvre des programmes au ministère' }}
            </td>
        </tr>
        <tr>
            <th>ACTION:</th>
            <td>{{ $action?->libelle ?? 'Gestion budgétaire et financière' }}</td>
        </tr>
        <tr>
            <th>ACTIVITÉ:</th>
            <td>{{ $activite?->libelle ?? 'Appuyer les services en consommables médicaux' }}</td>
        </tr>
        <tr>
            <th>TACHE:</th>
            <td>{{ $tache?->libelle ?? ($nomenclature?->libelle ?? 'N/A') }}</td>
        </tr>
    </table>
@endsection
