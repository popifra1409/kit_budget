@extends('pdf.layouts.master', ['typeHeader' => 'op', 'typeFooter' => 'op', 'orientation' => 'landscape'])

@php
$ordonnance = $donnees['_raw'];

// Charger l'engagement avec sa relation polymorphique
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

// ✅ PRIORITÉ 1 : snapshots figés à la date d'engagement
if ($engagement->snapshot_disponible_avant !== null) {

$dotationInitiale = (float) ($engagement->snapshot_budget_initial ?? $dotationInitiale);
$budgetRectifie = (float) ($engagement->snapshot_budget_rectifie ?? $budgetRectifie);
$disponibleAvant = (float) $engagement->snapshot_disponible_avant;
$disponibleApres = (float) $engagement->snapshot_disponible_apres;

} else {
// ✅ FALLBACK chronologique pour anciens engagements
// '<' au lieu de '!=' → uniquement les engagements ANTÉRIEURS
    $totalEngageAvant=\App\Models\Engagement::withoutGlobalScope('exercice')
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

        // ── Nomenclature (rechargement) ───────────────────────────
        if ($engagement) {
        $engagement->load('nomenclaturePrincipale');
        $nomenclature = $engagement->nomenclaturePrincipale;
        }

        // ── Hiérarchie budgétaire ─────────────────────────────────
        $tache = null;
        $activite = null;
        $action = null;
        $programme = null;
        $sousProgramme = null;
        $objectif = null;

        $codeProgramme = '';
        $codeSousProgramme = '';
        $codeAction = '';
        $codeActivite = '';
        $codeTache = '';
        $codeArticle = '';
        $codeParagraphe = '';
        $codeChapitre = '';

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
        ? $objectifs->first()
        : $objectifs;
        }
        } catch (\Exception $e) {
        \Log::warning('Erreur récupération objectif', [
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
        'cnps' => 0,
        'irnc' => 0,
        'feicom' => 0,
        'redevance_av' => 0,
        'autres' => 0,
        ];
        $montantTotalImpots = $detailImpots['ir']
        + $detailImpots['tva']
        + $detailImpots['tsr'];
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
        $montantTotalImpots = $detailImpots['ir']
        + $detailImpots['tva']
        + $detailImpots['cnps']
        + $detailImpots['irnc']
        + $detailImpots['feicom']
        + $detailImpots['redevance_av']
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
                margin: 8mm 8mm 8mm 8mm;
            }

            body {
                font-family: Arial, sans-serif;
                font-size: 8pt;
                line-height: 1.05;
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
                font-size: 7.5pt;
            }

            .font-tiny {
                font-size: 7pt;
            }

            .padding-5 {
                padding: 5px;
            }

            .padding-3 {
                padding: 3px;
            }

            .min-height-30 {
                min-height: 30px;
            }

            .info-box {
                border: 1px solid #000;
                padding: 4px;
                margin: 4px 0;
                font-size: 9pt;
            }

            .objet-block {
                margin: 3px 0;
                line-height: 1.05;
            }

            .objet-titre {
                font-weight: bold;
                font-size: 7pt;
                margin: 0;
            }

            .objet-sub {
                font-size: 6.5pt;
                font-style: italic;
                margin: 0;
            }

            .objet-text {
                margin-top: 2px;
                font-size: 8pt;
                line-height: 1.1;
                word-break: break-word;
                overflow-wrap: break-word;
            }

            .montant-block {
                margin-top: 5px;
                line-height: 1.05;
            }

            .separator {
                border-bottom: 2px solid #000;
                margin: 3px 0;
            }

            /* ✅ Notice snapshot */
            .snapshot-notice {
                font-size: 6.5pt;
                color: #555;
                font-style: italic;
                padding: 1px 3px;
                border-left: 2px solid #aaa;
                background: #f5f5f5;
                margin-bottom: 2px;
            }
        </style>
        @endsection

        @section('content')
        <table style="width: 100%; border: none; margin-top: 0px;">
            <tr>
                {{-- ── Colonne gauche : Imputation budgétaire ────────────── --}}
                <td style="width: 30%; vertical-align: top; padding: 5px;" class="border-right">
                    <div class="font-bold" style="font-size: 8pt; text-align:center;">IMPUTATION BUDGETAIRE</div>
                    <div class="font-tiny" style="font-style: italic; text-align:center;">BUDGETARY CHARGE</div>

                    <div style="margin-top: 5px; font-size: 8pt;">
                        @if ($codeProgramme || $codeSousProgramme)
                        <div>
                            <span class="font-bold" style="font-size: 8pt;">
                                • {{ $codeSousProgramme ? 'SOUS-PROGRAMME :' : 'PROGRAMME :' }}
                            </span>
                            @if ($codeSousProgramme)
                            <span style="font-weight: bold;">
                                {{ $codeSousProgramme }} - {{ $sousProgramme->libelle ?? '' }}
                            </span>
                            @else
                            {{ $codeProgramme }} - {{ $programme->libelle ?? '' }}
                            @endif
                        </div>
                        @endif

                        @if ($codeAction)
                        <div>• <span class="font-bold" style="font-size: 7.5pt;">ACTION : </span>
                            {{ $codeAction }} - {{ $action->libelle ?? '' }}
                        </div>
                        @endif

                        @if ($codeActivite)
                        <div>• <span class="font-bold" style="font-size: 7.5pt;">ACTIVITÉ : </span>
                            {{ $codeActivite }} - {{ $activite->libelle ?? '' }}
                        </div>
                        @endif

                        @if ($codeArticle)
                        <div>• <span class="font-bold" style="font-size: 7.5pt;">ARTICLE : </span>{{ $codeArticle }}</div>
                        @endif

                        @if ($codeParagraphe)
                        <div>• <span class="font-bold" style="font-size: 7.5pt;">PARAGRAPHE : </span>{{ $codeParagraphe }}</div>
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

                            <div style="margin-top:4px;">
                                <div class="objet-titre">Arrêté en toutes lettres:</div>
                                <div class="objet-sub">Closed at the sum of (in words)</div>
                                <div style="margin-top:2px; text-align:center;">
                                    <strong style="font-size:8pt; text-transform:uppercase;">
                                        @yield('montant_lettres_ttc')
                                    </strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div style="border-bottom: 2px solid #000; margin: 0;"></div>
                        <div style="margin-top: 3px;">
                            <div class="font-bold" style="font-size: 8pt;">PIECES JOINTES:</div>
                            <div style="margin-top: 3px; font-size: 6pt;">
                                • Ordre de mission<br>• Bulletin de solde<br>• Lettre d'invitation<br>
                                • Photocopie du passport<br>• Bon de commande administratif<br>
                                • Lettre commande<br>• Convention ou Contrat<br>• Facture Proforma<br>
                                • Expressions des besoins<br>• Certificat d'engagement<br>
                                • Facture définitive liquidée<br>• Procès verbal de réception<br>
                                • Bordereau de livraison<br>• Quittance d'enregistrement<br>
                                • Attestation de domiciliation bancaire<br>
                                • Attestation d'immatriculation<br>• Certificat de garantie<br>
                                • Avis d'imposition à l'enregistrement<br>
                                • Avis d'imposition des retenues à la source<br>
                                • Attestation de Non Redevance<br>
                                • Certificat de non exclusion (ARMP)<br>
                                • Plan de localisation<br>• Etc.
                            </div>
                        </div>
                    </div>
                </td>

                {{-- ── Colonne centrale ────────────────────────────────── --}}
                <td>
                    <table>
                        <tr>
                            <td colspan="2">
                                <div class="font-normal" style="font-size: 11.5pt; text-align:center;">
                                    MONTANT NET A PAYER:
                                    <strong>{{ number_format($montantNet, 0, ',', ' ') }}</strong> F CFA
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td style="width: 15%; vertical-align: middle; padding: 2px;" class="border-right">
                                <div style="margin-top: 2px; font-size: 8pt; line-height: 1.1;">
                                    <strong>CE N°</strong> {{ $engagement->numero }}
                                </div>
                                <div style="margin-top: 3px; font-size: 8pt; line-height: 1.1;">
                                    <strong>Du</strong>
                                    {{ $engagement->date_engagement->format('d/m/Y') }}
                                </div>
                            </td>
                            <td style="width: 25%; vertical-align: middle; padding: 2px;" class="border-right">
                                @if ($ligneBudgetaire)
                                <div class="info-line">
                                    <strong>DOTATION INITIALE:</strong>
                                    {{ number_format($dotationInitiale, 0, ',', ' ') }} F CFA
                                </div>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" style="width: 30%; vertical-align: middle; padding: 3px;" class="border-right">
                                <div class="font-bold" style="font-size: 8pt; text-align:center;">
                                    Engagements - <span style="font-weight:300; font-style: italic;">Commitments</span>
                                </div>

                                <table style="width: 100%; border: none; margin-top: 0px; padding: 0px;">

                                    @if ($ligneBudgetaire)
                                    <tr>
                                        <td style="width: 50%;">
                                            <div class="font-bold" style="font-size: 8pt;">
                                                <strong>Montant disponible antérieur:</strong>
                                            </div>
                                            <div class="font-tiny" style="font-style: italic;">Amount available</div>
                                        </td>
                                        <td>
                                            {{ number_format($disponibleAvant, 0, ',', ' ') }} F CFA
                                            {{-- ✅ Indicateur snapshot --}}
                                            @if ($engagement->snapshot_disponible_avant !== null)
                                            <div class="snapshot-notice">
                                                📌 Figé au
                                                {{ \Carbon\Carbon::parse($engagement->date_engagement)->format('d/m/Y') }}
                                            </div>
                                            @endif
                                        </td>
                                    </tr>
                                    @endif

                                    <tr>
                                        <td style="width: 50%; padding: 2px 0;">
                                            <div class="font-bold" style="font-size: 8pt;">
                                                <strong>Montant brut de l'ordonnance:</strong>
                                            </div>
                                            <div class="font-tiny" style="font-style: italic;">Gross amount</div>
                                        </td>
                                        <td>
                                            <strong>{{ number_format($montantBrut, 0, ',', ' ') }} F CFA</strong>
                                        </td>
                                    </tr>

                                    @if ($ligneBudgetaire)
                                    <tr>
                                        <td style="width: 50%;">
                                            <div class="font-bold" style="font-size: 8pt;">
                                                <strong>Montant nouveau disponible:</strong>
                                            </div>
                                            <div class="font-tiny" style="font-style: italic;">New available amount</div>
                                        </td>
                                        <td>
                                            <span style="{{ $disponibleApres < 0 ? 'color: red; font-weight: bold;' : '' }}">
                                                {{ number_format($disponibleApres, 0, ',', ' ') }} F CFA
                                            </span>
                                        </td>
                                    </tr>
                                    @endif

                                    <tr>
                                        <td colspan="2">
                                            <div style="font-weight: bold; margin-bottom: 2px; line-height: 1.1;">
                                                DESIGNATION DU CREANCIER:
                                                <div style="font-weight: normal; font-style: italic; font-size: 8pt; line-height: 1.1;">
                                                    DESIGNATION OF THE CREDITOR:
                                                </div>
                                            </div>
                                            <div style="margin-top: 8px; font-size: 10pt; font-weight: bold; min-height: 30px; line-height: 1.1;">
                                                {{ $nomBeneficiaire }} - {{ $beneficiaire->adresse ?? '' }} -
                                                {{ $beneficiaire->ville ?? '' }}
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>

                    <div>
                        <div style="border-bottom: 2px solid #000; margin: 15px 0 0 0;"></div>
                        <div class="border-all" style="margin-top: 3px; text-align:center;">
                            <div class="font-bold" style="font-size: 8pt;">Visa Contrôle Financier</div>
                            <div class="font-tiny" style="font-style: italic;">Financial Control Stamp</div>
                        </div>
                    </div>

                    <table style="width:100%;">
                        <tr>
                            <td style="height:180px; width:40%; position:relative;">
                                <div style="position:absolute; top:50%; left:50%;
                            transform:translate(-50%, -50%) rotate(-30deg);
                            color: rgba(0,0,0,0.2); font-size: 18px; white-space:nowrap;">
                                    Visa engagement <br>comptable
                                </div>
                            </td>
                            <td style="height:180px; width:60%; position:relative;">
                                <div style="position:absolute; top:50%; left:50%;
                            transform:translate(-50%, -50%) rotate(-30deg);
                            color: rgba(0,0,0,0.2); font-size: 18px; white-space:nowrap;">
                                    Validation de la <br>dépense
                                </div>
                            </td>
                        </tr>
                    </table>
                </td>

                {{-- ── Colonne droite ──────────────────────────────────── --}}
                <td style="width: 30%; vertical-align: top; padding: 5px;" class="border-right">
                    <div style="font-size: 8pt; line-height: 1.1; text-align:left; margin-bottom: 35px;">
                        <div style="font-weight: bold;">Vu, bon à payer</div>
                        <div style="font-style: italic;">Votes available</div>
                    </div>

                    <table style="margin-bottom: 8px; border-collapse: collapse;">
                        <tr>
                            <td style="width: 60%; padding: 0;">
                                <div class="font-bold" style="font-size: 8pt; margin:0;">Montant brut de l'ordonnance</div>
                                <div class="font-tiny" style="font-style: italic; margin:0;">Gross amount</div>
                            </td>
                            <td style="width: 40%; padding: 0;">
                                <div class="border-all text-center padding-3">
                                    <strong>{{ number_format($montantBrut, 0, ',', ' ') }}</strong>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 0;">
                                <div class="font-bold" style="font-size: 8pt; margin:0;">A PRECOMPTER</div>
                                <div class="font-tiny" style="font-style: italic; margin:0;">TO BE DEDUCTED</div>
                            </td>
                            <td style="padding: 0;">
                                <div class="border-all text-center padding-3">
                                    <strong>{{ number_format($montantTotalImpots, 0, ',', ' ') }}</strong>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 0;">
                                <div class="font-bold" style="font-size: 8pt; margin:0;">Montant net à payer ou à virer</div>
                                <div class="font-tiny" style="font-style: italic; margin:0;">Net amount to be paid or transfer</div>
                            </td>
                            <td style="padding: 0;">
                                <div class="border-all text-center padding-3" style="background-color: #f0f0f0;">
                                    <strong>{{ number_format($montantNet, 0, ',', ' ') }}</strong>
                                </div>
                            </td>
                        </tr>
                    </table>

                    <div style="margin-top: 8px; margin-bottom: 8px;">
                        <div class="font-bold" style="font-size: 8pt; text-align: left;">
                            Arrêté par nous le présent ordre de paiement au montant (en toutes lettres) net à percevoir de:
                        </div>
                        <div class="font-tiny" style="font-style: italic; text-align: left;">
                            We hereby make up this order at the amount (in words) of
                        </div>
                        <div class="border-all" style="margin-top: 10px; text-align: center;">
                            <strong style="font-size: 8pt; text-transform: uppercase;">@yield('montant_lettres')</strong>
                        </div>
                    </div>

                    <table style="border-collapse: separate; border-spacing: 30px 5px; margin:0; padding:0;">
                        <tr>
                            <td style="border:none; width: 15px; height:5px;">
                                <strong>Espèce</strong><br>Cash
                            </td>
                            <td style="border:none; width: 15px; height:5px;">
                                <strong>Virement</strong><br>Transfer
                            </td>
                            <td style="border:none; width: 15px; height:5px;">
                                <strong>Chèque</strong><br>Cheque
                            </td>
                        </tr>
                        <tr>
                            <td class="border-all" style="width: 15px; height:15px;"></td>
                            <td class="border-all" style="width: 15px; height:15px;"></td>
                            <td class="border-all" style="width: 15px; height:15px;"></td>
                        </tr>
                    </table>

                    <div style="margin-bottom: 10px; line-height:1.4;">
                        <div style="font-size: 8pt;">
                            CNI N° _________________________ du _____________<br>
                            Délivrée par _______________________________<br>
                            A _________________ le _________________________
                        </div>
                    </div>

                    <div style="border-bottom: 2px solid #000; margin: 5px 0;"></div>

                    <div style="margin-top: 3px;">
                        <div style="font-size: 8pt;">
                            En vertu des crédits ouverts au titre de l'imputation budgétaire désignée,
                            l'<strong>Ordonnateur</strong> soussigné ordonne sur la caisse de
                            {{ strtoupper($parametres->sigle) }}, le paiement de la créance détaillée ci-dessus.
                        </div>
                        <div class="font-tiny" style="font-style: italic;">
                            Pursuant to the appropriations opened under the designated budgetary allocation,
                            the undersigned <strong>Authorizing Officer</strong> orders, from
                            {{ strtoupper($parametres->sigle) }} treasury, the payment of the above-detailed debt.
                        </div>
                    </div>

                    <div style="margin-top: 10px;">
                        <div class="font-bold" style="font-size: 8pt;">Yaoundé, le _____________</div>
                        <div class="font-tiny" style="font-style: italic;">Yaounde, the</div>
                        <div style="margin-top: 15px; text-align: right;">
                            <div class="font-bold" style="font-size: 7.5pt;">(Signature et timbre de l'ordonnateur)</div>
                            <div class="font-tiny" style="font-style: italic;">(Signature and stamp)</div>
                        </div>
                    </div>
                </td>
            </tr>
        </table>
        @endsection