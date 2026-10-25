@php
/**
 * ✅ Template passant par EtatConfig
 * Variables reçues :
 *   $donnees['_raw']            → RegieAvance
 *   $donnees['_etat_config']    → EtatConfig (pour getEntete)
 *   $donnees['depenses']        → Collection (une entrée par pièce : BCR ou achat direct)
 *   $donnees['lignesApurement'] → Collection de strings (pièces justificatives)
 *   $donnees['numeroTranche']   → string  « 1ERE ENCAISSE 2026 ET LA 2EME ENCAISSE »
 *   $donnees['dateDebut']       → Carbon
 *   $donnees['dateFin']         → Carbon
 *   $donnees['totaux']          → array [ttc, tva, ir, net]
 *   $donnees['ordre']           → 'montant' (blocs triés par net décroissant) | 'date'
 *   $donnees['montantRecu']     → float   somme des encaisses apurés
 *   $donnees['soldeReverse']    → float   reçu − dépensé
 */
use App\Helpers\MiseEnPagePdf;

$MEP             = MiseEnPagePdf::class;
$regie           = $donnees['_raw'];
$etatConfig      = $donnees['_etat_config'] ?? null;
$depenses        = $donnees['depenses'];
$ordre           = $donnees['ordre'] ?? 'montant';
$lignesApurement = $donnees['lignesApurement'] ?? collect();
$numeroTranche   = trim($donnees['numeroTranche'] ?? '');
$dateDebut       = $donnees['dateDebut'];
$dateFin         = $donnees['dateFin'];
$totaux          = $donnees['totaux'];
$montantRecu     = (float) ($donnees['montantRecu'] ?? $totaux['net']);
$soldeReverse    = (float) ($donnees['soldeReverse'] ?? 0);

// ✅ Entête via EtatConfig — même pattern que bon-commande-simple
$parametres = \App\Models\ParametresStructure::where('actif', true)->first();

if ($etatConfig instanceof \App\Models\EtatConfig) {
    $entete = $etatConfig->getEntete($parametres);
} else {
    $entete = [
        'titre_fr'          => $parametres?->nom_complet      ?? 'CENTRE HOSPITALIER ET UNIVERSITAIRE DE YAOUNDE',
        'titre_en'          => $parametres?->nom_structure_en ?? 'YAOUNDE UNIVERSITY TEACHING HOSPITAL',
        'sigle'             => $parametres?->sigle             ?? 'CHUY',
        'ministere_fr'      => 'MINISTERE DE LA SANTE PUBLIQUE',
        'ministere_en'      => 'MINISTRY OF PUBLIC HEALTH',
        'sous_direction_fr' => 'DIRECTION GÉNÉRALE',
        'sous_direction_en' => 'GENERAL MANAGEMENT',
        'titre_document'    => 'COMPTE D\'EMPLOI',
        'logo_override'     => null,
    ];
}

$sigle = $entete['sigle'] ?? $parametres?->sigle ?? 'CHUY';

// ✅ Logo standard
$logoStdBase64 = null; $logoStdMimeType = 'image/jpeg';
if ($parametres?->logo) {
    $p = storage_path('app/public/' . ltrim($parametres->logo, '/'));
    if (file_exists($p)) {
        $logoStdBase64   = base64_encode(file_get_contents($p));
        $logoStdMimeType = mime_content_type($p) ?: 'image/jpeg';
    }
}

// ── Titrage et montants, calculés avant la pagination ─────────
// Le franc CFA n'a pas de sous-division et le tableau arrondit déjà au franc :
// l'arrêté doit énoncer exactement les mêmes montants que la ligne TOTAL.
$fmt = fn($v) => number_format(round((float) $v), 0, ',', ' ');

$intituleRegie = mb_strtoupper($regie->intitule_regie);

$titreDoc = trim(
    'APUREMENT DE ' . ($numeroTranche !== '' ? 'LA ' . mb_strtoupper($numeroTranche) . ' ' : '')
    . 'DE LA ' . $intituleRegie . ' '
    . ($dateDebut && $dateFin ? 'DU ' . $dateDebut->format('d/m/Y') . ' AU ' . $dateFin->format('d/m/Y') : '')
);

$lignesArrete = [
    ['Montant net à payer reçu',       $montantRecu],
    ['Montant dépensé',                $totaux['ttc']],
    ['IR',                             $totaux['ir']],
    ["Solde reversé à l'Agence Comptable du {$sigle}", $soldeReverse],
];

// ── Géométrie : le bloc de pièces allonge le chapeau, le tableau suit ──
$largeurContenu = $MEP::LARGEUR_CONTENU_PAYSAGE;
$colG = $largeurContenu * 0.20;
$colC = $largeurContenu * 0.60;

$empilement = function (array $blocs) use ($MEP) {
    $hauteur = 0.0;
    foreach ($blocs as [$texte, $largeur, $taille, $gras]) {
        $hauteur += $MEP::hauteurBloc($MEP::nbLignes((string) $texte, $largeur, $taille, $gras), $taille);
    }

    return $hauteur;
};

$hauteurEntete = 5 + max(
    $empilement([
        ['REPUBLIQUE DU CAMEROUN', $colG, 7, true],
        ['Paix - Travail - Patrie', $colG, 7, false],
        [$entete['ministere_fr'] ?? '', $colG, 6.5, false],
        [$entete['sous_direction_fr'] ?? '', $colG, 6, false],
    ]),
    $empilement([
        [$entete['titre_fr'] ?? '', $colC, 9.5, true],
        [$entete['titre_en'] ?? '', $colC, 7.5, false],
    ]) + ($logoStdBase64 ? 36 * $MEP::PX : 0) + 1.5 + $empilement([
        [$entete['sous_direction_fr'] ?? '', $colC, 7, true],
        [$entete['sous_direction_en'] ?? '', $colC, 6.5, false],
    ]),
    $empilement([
        ['REPUBLIC OF CAMEROON', $colG, 7, true],
        ['Peace - Work - Fatherland', $colG, 7, false],
        [$entete['ministere_en'] ?? '', $colG, 6.5, false],
        [$entete['sous_direction_en'] ?? '', $colG, 6, false],
    ])
) + 12; // filet de séparation et ses marges

$hauteurChapeau = $hauteurEntete
    // bandeau titre : padding 7px haut/bas + bordure + interligne 11pt
    + 10.5 + 1.5 + $MEP::hauteurBloc($MEP::nbLignes($titreDoc, $largeurContenu - 12, 11, true), 11) + 3
    // « COMPTE D'EMPLOI » + marge basse
    + $MEP::hauteurBloc(1, 9.5) + 8;

foreach ($lignesApurement as $piece) {
    $hauteurChapeau += $MEP::hauteurLigne(
        $MEP::nbLignes($piece, $largeurContenu - 16.5 - 4, 8.5),
        8.5,
        1
    );
}
$hauteurChapeau += 8 + 6 + $MEP::hauteurBloc(1, 9) + 4; // liste + « Tableau 1 : »

$largeurColFact = $largeurContenu * 0.30 - 4.5;
$largeurColFour = $largeurContenu * 0.24 - 4.5;
$hauteurThead   = $MEP::hauteurLigne(1, 8, 5);
$hauteurEnTeteSuite = $MEP::hauteurBloc(1, 8.5) + 8;

// Bloc de fin : total, arrêté, signature, mentions — jamais coupé entre deux pages.
$hauteurArrete = 12 + $MEP::hauteurBloc(1, 8.5);
foreach ($lignesArrete as [$libelle, $valeur]) {
    $texte = $libelle . ' : ' . $fmt($valeur) . ' FCFA ('
        . \App\Helpers\NombreEnLettres::montantCFA($valeur) . ')';
    $hauteurArrete += $MEP::hauteurBloc($MEP::nbLignes($texte, $largeurContenu - 30, 8.5), 8.5) + 3;
}

$reserveBlocFin = $MEP::hauteurLigne(1, 8, 3)
    + $hauteurArrete
    + 30 + $MEP::hauteurBloc(1, 9)                       // « Le Régisseur »
    + 14 + 2.25 + 0.75 + $MEP::hauteurBloc(1, 6.5) + 1.5 // mentions d'impression
    + 18;                                                // marge de sécurité

// ── Lignes structurées du tableau ─────────────────────────────
// En option « montants décroissants », les pièces sont regroupées par nature :
// un titre de bloc et un sous-total s'intercalent entre les lignes. Le découpage
// en pages porte donc sur cette suite structurée — un titre ne peut pas terminer
// une page ni un sous-total la commencer.
$libelleBloc = fn(?string $nature): string => match ($nature) {
    'bon_commande' => 'BONS DE COMMANDE',
    'achat_direct' => 'ACHATS DIRECTS',
    default        => mb_strtoupper(str_replace('_', ' ', (string) $nature)),
};

$regroupe = $ordre === 'montant';
$blocs    = $regroupe ? $depenses->groupBy('nature') : collect([null => $depenses]);

$rangees = collect();
$numero  = 0;
foreach ($blocs as $nature => $bloc) {
    if ($regroupe && $bloc->isNotEmpty()) {
        $rangees->push([
            'type'  => 'entete',
            'titre' => $libelleBloc($nature) . ' — ' . $bloc->count() . ' pièce' . ($bloc->count() > 1 ? 's' : ''),
        ]);
    }

    foreach ($bloc as $d) {
        $rangees->push(['type' => 'ligne', 'n' => ++$numero, 'd' => $d]);
    }

    if ($regroupe && $bloc->isNotEmpty()) {
        $rangees->push([
            'type'    => 'sous-total',
            'libelle' => 'Sous-total ' . $libelleBloc($nature),
            'ttc'     => $bloc->sum('montant_ttc'),
            'tva'     => $bloc->sum('montant_tva'),
            'ir'      => $bloc->sum('montant_ir'),
            'net'     => $bloc->sum('net_a_payer'),
        ]);
    }
}

$hauteurRangee = function (array $r) use ($MEP, $largeurContenu, $largeurColFact, $largeurColFour): float {
    if ($r['type'] === 'entete') {
        return $MEP::hauteurLigne($MEP::nbLignes($r['titre'], $largeurContenu - 8, 8, true), 8, 4);
    }

    if ($r['type'] === 'sous-total') {
        return $MEP::hauteurLigne(1, 8, 3);
    }

    return $MEP::hauteurLigne(
        max(
            $MEP::nbLignes((string) $r['d']->numero, $largeurColFact, 8),
            $MEP::nbLignes((string) ($r['d']->fournisseur_nom ?? '—'), $largeurColFour, 8)
        ),
        8,
        4
    );
};

$gardeApres = $rangees
    ->map(fn(array $r, int $i) => $r['type'] === 'entete'
        || ($rangees[$i + 1]['type'] ?? '') === 'sous-total')
    ->values()
    ->all();

$repartir = function () use ($MEP, $rangees, $hauteurRangee, $gardeApres, $hauteurChapeau, $hauteurEnTeteSuite, $hauteurThead, $reserveBlocFin) {
    return $MEP::lignesParPage(
        array_map($hauteurRangee, $rangees->all()),
        $MEP::placePaysage($hauteurChapeau + 6 + $hauteurThead),
        $MEP::placePaysage($hauteurEnTeteSuite + 6 + $hauteurThead),
        $reserveBlocFin,
        $gardeApres
    );
};

$nbLignesParPage = $repartir();

// Tableau vide + chapeau de pièces trop long : rien ne tient sous le chapeau,
// le tableau et l'arrêté ouvrent donc la page suivante.
$pageTableau = 0;
if ($nbLignesParPage === [0] && $reserveBlocFin > $MEP::placePaysage($hauteurChapeau + 6 + $hauteurThead)) {
    $nbLignesParPage = [0, 0];
    $pageTableau     = 1;
}

$reste  = collect($rangees->all());
$chunks = collect($nbLignesParPage)->map(fn($n) => $reste->splice(0, $n));
if ($chunks->isEmpty()) {
    $chunks = collect([collect()]);
}

$dateImpression = now()->format('d/m/Y à H:i');
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Compte d'Emploi — {{ $regie->numero }}</title>
<style>
    @page {
        size: A4 landscape !important;
        margin-top: 8mm;
        margin-bottom: 1.4cm;
        margin-left: 1.2cm;
        margin-right: 1.2cm;
    }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color:#000; }

    .entete-table { width:100%; border-collapse:collapse; border:none; margin-bottom:5px; }
    .entete-table td { border:none; vertical-align:top; font-size:7pt; text-align:center; }
    .entete-logo { height:36px; }
    .entete-sep  { border:none; border-top:1px solid #000; margin:3px 0 8px; }

    .titre-doc {
        text-align:center; font-weight:bold; font-size:11pt;
        background:#eee; border:1px solid #000; padding:7px 4px; margin-bottom:3px;
    }
    .sous-titre  { text-align:center; font-weight:bold; font-size:9.5pt; margin-bottom:8px; }

    .liste-apurement { font-size:8.5pt; margin-bottom:8px; }
    .liste-apurement table { width:100%; border-collapse:collapse; }
    .liste-apurement td    { border:none; padding:1px 0; vertical-align:top; }
    .liste-apurement .num  { width:22px; font-weight:bold; padding-right:5px; }

    .suite-titre { text-align:right; font-size:8.5pt; margin-bottom:8px; }

    table.depenses { width:100%; border-collapse:collapse; font-size:8pt; table-layout:fixed; }
    table.depenses th {
        border:1px solid #000; background:#e6e6e6; padding:5px 3px;
        text-align:center; font-weight:bold; font-size:8pt;
    }
    table.depenses td {
        border:1px solid #000; padding:4px 3px; font-size:8pt;
        word-wrap:break-word; vertical-align:top;
    }
    table.depenses td.num   { text-align:center; }
    table.depenses td.money { text-align:right; white-space:nowrap; }
    table.depenses tr.total-row td { font-weight:bold; background:#f0f0f0; }
    table.depenses tr.bloc-row  td { font-weight:bold; background:#f7f7f7; text-align:left;
                                     letter-spacing:0.4px; }

    .col-no   { width:4%; }
    .col-fact { width:30%; }
    .col-four { width:24%; }
    .col-ttc  { width:11%; }
    .col-tva  { width:11%; }
    .col-ir   { width:10%; }
    .col-net  { width:10%; }

    .page-break { page-break-after:always; break-after:page; }

    .arrete { margin-top:12px; font-size:8.5pt; page-break-inside:avoid; break-inside:avoid; }
    .arrete table { width:100%; border-collapse:collapse; }
    .arrete td    { border:none; padding:1px 0; vertical-align:top; }
    .arrete .num  { width:26px; font-weight:bold; }

    .signature { margin-top:30px; text-align:right; font-size:9pt; font-weight:bold; page-break-inside:avoid; }

    /* Mentions d'impression : fin du document, plus de pied de page fixe qui
       peint par-dessus le texte des pages. */
    .fin-document { clear:both; margin-top:14px; padding-top:3px; border-top:1px solid #ccc;
                    page-break-inside:avoid; break-inside:avoid; }
    .fin-document table { width:100%; border-collapse:collapse; }
    .fin-document td    { border:none; padding:0 4px; font-size:6.5pt; color:#333; vertical-align:middle; }
</style>
</head>
<body>

@foreach ($chunks as $i => $lignesPage)

@if ($i === 0)
    {{-- ✅ En-tête via EtatConfig --}}
    <table class="entete-table">
        <tr>
            <td style="width:20%;">
                <strong>REPUBLIQUE DU CAMEROUN</strong><br>
                <em>Paix - Travail - Patrie</em><br>
                <span style="font-size:6.5pt;">{{ $entete['ministere_fr'] ?? 'MINISTERE DE LA SANTE PUBLIQUE' }}</span>
                @if($entete['sous_direction_fr'] ?? null)
                <br><span style="font-size:6pt; font-style:italic;">{{ $entete['sous_direction_fr'] }}</span>
                @endif
            </td>
            <td style="width:60%;">
                <div style="font-size:9.5pt; font-weight:bold; text-transform:uppercase;">{{ $entete['titre_fr'] }}</div>
                <div style="font-size:7.5pt; font-style:italic;">{{ $entete['titre_en'] }}</div>
                @if($logoStdBase64)
                <img src="data:{{ $logoStdMimeType }};base64,{{ $logoStdBase64 }}" class="entete-logo">
                @endif
                @if($entete['sous_direction_fr'] ?? null)
                <div style="font-size:7pt; margin-top:2px; font-weight:bold;">
                    {{ $entete['sous_direction_fr'] }}
                    @if($entete['sous_direction_en'] ?? null)
                    <br><em style="font-size:6.5pt; font-weight:normal;">{{ $entete['sous_direction_en'] }}</em>
                    @endif
                </div>
                @endif
            </td>
            <td style="width:20%;">
                <strong>REPUBLIC OF CAMEROON</strong><br>
                <em>Peace - Work - Fatherland</em><br>
                <span style="font-size:6.5pt;">{{ $entete['ministere_en'] ?? 'MINISTRY OF PUBLIC HEALTH' }}</span>
                @if($entete['sous_direction_en'] ?? null)
                <br><span style="font-size:6pt; font-style:italic;">{{ $entete['sous_direction_en'] }}</span>
                @endif
            </td>
        </tr>
    </table>
    <hr class="entete-sep">

    <div class="titre-doc">{{ $titreDoc }}</div>
    <div class="sous-titre">COMPTE D'EMPLOI</div>

    @if ($lignesApurement->count() > 0)
    <div class="liste-apurement">
        <table>
            @foreach ($lignesApurement as $idx => $ligne)
            <tr>
                <td class="num">{{ $idx + 1 }}.</td>
                <td>{{ $ligne }}</td>
            </tr>
            @endforeach
        </table>
    </div>
    @endif

    @if ($pageTableau === 0)
    <div style="font-weight:bold; font-size:9pt; margin:6px 0 4px;">Tableau 1 :</div>
    @endif
@else
    <div class="suite-titre">
        <strong>{{ $sigle }} — {{ $regie->numero }}</strong>
        — Compte d'emploi{{ $numeroTranche !== '' ? ' — ' . $numeroTranche : '' }}
        @if ($i > $pageTableau) — Suite, page {{ $i + 1 }} @endif
    </div>
@endif

@if ($i === $pageTableau && $pageTableau > 0)
    <div style="font-weight:bold; font-size:9pt; margin:6px 0 4px;">Tableau 1 :</div>
@endif

@php $dernierePage = $i === $chunks->count() - 1; @endphp
@if ($i >= $pageTableau)
<table class="depenses">
    <colgroup>
        <col class="col-no"><col class="col-fact"><col class="col-four">
        <col class="col-ttc"><col class="col-tva"><col class="col-ir"><col class="col-net">
    </colgroup>
    <thead>
        <tr>
            <th class="col-no">N°</th>
            <th class="col-fact">N° FACTURE</th>
            <th class="col-four">FOURNISSEUR</th>
            <th class="col-ttc">TTC</th>
            <th class="col-tva">TVA</th>
            <th class="col-ir">IR</th>
            <th class="col-net">NET À PAYER</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($lignesPage as $r)
            @if ($r['type'] === 'entete')
            <tr class="bloc-row">
                <td colspan="7">{{ $r['titre'] }}</td>
            </tr>
            @elseif ($r['type'] === 'sous-total')
            <tr class="total-row">
                <td colspan="3" style="text-align:right; padding:3px 8px;">{{ $r['libelle'] }}</td>
                <td class="money">{{ number_format($r['ttc'], 0, ',', ' ') }}</td>
                <td class="money">{{ number_format($r['tva'], 0, ',', ' ') }}</td>
                <td class="money">{{ number_format($r['ir'],  0, ',', ' ') }}</td>
                <td class="money">{{ number_format($r['net'], 0, ',', ' ') }}</td>
            </tr>
            @else
            @php $d = $r['d']; @endphp
            <tr>
                <td class="num">{{ $r['n'] }}</td>
                <td>{{ $d->numero }}@if($d->date_emission) du {{ \Carbon\Carbon::parse($d->date_emission)->format('d/m/Y') }}@endif</td>
                <td>{{ $d->fournisseur_nom ?? '—' }}</td>
                <td class="money">{{ number_format($d->montant_ttc, 0, ',', ' ') }}</td>
                <td class="money">{{ number_format($d->montant_tva, 0, ',', ' ') }}</td>
                <td class="money">{{ number_format($d->montant_ir,  0, ',', ' ') }}</td>
                <td class="money">{{ number_format($d->net_a_payer, 0, ',', ' ') }}</td>
            </tr>
            @endif
        @endforeach

        @if ($dernierePage)
        <tr class="total-row">
            <td colspan="3" style="text-align:right; padding:3px 8px;">TOTAL</td>
            <td class="money">{{ number_format($totaux['ttc'], 0, ',', ' ') }}</td>
            <td class="money">{{ number_format($totaux['tva'], 0, ',', ' ') }}</td>
            <td class="money">{{ number_format($totaux['ir'],  0, ',', ' ') }}</td>
            <td class="money">{{ number_format($totaux['net'], 0, ',', ' ') }}</td>
        </tr>
        @endif
    </tbody>
</table>
@endif

@if ($dernierePage)
    <div class="arrete">
        <div style="font-weight:bold;">Arrêté le présent compte d'emploi à :</div>
        <table>
            @foreach ($lignesArrete as $idx => [$libelle, $valeur])
            <tr>
                <td class="num">{{ $idx + 1 }}-</td>
                <td>
                    {{ $libelle }} :
                    <strong>{{ $fmt($valeur) }} FCFA</strong>
                    ({{ ucfirst(\App\Helpers\NombreEnLettres::montantCFA($valeur)) }})
                </td>
            </tr>
            @endforeach
        </table>
    </div>

    <div class="signature">Le Régisseur</div>

    <div class="fin-document">
        <table>
            <tr>
                <td style="width:35%">Imprimé le : {{ $dateImpression }}</td>
                <td style="width:30%;text-align:center">{{ $sigle }} — Compte d'emploi {{ $regie->numero }}</td>
                <td style="width:35%;text-align:right;">{{ $numeroTranche !== '' ? mb_strtoupper($numeroTranche) : $regie->libelle }}</td>
            </tr>
        </table>
    </div>
@else
    <div class="page-break"></div>
@endif

@endforeach
</body>
</html>
