<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 8pt;
            margin: 0;
        }

        h2 {
            text-align: center;
            font-size: 11pt;
            margin-bottom: 4px;
        }

        .meta {
            text-align: center;
            font-size: 8pt;
            color: #555;
            margin-bottom: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            font-size: 7.5pt;
        }

        thead th {
            background: #1F4E79;
            color: #fff;
            padding: 6px 4px;
            text-align: center;
            border: 1px solid #ccc;
        }

        tbody td {
            border: 1px solid #ccc;
            padding: 4px 5px;
            vertical-align: middle;
        }

        tbody tr:nth-child(even) {
            background: #f5f8ff;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .font-bold {
            font-weight: bold;
        }

        tfoot td {
            background: #D9E1F2;
            font-weight: bold;
            border: 1px solid #999;
            padding: 5px 4px;
        }

        .footer {
            margin-top: 16px;
            font-size: 7pt;
            color: #777;
            border-top: 1px solid #ccc;
            padding-top: 4px;
            display: flex;
            justify-content: space-between;
        }
    </style>
</head>

<body>

    <h2>{{ $titre }}</h2>
    <div class="meta">
        @if($periode) Période : <strong>{{ $periode }}</strong> &nbsp;|&nbsp; @endif
        Généré le {{ $dateGeneration }} par {{ $utilisateur }}
        &nbsp;|&nbsp; {{ $ordonnances->count() }} ordonnance(s)
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:14%;">N° OP</th>
                <th style="width:14%;">N° BE</th>
                <th style="width:25%;">Nomenclature</th>
                <th style="width:22%;">Objet</th>
                <th style="width:12%;">Montant engagé</th>
                <th style="width:8%;">Dt. engagement</th>
                <th style="width:8%;">Dt. paiement</th>
            </tr>
        </thead>
        <tbody>
            @forelse($ordonnances as $op)
            @php
            $eng = $op->engagement;
            $nomenclature = $eng?->nomenclaturePrincipale;
            $montant = (float) ($eng?->montant_engage ?? 0);
            @endphp
            <tr>
                <td class="font-bold">{{ $op->numero }}</td>
                <td>{{ $eng?->numero ?? '—' }}</td>
                <td style="font-size:7pt;">
                    @if($nomenclature)
                    <strong>{{ $nomenclature->code }}</strong>
                    — {{ \Str::limit($nomenclature->libelle, 35) }}
                    @else —
                    @endif
                </td>
                <td style="font-size:7pt;">
                    {{ \Str::limit($op->objet ?? $eng?->objet ?? '—', 40) }}
                </td>
                <td class="text-right font-bold">
                    {{ number_format($montant, 0, ',', ' ') }}
                </td>
                <td class="text-center">
                    {{ $eng?->date_engagement
                        ? \Carbon\Carbon::parse($eng->date_engagement)->format('d/m/Y')
                        : '—' }}
                </td>
                <td class="text-center">
                    {{ $op->date_paiement
                        ? \Carbon\Carbon::parse($op->date_paiement)->format('d/m/Y')
                        : '—' }}
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center" style="color:#999; padding:16px;">
                    Aucune ordonnance pour les critères sélectionnés
                </td>
            </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="text-right">TOTAL :</td>
                <td class="text-right">
                    {{ number_format($total, 0, ',', ' ') }} FCFA
                </td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>

</body>

</html>