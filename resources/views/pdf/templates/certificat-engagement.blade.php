@extends('pdf.layouts.master')

@section('content')
    {{-- Section 1 : Montant de l'autorisation --}}
    <div class="mb-20">
        <p class="font-bold mb-10">Une Autorisation d'Engagement d'un montant de:</p>

        @include('pdf.components.montant', [
            'montant' => $donnees['montant'],
            'montantLettres' => $donnees['montant_lettres'],
        ])
    </div>

    {{-- Section 2 : Acte administratif --}}
    <div class="mb-15">
        <p class="font-bold mb-10">Est réservée pour l'acte Administratif ci-après:</p>

        <table class="simple">
            <tr>
                <td class="label"><strong>Référence:</strong></td>
                <td class="valeur">{{ $donnees['reference'] ?? '' }}</td>
            </tr>
            <tr>
                <td class="label"><strong>Date de Signature</strong></td>
                <td class="valeur">{{ $donnees['date_signature'] ?? '....................................' }}</td>
            </tr>
            <tr>
                <td class="label"><strong>Signataire:</strong></td>
                <td class="valeur">{{ $donnees['signataire'] ?? '' }}</td>
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

    {{-- Section 3 : Imputation budgétaire --}}
    @include('pdf.components.imputation', [
        'chapitre' => $donnees['chapitre'] ?? '',
        'article' => $donnees['article'] ?? '',
        'paragraphe' => $donnees['paragraphe'] ?? '',
        'typeDocument' => 'autorisation d\'Engagement',
    ])

    {{-- Section 4 : Programme/Objectif/Action/Activité/Tâche --}}
    @if (isset($donnees['programme']) && $donnees['programme'])
        @include('pdf.components.poaat', [
            'programme' => $donnees['programme'] ?? '',
            'objectif' => $donnees['objectif'] ?? '',
            'action' => $donnees['action'] ?? '',
            'activite' => $donnees['activite'] ?? '',
            'tache' => $donnees['tache'] ?? '',
        ])
    @endif

    {{-- Section 5 : Visa de l'ordonnateur --}}
    <div class="mt-20">
        <p class="text-center font-bold">VISA DE L'ORDONNATEUR.</p>
    </div>
@endsection
