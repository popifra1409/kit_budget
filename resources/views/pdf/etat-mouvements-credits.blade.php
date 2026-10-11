@php
    $f = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $b = 'border:1px solid #555;padding:2px 4px;';
    $s = $synthese;
    [$du, $au] = $periode;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 14mm 9mm 13mm 9mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 7.6pt; color: #111; }
        h1 { font-size: 12.5pt; margin: 0 0 2px 0; text-align: center; }
        h2 { font-size: 9.5pt; margin: 11px 0 4px 0; color: #1f3a68; border-bottom: 1px solid #1f3a68; padding-bottom: 2px; }
        h3 { font-size: 8.5pt; margin: 8px 0 3px 0; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #e5e7eb; font-weight: bold; text-align: center; }
        .num { text-align: right; white-space: nowrap; }
        .centre { text-align: center; }
        .total td { background: #d9e2f3; font-weight: bold; }
        .sous td { background: #f3f4f6; font-weight: bold; }
        .gris { color: #555; }
        .alerte { color: #b91c1c; font-weight: bold; }
        .ok { color: #166534; font-weight: bold; }
        .saut { page-break-before: always; }
        tr { page-break-inside: avoid; }
        .pied { position: fixed; bottom: -8mm; left: 0; right: 0; font-size: 6.5pt; color: #777; text-align: center; }
    </style>
</head>
<body>
<div class="pied">
    {{ $structure?->sigle ?? $structure?->nom_structure }} — État des mouvements de crédits — budget {{ $budget->code }} — édité le {{ now()->format('d/m/Y à H:i') }}
</div>

@if ($inclureGestion)
    <h1>ÉTAT DES MOUVEMENTS DE CRÉDITS — EXERCICE {{ $budget->exercice }}</h1>
@else
    <h1>{{ $collectifs->count() > 1 ? 'COLLECTIFS BUDGÉTAIRES' : 'COLLECTIF BUDGÉTAIRE' }} — EXERCICE {{ $budget->exercice }}</h1>
@endif
<div class="centre gris">
    {{ $structure?->nom_complet ?? $structure?->nom_structure }} · Budget {{ $budget->code }} — {{ $budget->libelle }}
    · {{ $statuts }}
    @if ($du || $au) · Période : {{ $du ? 'du ' . $du->format('d/m/Y') : '' }} {{ $au ? 'au ' . $au->format('d/m/Y') : '' }} @endif
</div>

{{-- ══ 1. SYNTHÈSE ET PLAFONDS ══ --}}
<h2>1. Synthèse et plafonds</h2>
@if ($inclureGestion)
<table>
    <tr>
        <th style="{{ $b }}">Crédits ouverts ({{ $s['base'] }})</th>
        <th style="{{ $b }}">Cumul des virements</th>
        <th style="{{ $b }}">Plafond ({{ $s['plafond_pct'] }} %)</th>
        <th style="{{ $b }}">Taux atteint</th>
        <th style="{{ $b }}">Marge restante</th>
        <th style="{{ $b }}">Fongibilités</th>
        <th style="{{ $b }}">Transferts</th>
        <th style="{{ $b }}">Mouvements de gestion</th>
    </tr>
    <tr>
        <td style="{{ $b }}" class="num">{{ $f($s['credits_ouverts']) }}</td>
        <td style="{{ $b }}" class="num">{{ $f($s['cumul_virements']) }}</td>
        <td style="{{ $b }}" class="num">{{ $f($s['plafond_montant']) }}</td>
        <td style="{{ $b }}" class="num {{ $s['taux_virements'] > $s['plafond_pct'] ? 'alerte' : 'ok' }}">{{ $s['taux_virements'] }} %</td>
        <td style="{{ $b }}" class="num {{ $s['marge_virements'] < 0 ? 'alerte' : '' }}">{{ $f($s['marge_virements']) }}</td>
        <td style="{{ $b }}" class="num">{{ $f($s['fongibilites']) }}</td>
        <td style="{{ $b }}" class="num">{{ $f($s['transferts']) }}</td>
        <td style="{{ $b }}" class="num">{{ $s['nb_mouvements'] }}</td>
    </tr>
</table>
@endif
<table style="margin-top:4px;">
    <tr>
        <th style="{{ $b }}">Collectifs retenus</th>
        <th style="{{ $b }}">Augmentations de dépenses</th>
        <th style="{{ $b }}">Réductions de dépenses</th>
        <th style="{{ $b }}">Virements en collectif</th>
        <th style="{{ $b }}">Ajustement des recettes</th>
        <th style="{{ $b }}">Équilibre (recettes − dépenses)</th>
    </tr>
    <tr>
        <td style="{{ $b }}" class="num">{{ $s['collectifs']['nombre'] }}</td>
        <td style="{{ $b }}" class="num">{{ $f($s['collectifs']['augmentations_dep']) }}</td>
        <td style="{{ $b }}" class="num">{{ $f($s['collectifs']['reductions_dep']) }}</td>
        <td style="{{ $b }}" class="num">{{ $f($s['collectifs']['virements']) }}</td>
        <td style="{{ $b }}" class="num">{{ $f($s['collectifs']['recettes']) }}</td>
        @php $eq = $s['collectifs']['recettes'] - ($s['collectifs']['augmentations_dep'] - $s['collectifs']['reductions_dep']); @endphp
        <td style="{{ $b }}" class="num {{ abs($eq) < 1 ? 'ok' : 'alerte' }}">{{ abs($eq) < 1 ? 'Équilibré' : $f($eq) }}</td>
    </tr>
</table>
<p class="gris">Le plafond de {{ $s['plafond_pct'] }} % s'applique au cumul annuel des virements de gestion entre sous-programmes ;
    les fongibilités (à l'intérieur d'un sous-programme) et les mouvements votés en collectif n'y entrent pas.
    Plafond des annulations de crédits : {{ $s['plafond_annulations_pct'] }} % des crédits ouverts.</p>

{{-- ══ 2. MOUVEMENTS DE GESTION PAR TYPE (DÉBIT / CRÉDIT) ══ --}}
@if ($inclureGestion)
<h2>2. Mouvements de gestion par type</h2>
<p class="gris">LR : ligne de référence (existante) ; MN : mesure nouvelle (ligne créée par un collectif). AE = CP : un mouvement déplace à la fois les autorisations d'engagement et les crédits de paiement.</p>
@forelse ($parType as $g)
    <h3>{{ $g['libelle'] }} — {{ $g['aide'] }} ({{ $g['mouvements']->count() }} mouvement(s), {{ $f($g['total']) }} FCFA)</h3>
    <table>
        <thead>
            <tr>
                <th rowspan="2" style="{{ $b }}width:3%;">N°</th>
                <th colspan="5" style="{{ $b }}">DÉBIT (ligne réduite)</th>
                <th colspan="5" style="{{ $b }}">CRÉDIT (ligne augmentée)</th>
                <th rowspan="2" style="{{ $b }}width:11%;">Acte / sous-programmes</th>
            </tr>
            <tr>
                <th style="{{ $b }}">Imputation</th><th style="{{ $b }}">LR/MN</th><th style="{{ $b }}">Libellé</th><th style="{{ $b }}">AE</th><th style="{{ $b }}">CP</th>
                <th style="{{ $b }}">Imputation</th><th style="{{ $b }}">LR/MN</th><th style="{{ $b }}">Libellé</th><th style="{{ $b }}">AE</th><th style="{{ $b }}">CP</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($g['mouvements'] as $i => $m)
                <tr>
                    <td style="{{ $b }}" class="centre">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</td>
                    <td style="{{ $b }}" class="centre">{{ $m['debit_code'] }}</td>
                    <td style="{{ $b }}" class="centre">{{ $m['debit_lrmn'] }}</td>
                    <td style="{{ $b }}">{{ $m['debit_libelle'] }}</td>
                    <td style="{{ $b }}" class="num">{{ $f($m['montant']) }}</td>
                    <td style="{{ $b }}" class="num">{{ $f($m['montant']) }}</td>
                    <td style="{{ $b }}" class="centre">{{ $m['credit_code'] }}</td>
                    <td style="{{ $b }}" class="centre">{{ $m['credit_lrmn'] }}</td>
                    <td style="{{ $b }}">{{ $m['credit_libelle'] }}</td>
                    <td style="{{ $b }}" class="num">{{ $f($m['montant']) }}</td>
                    <td style="{{ $b }}" class="num">{{ $f($m['montant']) }}</td>
                    <td style="{{ $b }}font-size:6.5pt;">
                        {{ $m['numero'] }}{{ $m['reference'] ? ' — ' . $m['reference'] : '' }}<br>
                        {{ $m['sp_source']?->code ?? '?' }} → {{ $m['sp_destination']?->code ?? '?' }}
                    </td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="4" style="{{ $b }}">TOTAL {{ mb_strtoupper($g['libelle']) }}</td>
                <td style="{{ $b }}" class="num">{{ $f($g['total']) }}</td>
                <td style="{{ $b }}" class="num">{{ $f($g['total']) }}</td>
                <td colspan="3" style="{{ $b }}">TOTAL</td>
                <td style="{{ $b }}" class="num">{{ $f($g['total']) }}</td>
                <td style="{{ $b }}" class="num">{{ $f($g['total']) }}</td>
                <td style="{{ $b }}"></td>
            </tr>
        </tbody>
    </table>
@empty
    <p class="gris">Aucun mouvement de gestion sur la période retenue.</p>
@endforelse
@endif

{{-- ══ 3. PAR SOUS-PROGRAMME ══ --}}
@if ($inclureGestion)
<h2>3. Mouvements par sous-programme</h2>
@if ($parSousProgramme->isEmpty())
    <p class="gris">Aucun mouvement de gestion.</p>
@else
    <table>
        <thead>
            <tr>
                <th style="{{ $b }}text-align:left;">Sous-programme</th>
                <th style="{{ $b }}">Fongibilité (redéployé à l'intérieur)</th>
                <th style="{{ $b }}">Virements cédés</th>
                <th style="{{ $b }}">Virements reçus</th>
                <th style="{{ $b }}">Transferts (net)</th>
                <th style="{{ $b }}">Solde (dotation du SP)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($parSousProgramme as $sp)
                <tr>
                    <td style="{{ $b }}">{{ $sp['libelle'] }}</td>
                    <td style="{{ $b }}" class="num">{{ $f($sp['fongibilite']) }}</td>
                    <td style="{{ $b }}" class="num">{{ $sp['cede_virement'] ? '−' . $f($sp['cede_virement']) : '0' }}</td>
                    <td style="{{ $b }}" class="num">{{ $sp['recu_virement'] ? '+' . $f($sp['recu_virement']) : '0' }}</td>
                    <td style="{{ $b }}" class="num">{{ $f($sp['transfert']) }}</td>
                    <td style="{{ $b }}" class="num {{ $sp['solde'] < 0 ? 'alerte' : '' }}">{{ ($sp['solde'] > 0 ? '+' : '') . $f($sp['solde']) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td style="{{ $b }}">TOTAL</td>
                <td style="{{ $b }}" class="num">{{ $f($parSousProgramme->sum('fongibilite')) }}</td>
                <td style="{{ $b }}" class="num">{{ $f($parSousProgramme->sum('cede_virement')) }}</td>
                <td style="{{ $b }}" class="num">{{ $f($parSousProgramme->sum('recu_virement')) }}</td>
                <td style="{{ $b }}" class="num">{{ $f($parSousProgramme->sum('transfert')) }}</td>
                <td style="{{ $b }}" class="num">{{ $f($parSousProgramme->sum('solde')) }}</td>
            </tr>
        </tbody>
    </table>
    <p class="gris">Une fongibilité redéploie des crédits à l'intérieur d'un même sous-programme : sa dotation ne change pas.
        Un virement modifie la dotation des deux sous-programmes ; le total des soldes est nul.</p>
@endif
@endif

{{-- ══ 4. PAR TITRE DE DÉPENSE ══ --}}
<h2>4. Mouvements par titre de dépense (gestion et collectifs)</h2>
<table>
    <thead>
        <tr>
            <th style="{{ $b }}text-align:left;">Titre</th>
            <th style="{{ $b }}">Réductions</th>
            <th style="{{ $b }}">Augmentations</th>
            <th style="{{ $b }}">Solde</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($parTitreDepense as $t)
            <tr>
                <td style="{{ $b }}">{{ $t['libelle'] }}</td>
                <td style="{{ $b }}" class="num">{{ $t['reduction'] ? '−' . $f($t['reduction']) : '0' }}</td>
                <td style="{{ $b }}" class="num">{{ $t['augmentation'] ? '+' . $f($t['augmentation']) : '0' }}</td>
                <td style="{{ $b }}" class="num">{{ ($t['solde'] > 0 ? '+' : '') . $f($t['solde']) }}</td>
            </tr>
        @empty
            <tr><td colspan="4" style="{{ $b }}" class="gris centre">Aucun mouvement.</td></tr>
        @endforelse
        <tr class="total">
            <td style="{{ $b }}">TOTAL</td>
            <td style="{{ $b }}" class="num">{{ $f($parTitreDepense->sum('reduction')) }}</td>
            <td style="{{ $b }}" class="num">{{ $f($parTitreDepense->sum('augmentation')) }}</td>
            <td style="{{ $b }}" class="num">{{ $f($parTitreDepense->sum('solde')) }}</td>
        </tr>
    </tbody>
</table>

{{-- ══ 5. COLLECTIFS BUDGÉTAIRES (A-RECETTES / B-DÉPENSES) ══ --}}
@if ($collectifs->isNotEmpty())
    <h2 class="{{ $inclureGestion ? 'saut' : '' }}">5. Collectifs budgétaires</h2>
    @foreach ($collectifs as $c)
        @php $col = $c['collectif']; $n = max($c['recettes']->count(), $c['depenses']->count()); @endphp
        <h3>Collectif {{ $col->numero }} — {{ $col->libelle }}
            ({{ $col->date_collectif?->format('d/m/Y') }}{{ $col->reference ? ', ' . $col->reference : '' }})
            — {{ \App\Models\CollectifBudgetaire::STATUTS[$col->statut] ?? $col->statut }}</h3>
        @if (!empty($col->fusion_meta['origines']))
            <p class="gris">Issu de la fusion des collectifs {{ collect($col->fusion_meta['origines'])->map(fn ($o) => $o['numero'] . ' (' . $o['libelle'] . ')')->implode(', ') }}.</p>
        @endif
        <table>
            <thead>
                <tr>
                    <th colspan="5" style="{{ $b }}">A — RECETTES</th>
                    <th colspan="5" style="{{ $b }}">B — DÉPENSES</th>
                </tr>
                <tr>
                    <th style="{{ $b }}">Imputation</th><th style="{{ $b }}">LR/MN</th><th style="{{ $b }}">Libellé</th><th style="{{ $b }}">AE</th><th style="{{ $b }}">CP</th>
                    <th style="{{ $b }}">Imputation</th><th style="{{ $b }}">LR/MN</th><th style="{{ $b }}">Libellé</th><th style="{{ $b }}">AE</th><th style="{{ $b }}">CP</th>
                </tr>
            </thead>
            <tbody>
                @for ($i = 0; $i < $n; $i++)
                    @php $r = $c['recettes'][$i] ?? null; $d = $c['depenses'][$i] ?? null; @endphp
                    <tr>
                        <td style="{{ $b }}" class="centre">{{ $r['code'] ?? '' }}</td>
                        <td style="{{ $b }}" class="centre">{{ $r['lrmn'] ?? '' }}</td>
                        <td style="{{ $b }}">{{ $r['libelle'] ?? '' }}</td>
                        <td style="{{ $b }}" class="num">{{ $r ? $f($r['montant']) : '' }}</td>
                        <td style="{{ $b }}" class="num">{{ $r ? $f($r['montant']) : '' }}</td>
                        <td style="{{ $b }}" class="centre">{{ $d['code'] ?? '' }}</td>
                        <td style="{{ $b }}" class="centre">{{ $d['lrmn'] ?? '' }}</td>
                        <td style="{{ $b }}">{{ $d['libelle'] ?? '' }}</td>
                        <td style="{{ $b }}" class="num">{{ $d ? $f($d['montant']) : '' }}</td>
                        <td style="{{ $b }}" class="num">{{ $d ? $f($d['montant']) : '' }}</td>
                    </tr>
                @endfor
                <tr class="total">
                    <td colspan="3" style="{{ $b }}">TOTAL</td>
                    <td style="{{ $b }}" class="num">{{ $f($c['total_recettes']) }}</td>
                    <td style="{{ $b }}" class="num">{{ $f($c['total_recettes']) }}</td>
                    <td colspan="3" style="{{ $b }}">TOTAL</td>
                    <td style="{{ $b }}" class="num">{{ $f($c['total_depenses']) }}</td>
                    <td style="{{ $b }}" class="num">{{ $f($c['total_depenses']) }}</td>
                </tr>
            </tbody>
        </table>
        <p class="gris">
            Équilibre : {!! abs($c['equilibre']) < 1 ? '<span class="ok">recettes = dépenses</span>' : '<span class="alerte">écart de ' . $f($c['equilibre']) . ' FCFA</span>' !!}
            · Montants négatifs : réductions. LR : ligne de référence (existante) ; MN : mesure nouvelle (ligne créée).
        </p>

        @if ($c['virements']->isNotEmpty())
            <table>
                <thead>
                    <tr><th colspan="5" style="{{ $b }}">Virements votés par le collectif — DÉBIT</th><th colspan="5" style="{{ $b }}">CRÉDIT</th></tr>
                    <tr>
                        <th style="{{ $b }}">Imputation</th><th style="{{ $b }}">LR/MN</th><th style="{{ $b }}">Libellé</th><th style="{{ $b }}">AE</th><th style="{{ $b }}">CP</th>
                        <th style="{{ $b }}">Imputation</th><th style="{{ $b }}">LR/MN</th><th style="{{ $b }}">Libellé</th><th style="{{ $b }}">AE</th><th style="{{ $b }}">CP</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($c['virements'] as $v)
                        <tr>
                            <td style="{{ $b }}" class="centre">{{ $v['debit_code'] }}</td><td style="{{ $b }}" class="centre">{{ $v['debit_lrmn'] }}</td><td style="{{ $b }}">{{ $v['debit_libelle'] }}</td>
                            <td style="{{ $b }}" class="num">{{ $f($v['montant']) }}</td><td style="{{ $b }}" class="num">{{ $f($v['montant']) }}</td>
                            <td style="{{ $b }}" class="centre">{{ $v['credit_code'] }}</td><td style="{{ $b }}" class="centre">{{ $v['credit_lrmn'] }}</td><td style="{{ $b }}">{{ $v['credit_libelle'] }}</td>
                            <td style="{{ $b }}" class="num">{{ $f($v['montant']) }}</td><td style="{{ $b }}" class="num">{{ $f($v['montant']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach

    <h3>Ajustements des recettes par titre (collectifs)</h3>
    <table>
        <thead>
            <tr><th style="{{ $b }}text-align:left;">Titre de recettes</th><th style="{{ $b }}">Réductions</th><th style="{{ $b }}">Augmentations</th><th style="{{ $b }}">Solde</th></tr>
        </thead>
        <tbody>
            @forelse ($parTitreRecette as $t)
                <tr>
                    <td style="{{ $b }}">{{ $t['libelle'] }}</td>
                    <td style="{{ $b }}" class="num">{{ $t['reduction'] ? '−' . $f($t['reduction']) : '0' }}</td>
                    <td style="{{ $b }}" class="num">{{ $t['augmentation'] ? '+' . $f($t['augmentation']) : '0' }}</td>
                    <td style="{{ $b }}" class="num">{{ ($t['solde'] > 0 ? '+' : '') . $f($t['solde']) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" style="{{ $b }}" class="gris centre">Aucun ajustement de recettes.</td></tr>
            @endforelse
        </tbody>
    </table>
@endif

{{-- ══ SIGNATURES ══ --}}
<table style="margin-top:22px;">
    <tr>
        <td style="width:50%;text-align:center;"><strong>Le Directeur Général</strong><br>{{ $structure?->nom_ordonnateur }}<br><br><br><br>________________________</td>
        <td style="text-align:center;"><strong>Le Président du Conseil d'Administration</strong><br><br><br><br><br>________________________</td>
    </tr>
</table>
</body>
</html>