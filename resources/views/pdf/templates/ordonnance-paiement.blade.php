@extends('pdf.layouts.master', ['typeHeader' => 'op', 'typeFooter' => 'op', 'orientation' => 'landscape'])

@php
$ordonnance = $donnees['_raw'];

if (!$ordonnance->relationLoaded('engagement')) {
$ordonnance->load('engagement.engageable');
}

$engagement = $ordonnance->engagement;
$nomenclature = $engagement->nomenclaturePrincipale;
$documentSource = $engagement?->engageable;

// ── Ligne budgétaire et montants disponibles ──────────────
$ligneBudgetaire = null;
$dotationInitiale = 0;
$virementsEntrants = 0;
$virementsSortants = 0;
$budgetRectifie = 0;
$disponibleAvant = 0;
$disponibleApres = 0;

if ($nomenclature) {
$ligneBudgetaire = \App\Models\LigneBudgetaire::where('budget_id', $engagement->budget_id)
->where('nomenclature_id', $nomenclature->id)
->first();

if ($ligneBudgetaire) {
$dotationInitiale = (float) ($ligneBudgetaire->budget_initial
?? $ligneBudgetaire->montant_initial ?? 0);
$virementsEntrants = (float) ($ligneBudgetaire->virements_entrants ?? 0);
$virementsSortants = (float) ($ligneBudgetaire->virements_sortants ?? 0);
$budgetRectifie = (float) ($ligneBudgetaire->budget_rectifie
?? ($dotationInitiale + $virementsEntrants - $virementsSortants));
$montantEngage = (float) ($engagement->montant_engage ?? 0);

if ($engagement->snapshot_disponible_avant !== null) {
$dotationInitiale = (float) ($engagement->snapshot_budget_initial ?? $dotationInitiale);
$budgetRectifie = (float) ($engagement->snapshot_budget_rectifie ?? $budgetRectifie);
$disponibleAvant = (float) $engagement->snapshot_disponible_avant;
$disponibleApres = (float) $engagement->snapshot_disponible_apres;
} else {
$totalEngageAvant = \App\Models\Engagement::withoutGlobalScope('exercice')
->where('budget_id', $ligneBudgetaire->budget_id)
->where('nomenclature_principale_id', $ligneBudgetaire->nomenclature_id)
->where('id', '<', $engagement->id)
    ->whereIn('statut', ['provisoire', 'definitif'])
    ->sum('montant_engage');

    $disponibleAvant = $budgetRectifie - $totalEngageAvant;
    $disponibleApres = $disponibleAvant - $montantEngage;
    }
    }
    }

    // ── Nomenclature ──────────────────────────────────────────
    if ($engagement) {
    $engagement->load('nomenclaturePrincipale');
    $nomenclature = $engagement->nomenclaturePrincipale;
    }

    // ── Hiérarchie budgétaire ─────────────────────────────────
    $tache = $activite = $action = $programme = $sousProgramme = $objectif = null;
    $codeProgramme = $codeSousProgramme = $codeAction = $codeActivite = '';
    $codeTache = $codeArticle = $codeParagraphe = $codeChapitre = '';

    if ($nomenclature) {
    $tache = $nomenclature->tache ?? $nomenclature->taches()->first();
    if ($tache) {
    $tache->load('activite.action.programme.parent');
    $activite = $tache->activite;
    $action = $activite?->action;
    $programme = $action?->programme;

    if ($programme) {
    if ($programme->estSousProgramme()) {
    $sousProgramme = $programme;
    $programme = $programme->parent;
    } else {
    $sousProgramme = null;
    }
    try {
    if (method_exists($programme, 'objectifPrincipal')) {
    $objectif = $programme->objectifPrincipal;
    } elseif (method_exists($programme, 'objectifsPrincipaux')) {
    $objectifs = $programme->objectifsPrincipaux;
    $objectif = $objectifs instanceof \Illuminate\Support\Collection
    ? $objectifs->first() : $objectifs;
    }
    } catch (\Exception $e) {
    \Log::warning('Erreur objectif', [
    'programme_id' => $programme->id,
    'error' => $e->getMessage(),
    ]);
    $objectif = null;
    }
    }

    $codeTache = $tache->code ?? '';
    $codeActivite = $activite->code ?? '';
    $codeAction = $action->code ?? '';

    if ($sousProgramme) {
    $codeSousProgrammeBrut = $sousProgramme->code ?? '';
    $chiffres = preg_replace('/[^0-9]/', '', $codeSousProgrammeBrut);
    $codeSousProgramme = $chiffres !== ''
    ? '(' . (int) $chiffres . ')'
    : $codeSousProgrammeBrut;
    }

    $codeProgramme = $programme->code ?? '';
    }

    $codeChapitre = substr($nomenclature->code, 0, 2);
    $codeParagraphe = $nomenclature->code;
    $codeArticle = method_exists($nomenclature, 'getCodeArticle')
    ? $nomenclature->getCodeArticle()
    : substr($nomenclature->code, 0, 4);
    }

    // ── Bénéficiaire ──────────────────────────────────────────
    $beneficiaire = null;
    if ($ordonnance->beneficiaire) {
    $beneficiaire = $ordonnance->beneficiaire;
    } elseif ($documentSource) {
    if ($engagement->estBonCommande()) {
    $beneficiaire = $documentSource->fournisseur;
    } elseif ($engagement->estDecision()) {
    $beneficiaire = $documentSource->personnel;
    }
    }
    $nomBeneficiaire = $beneficiaire->raison_sociale
    ?? ($beneficiaire->nom_complet
    ?? ($beneficiaire->name ?? 'N/A'));

    // ── Montants selon le type de document ───────────────────
    if ($engagement && $engagement->engageable) {
    $donneesEngagement = $engagement->extraireDonneesDocument();

    if ($engagement->estBonCommande()) {
    $montantImputation = $documentSource->montant_ht ?? 0;
    $montantBrut = $donneesEngagement['montant_ttc'] ?? 0;
    $detailImpots = [
    'ir' => $donneesEngagement['montant_ir'] ?? 0,
    'tva' => $donneesEngagement['montant_tva'] ?? 0,
    'tsr' => $donneesEngagement['montant_tsr'] ?? 0,
    'cnps' => 0, 'irnc' => 0, 'feicom' => 0,
    'redevance_av' => 0, 'autres' => 0,
    ];
    $montantTotalImpots = $detailImpots['ir'] + $detailImpots['tva'] + $detailImpots['tsr'];
    $montantNet = $donneesEngagement['montant_net'] ?? 0;
    } else {
    $montantImputation = $donneesEngagement['montant_brut'] ?? 0;
    $montantBrut = $donneesEngagement['montant_brut'] ?? 0;
    $detailImpots = [
    'ir' => $donneesEngagement['montant_ir'] ?? 0,
    'tva' => $donneesEngagement['montant_tva'] ?? 0,
    'tsr' => 0,
    'cnps' => $donneesEngagement['montant_cnps'] ?? 0,
    'irnc' => $donneesEngagement['montant_irnc'] ?? 0,
    'feicom' => $donneesEngagement['montant_feicom'] ?? 0,
    'redevance_av' => $donneesEngagement['montant_redevance_av'] ?? 0,
    'autres' => $donneesEngagement['autres_retenues'] ?? 0,
    ];
    $montantTotalImpots = $detailImpots['ir'] + $detailImpots['tva']
    + $detailImpots['cnps'] + $detailImpots['irnc']
    + $detailImpots['feicom'] + $detailImpots['redevance_av']
    + $detailImpots['autres'];
    $montantNet = $donneesEngagement['montant_net'] ?? 0;
    }
    } else {
    $montantImputation = $ordonnance->montant_brut ?? 0;
    $montantBrut = $ordonnance->montant_brut ?? 0;
    $montantNet = $ordonnance->montant_net ?? 0;
    $montantTotalImpots = 0;
    $detailImpots = [
    'ir' => 0, 'tva' => 0, 'tsr' => 0, 'cnps' => 0,
    'irnc' => 0, 'feicom' => 0, 'redevance_av' => 0, 'autres' => 0,
    ];
    }

    $parametres = \App\Models\ParametresStructure::where('actif', true)->first();

    // ── Banque + Billetage (mode forfait DA uniquement) ───────
    $banque = 0;
    $billetage = 0;
    $aVentilation = false;

    if ($engagement->estDecision() && $documentSource) {
    $banque = (float) ($documentSource->banque ?? 0);
    $billetage = (float) ($documentSource->billetage ?? 0);
    $aVentilation = ($banque > 0 || $billetage > 0);
    }

    // ── Pied de page : créateur et dates ─────────────────────
    $dateImpression = now()->format('d/m/Y à H:i');
    $dateCreation = '—';
    $nomCreateur = '—';
    $sigle = $parametres->sigle ?? 'CHUY';
    $numeroDocument = $documentSource?->numero ?? $engagement->numero ?? '—';

    $createurId = $documentSource?->created_by ?? $ordonnance->created_by ?? null;

    if ($createurId) {
    $createur = \App\Models\User::find($createurId);
    if ($createur) {
    $nomCreateur = $createur->username
    ?? $createur->login
    ?? $createur->name
    ?? '—';
    }
    }

    $createdAt = $documentSource?->created_at ?? $ordonnance->created_at;
    if ($createdAt) {
    $dateCreation = \Carbon\Carbon::parse($createdAt)->format('d/m/Y à H:i');
    }
    @endphp

    @section('title', 'Ordonnance de Paiement')

    @section('montant_lettres')
    {{ \App\Helpers\NombreEnLettres::montantCFA($montantNet) }}
    @endsection

    @section('montant_lettres_ttc')
    {{ \App\Helpers\NombreEnLettres::montantCFA($montantBrut) }}
    @endsection

    @section('additional_styles')
    <style>
        @page {
            size: A4 landscape !important;
            margin: 5mm 6mm 16mm 6mm;
            /* ✅ Marges réduites */
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 8.5pt;
            line-height: 1.0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .border-all {
            border: 1px solid #000;
        }

        .border-right {
            border-right: 1px solid #000;
        }

        .border-bottom {
            border-bottom: 1px solid #000;
        }

        .border-top {
            border-top: 1px solid #000;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .font-bold {
            font-weight: bold;
        }

        .font-small {
            font-size: 8pt;
        }

        .font-tiny {
            font-size: 7.5pt;
        }

        .padding-5 {
            padding: 3px;
        }

        .padding-3 {
            padding: 2px;
        }

        .min-height-30 {
            min-height: 20px;
        }

        .info-box {
            border: 1px solid #000;
            padding: 2px;
            margin: 2px 0;
            font-size: 8.5pt;
        }

        .objet-block {
            margin: 2px 0;
            line-height: 1.0;
        }

        .objet-titre {
            font-weight: bold;
            font-size: 7.5pt;
            margin-top: 10;
        }

        .objet-sub {
            font-size: 7pt;
            font-style: italic;
            margin-top: 2px;
        }

        .objet-text {
            margin-top: 2px;
            font-size: 8.5pt;
            line-height: 1.05;
            word-break: break-word;
            overflow-wrap: break-word;
        }

        .montant-block {
            margin-top: 3px;
            line-height: 1.0;
        }

        .separator {
            border-bottom: 2px solid #000;
            margin: 2px 0;
        }

        .snapshot-notice {
            font-size: 7pt;
            color: #555;
            font-style: italic;
            padding: 1px 2px;
            border-left: 2px solid #aaa;
            background: #f5f5f5;
            margin-bottom: 1px;
        }

        /* ✅ Pied de page fixe compact */
        .pdf-footer-op {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 0.9cm;
            border-top: 1px solid #ccc;
            padding-top: 2px;
            font-size: 7.5pt;
            color: #000;
            background: #fff;
        }

        .pdf-footer-op table {
            width: 100%;
            border-collapse: collapse;
        }

        .pdf-footer-op td {
            border: none;
            padding: 0 3px;
            vertical-align: middle;
            font-size: 7.5pt;
            color: #000;
        }
    </style>
    @endsection

    @section('content')

    {{-- ✅ Pied de page fixe --}}
    <div class="pdf-footer-op">
        <table>
            <tr>
                <td style="width:30%; text-align:left;">
                    <strong>Imprimé le :</strong> {{ $dateImpression }}
                </td>
                <td style="width:35%; text-align:center; font-weight:bold;">
                    {{ $sigle }} — N° {{ $numeroDocument }}
                </td>
                <td style="width:35%; text-align:right;">
                    <strong>Créé le :</strong> {{ $dateCreation }}
                    &nbsp;|&nbsp;
                    <strong>Par :</strong> {{ $nomCreateur }}
                </td>
            </tr>
        </table>
    </div>

    <table style="width:100%; border:none; margin-top:0;">
        <tr>

            {{-- ══ COLONNE GAUCHE : Imputation budgétaire ════════════ --}}
            <td style="width:30%; vertical-align:top; padding:4px;" class="border-right">

                <div class="font-bold" style="font-size:7.5pt; text-align:center;">
                    IMPUTATION BUDGETAIRE
                </div>
                <div class="font-tiny" style="font-style:italic; text-align:center;">
                    BUDGETARY CHARGE
                </div>

                <div style="margin-top:3px; font-size:7.5pt;">
                    @if ($codeProgramme || $codeSousProgramme)
                    <div>
                        <span class="font-bold" style="font-size:7.5pt;">
                            • {{ $codeSousProgramme ? 'SOUS-PROGRAMME :' : 'PROGRAMME :' }}
                        </span>
                        @if ($codeSousProgramme)
                        <span style="font-weight:bold;">
                            {{ $codeSousProgramme }} - {{ $sousProgramme->libelle ?? '' }}
                        </span>
                        @else
                        {{ $codeProgramme }} - {{ $programme->libelle ?? '' }}
                        @endif
                    </div>
                    @endif

                    @if ($codeAction)
                    <div>• <span class="font-bold" style="font-size:7pt;">ACTION : </span>
                        {{ $codeAction }} - {{ $action->libelle ?? '' }}
                    </div>
                    @endif

                    @if ($codeActivite)
                    <div>• <span class="font-bold" style="font-size:7pt;">ACTIVITÉ : </span>
                        {{ $codeActivite }} - {{ $activite->libelle ?? '' }}
                    </div>
                    @endif

                    @if ($codeArticle)
                    <div>• <span class="font-bold" style="font-size:7pt;">ARTICLE : </span>
                        {{ $codeArticle }}
                    </div>
                    @endif

                    @if ($codeParagraphe)
                    <div>• <span class="font-bold" style="font-size:7pt;">PARAGRAPHE : </span>
                        {{ $codeParagraphe }}
                    </div>
                    @endif
                </div>

                <div class="objet-block">
                    <div class="separator"></div>

                    <div class="objet-titre">OBJET DE LA DEPENSE:</div>
                    <div class="objet-sub">SUBJECT OF EXPENDITURE</div>
                    <div class="objet-text">
                        {{ $ordonnance->objet ?? ($documentSource?->objet ?? 'Paiement') }}
                    </div>

                    <div class="montant-block">
                        <div class="objet-titre">
                            MONTANT TOTAL DE LA DEPENSE:
                            <strong>{{ number_format($montantBrut, 0, ',', ' ') }} F CFA</strong>
                        </div>
                        <div class="objet-sub">TOTAL AMOUNT OF EXPENSE</div>
                        <div style="margin-top:2px;">
                            <div class="objet-titre">Arrêté en toutes lettres:</div>
                            <div class="objet-sub">Closed at the sum of (in words)</div>
                            <div style="margin-top:1px; text-align:center;">
                                <strong style="font-size:7pt; text-transform:uppercase;">
                                    @yield('montant_lettres_ttc')
                                </strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <div style="border-bottom:2px solid #000; margin:0;"></div>
                    <div style="margin-top:10px;">
                        <div class="font-bold" style="font-size:7pt;">PIECES JOINTES:</div>
                        <div style="margin-top:2px; font-size:6.5pt; line-height:1.3;">
                            • Ordre de mission • Bulletin de solde • Lettre d'invitation<br>
                            • Photocopie du passport • Bon de commande administratif<br>
                            • Lettre commande • Convention ou Contrat • Facture Proforma<br>
                            • Expressions des besoins • Certificat d'engagement<br>
                            • Facture définitive liquidée • Procès verbal de réception<br>
                            • Bordereau de livraison • Quittance d'enregistrement<br>
                            • Attestation de domiciliation bancaire<br>
                            • Attestation d'immatriculation • Certificat de garantie<br>
                            • Avis d'imposition à l'enregistrement<br>
                            • Avis d'imposition des retenues à la source<br>
                            • Attestation de Non Redevance<br>
                            • Certificat de non exclusion (ARMP) • Plan de localisation • Etc.
                        </div>
                    </div>
                </div>
            </td>

            {{-- ══ COLONNE CENTRALE ═══════════════════════════════════ --}}
            <td style="vertical-align:top;">
                <table>

                    {{-- ── MONTANT NET A PAYER ── --}}
                    <tr>
                        <td colspan="2">
                            <div style="font-size:10pt; text-align:center; margin:2px 0;
                                    font-weight:normal;">
                                MONTANT NET A PAYER:
                                <strong>{{ number_format($montantNet, 0, ',', ' ') }}</strong> F CFA
                            </div>

                            {{-- ✅ Ventilation Banque + Billetage --}}
                            @if ($aVentilation)
                            <div style="margin-top:2px; text-align:center;">
                                <table style="width:auto; margin:0 auto; border-collapse:collapse;">
                                    <tr>
                                        @if ($banque > 0)
                                        <td style="padding:1px 5px; border:1px solid #000;
                                                text-align:center;">
                                            <div style="font-size:6pt; font-style:italic;">
                                                Virement bancaire
                                            </div>
                                            <div style="font-weight:bold; font-size:7.5pt;">
                                                {{ number_format($banque, 0, ',', ' ') }} F
                                            </div>
                                            <div style="font-size:6pt; font-weight:bold;">BANQUE</div>
                                        </td>
                                        @endif

                                        @if ($banque > 0 && $billetage > 0)
                                        <td style="padding:0 3px; border:none;
                                                font-size:9pt; font-weight:bold;">+</td>
                                        @endif

                                        @if ($billetage > 0)
                                        <td style="padding:1px 5px; border:1px solid #000;
                                                text-align:center;">
                                            <div style="font-size:6pt; font-style:italic;">
                                                En espèces
                                            </div>
                                            <div style="font-weight:bold; font-size:7.5pt;">
                                                {{ number_format($billetage, 0, ',', ' ') }} F
                                            </div>
                                            <div style="font-size:6pt; font-weight:bold;">BILLETAGE</div>
                                        </td>
                                        @endif

                                        <td style="padding:0 3px; border:none;
                                                font-size:9pt; font-weight:bold;">=</td>

                                        <td style="padding:1px 5px; border:2px solid #000;
                                                text-align:center; background:#f0f0f0;">
                                            <div style="font-size:6pt; font-style:italic;">
                                                Net à payer
                                            </div>
                                            <div style="font-weight:bold; font-size:7.5pt;">
                                                {{ number_format($montantNet, 0, ',', ' ') }} F
                                            </div>
                                        </td>
                                    </tr>
                                </table>

                                {{-- Vérification cohérence --}}
                                @php $somme = $banque + $billetage; @endphp
                                @if (abs($somme - $montantNet) > 1)
                                <div style="color:red; font-size:6pt; margin-top:1px;">
                                    ⚠️ Banque + Billetage ({{ number_format($somme, 0, ',', ' ') }})
                                    ≠ Net ({{ number_format($montantNet, 0, ',', ' ') }})
                                </div>
                                @endif
                            </div>
                            @endif
                        </td>
                    </tr>

                    {{-- ── CE + Dotation ── --}}
                    <tr>
                        <td style="width:15%; vertical-align:middle; padding:2px;"
                            class="border-right">
                            <div style="font-size:7.5pt; line-height:1.2;">
                                <strong>CE N°</strong> {{ $engagement->numero }}
                            </div>
                            <div style="margin-top:2px; font-size:7.5pt; line-height:1.2;">
                                <strong>Du</strong> _______________________
                            </div>
                        </td>
                        <td style="width:25%; vertical-align:middle; padding:2px;"
                            class="border-right">
                            @if ($ligneBudgetaire)
                            <div style="font-size:7.5pt;">
                                <strong>DOTATION INITIALE:</strong>
                                {{ number_format($dotationInitiale, 0, ',', ' ') }} F CFA
                            </div>
                            @endif
                        </td>
                    </tr>

                    {{-- ── Engagements ── --}}
                    <tr>
                        <td colspan="2" style="vertical-align:middle; padding:2px;"
                            class="border-right">
                            <div class="font-bold" style="font-size:7.5pt; text-align:center;">
                                Engagements -
                                <span style="font-weight:300; font-style:italic;">Commitments</span>
                            </div>

                            <table style="width:100%; border:none; margin-top:0; padding:0;">

                                @if ($ligneBudgetaire)
                                <tr>
                                    <td style="width:50%;">
                                        <div class="font-bold" style="font-size:7.5pt;">
                                            <strong>Montant disponible antérieur:</strong>
                                        </div>
                                        <div class="font-tiny" style="font-style:italic;">
                                            Amount available
                                        </div>
                                    </td>
                                    <td>
                                        {{ number_format($disponibleAvant, 0, ',', ' ') }} F CFA
                                        @if ($engagement->snapshot_disponible_avant !== null)
                                        <div class="snapshot-notice">
                                            📌 Figé au
                                            {{ \Carbon\Carbon::parse($engagement->date_engagement)
                                            ->format('d/m/Y') }}
                                        </div>
                                        @endif
                                    </td>
                                </tr>
                                @endif

                                <tr>
                                    <td style="width:50%; padding:2px 0;">
                                        <div class="font-bold" style="font-size:7.5pt;">
                                            <strong>Montant brut de l'ordonnance:</strong>
                                        </div>
                                        <div class="font-tiny" style="font-style:italic;">
                                            Gross amount
                                        </div>
                                    </td>
                                    <td>
                                        <strong>
                                            {{ number_format($montantBrut, 0, ',', ' ') }} F CFA
                                        </strong>
                                    </td>
                                </tr>

                                @if ($ligneBudgetaire)
                                <tr>
                                    <td style="width:50%;">
                                        <div class="font-bold" style="font-size:7.5pt;">
                                            <strong>Montant nouveau disponible:</strong>
                                        </div>
                                        <div class="font-tiny" style="font-style:italic;">
                                            New available amount
                                        </div>
                                    </td>
                                    <td>
                                        <span style="{{ $disponibleApres < 0
                                        ? 'color:red; font-weight:bold;' : '' }}">
                                            {{ number_format($disponibleApres, 0, ',', ' ') }} F CFA
                                        </span>
                                    </td>
                                </tr>
                                @endif

                                <tr>
                                    <td colspan="2">
                                        <div style="font-weight:bold; margin-bottom:1px;
                                                line-height:1.1;">
                                            DESIGNATION DU CREANCIER:
                                            <div style="font-weight:normal; font-style:italic;
                                                    font-size:7.5pt; line-height:1.1;">
                                                DESIGNATION OF THE CREDITOR:
                                            </div>
                                        </div>
                                        <div style="margin-top:4px; font-size:9pt;
                                                font-weight:bold; min-height:20px;
                                                line-height:1.1;">
                                            {{ $nomBeneficiaire }}
                                            - {{ $beneficiaire->adresse ?? '' }}
                                            - {{ $beneficiaire->ville   ?? '' }}
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                <div>
                    <div style="border-bottom:2px solid #000; margin:8px 0 0 0;"></div>
                    <div class="border-all" style="margin-top:2px; text-align:center;">
                        <div class="font-bold" style="font-size:7.5pt;">Visa Contrôle Financier</div>
                        <div class="font-tiny" style="font-style:italic;">Financial Control Stamp</div>
                    </div>
                </div>

                {{-- ✅ Hauteur réduite --}}
                <table style="width:100%;"> 
                    <tr>
                        <td style="height:197px; width:40%; position:relative;">
                            <div style="position:absolute; top:50%; left:50%;
                            transform:translate(-50%,-50%) rotate(-30deg);
                            color:rgba(0,0,0,0.2); font-size:14px; white-space:nowrap;">
                                Visa engagement <br>comptable
                            </div>
                        </td>
                        <td style="height:160px; width:60%; position:relative;">
                            <div style="position:absolute; top:50%; left:50%;
                            transform:translate(-50%,-50%) rotate(-30deg);
                            color:rgba(0,0,0,0.2); font-size:14px; white-space:nowrap;">
                                Validation de la <br>dépense
                            </div>
                        </td>
                    </tr>
                </table>
            </td>

            {{-- ══ COLONNE DROITE ═════════════════════════════════════ --}}
            <td style="width:30%; vertical-align:top; padding:4px;" class="border-right">

                <div style="font-size:7.5pt; line-height:1.1; text-align:left;
                        margin-bottom:20px;">
                    <div style="font-weight:bold;">Vu, bon à payer</div>
                    <div style="font-style:italic;">Votes available</div>
                </div>

                <table style="margin-bottom:6px; border-collapse:collapse;">
                    <tr>
                        <td style="width:60%; padding:0;">
                            <div class="font-bold" style="font-size:7.5pt; margin:0;">
                                Montant brut de l'ordonnance
                            </div>
                            <div class="font-tiny" style="font-style:italic; margin:0;">
                                Gross amount
                            </div>
                        </td>
                        <td style="width:40%; padding:0;">
                            <div class="border-all text-center padding-3">
                                <strong>{{ number_format($montantBrut, 0, ',', ' ') }}</strong>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0;">
                            <div class="font-bold" style="font-size:7.5pt; margin:0;">A PRECOMPTER</div>
                            <div class="font-tiny" style="font-style:italic; margin:0;">TO BE DEDUCTED</div>
                        </td>
                        <td style="padding:0;">
                            <div class="border-all text-center padding-3">
                                <strong>{{ number_format($montantTotalImpots, 0, ',', ' ') }}</strong>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0;">
                            <div class="font-bold" style="font-size:7.5pt; margin:0;">
                                Montant net à payer ou à virer
                            </div>
                            <div class="font-tiny" style="font-style:italic; margin:0;">
                                Net amount to be paid or transfer
                            </div>
                        </td>
                        <td style="padding:0;">
                            <div class="border-all text-center padding-3"
                                style="background-color:#f0f0f0;">
                                <strong>{{ number_format($montantNet, 0, ',', ' ') }}</strong>
                            </div>
                        </td>
                    </tr>
                </table>

                <div style="margin-top:5px; margin-bottom:5px;">
                    <div class="font-bold" style="font-size:7.5pt; text-align:left;">
                        Arrêté par nous le présent ordre de paiement au montant
                        (en toutes lettres) net à percevoir de:
                    </div>
                    <div class="font-tiny" style="font-style:italic; text-align:left;">
                        We hereby make up this order at the amount (in words) of
                    </div>
                    <div class="border-all" style="margin-top:5px; text-align:center;">
                        <strong style="font-size:7pt; text-transform:uppercase;">
                            @yield('montant_lettres')
                        </strong>
                    </div>
                </div>

                <table style="border-collapse:separate; border-spacing:20px 3px;
                          margin:0; padding:0;">
                    <tr>
                        <td style="border:none; width:15px; height:4px;">
                            <strong>Espèce</strong><br>Cash
                        </td>
                        <td style="border:none; width:15px; height:4px;">
                            <strong>Virement</strong><br>Transfer
                        </td>
                        <td style="border:none; width:15px; height:4px;">
                            <strong>Chèque</strong><br>Cheque
                        </td>
                    </tr>
                    <tr>
                        <td class="border-all" style="width:15px; height:12px;"></td>
                        <td class="border-all" style="width:15px; height:12px;"></td>
                        <td class="border-all" style="width:15px; height:12px;"></td>
                    </tr>
                </table>

                <div style="margin-bottom:35px; line-height:1.4;">
                    <div style="font-size:7.5pt;">
                        CNI N° _________________________ du _____________<br>
                        Délivrée par _______________________________<br>
                        A _________________ le _________________________
                    </div>
                </div>

                <div style="border-bottom:2px solid #000; margin:3px 0;"></div>

                <div style="margin-top:2px;">
                    <div style="font-size:7.5pt;">
                        En vertu des crédits ouverts au titre de l'imputation budgétaire désignée,
                        l'<strong>Ordonnateur</strong> soussigné ordonne sur la caisse de
                        {{ strtoupper($parametres->sigle) }},
                        le paiement de la créance détaillée ci-dessus.
                    </div>
                    <div class="font-tiny" style="font-style:italic;">
                        Pursuant to the appropriations opened under the designated budgetary
                        allocation, the undersigned <strong>Authorizing Officer</strong> orders,
                        from {{ strtoupper($parametres->sigle) }} treasury,
                        the payment of the above-detailed debt.
                    </div>
                </div>

                <div style="margin-top:6px;">
                    <div class="font-bold" style="font-size:7.5pt;">Yaoundé, le _____________</div>
                    <div class="font-tiny" style="font-style:italic;">Yaounde, the</div>
                    <div style="margin-top:10px; text-align:right; margin-bottom:70px;">
                        <div class="font-bold" style="font-size:7pt;">
                            (Signature et timbre de l'ordonnateur)
                        </div>
                        <div class="font-tiny" style="font-style:italic;">(Signature and stamp)</div>
                    </div>
                </div>
            </td>

        </tr>
    </table>

    @endsection