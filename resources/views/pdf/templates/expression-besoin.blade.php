@php
    $disableFooter = true;

    $eb = $donnees['_raw'];
    $lignes = $eb->lignes;  // ✅ Ajouté — dérivé de la relation déjà chargée
    $parametres = \App\Models\ParametresStructure::where('actif', true)->first();

    $dateExpression = $eb->date_expression
        ? \Carbon\Carbon::parse($eb->date_expression)->format('d/m/Y')
        : '—';
@endphp

@extends('pdf.layouts.master')

@section('title', 'Expression de Besoin ' . $eb->numero)

@push('styles')
<style>
    @page {
        size: A4 portrait !important;
        margin: 2cm 1.5cm;
    }

    .sous-titre {
        text-align: center;
        font-weight: bold;
        font-size: 9.5pt;
        line-height: 1.5;
        margin: 4px 0 10px 0;
    }

    .titre-document {
        text-align: center;
        font-weight: bold;
        font-size: 11pt;
        text-decoration: underline;
        margin: 10px 0 4px 0;
    }

    .date-droite {
        text-align: right;
        font-size: 9.5pt;
        margin-bottom: 10px;
    }

    table.lignes th,
    table.lignes td {
        text-align: center;
    }

    table.lignes td.designation {
        text-align: left;
    }

    .signature-eb {
        margin-top: 70px;
        text-align: right;
    }

    .signature-eb .fonction {
        font-weight: bold;
        font-size: 9pt;
    }

    .signature-eb .po {
        margin: 6px 0;
        font-size: 9pt;
    }

    .signature-eb .nom {
        border-top: 1px solid #000;
        padding-top: 4px;
        font-weight: bold;
        font-size: 9pt;
        display: inline-block;
        min-width: 180px;
    }
</style>
@endpush

@section('content')

<div class="sous-titre">
    DIRECTION GENERALE<br>
    DIRECTION MEDICALE<br>
    {{ strtoupper($eb->serviceDemandeur->nom ?? 'SERVICE') }}
</div>

<div class="date-droite">
    Yaoundé le : {{ $dateExpression }}
</div>

<div class="titre-document">
    EXPRESSION DES BESOINS — {{ strtoupper($eb->objet) }}
</div>

<table class="lignes">
    <thead>
        <tr>
            <th style="width: 6%;">N°</th>
            <th style="width: 38%;">Désignation</th>
            <th style="width: 20%;">Conditionnement</th>
            <th style="width: 18%;">Quantité<br>en stock</th>
            <th style="width: 18%;">Quantité<br>commandée</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($lignes as $index => $ligne)
        <tr>
            <td>{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</td>
            <td class="designation">{{ $ligne->article?->designation ?? '—' }}</td>
            <td>{{ $ligne->conditionnement?->libelle ?? ($ligne->article?->uniteMesure?->libelle ?? '—') }}</td>
            <td>{{ str_pad((int) $ligne->quantite_en_stock, 2, '0', STR_PAD_LEFT) }}</td>
            <td>{{ $ligne->quantite_demandee }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<div class="signature-eb">
    <div class="fonction">LE CHEF DE SERVICE</div>
    <div class="po">P.O</div>
    <div class="nom">{{ $eb->responsableService?->name ?? '' }}</div>
</div>

@endsection