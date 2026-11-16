{{-- resources/views/exports/disponibilites-budget-pdf.blade.php --}}
@php
    $b = $etat['budget'];
    $f = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $dateAdoption = filled($b->date_adoption ?? null)
        ? \Illuminate\Support\Carbon::parse($b->date_adoption)->format('d/m/Y')
        : '—';
    $situationAu = $etat['jusquau']->format('d/m/Y');
    $filtres = array_filter([
        $etat['engagees_seulement'] ? 'Lignes engagées uniquement' : null,
        $etat['programme_filtre'] ? 'Programme ' . $etat['programme_filtre'] : null,
    ]);
    $colonnes = 15;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>État des disponibilités budgétaires</title>
    <style>
        @page { margin: 20px 18px 34px 18px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 7px; color: #111; }

        h1 { font-size: 14px; text-align: center; color: #1F4E78; margin: 0 0 2px; }
        .situation { text-align: center; font-size: 11px; font-weight: bold; color: #1F4E78; margin: 0 0 2px; }
        .sous-titre { text-align: center; font-size: 9px; margin-bottom: 6px; }
        .mention { margin: 4px 0 0; padding: 3px 6px; font-size: 7.5px; font-weight: bold;
                   color: #C00000; background: #FFC7CE; border: 1px solid #C00000; }

        table.infos { width: 100%; border-collapse: collapse; margin-bottom: 6px; font-size: 7.5px; }
        table.infos td { padding: 2px 4px; }
        table.infos .lib { font-weight: bold; color: #1F4E78; }

        table.etat { width: 100%; border-collapse: collapse; }
        table.etat thead { display: table-header-group; } /* en-tete repete sur chaque page */
        table.etat th {
            background: #4472C4; color: #fff; padding: 3px 2px; border: 1px solid #8EA9DB;
            font-size: 6.5px; text-align: center; vertical-align: middle;
        }
        table.etat td { border: 1px solid #D0D0D0; padding: 2px; vertical-align: top; }
        table.etat tr { page-break-inside: avoid; }

        .num { text-align: right; white-space: nowrap; }
        .pos { color: #0B6B2E; }
        .neg { color: #C00000; background: #FFC7CE; font-weight: bold; }

        tr.prog td { background: #1F4E78; color: #fff; font-weight: bold; font-size: 8px; padding: 3px; }
        tr.sp td { background: #D9E2F3; color: #1F4E78; font-weight: bold; font-style: italic; }
        tr.act td { background: #EDF2FA; color: #2E5496; font-weight: bold; }
        tr.stot td { background: #F0F2F5; font-weight: bold; font-style: italic; }
        tr.total td { background: #E7E6E6; font-weight: bold; font-size: 7.5px; border: 1.5px solid #000; }

        tr.eng td { background: #FAFAFA; color: #444; font-style: italic; font-size: 6.5px; }
        tr.eng td.lib { padding-left: 10px; }
        tr.eng td.meta { color: #7F7F7F; }

        .legende { margin-top: 6px; font-size: 6.5px; color: #555; }
        .pied { position: fixed; bottom: -22px; left: 0; right: 0; text-align: center; font-size: 6.5px; color: #7F7F7F; }
    </style>
</head>
<body>
    <div class="pied">Document généré automatiquement - {{ now()->format('d/m/Y à H:i') }}</div>

    <h1>ÉTAT DES DISPONIBILITÉS BUDGÉTAIRES{{ $etat['detaille'] ? ' — DÉTAIL DES ENGAGEMENTS' : '' }}</h1>
    <div class="situation">Situation au {{ $situationAu }}</div>
    <div class="sous-titre">{{ $b->libelle }} — Généré le {{ now()->format('d/m/Y à H:i') }}</div>

    <table class="infos">
        <tr>
            <td><span class="lib">Exercice :</span> {{ $b->exercice }}</td>
            <td><span class="lib">Code budget :</span> {{ $b->code ?? '—' }}</td>
            <td><span class="lib">Statut :</span> {{ ucfirst((string) ($b->statut ?? '—')) }}</td>
            <td><span class="lib">Date adoption :</span> {{ $dateAdoption }}</td>
        </tr>
        <tr>
            <td><span class="lib">Lignes :</span> {{ $etat['nb_lignes_affichees'] }} / {{ $etat['nb_lignes_budget'] }}</td>
            <td><span class="lib">Sous-programmes EP :</span> {{ $etat['nb_sous_programmes'] }}</td>
            <td><span class="lib">Actions :</span> {{ $etat['nb_actions'] }}</td>
            <td><span class="lib">Budget rectifié :</span> {{ $f($etat['budget_total']) }} FCFA</td>
        </tr>
        <tr>
            <td><span class="lib">Engagements :</span> {{ $etat['nb_engagements'] }}</td>
            <td><span class="lib">Ordonnances émises :</span> {{ $etat['nb_op_emises'] }}</td>
            <td><span class="lib">Ordonnances payées :</span> {{ $etat['nb_op_payees'] }}</td>
            <td><span class="lib">Filtres :</span> {{ $filtres ? implode(' — ', $filtres) : 'aucun' }}</td>
        </tr>
    </table>

    @if ($etat['aucun_paiement'])
        <p class="mention">⚠ Aucune ordonnance payée enregistrée sur l'exercice à la date retenue :
            les colonnes « Payé », « Taxes reversées » et « Tx Exec. » sont à 0 parce que rien n'a été
            payé, pas parce que le calcul n'a rien trouvé. {{ $etat['nb_op_emises'] }} ordonnance(s)
            émise(s) à ce jour, dont {{ $etat['nb_op_payees'] }} payée(s).</p>
    @endif

    <table class="etat">
        <thead>
            <tr>
                <th style="width:5%">Code</th>
                <th style="width:{{ $etat['detaille'] ? '22' : '18' }}%">{{ $etat['detaille'] ? 'Nomenclature / Bénéficiaire — Objet' : 'Nomenclature' }}</th>
                <th>Budget<br>Initial</th>
                <th>Vir.<br>Entrants</th>
                <th>Vir.<br>Sortants</th>
                <th>Budget<br>Rectifié</th>
                <th>Engagé</th>
                <th>Ordonné</th>
                <th>Payé</th>
                <th>Taxes<br>Reversées</th>
                <th>Dispo.<br>Eng.</th>
                <th>Dispo.<br>Ord.</th>
                <th>Tx<br>Eng.</th>
                <th>Tx<br>Ord.</th>
                <th>Tx<br>Exec.</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($etat['groupes'] as $groupe)
                <tr class="prog">
                    <td colspan="{{ $colonnes }}">■ {{ $groupe['programme'] ? 'PROGRAMME : ' : '' }}{{ $groupe['libelle'] }}</td>
                </tr>

                @foreach ($groupe['sous_groupes'] as $sousGroupe)
                    @if ($sousGroupe['libelle'])
                        <tr class="sp">
                            <td colspan="{{ $colonnes }}">↳ Sous-programme de l'EP : {{ $sousGroupe['libelle'] }}</td>
                        </tr>
                    @endif

                    @foreach ($sousGroupe['actions'] as $groupeAction)
                        @if ($groupeAction['libelle'])
                            <tr class="act">
                                <td colspan="{{ $colonnes }}">→ Action : {{ $groupeAction['libelle'] }}</td>
                            </tr>
                        @endif

                        @foreach ($groupeAction['lignes'] as $ligne)
                            <tr>
                                <td>{{ $ligne['code'] }}</td>
                                <td>{{ $ligne['libelle'] }}</td>
                                @include('exports.partials.disponibilites-montants', ['c' => $ligne['c']])
                            </tr>

                            @foreach ($ligne['engagements'] as $e)
                                <tr class="eng">
                                    <td>{{ $e['numero'] }}</td>
                                    <td class="lib">
                                        ↳ <strong>{{ $e['beneficiaire'] }}</strong> — {{ $e['objet'] }}
                                        <span class="meta">({{ $e['date'] }}@if ($e['numeros_op']), OP {{ $e['numeros_op'] }}@endif)</span>
                                    </td>
                                    <td colspan="4"></td>
                                    <td class="num">{{ $f($e['engage']) }}</td>
                                    <td class="num">{{ $f($e['ordonne']) }}</td>
                                    <td class="num">{{ $f($e['paye']) }}</td>
                                    <td class="num">{{ $f($e['taxesReversees']) }}</td>
                                    <td colspan="5"></td>
                                </tr>
                            @endforeach
                        @endforeach

                        @if ($groupeAction['afficher_total'])
                            <tr class="stot">
                                <td colspan="2">Sous-total action</td>
                                @include('exports.partials.disponibilites-montants', ['c' => $groupeAction['total']])
                            </tr>
                        @endif
                    @endforeach

                    @if ($sousGroupe['afficher_total'])
                        <tr class="stot">
                            <td colspan="2">Sous-total sous-programme</td>
                            @include('exports.partials.disponibilites-montants', ['c' => $sousGroupe['total']])
                        </tr>
                    @endif
                @endforeach

                @if ($groupe['afficher_total'])
                    <tr class="stot">
                        <td colspan="2">Sous-total programme</td>
                        @include('exports.partials.disponibilites-montants', ['c' => $groupe['total']])
                    </tr>
                @endif
            @empty
                <tr><td colspan="{{ $colonnes }}" style="text-align:center; color:#999">Aucune ligne ne correspond aux critères choisis.</td></tr>
            @endforelse

            <tr class="total">
                <td colspan="2">TOTAL GÉNÉRAL</td>
                @include('exports.partials.disponibilites-montants', ['c' => $etat['total']])
            </tr>
        </tbody>
    </table>

    <p class="legende">
        Légende : Dispo. Eng. = Disponible à l'engagement · Dispo. Ord. = Disponible à l'ordonnancement ·
        Tx Eng. = Engagé / Budget rectifié · Tx Ord. = Ordonné / Engagé · Tx Exec. = Payé / Budget rectifié ·
        Taxes reversées = retenues (TVA, IR, TSR...) reversées au Trésor via l'OPT liée, colonne d'information :
        le montant brut d'une ordonnance contient déjà sa retenue, elles ne sont donc pas ajoutées au payé ·
        Budget rectifié = initial + virements <em>exécutés</em> au plus tard le {{ $situationAu }}
        + mouvements de collectifs <em>adoptés</em> au plus tard le {{ $situationAu }}, non annulés ·
        Engagé = lignes d'engagement des actes datés au plus tard le {{ $situationAu }} ·
        Ordonné = ordonnances émises au plus tard le {{ $situationAu }} (montant brut, net à défaut) ·
        Payé = ordonnances au statut « payée » au plus tard le {{ $situationAu }} (montant brut, net à défaut) ·
        ■ = Programme de rattachement · ↳ = Sous-programme de l'établissement (décomposition par l'EP) ·
        → = Action · un sous-total n'est imprimé que s'il n'est pas la copie conforme de son unique enfant
        @if ($etat['non_imputes']['nb'] > 0)
            · HORS TOTAUX : {{ $etat['non_imputes']['nb'] }} engagement(s) d'un montant total de
            {{ $f($etat['non_imputes']['engage']) }} FCFA ne sont imputés sur aucune ligne de ce budget
            (ni répartition, ni nomenclature principale rattachée) — à rattacher.
        @endif
        @if ($etat['detaille'])
            · Sous chaque ligne : engagements (bénéficiaire — objet, date, n° d'OP) ; leur somme est égale aux montants de la ligne.
        @endif
    </p>
</body>
</html>
