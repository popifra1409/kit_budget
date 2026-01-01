@extends('pdf.layouts.master')

@section('content')
    {{-- Section 1 : Type et État --}}
    <div class="mb-15">
        <table class="simple">
            <tr>
                <td class="label"><strong>Type Autorisation Engagement:</strong></td>
                <td class="valeur">{{ $donnees['type_autorisation'] ?? 'DECISION' }}</td>
                <td class="label" style="width: 20%;"><strong>Etat:</strong></td>
                <td class="valeur" style="width: 15%;">{{ $donnees['etat'] ?? 'Annuel' }}</td>
            </tr>
        </table>
    </div>

    {{-- Section 2 : Montant --}}
    @include('pdf.components.montant', [
        'montant' => $donnees['montant'],
        'montantLettres' => $donnees['montant_lettres'],
    ])

    {{-- Section 3 : Informations générales --}}
    <div class="mb-15">
        <p class="font-bold mb-10">A été contractée au titre de l'exercice:</p>

        <table class="simple">
            <tr>
                <td class="label"><strong>Exercice:</strong></td>
                <td class="valeur font-bold">{{ $donnees['exercice'] ?? date('Y') }}</td>
            </tr>
            <tr>
                <td class="label"><strong>Référence:</strong></td>
                <td class="valeur">{{ $donnees['reference'] ?? '' }}</td>
            </tr>
            <tr>
                <td class="label"><strong>Date de signature</strong></td>
                <td class="valeur">{{ $donnees['date_signature'] ?? '....................................' }}</td>
            </tr>
            <tr>
                <td class="label"><strong>Signataire:</strong></td>
                <td class="valeur font-bold">{{ $donnees['signataire'] ?? '' }}</td>
            </tr>
            <tr>
                <td class="label"><strong>Objet:</strong></td>
                <td class="valeur font-bold">{{ $donnees['objet'] ?? '' }}</td>
            </tr>
            <tr>
                <td class="label"><strong>Bénéficiaire:</strong></td>
                <td class="valeur font-bold">{{ $donnees['beneficiaire'] ?? '' }}</td>
            </tr>
        </table>
    </div>

    {{-- Section 4 : Imputation budgétaire --}}
    @include('pdf.components.imputation', [
        'chapitre' => $donnees['chapitre'] ?? '',
        'article' => $donnees['article'] ?? '',
        'paragraphe' => $donnees['paragraphe'] ?? '',
        'titre' => 'Cette autorisation sera imputée de la manière suivante:',
    ])

    {{-- Section 5 : Programme/Objectif/Action/Activité/Tâche --}}
    @if (isset($donnees['programme']) && $donnees['programme'])
        @include('pdf.components.poaat', [
            'programme' => $donnees['programme'] ?? '',
            'objectif' => $donnees['objectif'] ?? '',
            'action' => $donnees['action'] ?? '',
            'activite' => $donnees['activite'] ?? '',
            'tache' => $donnees['tache'] ?? '',
        ])
    @endif

    {{-- Section 6 : Visa de l'ordonnateur --}}
    <div class="mt-20">
        <p class="text-center font-bold">VISA DE L'ORDONNATEUR.</p>
    </div>
@endsection
