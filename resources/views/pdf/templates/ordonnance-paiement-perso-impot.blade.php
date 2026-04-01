{{-- resources/views/pdf/templates/ordonnance-paiement-impot-perso.blade.php --}}
{{-- Portrait A4 — OPT Impôts/Taxes — Calqué sur ordonnance-paiement-perso.blade --}}
@php
    $ordonnance = $donnees['_raw'];
    $params = \App\Models\ParametresStructure::where('actif', true)->first();

    // ── Charger les relations ─────────────────────────────
    if (!$ordonnance->relationLoaded('engagement')) {
        $ordonnance->load([
            'engagement.nomenclaturePrincipale',
            'engagement.engageable',
            'engagement.exercice',
            'exercice',
            'beneficiaire',
        ]);
    }

    $engagement = $ordonnance->engagement;
    $engageable = $engagement?->engageable;
    $documentSource = $engageable;

    // ── Paramètres structure ──────────────────────────────
    $nomStructure = $params?->nom_complet ?? 'HOPITAL GENERAL DE YAOUNDE';
    $sigle = $params?->sigle ?? 'HGY';
    $ville = $params?->ville ?? 'Yaoundé';
    $bp = $params?->bp ?? 'B.P 5408 YAOUNDE';
    $tel = $params?->telephone ?? '(237) 222 21 20 18';
    $fax = $params?->fax ?? '(237) 222 21 20 15';
    $nomCourtEn = $params?->nom_structure_en ?? '';
    $sous_direction = $params?->sous_direction ?? 'DAAF';

    // ── Logo ──────────────────────────────────────────────
    $logoPath = null;
    $logoExists = false;
    if ($params?->logo) {
        $logoPath = public_path('storage/' . ltrim($params->logo, '/'));
        $logoExists = file_exists($logoPath);
    }

    // ── Numéros ───────────────────────────────────────────
    $numOP = $ordonnance->numero ?? '—';
    $numEngagement = $engagement?->numero ?? '—';
    $annee = $ordonnance->exercice?->annee
        ?? $engagement?->exercice?->annee
        ?? now()->year;
    $moisEmission = $ordonnance->date_emission
        ? $ordonnance->date_emission->format('m/Y')
        : now()->format('m/Y');
    $dateEmission = $ordonnance->date_emission
        ? $ordonnance->date_emission->format('d/m/Y')
        : now()->format('d/m/Y');

    $imputation = $engagement?->nomenclaturePrincipale?->code ?? '';

    // ── Reverseur (contribuable ayant subi les retenues) ──
    $reverseur = null;
    if ($documentSource) {
        if ($engagement?->estBonCommande()) {
            $reverseur = $documentSource->fournisseur;
        } elseif ($engagement?->estDecision()) {
            $reverseur = $documentSource->personnel;
        }
    }
    if (!$reverseur && $ordonnance->beneficiaire) {
        $reverseur = $ordonnance->beneficiaire;
    }
    $nomReverseur = $reverseur?->raison_sociale
        ?? $reverseur?->nom_complet
        ?? $reverseur?->name
        ?? '—';

    // ── Bénéficiaire OPT = LE RECEVEUR ────────────────────
    $nomBeneficiaire = 'LE RECEVEUR';

    // ── Détail impôts via getDetailImpots() ───────────────
    $detailImpots = $ordonnance->getDetailImpots();
    $montantTotalImpots = (float) ($detailImpots['total'] ?? 0);

    // ── Montants OPT ──────────────────────────────────────
    // Montant brut OPT = 0 (pas de brut propre à l'OPT)
    $montantBrut = 0;
    // A précompter = total des impôts
    $aPrecompter = $montantTotalImpots;
    // Somme nette = total impôts (ce qu'on reverse)
    $sommeNette = $montantTotalImpots;

    // Référence OP principale
    $opPrincipaleNumero = $ordonnance->op_principale_numero
        ?? $ordonnance->opPrincipale?->numero
        ?? $numEngagement;

    $objet = $ordonnance->objet
        ?? 'Reversement des impôts et taxes — OP N° ' . $opPrincipaleNumero;

    $montantLettres = \App\Helpers\NombreEnLettres::montantCFA($sommeNette);
@endphp
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>OPT N° {{ $numOP }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 7mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 8pt;
            color: #000;
            margin: 0;
            padding: 0;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        td,
        th {
            font-size: 8.5pt;
        }

        .b {
            font-weight: bold;
        }

        .it {
            font-style: italic;
        }

        .sm {
            font-size: 8pt;
        }

        .xsm {
            font-size: 7pt;
        }

        .tr {
            text-align: right;
        }

        .tc {
            text-align: center;
        }

        .bg {
            background: #f0f0f0;
        }

        .ba {
            border: 1px solid #000;
        }

        .bt {
            border-top: 1px solid #000;
        }

        .bb {
            border-bottom: 1px solid #000;
        }

        .br {
            border-right: 1px solid #000;
        }

        .val-box {
            border: 1.5px solid #000;
            padding: 0.5mm 2mm;
            font-weight: bold;
            font-size: 9pt;
        }

        .op-title {
            font-size: 10pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .op-struct {
            font-size: 9.5pt;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.3;
        }

        .footer {
            font-size: 6.5pt;
            margin-top: 1.5mm;
            border-top: 1px solid #000;
            padding-top: 0.5mm;
        }
    </style>
</head>

<body>

    {{-- ═══════════════════════════════════════
    EN-TÊTE — identique à ordonnance-paiement-perso
    ═══════════════════════════════════════ --}}
    <table style="margin-bottom:1.5mm;">
        <tr>
            {{-- Col 1 : Logo + Visa DAAF --}}
            <td class="ba" style="width:22%; padding:2mm; vertical-align:top;">
                @if($logoExists)
                    <div style="text-align:center; margin-bottom:1.5mm;">
                        <img src="{{ $logoPath }}" style="max-height:14mm; max-width:26mm;">
                    </div>
                @endif
                <div class="b" style="font-size:7.5pt; line-height:1.3; text-align:center;">
                    VISA {{ $sous_direction }}
                </div>
                <div style="height:14mm;"></div>
            </td>

            {{-- Col 2 : Titre central --}}
            <td style="width:44%; vertical-align:middle; text-align:center; padding:2mm;">
                <div class="op-struct">{{ $nomStructure }}</div>
                <div class="it" style="font-size:7.5pt;">{{ strtoupper($nomCourtEn) }}</div>
                <div style="font-size:6.5pt; margin-top:0.5mm;">
                    {{ $bp }} &nbsp; Tél. {{ $tel }} &nbsp; Fax {{ $fax }}
                </div>
                <div class="bt bb" style="margin:1.5mm 0; padding:0.8mm 0;">
                    <div class="op-title">ORDONNANCE DE PAIEMENT — IMPÔT</div>
                    <div style="font-size:7.5pt;"><em>TAX PAYMENT ORDER</em></div>
                </div>
                <div class="bt sm it" style="padding-top:0.8mm; line-height:1.3;">
                    L'Agent comptable de l'{{ $sigle }} est autorisé à reverser les impôts et taxes<br>
                    <em class="xsm">
                        The accounting officer of the {{ $sigle }} is hereby authorized to remit taxes
                    </em>
                </div>
            </td>

            {{-- Col 3 : Boxes numéros --}}
            <td style="width:34%; vertical-align:top; padding:0;">
                <table>
                    <tr>
                        <td class="ba bg sm" style="padding:0.8mm 1.5mm; width:60%;">
                            Mois et exercice d'émission<br><em>Month and budgetary</em>
                        </td>
                        <td class="ba tr b" style="padding:0.8mm 1.5mm;">{{ $moisEmission }}</td>
                    </tr>
                    <tr>
                        <td class="ba bg sm" style="padding:0.8mm 1.5mm;">
                            Exercice budgétaire<br><em>Budgetary year</em>
                        </td>
                        <td class="ba tr b" style="padding:0.8mm 1.5mm;">{{ $annee }}</td>
                    </tr>
                    <tr>
                        <td class="ba bg sm" style="padding:0.8mm 1.5mm;">
                            Réf. OP principale<br><em>Main payment order ref.</em>
                        </td>
                        <td class="ba tr b" style="padding:0.8mm 1.5mm;">{{ $opPrincipaleNumero }}</td>
                    </tr>
                    <tr>
                        <td class="ba bg sm" style="padding:0.8mm 1.5mm;">
                            N° Emission<br><em>N° of emission</em>
                        </td>
                        <td class="ba tr b" style="padding:0.8mm 1.5mm;">{{ $numOP }}</td>
                    </tr>
                    <tr>
                        <td class="ba bg sm" style="padding:0.8mm 1.5mm;">
                            N° OPT<br><em>N° of tax order</em>
                        </td>
                        <td class="ba tr b" style="padding:0.8mm 1.5mm;">{{ $numOP }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- ═══════════════════════════════════════
    CORPS
    ═══════════════════════════════════════ --}}
    <table>

        {{-- ── R1 : Objet + Imputation + Montant ── --}}
        <tr>
            <td class="ba" style="width:55%; padding:1.5mm 2mm;">
                <div class="b" style="font-size:7.5pt;">OBJET DE LA DEPENSE:</div>
                <div class="sm it">SUBJECT OF EXPENDITURE:</div>
                <div class="b" style="margin-top:0.8mm; font-size:9pt;">{{ strtoupper($objet) }}</div>
            </td>
            <td class="ba bg tc" style="width:14%; padding:1mm; vertical-align:middle;">
                <span class="b">Imputation</span><br>
                <em class="xsm">Imputation</em>
            </td>
            <td class="ba bg tc" style="width:31%; padding:1mm; vertical-align:middle;">
                <span class="b">Montant:</span><br>
                <em class="xsm">Amount</em>
            </td>
        </tr>

        {{-- ── R2 : Référence OP principale ── --}}
        <tr>
            <td class="ba" style="padding:1mm 2mm;">
                <span style="font-size:7.5pt;">Reversement sur OP N°</span>
                <strong style="margin-left:4mm;">{{ $opPrincipaleNumero }}</strong>
            </td>
            <td class="ba tr" style="padding:1mm 2mm;">{{ $imputation }}</td>
            <td class="ba tr b" style="padding:1mm 2mm;">
                {{ number_format($montantTotalImpots, 0, ',', ' ') }}
            </td>
        </tr>

        {{-- ── R3–R5 : Reverseur + Bénéficiaire (rowspan 3) + détail + boxes montants ── --}}
        <tr>
            <td class="ba" rowspan="3" style="vertical-align:top; padding:2mm;">

                {{-- Reverseur --}}
                <div class="b sm">REVERSEUR (Contribuable ayant subi les retenues):</div>
                <div class="sm it">TAXPAYER HAVING INCURRED THE DEDUCTIONS:</div>
                <div class="b" style="margin-top:1.5mm; font-size:9.5pt;">
                    {{ strtoupper($nomReverseur) }}
                </div>

                <div style="border-bottom:1px solid #000; margin:2mm 0;"></div>

                {{-- Bénéficiaire = LE RECEVEUR --}}
                <div class="b sm">DESIGNATION DU CREANCIER(1):</div>
                <div class="sm it">DESIGNATION OF THE CREDITOR(1):</div>
                <div class="b" style="margin-top:1mm; font-size:10pt;">
                    {{ strtoupper($nomBeneficiaire) }}
                </div>

                <div style="margin-top:3mm;">
                    <div class="b xsm">PIECES JUSTIFICATIVES DE LA DEPENSE(1)</div>
                    <div class="xsm it">RELEVANT OF THE CREDITOR(1)</div>
                    <div style="height:8mm;"></div>
                </div>

                {{-- Détail impôts --}}
                <div class="b sm" style="margin-bottom:1mm;">DÉTAIL DES IMPÔTS ET TAXES:</div>
                <table style="width:100%;">
                    @if(($detailImpots['ir'] ?? 0) > 0)
                        <tr>
                            <td class="ba bg xsm" style="padding:0.5mm 1.5mm; width:65%;">
                                Impôt sur le Revenu (IR)
                            </td>
                            <td class="ba tr b xsm" style="padding:0.5mm 1.5mm;">
                                {{ number_format($detailImpots['ir'], 0, ',', ' ') }}
                            </td>
                        </tr>
                    @endif
                    @if(($detailImpots['tva'] ?? 0) > 0)
                        <tr>
                            <td class="ba bg xsm" style="padding:0.5mm 1.5mm;">TVA</td>
                            <td class="ba tr b xsm" style="padding:0.5mm 1.5mm;">
                                {{ number_format($detailImpots['tva'], 0, ',', ' ') }}
                            </td>
                        </tr>
                    @endif
                    @if(($detailImpots['tsr'] ?? 0) > 0)
                        <tr>
                            <td class="ba bg xsm" style="padding:0.5mm 1.5mm;">TSR</td>
                            <td class="ba tr b xsm" style="padding:0.5mm 1.5mm;">
                                {{ number_format($detailImpots['tsr'], 0, ',', ' ') }}
                            </td>
                        </tr>
                    @endif
                    @if(($detailImpots['cnps'] ?? 0) > 0)
                        <tr>
                            <td class="ba bg xsm" style="padding:0.5mm 1.5mm;">CNPS</td>
                            <td class="ba tr b xsm" style="padding:0.5mm 1.5mm;">
                                {{ number_format($detailImpots['cnps'], 0, ',', ' ') }}
                            </td>
                        </tr>
                    @endif
                    @if(($detailImpots['irnc'] ?? 0) > 0)
                        <tr>
                            <td class="ba bg xsm" style="padding:0.5mm 1.5mm;">IRNC</td>
                            <td class="ba tr b xsm" style="padding:0.5mm 1.5mm;">
                                {{ number_format($detailImpots['irnc'], 0, ',', ' ') }}
                            </td>
                        </tr>
                    @endif
                    @if(($detailImpots['feicom'] ?? 0) > 0)
                        <tr>
                            <td class="ba bg xsm" style="padding:0.5mm 1.5mm;">FEICOM</td>
                            <td class="ba tr b xsm" style="padding:0.5mm 1.5mm;">
                                {{ number_format($detailImpots['feicom'], 0, ',', ' ') }}
                            </td>
                        </tr>
                    @endif
                    @if(($detailImpots['redevance_av'] ?? 0) > 0)
                        <tr>
                            <td class="ba bg xsm" style="padding:0.5mm 1.5mm;">Redevance AV</td>
                            <td class="ba tr b xsm" style="padding:0.5mm 1.5mm;">
                                {{ number_format($detailImpots['redevance_av'], 0, ',', ' ') }}
                            </td>
                        </tr>
                    @endif
                    @if(($detailImpots['autres'] ?? 0) > 0)
                        <tr>
                            <td class="ba bg xsm" style="padding:0.5mm 1.5mm;">Autres retenues</td>
                            <td class="ba tr b xsm" style="padding:0.5mm 1.5mm;">
                                {{ number_format($detailImpots['autres'], 0, ',', ' ') }}
                            </td>
                        </tr>
                    @endif
                    {{-- Total --}}
                    <tr class="bg">
                        <td class="ba b xsm" style="padding:0.8mm 1.5mm;">TOTAL</td>
                        <td class="ba tr b" style="padding:0.8mm 1.5mm; font-size:9pt;">
                            {{ number_format($montantTotalImpots, 0, ',', ' ') }}
                        </td>
                    </tr>
                </table>
            </td>

            {{-- Montant brut = 0 --}}
            <td class="ba" colspan="2" style="padding:1mm 2mm;">
                <table>
                    <tr>
                        <td style="border:none; padding:0; width:60%; font-size:7pt;">
                            Montant brut de l'ordonnance<br>
                            <em class="xsm">Gross amount of the order</em>
                        </td>
                        <td style="border:none; padding:0; text-align:right;">
                            <span class="val-box">{{ number_format($montantBrut, 0, ',', ' ') }}</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr>
            {{-- A précompter = total impôts --}}
            <td class="ba" colspan="2" style="padding:1mm 2mm;">
                <table>
                    <tr>
                        <td style="border:none; padding:0; width:60%; font-size:7pt;">
                            A PRECOMPTER (total impôts et taxes)<br>
                            <em class="xsm">TO BE DEDUCTED (total taxes)</em>
                        </td>
                        <td style="border:none; padding:0; text-align:right;">
                            <span class="val-box">{{ number_format($aPrecompter, 0, ',', ' ') }}</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr>
            {{-- Somme nette = total à reverser --}}
            <td class="ba" colspan="2" style="padding:1mm 2mm;">
                <table>
                    <tr>
                        <td style="border:none; padding:0; width:60%; font-size:7pt;">
                            Somme nette à payer ou à virer(A)<br>
                            <em class="xsm">Net sum to be paid or tranfered(A)</em>
                        </td>
                        <td style="border:none; padding:0; text-align:right;">
                            <span class="val-box">{{ number_format($sommeNette, 0, ',', ' ') }}</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- ── R6 : Montant en lettres ── --}}
        <tr>
            <td class="ba" colspan="3" style="text-align:center; padding:1.5mm 2mm;">
                <div class="sm it">
                    Arrêté par nous le présent ordre de paiement à la somme de:<br>
                    <em class="xsm">We hereby write up this order at the amount of:</em>
                </div>
                <div class="b" style="font-size:9.5pt; margin-top:1mm; text-transform:uppercase;">
                    {{ ucfirst($montantLettres) }}
                </div>
            </td>
        </tr>

        {{-- ── R7 : Agent comptable + Émis le + Signature ── --}}
        <tr>
            <td class="ba" style="width:30%; vertical-align:top; padding:2mm;">
                <div class="b sm">L'AGENT COMPTABLE:</div>
                <div class="sm it">THE ACCOUNTING OFFICER</div>
                <div style="height:20mm;"></div>
            </td>
            <td class="ba" colspan="2" style="padding:0; vertical-align:top;">
                <table>
                    <tr>
                        <td
                            style="border:none; border-right:1px solid #ccc; padding:2mm; width:50%; vertical-align:top;">
                            <div class="sm it">émis à {{ $ville }} le<br>
                                <em class="xsm">Issued at {{ $ville }} on</em>
                            </div>
                            <div class="b" style="margin-top:0.8mm;">{{ $dateEmission }}</div>
                            <div style="height:18mm;"></div>
                        </td>
                        <td style="border:none; padding:2mm; width:50%; vertical-align:bottom;
                                text-align:center; padding-top:10mm;">
                            <div class="xsm it">
                                (Signature et timbre de l'ordonnateur)<br>
                                <em>(Signature and stamp of the Vote Holder)</em>
                            </div>
                            <div style="height:18mm;"></div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- ── R8 : Paiement par + Contrôleur Financier ── --}}
        <tr>
            <td class="ba" style="vertical-align:top; padding:2mm;">
                <div class="b sm">PAIEMENT PAR:</div>
                <div class="xsm it">We hereby make up this order at</div>
                <div style="height:7mm;"></div>
                <div style="font-size:7pt;">
                    A {{ $ville }}, le _______________<br>
                    <em class="xsm">At {{ $ville }}, on the</em>
                </div>
                <div style="height:8mm;"></div>
            </td>
            <td colspan="2" class="ba" style="vertical-align:top; padding:2mm; text-align:center;">
                <div class="b sm">Le Contrôleur Financier</div>
                <div class="sm it">The Financial Controller</div>
                <div style="height:20mm;"></div>
            </td>
        </tr>

        {{-- ── R9 : Compte à créditer ── --}}
        <tr>
            <td class="ba" colspan="3" style="vertical-align:top; padding:2mm;">
                <div class="b sm">COMPTE A CREDITER (Receveur des Impôts)</div>
                <div class="xsm it">ACCOUNT TO BE CREDITED (Tax collector)</div>
                <div style="height:10mm;"></div>
            </td>
        </tr>

    </table>

    {{-- ═══════════════════════════════════════
    FOOTER
    ═══════════════════════════════════════ --}}
    <div class="footer">
        <p style="margin:0;">1)Nom, Prénom, adresse complète. Pour les sociétés: Raisons sociales exactes.</p>
        <p style="margin:0;">1)Surname, name and full adress. Precise company name</p>
    </div>

</body>

</html>