@php
    use App\Models\CbmtLigne;
    use App\Services\Budget\EtatsClotureService;

    $f = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $t = $cloture->totaux ?? [];
    $annee = $cloture->exercice->annee;
    $b = 'border:1px solid #888;padding:3px 4px;';
    $libelleTitre = fn ($titre) => $titre === 'sans'
        ? 'Sans titre (comptes à classer)'
        : "Titre {$titre} — " . (CbmtLigne::TITRES_DEPENSES[$titre] ?? '');
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 16mm 10mm 14mm 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #111; }
        h1 { font-size: 13pt; margin: 0 0 2px 0; }
        h2 { font-size: 10pt; margin: 12px 0 4px 0; color: #1f3a68; border-bottom: 1px solid #1f3a68; padding-bottom: 2px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #e8edf5; }
        .num { text-align: right; white-space: nowrap; }
        .titre td { background: #f3f4f6; font-weight: bold; }
        .total td { background: #d9e2f3; font-weight: bold; }
        .entete { border-bottom: 2px solid #1f3a68; padding-bottom: 5px; margin-bottom: 6px; }
        .gris { color: #555; }
        .pied { position: fixed; bottom: -9mm; left: 0; right: 0; font-size: 7pt; color: #777; text-align: center; }
        .saut { page-break-before: always; }
        tr { page-break-inside: avoid; }
        .alerte { color: #b91c1c; font-weight: bold; }
    </style>
</head>
<body>
<div class="pied">
    {{ $structure?->sigle ?? $structure?->nom_structure }} — Clôture de l'exercice {{ $annee }} — édité le {{ now()->format('d/m/Y à H:i') }}
</div>

{{-- ══ EN-TÊTE ══ --}}
<div class="entete">
    <h1>Clôture de l'exercice {{ $annee }} — Reports, annulations et états de fin de gestion</h1>
    <div class="gris">
        {{ $structure?->nom_complet ?? $structure?->nom_structure }}
        · Budget {{ $cloture->budget->code }} — {{ $cloture->budget->libelle }}
        · Statut : {{ $cloture->statut_label }}
        · Phase : {{ $periode['libelle'] }} (fin de la période complémentaire : {{ $periode['fin_complementaire']->format('d/m/Y') }})
    </div>
</div>

{{-- ══ 1. SYNTHÈSE ══ --}}
<h2>1. Synthèse : dotation actualisée = payé + reporté + annulé</h2>
<table>
    <tr>
        <th style="{{ $b }}">Dotation actualisée</th><th style="{{ $b }}">Engagé</th><th style="{{ $b }}">Payé</th>
        <th style="{{ $b }}">Engagé non payé</th><th style="{{ $b }}">Reports retenus</th><th style="{{ $b }}">Annulations</th>
        <th style="{{ $b }}">Engagé non payé non reporté</th>
    </tr>
    <tr>
        <td style="{{ $b }}" class="num">{{ $f($t['dotation'] ?? 0) }}</td>
        <td style="{{ $b }}" class="num">{{ $f($t['engage'] ?? 0) }}</td>
        <td style="{{ $b }}" class="num">{{ $f($t['paye'] ?? 0) }}</td>
        <td style="{{ $b }}" class="num">{{ $f($t['engage_non_paye'] ?? 0) }}</td>
        <td style="{{ $b }}" class="num"><strong>{{ $f($t['report_retenu'] ?? 0) }}</strong></td>
        <td style="{{ $b }}" class="num"><strong>{{ $f($t['annule'] ?? 0) }}</strong> ({{ $t['taux_annulation'] ?? 0 }} %)</td>
        <td style="{{ $b }}" class="num {{ ($t['non_reporte_non_paye'] ?? 0) > 0 ? 'alerte' : '' }}">{{ $f($t['non_reporte_non_paye'] ?? 0) }}</td>
    </tr>
</table>
<p class="gris">Report de CP en fonctionnement : {{ $cloture->report_fonctionnement_autorise ? 'autorisé (paramètre)' : 'non autorisé — investissement seul' }}.
    Situation calculée le {{ $cloture->date_calcul?->format('d/m/Y à H:i') }}.</p>

{{-- ══ 2. ÉTATS ══ --}}
<h2>2. États de fin de gestion</h2>
<table>
    <tr><th style="{{ $b }}text-align:left;">État</th><th style="{{ $b }}">Montant (FCFA)</th><th style="{{ $b }}text-align:left;">Détail</th></tr>
    @foreach (EtatsClotureService::LIBELLES_ETATS as $cle => $libelle)
        @php $e = $etats['etats'][$cle]; @endphp
        <tr>
            <td style="{{ $b }}">{{ $libelle }}</td>
            <td style="{{ $b }}" class="num">{{ $f($e['montant']) }}</td>
            <td style="{{ $b }}">
                @switch($cle)
                    @case('rar') {{ $e['note'] ?? $e['nombre'] . ' recette(s) constatée(s)' }} @break
                    @case('reste_a_payer') {{ $e['nombre'] }} OP — net à décaisser {{ $f($e['net']) }} @break
                    @case('arrieres') {{ $e['nombre'] }} OP au-delà du délai réglementaire @break
                    @case('dette') DENO {{ $f($e['deno']) }} + reste à payer {{ $f($e['reste_a_payer']) }} + engagé sans service fait {{ $f($e['engage_sans_service_fait']) }} @break
                    @default —
                @endswitch
            </td>
        </tr>
    @endforeach
</table>

{{-- ══ 3. TAUX ══ --}}
<h2>3. Taux de fin de gestion</h2>
<table>
    <tr><th style="{{ $b }}text-align:left;">Taux</th><th style="{{ $b }}">Valeur</th><th style="{{ $b }}">Numérateur</th><th style="{{ $b }}text-align:left;">Base</th></tr>
    @foreach (EtatsClotureService::LIBELLES_TAUX as $cle => $libelle)
        @php $x = $etats['taux'][$cle]; @endphp
        <tr>
            <td style="{{ $b }}">{{ $libelle }}</td>
            <td style="{{ $b }}" class="num"><strong>{{ $x['valeur'] === null ? '—' : $x['valeur'] . ' %' }}</strong></td>
            <td style="{{ $b }}" class="num">{{ $f($x['numerateur']) }}</td>
            <td style="{{ $b }}">{{ $x['libelle_base'] }} : {{ $f($x['base']) }}</td>
        </tr>
    @endforeach
</table>
<p class="gris">Liquidé = liquidations arrêtées ({{ $f($etats['detail_liquidation']['explicite']) }})
    + OP émises sans liquidation, service fait implicite ({{ $f($etats['detail_liquidation']['implicite_op_sans_liquidation']) }}).</p>

{{-- ══ 4. SITUATION DES LIGNES ══ --}}
<h2 class="saut">4. Situation des lignes budgétaires par titre</h2>
<table>
    <thead>
        <tr>
            <th style="{{ $b }}text-align:left;">Compte</th><th style="{{ $b }}text-align:left;">Libellé</th><th style="{{ $b }}">SP</th>
            <th style="{{ $b }}">Dotation</th><th style="{{ $b }}">Engagé</th><th style="{{ $b }}">Payé</th>
            <th style="{{ $b }}">Engagé non payé</th><th style="{{ $b }}">Report retenu</th><th style="{{ $b }}">Annulé</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($parTitre as $titre => $groupe)
            <tr class="titre">
                <td colspan="3" style="{{ $b }}">{{ $libelleTitre($titre) }}</td>
                @foreach (['dotation', 'engage', 'paye', 'engage_non_paye', 'report_retenu', 'annule'] as $c)
                    <td style="{{ $b }}" class="num">{{ $f($groupe->sum($c)) }}</td>
                @endforeach
            </tr>
            @foreach ($groupe as $l)
                <tr>
                    <td style="{{ $b }}">{{ $l->code }}</td>
                    <td style="{{ $b }}">{{ $l->libelle }}</td>
                    <td style="{{ $b }}">{{ $l->sousProgramme?->code ?? '—' }}</td>
                    @foreach (['dotation', 'engage', 'paye', 'engage_non_paye', 'report_retenu', 'annule'] as $c)
                        <td style="{{ $b }}" class="num">{{ $f($l->{$c}) }}</td>
                    @endforeach
                </tr>
            @endforeach
        @endforeach
        <tr class="total">
            <td colspan="3" style="{{ $b }}">TOTAL</td>
            @foreach (['dotation', 'engage', 'paye', 'engage_non_paye', 'report_retenu', 'annule'] as $c)
                <td style="{{ $b }}" class="num">{{ $f($lignes->sum($c)) }}</td>
            @endforeach
        </tr>
    </tbody>
</table>

{{-- ══ 5. ÉTAT DES REPORTS ══ --}}
<h2 class="saut">5. État des reports de crédits (annexe à l'arrêté de report)</h2>
@if ($reports->isEmpty())
    <p>Aucun report retenu.</p>
@else
    <table>
        <thead>
            <tr>
                <th style="{{ $b }}text-align:left;">Compte</th><th style="{{ $b }}text-align:left;">Libellé</th><th style="{{ $b }}">Titre</th><th style="{{ $b }}">SP</th>
                <th style="{{ $b }}">Engagé non payé</th><th style="{{ $b }}">Report proposé</th><th style="{{ $b }}">Report retenu</th><th style="{{ $b }}text-align:left;">Motif d'écart</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($reports as $l)
                <tr>
                    <td style="{{ $b }}">{{ $l->code }}</td>
                    <td style="{{ $b }}">{{ $l->libelle }}</td>
                    <td style="{{ $b }}text-align:center;">{{ $l->titre ? 'T' . $l->titre : '—' }}</td>
                    <td style="{{ $b }}">{{ $l->sousProgramme?->code ?? '—' }}</td>
                    <td style="{{ $b }}" class="num">{{ $f($l->engage_non_paye) }}</td>
                    <td style="{{ $b }}" class="num">{{ $f($l->report_propose) }}</td>
                    <td style="{{ $b }}" class="num"><strong>{{ $f($l->report_retenu) }}</strong></td>
                    <td style="{{ $b }}">{{ $l->motif_ecart ?? '' }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="4" style="{{ $b }}">TOTAL DES REPORTS</td>
                <td style="{{ $b }}" class="num">{{ $f($reports->sum('engage_non_paye')) }}</td>
                <td style="{{ $b }}" class="num">{{ $f($reports->sum('report_propose')) }}</td>
                <td style="{{ $b }}" class="num">{{ $f($reports->sum('report_retenu')) }}</td>
                <td style="{{ $b }}"></td>
            </tr>
        </tbody>
    </table>
@endif

{{-- ══ 6. ACTES ET REPRISE ══ --}}
<h2>6. Actes et reprise en {{ $annee + 1 }}</h2>
<table>
    <tr>
        <td style="{{ $b }}width:33%;"><strong>Arrêté de report de l'ordonnateur</strong><br>
            {{ $cloture->reference_arrete ?? 'Non encore pris' }}{{ $cloture->date_arrete ? ' du ' . $cloture->date_arrete->format('d/m/Y') : '' }}</td>
        <td style="{{ $b }}width:33%;"><strong>Avis du conseil d'administration</strong><br>
            @if ($cloture->avis_ca)
                {{ $cloture->avis_ca === 'conforme' ? 'Conforme' : 'Défavorable' }} — {{ $cloture->reference_avis_ca }}{{ $cloture->date_avis_ca ? ' du ' . $cloture->date_avis_ca->format('d/m/Y') : '' }}
            @else
                Non encore rendu
            @endif
        </td>
        <td style="{{ $b }}"><strong>Reprise en {{ $annee + 1 }}</strong><br>
            @if ($cloture->collectifReports)
                Collectif {{ $cloture->collectifReports->numero }} ({{ $cloture->collectifReports->statut }}) —
                {{ $f($cloture->bilan_reprise['montant_repris'] ?? 0) }} FCFA sur {{ $cloture->bilan_reprise['lignes_reprises'] ?? 0 }} ligne(s)
            @else
                Non encore effectuée
            @endif
        </td>
    </tr>
</table>

@if (filled($cloture->observations))
    <p><strong>Observations :</strong> {!! nl2br(e($cloture->observations)) !!}</p>
@endif

{{-- ══ SIGNATURES ══ --}}
<table style="margin-top:18px;">
    <tr>
        <td style="width:33%;text-align:center;"><strong>Le Directeur des Affaires Administratives et Financières</strong><br><br><br><br>________________________</td>
        <td style="width:33%;text-align:center;"><strong>Le Contrôleur Financier</strong><br><br><br><br>________________________</td>
        <td style="text-align:center;"><strong>L'Ordonnateur</strong><br>{{ $structure?->nom_ordonnateur }}<br><br><br>________________________</td>
    </tr>
</table>
</body>
</html>