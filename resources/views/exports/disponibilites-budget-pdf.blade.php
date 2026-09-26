{{-- resources/views/exports/disponibilites-budget-pdf.blade.php --}}
@php
    $b = $etat['budget'];
    $f = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $dateAdoption = filled($b->date_adoption ?? null)
        ? \Illuminate\Support\Carbon::parse($b->date_adoption)->format('d/m/Y')
        : '—';
    $filtres = array_filter([
        $etat['engagees_seulement'] ? 'Lignes engagées uniquement' : null,
        $etat['programme_filtre'] ? 'Programme ' . $etat['programme_filtre'] : null,
    ]);
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
        .sous-titre { text-align: center; font-size: 9px; margin-bottom: 6px; }

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
        tr.stot td { background: #F0F2F5; font-weight: bold; font-style: italic; }
        tr.total td { background: #E7E6E6; font-weight: bold; font-size: 7.5px; border: 1.5px solid #000; }

        tr.eng td { background: #FAFAFA; color: #444; font-style: italic; font-size: 6.5px; }
        tr.eng td.lib { padding-left: 10px; }
        tr.eng .meta { color: #7F7F7F; }

        .legende { margin-top: 6px; font-size: 6.5px; color: #555; }
        .pied { position: fixed; bottom: -22px; left: 0; right: 0; text-align: center; font-size: 6.5px; color: #7F7F7F; }
    </style>
</head>
<body>
    <div class="pied">Document généré automatiquement - {{ now()->format('d/m/Y à H:i') }}</div>

    <h1>ÉTAT DES DISPONIBILITÉS BUDGÉTAIRES{{ $etat['detaille'] ? ' — DÉTAIL DES ENGAGEMENTS' : '' }}</h1>
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
            <td><span class="lib">Budget total :</span> {{ $f($etat['budget_total']) }} FCFA</td>
            <td><span class="lib">Engagements :</span> {{ $etat['nb_engagements'] }}</td>
            <td><span class="lib">Filtres :</span> {{ $filtres ? implode(' — ', $filtres) : 'aucun' }}</td>
        </tr>
    </table>

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
                    <td colspan="15">■ {{ $groupe['programme'] ? 'PROGRAMME : ' : '' }}{{ $groupe['libelle'] }}</td>
                </tr>

                @foreach ($groupe['sous_groupes'] as $sousGroupe)
                    @if ($sousGroupe['libelle'])
                        <tr class="sp">
                            <td colspan="15">↳ Sous-programme (gestion interne) : {{ $sousGroupe['libelle'] }}</td>
                        </tr>
                    @endif

                    @foreach ($sousGroupe['lignes'] as $ligne)
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

                    @if ($sousGroupe['libelle'])
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
                <tr><td colspan="15" style="text-align:center; color:#999">Aucune ligne ne correspond aux critères choisis.</td></tr>
            @endforelse

            <tr class="total">
                <td colspan="2">TOTAL GÉNÉRAL</td>
                @include('exports.partials.disponibilites-montants', ['c' => $etat['total']])
            </tr>
        </tbody>
    </table>

    <p class="legende">
        Légende : Dispo. Eng. = Disponible à l'engagement · Dispo. Ord. = Disponible à l'ordonnancement ·
        Tx Eng. = Engagé / Budget rectifié · Tx Ord. = Ordonné / Engagé · Tx Exec. = (Payé + Taxes reversées) / Budget rectifié ·
        Taxes reversées = retenues (TVA, IR, TSR...) reversées au Trésor via l'OPT liée ·
        ■ = Programme (rattachement tutelle) · ↳ = Sous-programme (subdivision de gestion interne) ou engagement
        @if ($etat['detaille'])
            · Sous chaque ligne : engagements (bénéficiaire — objet, date, n° d'OP) ; leur somme est égale aux montants de la ligne.
        @endif
    </p>
</body>
</html>