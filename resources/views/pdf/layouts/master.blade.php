<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Document PDF')</title>
    @stack('styles')

    <style>
        @page {
            size: A4 portrait;
            margin: 0mm 15mm 18mm 15mm;
        }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 9.5pt;
            color: #000;
        }

        /* ================= HEADER ================= */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border: none;
            margin-bottom: 12px;
        }

        .header-table td {
            border: none;
            text-align: center;
            vertical-align: middle;
        }

        .header-left {
            width: 33%;
        }

        .header-center {
            width: 34%;
        }

        .header-right {
            width: 33%;
        }

        .logo {
            display: block;
            max-width: 90px;
            margin: 0 auto 4px auto;
        }

        .structure {
            font-weight: bold;
            font-size: 10pt;
            line-height: 1.2;
            text-align: center;
        }

        .adresse {
            font-size: 8pt;
            text-align: center;
        }

        .republique {
            font-weight: bold;
            font-size: 8.5pt;
            line-height: 1.2;
            text-align: center;
        }

        .commande-box {
            border: 2px solid #000;
            padding: 5px;
            font-weight: bold;
            text-align: center;
            margin-top: 6px;
        }

        /* ================= INFOS ================= */
        .doc-title-wrapper {
            text-align: center;
            margin: 50px 0 20px 0;
        }

        .doc-title {
            display: block;
            width: 100%;
            text-align: center;
            font-size: 11pt;
            font-weight: bold;
            margin: 10px 0 20px 0;
            border: 1px solid #000;
            padding: 6px 0;
        }

        .info-line {
            margin: 6px 0;
            font-size: 9pt;
            line-height: 1.4;
        }

        .info {
            margin: 10px 0;
            font-size: 8.8pt;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 8.5pt;
        }

        .info-table td {
            border: none;
            padding: 4px 6px;
            vertical-align: top;
        }

        .info-table .label {
            width: 35%;
            font-weight: bold;
            white-space: nowrap;
        }

        .info-table .value {
            width: 65%;
            text-align: left;
        }

        /* ================= TABLE ================= */

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            font-size: 8.5pt;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 4px;
        }

        th {
            background: #e6e6e6;
            text-align: center;
            font-weight: bold;
        }

        td.num {
            text-align: center;
            white-space: nowrap;
        }

        td.money {
            text-align: right;
            white-space: nowrap;
        }

        td.designation {
            word-break: break-word;
        }

        /* ================= TOTAUX ================= */
        .totaux {
            width: 45%;
            margin-left: auto;
            margin-top: 10px;
            font-size: 8.8pt;
        }

        .totaux table {
            width: 100%;
        }

        .totaux td {
            border: none;
            padding: 3px;
        }

        .total-final {
            border: 2px solid #000;
            font-weight: bold;
            background: #e6e6e6;
        }

        /* ================= FOOTER ================= */
        .montant-lettres {
            margin-top: 20px;
            text-align: center;
            font-style: italic;
            font-size: 8.5pt;
        }

        .bas-page {
            width: 100%;
            margin-top: 70px;
        }

        .bas-page::after {
            content: "";
            display: table;
            clear: both;
        }

        .mention-gauche {
            float: left;
            width: 45%;
            font-size: 8.5pt;
            text-align: left;
        }

        .signature {
            float: right;
            width: 45%;
            text-align: right;
        }

        .signature-box {
            display: inline-block;
            text-align: center;
        }

        .signature .fonction {
            font-weight: bold;
            margin-bottom: 35px;
            font-size: 8.5pt;
        }

        .signature .nom {
            border-top: 1px solid #000;
            padding-top: 4px;
            font-weight: bold;
            font-size: 8.5pt;
        }

        @yield('additional_styles')
    </style>
</head>

<body>
    @php
        $parametres = \App\Models\ParametresStructure::where('actif', true)->first();
    @endphp

    {{-- Header personnalisable --}}
    @if (isset($typeHeader) && View::exists("pdf.partials.header-{$typeHeader}"))
        @include("pdf.partials.header-{$typeHeader}")
    @else
        @include('pdf.partials.header')
    @endif

    {{-- Contenu principal --}}
    <div class="content">
        @yield('content')
    </div>

    {{-- Footer personnalisable (peut être désactivé avec $disableFooter) --}}
    @if (!isset($disableFooter) || !$disableFooter)
        @if (isset($typeFooter) && View::exists("pdf.partials.footer-{$typeFooter}"))
            @include("pdf.partials.footer-{$typeFooter}")
        @else
            @include('pdf.partials.footer')
        @endif
    @endif
</body>

</html>
