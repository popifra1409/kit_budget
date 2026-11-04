<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fiche de présence {{ $fiche->numero }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        @page { size: A4 portrait; margin: 14mm 12mm 16mm 12mm; }

        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8pt; color: #000; }

        /* Règle DomPDF : table-layout:fixed + colonnes bornées = pas de débordement,
           et aucune cellule étalée sur plusieurs lignes — les lignes se coupent net
           entre deux pages, une cellule qui en chevauche deux se retrouve à l'envers. */
        table { table-layout: fixed; border-collapse: collapse; width: 100%; }
        th, td { word-wrap: break-word; overflow-wrap: break-word; overflow: hidden; padding: 2px 3px; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }

        .entete { text-align: center; border-bottom: 2px solid #000; padding-bottom: 4px; margin-bottom: 6px; }
        .entete .autorite { font-size: 7.5pt; text-transform: uppercase; letter-spacing: .3px; }
        .entete h1 { font-size: 12pt; font-weight: bold; text-transform: uppercase; margin: 3px 0 1px; }
        .entete .ref { font-size: 8pt; font-weight: bold; }

        .cadre { border: 1px solid #000; margin-bottom: 6px; }
        .cadre td { font-size: 7.5pt; vertical-align: top; border: none; }
        .cadre .lbl { font-weight: bold; text-transform: uppercase; width: 22%; background: #f2f2f2; }

        table.feuille { border: 1px solid #000; }
        table.feuille th {
            background: #333; color: #fff; font-size: 7pt; text-transform: uppercase;
            text-align: center; border: 1px solid #000; padding: 3px 2px;
        }
        table.feuille td { border: 1px solid #999; font-size: 7.5pt; vertical-align: middle; height: 22px; }
        table.feuille td.c { text-align: center; }
        table.feuille .nom { font-weight: bold; }
        table.feuille .motif { font-size: 6.5pt; font-style: italic; color: #333; }
        table.feuille .visa { font-size: 6.5pt; }

        .case { display: inline-block; width: 11px; height: 11px; border: 1px solid #000;
                text-align: center; line-height: 10px; font-size: 8px; font-weight: bold; }

        .resume { font-size: 7.5pt; margin: 5px 0; }
        .attention { border: 1px solid #000; background: #f2f2f2; padding: 4px; font-size: 7.5pt; margin: 5px 0; }
        .attention strong { text-transform: uppercase; }

        .signatures { width: 100%; margin-top: 14px; }
        .signatures td { vertical-align: top; font-size: 7.5pt; width: 50%; padding: 0 6px; }
        .signatures .boite { border: 1px solid #000; height: 26mm; padding: 2px 3px; }
        .signatures .qui { font-weight: bold; }
        .signatures .legende { font-size: 6.5pt; text-transform: uppercase; margin-top: 1px; }

        .pied { margin-top: 8px; font-size: 6.5pt; text-align: right; }
    </style>
</head>
<body>

    <div class="entete">
        @if ($commission?->service?->nom)
            <div class="autorite">{{ $commission->service->nom }}</div>
        @endif
        <h1>Fiche de présence de la commission</h1>
        <div class="ref">{{ $fiche->numero }}</div>
    </div>

    {{-- L'identité de la séance : ce que la fiche atteste. --}}
    <table class="cadre">
        <tr>
            <td class="lbl">Marché</td>
            <td>
                {{ $marche?->numero ?? '—' }}
                @if ($marche?->titre) — {{ $marche->titre }} @endif
                @if ($marche)
                    <br><span style="font-size:6.5pt">Statut du marché à la lecture de cette fiche :
                    {{ \App\Models\Marche::statutLibelle($marche->statut) }}</span>
                @endif
            </td>
            <td class="lbl">Séance du</td>
            <td>{{ $fiche->date_seance?->format('d/m/Y') ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Commission</td>
            <td>{{ $commission?->libelle ?? '—' }}{{ $commission?->code ? ' (' . $commission->code . ')' : '' }}</td>
            <td class="lbl">Lieu</td>
            <td>{{ $fiche->lieu ?: '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Objet de la séance</td>
            <td colspan="3">{{ $fiche->objet }}</td>
        </tr>
        <tr>
            <td class="lbl">Ouverture des plis</td>
            <td>{{ $marche?->date_ouverture_plis?->format('d/m/Y') ?? '—' }}</td>
            <td class="lbl">Dressée par</td>
            <td>{{ $fiche->etabliePar?->name ?? '—' }}</td>
        </tr>
    </table>

    {{-- La feuille d'émargement : une ligne par membre appelé, sa case et son visa. --}}
    <table class="feuille">
        <thead>
            <tr>
                <th style="width:4%">N°</th>
                <th style="width:14%">Qualité</th>
                <th style="width:30%">Nom et prénoms</th>
                <th style="width:13%">Téléphone</th>
                <th style="width:9%">Présent</th>
                <th style="width:15%">Visa</th>
                <th style="width:15%">Signature</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($membres as $ligne)
                <tr>
                    <td class="c">{{ $ligne->ordre }}</td>
                    <td>{{ $titres[$ligne->titre] ?? $ligne->titre }}</td>
                    <td>
                        <span class="nom">{{ $ligne->nom }}</span>
                        @if ($ligne->present === false && filled($ligne->motif_absence))
                            <br><span class="motif">Absence : {{ $ligne->motif_absence }}</span>
                        @endif
                    </td>
                    <td>{{ $ligne->telephone ?: '—' }}</td>
                    <td class="c">
                        <span class="case">{{ $ligne->present === true ? 'X' : '' }}</span>
                        @if ($ligne->present === false)
                            <br><span class="case" style="border-style:dashed">A</span>
                        @endif
                    </td>
                    <td class="c visa">
                        @if ($ligne->estVisee())
                            <span class="case">X</span>
                            <br>{{ $ligne->date_visa?->format('d/m/Y H:i') }}
                        @else
                            <span class="case"></span>
                        @endif
                    </td>
                    <td>{{-- la case que le membre remplit de sa main sur le papier --}}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="c" style="font-style:italic;height:14mm">
                        Aucun membre sur cette fiche : elle ne constate personne.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Comparaisons strictes : `null == false` est vrai en PHP, un where() ordinaire
         compterait les absents dans les émargements non portés. --}}
    <div class="resume">
        <strong>{{ $membres->count() }}</strong> membre(s) appelé(s) ·
        <strong>{{ $membres->filter(fn ($l) => $l->present === true)->count() }}</strong> présent(s) ·
        <strong>{{ $membres->filter(fn ($l) => $l->present === false)->count() }}</strong> absent(s) ·
        <strong>{{ $membres->filter(fn ($l) => $l->present === null)->count() }}</strong> émargement(s) non porté(s) ·
        <strong>{{ $membres->filter(fn ($l) => $l->visa === true)->count() }}</strong> visa(s)
    </div>

    @if ($attentes !== [])
        <div class="attention">
            <strong>Fiche non complète</strong> — {{ implode(' ; ', $attentes) }}.
            Ce papier n'atteste pas encore la séance dans son état : il se corrige avant d'être versé au dossier.
        </div>
    @endif

    @if (filled($fiche->observations))
        <table class="cadre">
            <tr>
                <td class="lbl">Observations</td>
                <td>{{ $fiche->observations }}</td>
            </tr>
        </table>
    @endif

    {{-- Le bas de la feuille : la présidence confirme la tenue de la séance. Les cases
         de visa et de signature des membres sont plus haut, ligne par ligne. --}}
    <table class="signatures">
        <tr>
            @php($president = $membres->firstWhere('titre', 'president'))
            @php($secretaire = $membres->firstWhere('titre', 'secretaire'))
            <td>
                <div class="boite">
                    <div class="qui">{{ $president?->nom ?? 'Président non nommé' }}</div>
                    <div>Président de la commission</div>
                    <div class="legende">Cachet et signature</div>
                </div>
            </td>
            <td>
                <div class="boite">
                    <div class="qui">{{ $secretaire?->nom ?? '—' }}</div>
                    <div>Secrétaire de la commission</div>
                    <div class="legende">Cachet et signature</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="pied">
        {{ $fiche->numero }} — séance du {{ $fiche->date_seance?->format('d/m/Y') ?? '—' }} —
        édition du {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
