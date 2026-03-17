<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>

    <style>
        /* ✅ MODIFICATION : Rendre l'orientation paramétrable */
        @page {
            @if(isset($orientation) && $orientation ==='landscape') size: A4 landscape;
            margin: 10mm 8mm;
            @else size: A4 portrait;
            margin: 2cm 1.5cm;
            @endif
        }

        /* Vos autres styles... */
    </style>

    @stack('styles')
</head>

<body>
    @yield('content')
</body>

</html>