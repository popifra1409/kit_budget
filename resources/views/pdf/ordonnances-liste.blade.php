<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>État des Ordonnances de Paiement</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        @page {
            size: A4 landscape;
            margin: 8mm 15mm;  /* ✅ marges gauche/droite 15mm pour impression */
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 6.5pt;
            line-height: 1.15;
            color: #000;
        }

        .container { width: 100%; }

        /* ── En-tête ─────────────────────────── */
        .header {
            text-align: center;
            margin-bottom: 5px;
            border-bottom: 2px solid #1e3a5f;
            padding-bottom: 4px;
        }
        .header .structure {
            font-size: 8pt;
            font-weight: bold;
            color: #1e3a5f;
            text-transform: uppercase;
            letter-spacing: .05em;
        }
        .header h1 {
            font-size: 10pt;
            font-weight: bold;
            color: #000;
            margin: 2px 0;
        }
        .header .period {
            font-size: 7.5pt;
            font-weight: bold;
            color: #1e3a5f;
        }

        /* ── Filtres ─────────────────────────── */
        .filter-info {
            background: #eff6ff;
            padding: 2px 5px;
            margin-bottom: 4px;
            border-left: 3px solid #2563eb;
            font-size: 6pt;
            color: #1e3a5f;
        }

        /* ── Stats ───────────────────────────── */
        .stats {
            display: table;
            width: 100%;
            margin-bottom: 5px;
            border: 1px solid #bfdbfe;
            background: #eff6ff;
        }
        .stat-item {
            display: table-cell;
            padding: 3px 5px;
            text-align: center;
            border-right: 1px solid #bfdbfe;
        }
        .stat-item:last-child { border-right: none; }
        .stat-label { font-size: 5.5pt; color: #64748b; display: block; }
        .stat-value { font-size: 8pt; font-weight: bold; color: #1e3a5f; }

        /* ── Tableau ─────────────────────────── */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
            table-layout: fixed;
        }

        thead { background: #1e3a5f; color: #fff; }

        th {
            border: 1px solid #1e3a5f;
            padding: 3px 2px;
            text-align: center;
            font-size: 6pt;
            font-weight: bold;
            word-wrap: break-word;
        }

        td {
            border: 1px solid #d1d5db;
            padding: 2px 2px;
            font-size: 6pt;
            vertical-align: top;
            word-wrap: break-word;
            overflow: hidden;
        }

        td.center { text-align: center; }
        td.money {
            text-align: right;
            font-family: 'Courier New', monospace;
            font-size: 6pt;
            white-space: nowrap;
        }

        tbody tr:nth-child(even) { background: #f0f9ff; }

        /* ── Largeurs — 11 colonnes, 100% sur 287mm ─ */
        .col-numero      { width: 9%;  }
        .col-date        { width: 7%;  }
        .col-engagement  { width: 9%;  }
        .col-objet       { width: 20%; }
        .col-beneficiaire{ width: 14%; }
        .col-brut        { width: 10%; }
        .col-precompte   { width: 9%;  }
        .col-net         { width: 10%; }
        .col-mode        { width: 7%;  }
        .col-paiement    { width: 8%;  }
        .col-ref         { width: 8%;  }
        .col-exercice    { width: 4%;  }
        /* TOTAL = 115% → ajustement : 9+7+9+20+14+10+9+10+7+8+8+4 = 115
           réduit : objet 20, benef 14 au lieu de 23,16 → 115-4 = 111
           on supprime col-num(3%) → 111-3 = 108 → ok avec les arrondis
        */

        /* ── Totaux ──────────────────────────── */
        .totaux {
            margin-top: 4px;
            border: 1px solid #bfdbfe;
            background: #eff6ff;
        }
        .totaux-grid { display: table; width: 60%; margin-left: auto; }
        .totaux-row  { display: table-row; }
        .totaux-cell {
            display: table-cell;
            padding: 2px 8px;
            font-size: 6.5pt;
            border-bottom: 1px solid #bfdbfe;
        }
        .totaux-cell:first-child { text-align: right; font-weight: bold; width: 65%; }
        .totaux-cell:last-child  { text-align: right; font-family: 'Courier New', monospace; }
        .totaux-cell.total-general {
            background: #1e3a5f;
            color: #fff;
            font-weight: bold;
            font-size: 7.5pt;
            border: none;
        }

        /* ── Pied ────────────────────────────── */
        .footer {
            margin-top: 5px;
            padding-top: 3px;
            border-top: 1px solid #ccc;
            text-align: center;
            font-size: 5.5pt;
            color: #666;
        }
    </style>
</head>
<body>
<div class="container">

    {{-- ── En-tête avec nom de la structure ──────────────── --}}
    @php
        $parametres = \App\Models\ParametresStructure::where('actif', true)->first();
        $nomStructure = $parametres?->nom_structure
            ?? $parametres?->nom
            ?? $parametres?->libelle
            ?? '';
    @endphp
    <div class="header">
        @if($nomStructure)
            <div class="structure">{{ $nomStructure }}</div>
        @endif
        <h1>ÉTAT DES ORDONNANCES DE PAIEMENT</h1>
        @if(isset($periode))
            <div class="period">{{ $periode }}</div>
        @endif
    </div>

    {{-- ── Filtres ─────────────────────────────────────── --}}
    @if(isset($filtres) && count($filtres) > 0)
    <div class="filter-info">
        <strong>Filtres :</strong>
        @foreach($filtres as $filtre) {{ $filtre }}{{ !$loop->last ? ' | ' : '' }} @endforeach
    </div>
    @endif

    {{-- ── Statistiques ────────────────────────────────── --}}
    <div class="stats">
        <div class="stat-item">
            <span class="stat-label">Nombre d'ordonnances</span>
            <span class="stat-value">{{ $statistiques['nombre_total'] ?? 0 }}</span>
        </div>
        <div class="stat-item">
            <span class="stat-label">Montant Brut (FCFA)</span>
            <span class="stat-value">{{ number_format($statistiques['montant_brut'] ?? 0, 0, ',', ' ') }}</span>
        </div>
        <div class="stat-item">
            <span class="stat-label">À Précompter (FCFA)</span>
            <span class="stat-value">{{ number_format($statistiques['montant_impot'] ?? 0, 0, ',', ' ') }}</span>
        </div>
        <div class="stat-item">
            <span class="stat-label">Net à Payer (FCFA)</span>
            <span class="stat-value">{{ number_format($statistiques['montant_net'] ?? 0, 0, ',', ' ') }}</span>
        </div>
    </div>

    {{-- ── Tableau ──────────────────────────────────────── --}}
    <table>
        <thead>
            <tr>
                <th class="col-numero">N° OP/OPT</th>
                <th class="col-date">Date Émis.</th>
                <th class="col-engagement">N° Engag.</th>
                <th class="col-objet">Objet</th>
                <th class="col-beneficiaire">Bénéficiaire</th>
                <th class="col-brut">Mnt Brut</th>
                <th class="col-precompte">Précompte</th>
                <th class="col-net">Net à Payer</th>
                <th class="col-mode">Mode</th>
                <th class="col-ref">Réf. Paiement</th>
                <th class="col-paiement">Date Paimt</th>
                <th class="col-exercice">Exo</th>
            </tr>
        </thead>
        <tbody>
            @forelse($ordonnances as $op)
            <tr>
                <td>{{ $op->numero }}</td>
                <td class="center">
                    {{ $op->date_emission ? \Carbon\Carbon::parse($op->date_emission)->format('d/m/Y') : '—' }}
                </td>
                <td>{{ $op->engagement?->numero ?? '—' }}</td>
                <td>{{ \Str::limit($op->objet ?? '', 48) }}</td>
                <td>
                    @if($op->type_ordonnance === 'impot')
                        TRÉSOR / DGI
                    @else
                        {{ \Str::limit(
                            $op->beneficiaire?->raison_sociale
                            ?? $op->beneficiaire?->nom_complet
                            ?? $op->beneficiaire?->name
                            ?? 'N/A', 25) }}
                    @endif
                </td>
                {{-- ✅ Utiliser valeurs calculées depuis OPT liée --}}
                <td class="money">{{ number_format($op->_brut_calcule ?? ((float)$op->montant_brut + (float)$op->montant_impot + (float)$op->montant_net) ?? 0, 0, ',', ' ') }}</td>
                <td class="money">{{ number_format($op->_precompte_calcule ?? $op->montant_impot ?? 0, 0, ',', ' ') }}</td>
                <td class="money" style="font-weight:bold;">
                    {{ number_format($op->montant_net ?? 0, 0, ',', ' ') }}
                </td>
                <td class="center">
                    {{ match($op->mode_paiement ?? '') {
                        'virement'       => 'Virement',
                        'cheque'         => 'Chèque',
                        'especes'        => 'Espèces',
                        'ordre_virement' => 'O.Virt.',
                        'mandat'         => 'Mandat',
                        'mobile_money'   => 'Mobile',
                        default          => '—',
                    } }}
                </td>
                <td>{{ \Str::limit($op->reference_paiement ?? '—', 18) }}</td>
                <td class="center">
                    {{ $op->date_paiement ? \Carbon\Carbon::parse($op->date_paiement)->format('d/m/Y') : '—' }}
                </td>
                <td class="center">{{ $op->exercice?->annee ?? '' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="12" class="center" style="padding:8px; color:#999;">
                    Aucune ordonnance trouvée pour cette période
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    {{-- ── Totaux ───────────────────────────────────────── --}}
    @if(count($ordonnances) > 0)
    <div class="totaux">
        <div class="totaux-grid">
            <div class="totaux-row">
                <div class="totaux-cell">Nombre d'ordonnances :</div>
                <div class="totaux-cell">{{ $statistiques['nombre_total'] ?? count($ordonnances) }}</div>
            </div>
            <div class="totaux-row">
                <div class="totaux-cell">Total Montant Brut :</div>
                <div class="totaux-cell">{{ number_format($statistiques['montant_brut'] ?? 0, 0, ',', ' ') }} FCFA</div>
            </div>
            <div class="totaux-row">
                <div class="totaux-cell">Total Précomptes :</div>
                <div class="totaux-cell">{{ number_format($statistiques['montant_impot'] ?? 0, 0, ',', ' ') }} FCFA</div>
            </div>
            <div class="totaux-row">
                <div class="totaux-cell total-general">TOTAL NET À PAYER :</div>
                <div class="totaux-cell total-general">
                    {{ number_format($statistiques['montant_net'] ?? 0, 0, ',', ' ') }} FCFA
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ── Pied de page ─────────────────────────────────── --}}
    <div class="footer">
        Généré le {{ now()->format('d/m/Y à H:i') }}
        @if(isset($utilisateur)) — Par : {{ $utilisateur }} @endif
        @if($nomStructure) — {{ $nomStructure }} @endif
    </div>

</div>
</body>
</html>