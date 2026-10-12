@php
    use App\Services\Budget\EtatsClotureService;
    $f = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $p = fn ($v) => $v === null ? '—' : number_format((float) $v, 1, ',', ' ') . ' %';
    $b = 'border:1px solid #666;padding:2px 3px;';
    $R = $recettes; $D = $depenses; $res = $resultat;
    $annee = $exercice->annee;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 13mm 8mm 13mm 8mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 7.3pt; color: #111; }
        h1 { font-size: 15pt; text-align: center; margin: 0; }
        h2 { font-size: 10pt; margin: 12px 0 4px 0; color: #1f3a68; border-bottom: 1px solid #1f3a68; padding-bottom: 2px; }
        h3 { font-size: 8.5pt; margin: 8px 0 3px 0; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #e5e7eb; text-align: center; }
        .num { text-align: right; white-space: nowrap; }
        .centre { text-align: center; }
        .total td { background: #d9e2f3; font-weight: bold; }
        .gris { color: #555; }
        .saut { page-break-before: always; }
        tr { page-break-inside: avoid; }
        .couverture { text-align: center; margin-top: 45mm; }
        .pied { position: fixed; bottom: -8mm; left: 0; right: 0; font-size: 6.5pt; color: #777; text-align: center; }
    </style>
</head>
<body>
<div class="pied">{{ $structure?->sigle ?? $structure?->nom_structure }} — Compte administratif {{ $annee }} — édité le {{ now()->format('d/m/Y') }}</div>

{{-- ══ COUVERTURE ══ --}}
<div class="couverture">
    <div style="font-size:11pt;">{{ $structure?->nom_complet ?? $structure?->nom_structure }}</div>
    <div style="height:18mm;"></div>
    <h1>COMPTE ADMINISTRATIF</h1>
    <div style="font-size:13pt;margin-top:4mm;">EXERCICE {{ $annee }}</div>
    <div style="height:12mm;"></div>
    <div class="gris">Budget {{ $budget->code }} — {{ $budget->libelle }}</div>
    <div class="gris">
        {{ $cloture ? 'Clôture des crédits : ' . $cloture->statut_label : 'Clôture des crédits non préparée : reports et annulations estimés' }}
        · Exercice {{ $exercice->getBadgeStatut() }}
    </div>
</div>

{{-- ══ 1. PRÉSENTATION GÉNÉRALE ══ --}}
<h2 class="saut">1. Présentation générale</h2>
<table>
    <tr>
        <th style="{{ $b }}"></th><th style="{{ $b }}">Prévisions initiales</th><th style="{{ $b }}">Collectifs</th><th style="{{ $b }}">Virements</th>
        <th style="{{ $b }}">Prévisions définitives</th><th style="{{ $b }}">Réalisations</th><th style="{{ $b }}">Taux</th>
    </tr>
    <tr>
        <td style="{{ $b }}"><strong>Recettes</strong> (recouvrements)</td>
        <td style="{{ $b }}" class="num">{{ $f($R['totaux']['initial']) }}</td><td style="{{ $b }}" class="num">{{ $f($R['totaux']['collectifs']) }}</td><td style="{{ $b }}" class="num">—</td>
        <td style="{{ $b }}" class="num">{{ $f($R['totaux']['definitif']) }}</td><td style="{{ $b }}" class="num">{{ $f($R['totaux']['recouvre']) }}</td>
        <td style="{{ $b }}" class="num">{{ $p($taux['execution_recettes']) }}</td>
    </tr>
    <tr>
        <td style="{{ $b }}"><strong>Dépenses</strong> (paiements)</td>
        <td style="{{ $b }}" class="num">{{ $f($D['totaux']['initial']) }}</td><td style="{{ $b }}" class="num">{{ $f($D['totaux']['collectifs']) }}</td><td style="{{ $b }}" class="num">{{ $f($D['totaux']['virements']) }}</td>
        <td style="{{ $b }}" class="num">{{ $f($D['totaux']['definitif']) }}</td><td style="{{ $b }}" class="num">{{ $f($D['totaux']['paye']) }}</td>
        <td style="{{ $b }}" class="num">{{ $p($taux['paiement']) }}</td>
    </tr>
</table>
<p class="gris">Taux d'engagement des dépenses : {{ $p($taux['engagement']) }} · Engagé : {{ $f($D['totaux']['engage']) }} · Ordonnancé : {{ $f($D['totaux']['ordonnance']) }} FCFA.
    Les virements de gestion se compensent entre lignes : leur total est nul.</p>

{{-- ══ 2. EXÉCUTION DES RECETTES ══ --}}
<h2>2. Exécution des recettes</h2>
<h3>2.1 Par titre</h3>
<table>
    <tr><th style="{{ $b }}">Titre</th><th style="{{ $b }}">Initiales</th><th style="{{ $b }}">Collectifs</th><th style="{{ $b }}">Définitives</th><th style="{{ $b }}">Attendues (titres)</th><th style="{{ $b }}">Recouvrées</th><th style="{{ $b }}">Restes à recouvrer</th><th style="{{ $b }}">Taux</th></tr>
    @foreach ($R['parTitre'] as $t)
        <tr>
            <td style="{{ $b }}">{{ $t['libelle'] }}</td>
            @foreach (['initial', 'collectifs', 'definitif', 'attendu', 'recouvre', 'rar'] as $c)<td style="{{ $b }}" class="num">{{ $f($t[$c]) }}</td>@endforeach
            <td style="{{ $b }}" class="num">{{ $p($t['definitif'] > 0 ? $t['recouvre'] / $t['definitif'] * 100 : null) }}</td>
        </tr>
    @endforeach
    <tr class="total"><td style="{{ $b }}">TOTAL</td>
        @foreach (['initial', 'collectifs', 'definitif', 'attendu', 'recouvre', 'rar'] as $c)<td style="{{ $b }}" class="num">{{ $f($R['totaux'][$c]) }}</td>@endforeach
        <td style="{{ $b }}" class="num">{{ $p($taux['execution_recettes']) }}</td></tr>
</table>

<h3>2.2 Par ligne</h3>
<table>
    <tr><th style="{{ $b }}">Compte</th><th style="{{ $b }}">Libellé</th><th style="{{ $b }}">Initiales</th><th style="{{ $b }}">Collectifs</th><th style="{{ $b }}">Définitives</th><th style="{{ $b }}">Attendues</th><th style="{{ $b }}">Recouvrées</th><th style="{{ $b }}">RAR</th><th style="{{ $b }}">Taux</th></tr>
    @foreach ($R['lignes'] as $l)
        <tr>
            <td style="{{ $b }}" class="centre">{{ $l['code'] }}</td><td style="{{ $b }}">{{ $l['libelle'] }}</td>
            @foreach (['initial', 'collectifs', 'definitif', 'attendu', 'recouvre', 'rar'] as $c)<td style="{{ $b }}" class="num">{{ $f($l[$c]) }}</td>@endforeach
            <td style="{{ $b }}" class="num">{{ $p($l['definitif'] > 0 ? $l['recouvre'] / $l['definitif'] * 100 : null) }}</td>
        </tr>
    @endforeach
    <tr class="total"><td colspan="2" style="{{ $b }}">TOTAL RECETTES</td>
        @foreach (['initial', 'collectifs', 'definitif', 'attendu', 'recouvre', 'rar'] as $c)<td style="{{ $b }}" class="num">{{ $f($R['totaux'][$c]) }}</td>@endforeach
        <td style="{{ $b }}" class="num">{{ $p($taux['execution_recettes']) }}</td></tr>
</table>

{{-- ══ 3. EXÉCUTION DES DÉPENSES ══ --}}
<h2 class="saut">3. Exécution des dépenses</h2>
@php $colsD = ['initial' => 'Initiaux', 'collectifs' => 'Collectifs', 'virements' => 'Virements', 'definitif' => 'Définitifs', 'engage' => 'Engagés', 'liquide' => 'Liquidés', 'ordonnance' => 'Ordonnancés', 'paye' => 'Payés', 'reste_a_payer' => 'Restes à payer', 'report' => 'Reportés', 'annule' => 'Annulés']; @endphp
<h3>3.1 Par titre</h3>
<table>
    <tr><th style="{{ $b }}">Titre</th>@foreach ($colsD as $lib)<th style="{{ $b }}">{{ $lib }}</th>@endforeach<th style="{{ $b }}">Taux paiement</th></tr>
    @foreach ($D['parTitre'] as $t)
        <tr><td style="{{ $b }}">{{ $t['libelle'] }}</td>
            @foreach (array_keys($colsD) as $c)<td style="{{ $b }}" class="num">{{ $f($t[$c]) }}</td>@endforeach
            <td style="{{ $b }}" class="num">{{ $p($t['definitif'] > 0 ? $t['paye'] / $t['definitif'] * 100 : null) }}</td></tr>
    @endforeach
    <tr class="total"><td style="{{ $b }}">TOTAL</td>@foreach (array_keys($colsD) as $c)<td style="{{ $b }}" class="num">{{ $f($D['totaux'][$c]) }}</td>@endforeach
        <td style="{{ $b }}" class="num">{{ $p($taux['paiement']) }}</td></tr>
</table>

<h3>3.2 Par sous-programme</h3>
<table>
    <tr><th style="{{ $b }}">Sous-programme</th><th style="{{ $b }}">Crédits définitifs</th><th style="{{ $b }}">Engagés</th><th style="{{ $b }}">Payés</th><th style="{{ $b }}">Restes à payer</th><th style="{{ $b }}">Reportés</th><th style="{{ $b }}">Annulés</th><th style="{{ $b }}">Taux paiement</th></tr>
    @foreach ($D['parSousProgramme'] as $s)
        <tr><td style="{{ $b }}">{{ $s['libelle'] }}</td>
            @foreach (['definitif', 'engage', 'paye', 'reste_a_payer', 'report', 'annule'] as $c)<td style="{{ $b }}" class="num">{{ $f($s[$c]) }}</td>@endforeach
            <td style="{{ $b }}" class="num">{{ $p($s['definitif'] > 0 ? $s['paye'] / $s['definitif'] * 100 : null) }}</td></tr>
    @endforeach
</table>

<h3>3.3 Par ligne</h3>
<table>
    <tr><th style="{{ $b }}">Compte</th><th style="{{ $b }}">Libellé</th><th style="{{ $b }}">T</th>@foreach ($colsD as $lib)<th style="{{ $b }}">{{ $lib }}</th>@endforeach</tr>
    @foreach ($D['lignes'] as $l)
        <tr><td style="{{ $b }}" class="centre">{{ $l['code'] }}</td><td style="{{ $b }}">{{ $l['libelle'] }}</td><td style="{{ $b }}" class="centre">{{ $l['titre'] }}</td>
            @foreach (array_keys($colsD) as $c)<td style="{{ $b }}" class="num">{{ $f($l[$c]) }}</td>@endforeach</tr>
    @endforeach
    <tr class="total"><td colspan="3" style="{{ $b }}">TOTAL DÉPENSES</td>@foreach (array_keys($colsD) as $c)<td style="{{ $b }}" class="num">{{ $f($D['totaux'][$c]) }}</td>@endforeach</tr>
</table>

{{-- ══ 4. RÉSULTAT ══ --}}
<h2 class="saut">4. Résultat de l'exercice {{ $annee }}</h2>
<table style="width:70%;">
    <tr><td style="{{ $b }}">Recettes recouvrées</td><td style="{{ $b }}" class="num">{{ $f($res['recettes_recouvrees']) }}</td></tr>
    <tr><td style="{{ $b }}">− Dépenses payées</td><td style="{{ $b }}" class="num">{{ $f($res['depenses_payees']) }}</td></tr>
    <tr class="total"><td style="{{ $b }}">= Résultat de caisse (excédent / déficit)</td><td style="{{ $b }}" class="num">{{ $f($res['resultat_caisse']) }}</td></tr>
    <tr><td style="{{ $b }}">+ Restes à recouvrer (RAR)</td><td style="{{ $b }}" class="num">{{ $f($res['restes_a_recouvrer']) }}</td></tr>
    <tr><td style="{{ $b }}">− Restes à payer (ordonnancés non payés)</td><td style="{{ $b }}" class="num">{{ $f($res['restes_a_payer']) }}</td></tr>
    <tr class="total"><td style="{{ $b }}">= Résultat corrigé de l'exercice</td><td style="{{ $b }}" class="num">{{ $f($res['resultat_corrige']) }}</td></tr>
</table>
<p class="gris">Un résultat positif est un excédent, un résultat négatif un déficit.</p>

{{-- ══ 5. ANNEXES ══ --}}
@php $E = $annexes['etats']; $M = $annexes['mouvements']['synthese']; @endphp
<h2>5. Annexes</h2>
<h3>5.1 États de fin de gestion</h3>
<table>
    @foreach (EtatsClotureService::LIBELLES_ETATS as $cle => $libelle)
        <tr><td style="{{ $b }}">{{ $libelle }}</td><td style="{{ $b }}" class="num">{{ $f($E['etats'][$cle]['montant']) }}</td></tr>
    @endforeach
</table>
<h3>5.2 Taux de fin de gestion</h3>
<table>
    @foreach (EtatsClotureService::LIBELLES_TAUX as $cle => $libelle)
        <tr><td style="{{ $b }}">{{ $libelle }}</td><td style="{{ $b }}" class="num">{{ $p($E['taux'][$cle]['valeur']) }}</td><td style="{{ $b }}" class="gris">{{ $E['taux'][$cle]['libelle_base'] }} : {{ $f($E['taux'][$cle]['base']) }}</td></tr>
    @endforeach
</table>
<h3>5.3 Mouvements de crédits et collectifs</h3>
<table>
    <tr><td style="{{ $b }}">Cumul des virements de gestion / plafond ({{ $M['plafond_pct'] }} %)</td><td style="{{ $b }}" class="num">{{ $f($M['cumul_virements']) }} ({{ $M['taux_virements'] }} %)</td></tr>
    <tr><td style="{{ $b }}">Fongibilités</td><td style="{{ $b }}" class="num">{{ $f($M['fongibilites']) }}</td></tr>
    <tr><td style="{{ $b }}">Collectifs adoptés : nombre / augmentations / réductions de dépenses</td><td style="{{ $b }}" class="num">{{ $M['collectifs']['nombre'] }} / {{ $f($M['collectifs']['augmentations_dep']) }} / {{ $f($M['collectifs']['reductions_dep']) }}</td></tr>
    <tr><td style="{{ $b }}">Collectifs : ajustement des recettes</td><td style="{{ $b }}" class="num">{{ $f($M['collectifs']['recettes']) }}</td></tr>
</table>
<h3>5.4 Reports et annulations de crédits</h3>
<table>
    <tr><td style="{{ $b }}">Crédits reportés sur {{ $annee + 1 }}</td><td style="{{ $b }}" class="num">{{ $f($D['totaux']['report']) }}</td></tr>
    <tr><td style="{{ $b }}">Crédits annulés</td><td style="{{ $b }}" class="num">{{ $f($D['totaux']['annule']) }}</td></tr>
    @if ($cloture)
        <tr><td style="{{ $b }}">Arrêté de report / avis du CA</td><td style="{{ $b }}">{{ $cloture->reference_arrete ?? '—' }} / {{ $cloture->reference_avis_ca ?? '—' }}</td></tr>
    @endif
</table>

{{-- ══ 6. DÉLIBÉRATION ══ --}}
<h2>6. Délibération</h2>
<p>Le Conseil d'Administration, réuni le ____________________, après avoir examiné le compte administratif de l'exercice {{ $annee }}
    présenté par l'ordonnateur, arrête les résultats de l'exercice comme suit : recettes recouvrées {{ $f($res['recettes_recouvrees']) }} FCFA,
    dépenses payées {{ $f($res['depenses_payees']) }} FCFA, résultat de caisse {{ $f($res['resultat_caisse']) }} FCFA,
    résultat corrigé {{ $f($res['resultat_corrige']) }} FCFA.</p>
<p>Article unique : le compte administratif de l'exercice {{ $annee }} est adopté. La présente délibération sera enregistrée et communiquée partout où besoin sera.</p>
<table style="margin-top:16px;">
    <tr>
        <td style="width:50%;text-align:center;"><strong>L'Ordonnateur</strong><br>{{ $structure?->nom_ordonnateur }}<br><br><br><br>________________________</td>
        <td style="text-align:center;"><strong>Le Président du Conseil d'Administration</strong><br><br><br><br><br>________________________</td>
    </tr>
</table>
</body>
</html>