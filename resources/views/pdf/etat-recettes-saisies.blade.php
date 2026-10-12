@php
    use App\Services\Budget\EtatRecettesSaisiesService as E;
    $f = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $b = 'border:1px solid #666;padding:2px 3px;';
    [$du, $au] = $periode;
    $libMois = $mois ? collect($mois)->map(fn ($m) => E::MOIS[$m] ?? $m)->implode(', ') : null;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 13mm 8mm 13mm 8mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 7.4pt; color: #111; }
        h1 { font-size: 12pt; text-align: center; margin: 0 0 2px 0; }
        h2 { font-size: 9pt; margin: 10px 0 3px 0; color: #1f3a68; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #e5e7eb; text-align: center; }
        .num { text-align: right; white-space: nowrap; }
        .centre { text-align: center; }
        .groupe td { background: #eef2f7; font-weight: bold; }
        .total td { background: #d9e2f3; font-weight: bold; }
        .gris { color: #555; }
        .rouge { color: #b91c1c; font-weight: bold; }
        .orange { color: #b45309; }
        tr { page-break-inside: avoid; }
        .pied { position: fixed; bottom: -8mm; left: 0; right: 0; font-size: 6.5pt; color: #777; text-align: center; }
    </style>
</head>
<body>
<div class="pied">
    {{ $structure?->sigle ?? $structure?->nom_structure }} — Recettes réelles saisies — édité le {{ now()->format('d/m/Y à H:i') }} par {{ auth()->user()?->name }}
</div>

<h1>ÉTAT DES RECETTES RÉELLES SAISIES</h1>
<div class="centre gris">
    {{ $structure?->nom_complet ?? $structure?->nom_structure }}
    @if ($exercice) · Exercice {{ $exercice->annee }} @endif
    @if ($libMois) · {{ $libMois }} @endif
    @if ($du || $au) · {{ $du ? 'du ' . \Carbon\Carbon::parse($du)->format('d/m/Y') : '' }} {{ $au ? 'au ' . \Carbon\Carbon::parse($au)->format('d/m/Y') : '' }} @endif
    @if ($selection) · Sélection de recettes @endif
    · {{ $regroupement }}
</div>

<h2>Recettes saisies ({{ $totaux['nombre'] }})</h2>
<table>
    <thead>
        <tr>
            <th style="{{ $b }}width:9%;">N°</th>
            <th style="{{ $b }}width:6%;">Date</th>
            <th style="{{ $b }}width:6%;">Compte</th>
            <th style="{{ $b }}">Ligne de recettes</th>
            <th style="{{ $b }}">Débiteur / payeur</th>
            <th style="{{ $b }}width:8%;">Attendu</th>
            <th style="{{ $b }}width:8%;">Encaissé</th>
            <th style="{{ $b }}width:7%;">RAR</th>
            <th style="{{ $b }}width:6%;">Mode</th>
            <th style="{{ $b }}width:8%;">Référence</th>
            <th style="{{ $b }}width:7%;">Statut</th>
            <th style="{{ $b }}width:4%;">Pointé</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($groupes as $libelle => $g)
            <tr class="groupe"><td colspan="12" style="{{ $b }}">{{ $libelle }} — {{ $g['nombre'] }} recette(s)</td></tr>
            @foreach ($g['recettes'] as $r)
                <tr>
                    <td style="{{ $b }}">{{ $r['numero'] }}</td>
                    <td style="{{ $b }}" class="centre">{{ $r['date']?->format('d/m/Y') }}</td>
                    <td style="{{ $b }}" class="centre">{{ $r['code'] }}</td>
                    <td style="{{ $b }}">{{ $r['ligne'] }}</td>
                    <td style="{{ $b }}">{{ $r['debiteur'] }}</td>
                    <td style="{{ $b }}" class="num">{{ $f($r['attendu']) }}</td>
                    <td style="{{ $b }}" class="num">{{ $f($r['encaisse']) }}</td>
                    <td style="{{ $b }}" class="num {{ $r['rar'] > 0 ? 'orange' : '' }}">{{ $r['rar'] > 0 ? $f($r['rar']) : '—' }}</td>
                    <td style="{{ $b }}" class="centre">{{ $r['mode'] ?? '—' }}</td>
                    <td style="{{ $b }}">{{ $r['reference'] ?? '' }}</td>
                    <td style="{{ $b }}" class="centre">{{ $r['statut'] }}</td>
                    <td style="{{ $b }}" class="centre">☐</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="5" style="{{ $b }}" class="num gris">Sous-total</td>
                <td style="{{ $b }}" class="num">{{ $f($g['attendu']) }}</td>
                <td style="{{ $b }}" class="num">{{ $f($g['encaisse']) }}</td>
                <td style="{{ $b }}" class="num">{{ $f($g['rar']) }}</td>
                <td colspan="4" style="{{ $b }}"></td>
            </tr>
        @empty
            <tr><td colspan="12" style="{{ $b }}" class="centre gris">Aucune recette saisie pour les critères retenus.</td></tr>
        @endforelse
        <tr class="total">
            <td colspan="5" style="{{ $b }}">TOTAL ({{ $totaux['nombre'] }} recettes)</td>
            <td style="{{ $b }}" class="num">{{ $f($totaux['attendu']) }}</td>
            <td style="{{ $b }}" class="num">{{ $f($totaux['encaisse']) }}</td>
            <td style="{{ $b }}" class="num">{{ $f($totaux['rar']) }}</td>
            <td colspan="4" style="{{ $b }}"></td>
        </tr>
    </tbody>
</table>

@if ($controle->isNotEmpty())
    <h2>Contrôle : prévu {{ $libMois ? 'de ' . $libMois : 'de la période' }} face à l'encaissé saisi, par ligne</h2>
    <table>
        <thead>
            <tr>
                <th style="{{ $b }}width:7%;">Compte</th>
                <th style="{{ $b }}">Ligne de recettes</th>
                <th style="{{ $b }}width:7%;">Recettes</th>
                <th style="{{ $b }}width:11%;">Prévu</th>
                <th style="{{ $b }}width:11%;">Encaissé saisi</th>
                <th style="{{ $b }}width:11%;">Écart</th>
                <th style="{{ $b }}width:7%;">Taux</th>
                <th style="{{ $b }}width:14%;">Observation</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($controle as $c)
                <tr>
                    <td style="{{ $b }}" class="centre">{{ $c['code'] }}</td>
                    <td style="{{ $b }}">{{ $c['libelle'] }}</td>
                    <td style="{{ $b }}" class="centre">{{ $c['nombre'] }}</td>
                    <td style="{{ $b }}" class="num">{{ $f($c['prevu']) }}</td>
                    <td style="{{ $b }}" class="num">{{ $f($c['encaisse']) }}</td>
                    <td style="{{ $b }}" class="num {{ $c['ecart'] < 0 ? 'orange' : '' }}">{{ ($c['ecart'] > 0 ? '+' : '') . $f($c['ecart']) }}</td>
                    <td style="{{ $b }}" class="num">{{ $c['taux'] !== null ? $c['taux'] . ' %' : '—' }}</td>
                    <td style="{{ $b }}" class="{{ $c['nombre'] === 0 && $c['prevu'] > 0 ? 'rouge' : '' }}">
                        {{ $c['nombre'] === 0 && $c['prevu'] > 0 ? 'Aucune saisie : oubli ?' : ($c['prevu'] == 0 && $c['encaisse'] > 0 ? 'Non prévu' : '') }}
                    </td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="2" style="{{ $b }}">TOTAL</td>
                <td style="{{ $b }}" class="centre">{{ $controle->sum('nombre') }}</td>
                <td style="{{ $b }}" class="num">{{ $f($controle->sum('prevu')) }}</td>
                <td style="{{ $b }}" class="num">{{ $f($controle->sum('encaisse')) }}</td>
                <td style="{{ $b }}" class="num">{{ $f($controle->sum('ecart')) }}</td>
                <td style="{{ $b }}" class="num">{{ $controle->sum('prevu') > 0 ? round($controle->sum('encaisse') / $controle->sum('prevu') * 100, 1) . ' %' : '—' }}</td>
                <td style="{{ $b }}"></td>
            </tr>
        </tbody>
    </table>
    <p class="gris">« Aucune saisie : oubli ? » : une recette était prévue sur la ligne pour la période, sans aucune saisie.
        « Non prévu » : des recettes ont été saisies sur une ligne sans prévision pour la période (vérifier l'imputation).</p>
@endif

<table style="margin-top:20px;">
    <tr>
        <td style="width:50%;text-align:center;"><strong>L'agent de saisie</strong><br><br><br><br>________________________<br>Date :</td>
        <td style="text-align:center;"><strong>Le vérificateur</strong><br>(Chef de service des recettes / Agent comptable)<br><br><br>________________________<br>Date :</td>
    </tr>
</table>
</body>
</html>