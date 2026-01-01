<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>{{ $config->nom }}</title>
    <style>
        @page {
            margin: 15mm 10mm;
            size: A4 portrait;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', 'Arial', sans-serif;
            font-size: 10pt;
            line-height: 1.3;
            color: #000;
        }

        .container {
            width: 100%;
            max-width: 100%;
            padding: 0 5px;
        }

        /* En-tête - Version simplifiée sans float */
        .header {
            margin-bottom: 10px;
            width: 100%;
        }

        .header-row {
            width: 100%;
            border-collapse: collapse;
        }

        .header-row td {
            vertical-align: top;
            padding: 0 5px;
        }

        .header-left {
            width: 45%;
            text-align: left;
        }

        .header-center {
            width: 10%;
            text-align: center;
        }

        .header-right {
            width: 45%;
            text-align: right;
        }

        .institution {
            font-weight: bold;
            font-size: 9pt;
            margin-bottom: 2px;
        }

        .etablissement {
            font-weight: bold;
            font-size: 10pt;
            color: #cc0000;
            margin-bottom: 2px;
        }

        .adresse {
            font-size: 8pt;
            line-height: 1.2;
        }

        /* Titre du document */
        .titre-document {
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            margin: 15px 0;
            padding: 8px;
            border: 2px solid #000;
            text-transform: uppercase;
        }

        /* Styles communs */
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0;
        }

        table.simple td,
        table.simple th {
            padding: 4px;
            vertical-align: top;
            word-wrap: break-word;
        }

        table.bordered,
        table.bordered td,
        table.bordered th {
            border: 1px solid #000;
            padding: 6px;
            word-wrap: break-word;
        }

        table.bordered th {
            background-color: #f0f0f0;
            font-weight: bold;
        }

        .label {
            font-weight: bold;
            width: 35%;
        }

        .valeur {
            width: 65%;
            word-wrap: break-word;
        }

        /* Montant */
        .montant-box {
            background-color: #f9f9f9;
            border: 1px solid #ccc;
            padding: 8px;
            margin: 10px 0;
        }

        .montant-chiffres {
            font-size: 12pt;
            font-weight: bold;
        }

        .montant-lettres {
            font-style: italic;
            margin-top: 4px;
            font-size: 9pt;
        }

        /* Imputation budgétaire */
        .imputation-section {
            margin: 10px 0;
        }

        .imputation-titre {
            font-weight: bold;
            margin-bottom: 8px;
            font-size: 10pt;
        }

        /* Programme/Objectif/Action/Activité/Tâche */
        .poaat-table {
            border: 1px solid #000;
            margin: 10px 0;
        }

        .poaat-table td {
            padding: 6px;
            border: 1px solid #000;
            word-wrap: break-word;
        }

        .poaat-label {
            font-weight: bold;
            width: 22%;
            background-color: #f0f0f0;
        }

        .poaat-valeur {
            width: 78%;
        }

        /* Signatures */
        .signature-zone {
            margin-top: 30px;
            page-break-inside: avoid;
        }

        .signature-row {
            width: 100%;
            border-collapse: collapse;
        }

        .signature-row td {
            text-align: center;
            vertical-align: top;
            padding: 8px;
        }

        .signature-titre {
            font-weight: bold;
            margin-bottom: 50px;
            text-decoration: underline;
            font-size: 9pt;
        }

        .signature-nom {
            font-style: italic;
            font-size: 9pt;
        }

        /* Utilitaires */
        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .text-left {
            text-align: left;
        }

        .font-bold {
            font-weight: bold;
        }

        .font-italic {
            font-style: italic;
        }

        .mb-10 {
            margin-bottom: 10px;
        }

        .mb-15 {
            margin-bottom: 15px;
        }

        .mb-20 {
            margin-bottom: 20px;
        }

        .mt-10 {
            margin-top: 10px;
        }

        .mt-15 {
            margin-top: 15px;
        }

        .mt-20 {
            margin-top: 20px;
        }

        /* Éviter les débordements */
        p,
        div,
        span {
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        /* Styles spécifiques au document */
        @yield('styles')
    </style>
</head>

<body>
    <div class="container">
        {{-- En-tête --}}
        @include('pdf.layouts.header')

        {{-- Titre du document --}}
        <div class="titre-document">
            {{ $config->nom }}
        </div>

        {{-- Contenu principal --}}
        @yield('content')

        {{-- Pied de page (si nécessaire) --}}
        @if (isset($config->pied_page_config['afficher']) && $config->pied_page_config['afficher'])
            @include('pdf.layouts.footer')
        @endif
    </div>
</body>

</html>
