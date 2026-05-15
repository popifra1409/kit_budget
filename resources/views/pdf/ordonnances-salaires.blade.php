{{-- resources/views/pdf/ordonnances-salaires.blade.php --}}
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
            font-size: 7.5pt;
            color: #555;
            margin-bottom: 10px;
        }

        /* ── En-tête groupe ── */
        .groupe-header {
            background: #D6E4F0;
            color: #1F4E79;
            font-weight: bold;
            font-size: 8.5pt;
            padding: 5px 6px;
            border-left: 3px solid #1F4E79;
            margin-top: 10px;
            margin-bottom: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5pt;
            margin-bottom: 0;
        }

        thead th {
            background: #1F4E79;
            color: #fff;
            padding: 5px 4px;
            text-align: center;
            border: 1px solid #ccc;
        }

        tbody td {
            border: 1px solid #ddd;
            padding: 3px 5px;
            vertical-align: middle;
        }

        tbody tr:nth-child(even) {
            background: #f7faff;
        }

        /* ── Sous-total groupe ── */
        .sous-total td {
            background: #EBF5FB;
            font-weight: bold;
            font-style: italic;
            border-top: 2px solid #1F4E79;
            padding: 4px 5px;
        }

        /* ── Grand total ── */
        .grand-total td {
            background: #1F4E79;
            color: #fff;
            font-weight: bold;
            font-size: 9pt;
            padding: 6px 5px;
            border: 1px solid #1F4E79;
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
    </style>
</head>

<body>

    <h2>{{ $titre }}</h2>
    <div class="meta">
        @if($periode) Période : <strong>{{ $periode }}</strong> &nbsp;|&nbsp; @endif
        Généré le {{ $dateGeneration }} par {{ $utilisateur }}
    </div>

    {{-- ── En-tête des colonnes (une seule fois) ── --}}
    <table>
        <thead>
            <tr>
                <th style="width:13%;">N° OP</th>
                <th style="width:13%;">N° BE</th>
                <th style="width:32%;">Objet</th>
                <th style="width:14%;">Montant engagé</th>
                <th style="width:10%;">Dt. engagement</th>
                <th style="width:10%;">Dt. paiement</th>
                <th style="width:8%;">Statut</th>
            </tr>
        </thead>

        @php $grandTotal = 0; @endphp

        @forelse($groupes as $groupe)
        @php
        $nomenclature = $groupe['nomenclature'];
        $label = $nomenclature
        ? "{$nomenclature->code} — {$nomenclature->libelle}"
        : 'Sans nomenclature';
        $sousTotal = $groupe['total'];
        $grandTotal += $sousTotal;
        @endphp

        {{-- ── En-tête du groupe ── --}}
        <tbody>
            <tr>
                <td colspan="7" style="
                    background:#D6E4F0;
                    color:#1F4E79;
                    font-weight:bold;
                    font-size:8.5pt;
                    padding:5px 6px;
                    border-left:3px solid #1F4E79;
                ">
                    📁 {{ $label }}
                </td>
            </tr>

            {{-- ── Lignes OP du groupe ── --}}
            @foreach($groupe['ordonnances'] as $op)
            @php
            $eng = $op->engagement;
            $montant = (float) ($eng?->montant_engage ?? 0);
            @endphp
            <tr>
                <td class="font-bold">{{ $op->numero }}</td>
                <td>{{ $eng?->numero ?? '—' }}</td>
                <td style="font-size:7pt;">
                    {{ \Str::limit($op->objet ?? $eng?->objet ?? '—', 55) }}
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
                <td class="text-center">{{ ucfirst($op->statut ?? '—') }}</td>
            </tr>
            @endforeach

            {{-- ── Sous-total du groupe ── --}}
            <tr class="sous-total">
                <td colspan="3" class="text-right">
                    Sous-total — {{ $label }} :
                </td>
                <td class="text-right">
                    {{ number_format($sousTotal, 0, ',', ' ') }} FCFA
                </td>
                <td colspan="3"></td>
            </tr>

            {{-- Séparateur ── --}}
            <tr>
                <td colspan="7" style="padding:3px; border:none;"></td>
            </tr>

        </tbody>
        @empty
        <tbody>
            <tr>
                <td colspan="7" class="text-center"
                    style="color:#999; padding:20px;">
                    Aucune ordonnance pour les critères sélectionnés
                </td>
            </tr>
        </tbody>
        @endforelse

        {{-- ── GRAND TOTAL ── --}}
        <tfoot>
            <tr class="grand-total">
                <td colspan="3" class="text-right">TOTAL GÉNÉRAL :</td>
                <td class="text-right">
                    {{ number_format($grandTotal, 0, ',', ' ') }} FCFA
                </td>
                <td colspan="3"></td>
            </tr>
        </tfoot>
    </table>

</body>

</html>