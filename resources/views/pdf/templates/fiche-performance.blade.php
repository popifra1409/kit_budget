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
        \Log::warning('Aucune tâche liée à la nomenclature (Fiche Performance)', [
            'nomenclature_id' => $nomenclature->id,
            'nomenclature_code' => $nomenclature->code,
            'engagement_id' => $engagement->id,
        ]);
    }
}

// ✅ DEBUG (à retirer après)
\Log::info('Hiérarchie Fiche Performance', [
    'nomenclature' => $nomenclature?->code,
    'tache' => $tache?->libelle,
    'activite' => $activite?->libelle,
    'action' => $action?->libelle,
    'programme' => $programme?->libelle,
    'objectif' => $objectif?->libelle,
    ]);
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
        FICHE DE PERFORMANCE
    </div>

    {{-- ✅ Avertissement si données incomplètes --}}
    @if (!$tache)
        <div class="warning-box">
            ⚠️ Attention: La hiérarchie budgétaire complète n'est pas disponible pour cette nomenclature.
            Veuillez compléter les données dans le module de gestion budgétaire.
        </div>
    @endif

    {{-- Exercice --}}
    <div class="info-line">
        <strong>EXERCICE:</strong> {{ $engagement->exercice?->annee ?? now()->year }}
    </div>

    {{-- Programme --}}
    <div class="info-line">
        <strong>PROGRAMME:</strong> {{ $programme?->libelle ?? 'Non défini' }}
    </div>

    {{-- Objectif --}}
    <div class="info-line">
        <strong>OBJECTIF:</strong>
        {{ $objectif?->libelle ?? 'Non défini' }}
    </div>

    {{-- Action --}}
    <div class="info-line">
        <strong>ACTION:</strong> {{ $action?->libelle ?? 'Non défini' }}
    </div>

    {{-- Activité --}}
    <div class="info-line">
        <strong>ACTIVITÉ:</strong> {{ $activite?->libelle ?? 'Non défini' }}
    </div>

    {{-- Tâche --}}
    <div class="info-line">
        <strong>TACHE:</strong> {{ $tache?->libelle ?? ($nomenclature?->libelle ?? 'Non défini') }}
    </div>

    {{-- Indicateur de résultats --}}
    <div class="info-line">
        <strong>INDICATEUR DE RESULTATS:</strong>
        {{ $tache?->indicateur_resultat ?? ($activite?->indicateur_resultat ?? 'Non défini') }}
    </div>

    {{-- Valeur de référence --}}
    <div class="info-line">
        <strong>VALEUR DE REFERENCE:</strong>
        {{ $tache?->valeur_reference ?? ($activite?->valeur_reference ?? 'Non défini') }}
    </div>

    {{-- Niveau actuel d'avancement --}}
    <div class="info-line">
        <strong>NIVEAU ACTUEL D'AVANCEMENT:</strong>
        {{ $tache?->niveau_avancement ?? ($activite?->niveau_avancement ?? '') }}
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
            <td>{{ $nomenclature?->code ?? 'Non défini' }}</td>
        </tr>
        <tr>
            <td class="label">N°BON:</td>
            <td>{{ $engagement->reference_document ?? '' }}</td>
        </tr>
        <tr>
            <td class="label">Montant:</td>
            <td>{{ number_format($engagement->montant_engage ?? 0, 0, ',', ' ') }} F cfa</td>
        </tr>
        <tr>
            <td class="label">Date:</td>
            <td>{{ $engagement->date_engagement ? \Carbon\Carbon::parse($engagement->date_engagement)->format('d/m/Y') : '' }}
            </td>
        </tr>
        <tr>
            <td class="label">Objet de la dépense:</td>
            <td>{{ $engagement->objet ?? '' }}</td>
        </tr>
    </table>

    {{-- ✅ Section optionnelle : Détails de la tâche (si disponible) --}}
    @if ($tache)
        <div class="section-title">
            DÉTAILS DE LA TÂCHE
        </div>

        <table class="moyens-table">
            <tr>
                <td class="label">Code tâche:</td>
                <td>{{ $tache->code ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Description:</td>
                <td>{{ $tache->description ?? $tache->libelle }}</td>
            </tr>
            @if ($tache->date_debut)
                <tr>
                    <td class="label">Date début:</td>
                    <td>{{ \Carbon\Carbon::parse($tache->date_debut)->format('d/m/Y') }}</td>
                </tr>
            @endif
            @if ($tache->date_fin)
                <tr>
                    <td class="label">Date fin prévue:</td>
                    <td>{{ \Carbon\Carbon::parse($tache->date_fin)->format('d/m/Y') }}</td>
                </tr>
            @endif
            @if ($tache->responsable)
                <tr>
                    <td class="label">Responsable:</td>
                    <td>{{ $tache->responsable }}</td>
                </tr>
            @endif
        </table>
    @endif

    {{-- ✅ Section optionnelle : Suivi de performance (si données disponibles) --}}
    @if ($activite)
        <div class="section-title">
            SUIVI DE PERFORMANCE
        </div>

        <table class="moyens-table">
            <tr>
                <td class="label">Objectif quantifié:</td>
                <td>{{ $activite->objectif_quantifie ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Résultat attendu:</td>
                <td>{{ $activite->resultat_attendu ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Résultat obtenu:</td>
                <td>{{ $activite->resultat_obtenu ?? 'En cours' }}</td>
            </tr>
            <tr>
                <td class="label">Taux de réalisation:</td>
                <td>
                    @if ($activite->objectif_quantifie && $activite->resultat_obtenu)
                        {{ round(($activite->resultat_obtenu / $activite->objectif_quantifie) * 100, 2) }}%
                    @else
                        N/A
                    @endif
                </td>
            </tr>
        </table>
    @endif
@endsection
