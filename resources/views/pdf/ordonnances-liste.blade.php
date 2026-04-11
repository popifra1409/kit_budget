<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des Ordonnances de Paiement</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        @page {
            size: A4 landscape;
            /* Format paysage */
            margin: 10mm;
        }

        body {
            font-family: 'Arial', sans-serif;
            font-size: 8pt;
            /* Réduit pour paysage */
            line-height: 1.2;
            color: #000;
        }

        .container {
            width: 100%;
            padding: 5mm;
        }

        /* En-tête */
        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #000;
            padding-bottom: 8px;
        }

        .header h1 {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 3px;
        }

        .header .subtitle {
            font-size: 9pt;
            color: #666;
            margin-bottom: 2px;
        }

        .header .period {
            font-size: 9pt;
            font-weight: bold;
            color: #000;
        }

        /* Informations de filtre */
        .filter-info {
            background-color: #f5f5f5;
            padding: 6px;
            margin-bottom: 10px;
            border-left: 3px solid #4472C4;
            font-size: 7pt;
        }

        .filter-info p {
            margin: 2px 0;
        }

        /* Statistiques */
        .stats {
            display: table;
            width: 100%;
            margin-bottom: 10px;
            border: 1px solid #ddd;
        }

        .stat-item {
            display: table-cell;
            padding: 5px;
            text-align: center;
            border-right: 1px solid #ddd;
            width: 25%;
        }

        .stat-item:last-child {
            border-right: none;
        }

        .stat-label {
            font-size: 7pt;
            color: #666;
            display: block;
            margin-bottom: 2px;
        }

        .stat-value {
            font-size: 9pt;
            font-weight: bold;
            color: #000;
        }

        /* Tableau - Optimisé pour paysage */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        thead {
            background-color: #4472C4;
            color: #fff;
        }

        th {
            border: 1px solid #000;
            padding: 4px 3px;
            text-align: left;
            font-weight: bold;
            font-size: 7pt;
        }

        td {
            border: 1px solid #ccc;
            padding: 3px 2px;
            font-size: 7pt;
            vertical-align: top;
        }

        td.center {
            text-align: center;
        }

        td.right {
            text-align: right;
        }

        td.money {
            text-align: right;
            font-family: 'Courier New', monospace;
            white-space: nowrap;
        }

        /* Alternance des lignes */
        tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        /* Badge de type */
        .badge {
            padding: 1px 4px;
            border-radius: 2px;
            font-size: 6pt;
            font-weight: bold;
            display: inline-block;
        }

        .badge-standard {
            background-color: #d4edda;
            color: #155724;
        }

        .badge-impot {
            background-color: #fff3cd;
            color: #856404;
        }

        /* Statut */
        .statut {
            font-size: 6pt;
            padding: 1px 3px;
            border-radius: 2px;
        }

        .statut-brouillon {
            background-color: #e7e7e7;
        }

        .statut-emise {
            background-color: #cfe2ff;
        }

        .statut-visee {
            background-color: #d1ecf1;
        }

        .statut-payee {
            background-color: #d4edda;
        }

        .statut-annulee {
            background-color: #f8d7da;
        }

        /* Totaux */
        .totaux {
            margin-top: 10px;
            padding: 8px;
            background-color: #f5f5f5;
            border: 1px solid #ccc;
        }

        .totaux-grid {
            display: table;
            width: 100%;
        }

        .totaux-row {
            display: table-row;
        }

        .totaux-cell {
            display: table-cell;
            padding: 3px 8px;
            font-size: 8pt;
            border-bottom: 1px solid #ddd;
        }

        .totaux-cell:first-child {
            text-align: right;
            font-weight: bold;
            width: 70%;
        }

        .totaux-cell:last-child {
            text-align: right;
            font-family: 'Courier New', monospace;
            width: 30%;
        }

        .totaux-cell.total-general {
            background-color: #4472C4;
            color: #fff;
            font-weight: bold;
            font-size: 9pt;
            border-bottom: none;
        }

        /* Pied de page */
        .footer {
            margin-top: 15px;
            padding-top: 8px;
            border-top: 1px solid #ccc;
            text-align: center;
            font-size: 6pt;
            color: #666;
        }

        /* Largeurs de colonnes optimisées pour paysage */
        .col-num {
            width: 2%;
        }

        .col-numero {
            width: 8%;
        }

        .col-type {
            width: 4%;
        }

        .col-date {
            width: 7%;
        }

        .col-engagement {
            width: 9%;
        }

        .col-objet {
            width: 25%;
        }

        .col-beneficiaire {
            width: 15%;
        }

        .col-montant {
            width: 10%;
        }

        .col-statut {
            width: 6%;
        }

        .col-paiement {
            width: 7%;
        }

        .col-exercice {
            width: 5%;
        }
    </style>
</head>

<body>
    <div class="container">
        {{-- En-tête --}}
        <div class="header">
            <h1>LISTE DES ORDONNANCES DE PAIEMENT</h1>
            <div class="subtitle">{{ config('app.name', 'Système de Gestion Budgétaire') }}</div>
            @if (isset($periode))
                <div class="period">{{ $periode }}</div>
            @endif
        </div>

        {{-- Informations de filtre --}}
        @if (isset($filtres) && count($filtres) > 0)
            <div class="filter-info">
                <strong>Filtres appliqués :</strong>
                @foreach ($filtres as $filtre)
                    {{ $filtre }}{{ !$loop->last ? ' | ' : '' }}
                @endforeach
            </div>
        @endif

        {{-- Statistiques --}}
        <div class="stats">
            <div class="stat-item">
                <span class="stat-label">Nombre d'OP</span>
                <span class="stat-value">{{ $statistiques['nombre_total'] ?? 0 }}</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Montant Brut</span>
                <span class="stat-value">{{ number_format($statistiques['montant_brut'] ?? 0, 0, ',', ' ') }}</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">À Précompter</span>
                <span class="stat-value">{{ number_format($statistiques['montant_impot'] ?? 0, 0, ',', ' ') }}</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Montant Net</span>
                <span class="stat-value">{{ number_format($statistiques['montant_net'] ?? 0, 0, ',', ' ') }}</span>
            </div>
        </div>

        {{-- Tableau des ordonnances - Optimisé pour paysage --}}
        <table>
            <thead>
                <tr>
                    <th class="col-num">N°</th>
                    <th class="col-numero">N° OP</th>
                    <th class="col-type">Type</th>
                    <th class="col-date">Date</th>
                    <th class="col-engagement">Engagement</th>
                    <th class="col-objet">Objet</th>
                    <th class="col-beneficiaire">Bénéficiaire</th>
                    <th class="col-montant">Montant Net</th>
                    <th class="col-statut">Statut</th>
                    <th class="col-paiement">Paiement</th>
                    <th class="col-exercice">Exo</th>
                </tr>
            </thead>
            <tbody>
                @forelse($ordonnances as $index => $op)
                    <tr>
                        <td class="center">{{ $index + 1 }}</td>
                        <td>{{ $op->numero }}</td>
                        <td class="center">
                            <span class="badge badge-{{ $op->type_ordonnance }}">
                                {{ $op->type_ordonnance === 'standard' ? 'STD' : 'IMP' }}
                            </span>
                        </td>
                        <td class="center">
                            {{ $op->date_emission ? \Carbon\Carbon::parse($op->date_emission)->format('d/m/Y') : '' }}
                        </td>
                        <td>{{ $op->engagement?->numero ?? '' }}</td>
                        <td>{{ \Str::limit($op->objet, 60) }}</td>
                        <td>
                            @if ($op->type_ordonnance === 'impot')
                                TRÉSOR PUBLIC
                            @else
                                {{ \Str::limit(
                                    $op->beneficiaire?->raison_sociale ?? ($op->beneficiaire?->nom_complet ?? ($op->beneficiaire?->name ?? 'N/A')),
                                    30,
                                ) }}
                            @endif
                        </td>
                        <td class="money">{{ number_format($op->montant_net, 0, ',', ' ') }}</td>
                        <td class="center">
                            <span class="statut statut-{{ $op->statut }}">
                                {{ strtoupper(substr($op->statut, 0, 3)) }}
                            </span>
                        </td>
                        <td class="center">
                            {{ $op->date_paiement ? \Carbon\Carbon::parse($op->date_paiement)->format('d/m/Y') : '-' }}
                        </td>
                        <td class="center">{{ $op->exercice?->annee ?? '' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="center" style="padding: 15px; color: #999;">
                            Aucune ordonnance de paiement trouvée pour cette période
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{-- Totaux --}}
        @if (count($ordonnances) > 0)
            <div class="totaux">
                <div class="totaux-grid">
                    <div class="totaux-row">
                        <div class="totaux-cell">Nombre d'ordonnances :</div>
                        <div class="totaux-cell">{{ $statistiques['nombre_total'] ?? count($ordonnances) }}</div>
                    </div>
                    <div class="totaux-row">
                        <div class="totaux-cell">Montant Brut total :</div>
                        <div class="totaux-cell">{{ number_format($statistiques['montant_brut'] ?? 0, 0, ',', ' ') }}
                            FCFA</div>
                    </div>
                    <div class="totaux-row">
                        <div class="totaux-cell">Total à Précompter :</div>
                        <div class="totaux-cell">{{ number_format($statistiques['montant_impot'] ?? 0, 0, ',', ' ') }}
                            FCFA</div>
                    </div>
                    <div class="totaux-row">
                        <div class="totaux-cell total-general">MONTANT NET TOTAL :</div>
                        <div class="totaux-cell total-general">
                            {{ number_format($statistiques['montant_net'] ?? 0, 0, ',', ' ') }} FCFA</div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Pied de page --}}
        <div class="footer">
            <p>Document généré le {{ now()->format('d/m/Y à H:i') }}
                @if (isset($utilisateur))
                    - Par : {{ $utilisateur }}
                @endif
                - {{ config('app.name') }}
            </p>
        </div>
    </div>
</body>

</html>
