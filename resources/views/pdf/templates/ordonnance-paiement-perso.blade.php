{{-- resources/views/pdf/templates/ordonnance-paiement-perso.blade.php --}}
{{-- Portrait A4 — Standalone — Variables alignées avec ordonnance-paiement.blade --}}
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
    $nomCourtEn = $params?->nom_structure_en;
    $sous_direction = $params?->sous_direction;

    // ── Logo ──────────────────────────────────────────────
    $logoPath = null;
    $logoExists = false;
    if ($params?->logo) {
        $logoPath = public_path('storage/' . ltrim($params->logo, '/'));
        $logoExists = file_exists($logoPath);
    }

    // ── Numéros ───────────────────────────────────────────
    $numOP = $ordonnance->numero ?? '—';
    $numEngagement = $engagement?->numero ?? '—';  // ← Paiement selon le bon + N° BC
    $annee = $ordonnance->exercice?->annee ?? $engagement?->exercice?->annee ?? now()->year;
    $moisEmission = $ordonnance->date_emission
        ? $ordonnance->date_emission->format('m/Y')
        : now()->format('m/Y');
    $dateEmission = $ordonnance->date_emission
        ? $ordonnance->date_emission->format('d/m/Y')
        : now()->format('d/m/Y');

    $imputation = $engagement?->nomenclaturePrincipale?->code ?? '';

    // ── Bénéficiaire ──────────────────────────────────────
    $beneficiaire = null;
    if ($ordonnance->beneficiaire) {
        $beneficiaire = $ordonnance->beneficiaire;
    } elseif ($documentSource) {
        if ($engagement?->estBonCommande()) {
            $beneficiaire = $documentSource->fournisseur;
        } elseif ($engagement?->estDecision()) {
            $beneficiaire = $documentSource->personnel;
        }
    }
    $nomBeneficiaire = $beneficiaire?->raison_sociale
        ?? $beneficiaire?->nom_complet
        ?? $beneficiaire?->name
        ?? $ordonnance->beneficiaire_nom
        ?? '—';

    // ── Montants — identique à la logique de ordonnance-paiement.blade ──
    if ($engagement && $engagement->engageable) {
        $donneesEngagement = $engagement->extraireDonneesDocument();

        if ($engagement->estBonCommande()) {
            // BC : montant brut = TTC, imputation = HT
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
        } else {
            // DA : montant brut = brut avant retenues
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
        }

        // ✅ A précompter = somme de tous les impôts/taxes
        $aPrecompter = array_sum($detailImpots);

        // ✅ Somme nette = montant brut - a précompter
        $sommeNette = $montantBrut - $aPrecompter;

    } else {
        // Fallback
        $montantBrut = (float) ($ordonnance->montant_brut ?? 0);
        $aPrecompter = (float) ($ordonnance->montant_ir ?? 0);
        $sommeNette = (float) ($ordonnance->montant_net ?? $montantBrut - $aPrecompter);
        $detailImpots = [
            'ir' => $aPrecompter,
            'tva' => 0,
            'cnps' => 0,
            'irnc' => 0,
            'feicom' => 0,
            'redevance_av' => 0,
            'autres' => 0,
            'tsr' => 0
        ];
    }

    $objet = $ordonnance->objet ?? $documentSource?->objet ?? '—';
    $montantLettres = \App\Helpers\NombreEnLettres::montantCFA($sommeNette);
    $montantBrutLettres = \App\Helpers\NombreEnLettres::montantCFA($montantBrut);
@endphp
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>OP N° {{ $numOP }}</title>
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
    EN-TÊTE
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
                    <div class="op-title">ORDONNANCE DE PAIEMENT</div>
                    <div style="font-size:7.5pt;"><em>PAYMENT ORDER</em></div>
                </div>
                <div class="bt sm it" style="padding-top:0.8mm; line-height:1.3;">
                    L'Agent comptable de l'{{ $sigle }} est autorisé à payer la<br>
                    <em class="xsm">The accounting officer of the {{ $sigle }} is hereby autorized to pay the debit</em>
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
                            N° de bon de caisse<br><em>N° of the cash voucher</em>
                        </td>
                        {{-- ✅ N° de bon de caisse = N° engagement --}}
                        <td class="ba tr b" style="padding:0.8mm 1.5mm;">{{ $numEngagement }}</td>
                    </tr>
                    <tr>
                        <td class="ba bg sm" style="padding:0.8mm 1.5mm;">
                            N° Emission<br><em>N° of emission</em>
                        </td>
                        <td class="ba tr b" style="padding:0.8mm 1.5mm;">{{ $numOP }}</td>
                    </tr>
                    <tr>
                        <td class="ba bg sm" style="padding:0.8mm 1.5mm;">
                            N° OP<br><em>N° of OP</em>
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

        {{-- ── R1 : En-têtes Objet / Imputation / Montant ── --}}
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

        {{-- ── R2 : Référence engagement ── --}}
        <tr>
            <td class="ba" style="padding:1mm 2mm;">
                <span style="font-size:7.5pt;">Paiement selon le bon</span>
                {{-- ✅ N° engagement --}}
                <strong style="margin-left:4mm;">{{ $numEngagement }}</strong>
            </td>
            <td class="ba tr" style="padding:1mm 2mm;">{{ $imputation }}</td>
            <td class="ba tr b" style="padding:1mm 2mm;">
                {{ number_format($montantBrut, 0, ',', ' ') }}
            </td>
        </tr>

        {{-- ── R3–R5 : Bénéficiaire (rowspan 3) + 3 boxes montants ── --}}
        <tr>
            <td class="ba" rowspan="3" style="vertical-align:top; padding:2mm;">
                <div class="b sm">DESIGNATION DU CREANCIER(1):</div>
                <div class="sm it">DESIGNATION OF THE CREDITOR(1):</div>
                <div class="b" style="margin-top:2mm; font-size:10pt;">
                    {{ strtoupper($nomBeneficiaire) }}
                </div>
                <div style="margin-top:5mm;">
                    <div class="b xsm">PIECES JUSTIFICATIVES DE LA DEPENSE(1)</div>
                    <div class="xsm it">RELEVANT OF THE CREDITOR(1)</div>
                    <div style="height:16mm;"></div>
                </div>
            </td>

            {{-- ✅ Montant brut = montant engagé --}}
            <td class="ba" colspan="2" style="padding:1mm 2mm;">
                <table>
                    <tr>
                        <td style="border:none; padding:0; width:60%; font-size:7pt;">
                            Montant brut de l'ordonnance<br>
                            <em class="xsm">Gross amount of the orther</em>
                        </td>
                        <td style="border:none; padding:0; text-align:right;">
                            <span class="val-box">{{ number_format($montantBrut, 0, ',', ' ') }}</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr>
            {{-- ✅ A précompter = somme impôts/taxes --}}
            <td class="ba" colspan="2" style="padding:1mm 2mm;">
                <table>
                    <tr>
                        <td style="border:none; padding:0; width:60%; font-size:7pt;">
                            A PRECOMPTER
                            @if(array_sum($detailImpots) > 0)
                                <span class="xsm it">
                                    (IR: {{ number_format($detailImpots['ir'], 0, ',', ' ') }}
                                    @if($detailImpots['tva'] > 0)
                                        / TVA: {{ number_format($detailImpots['tva'], 0, ',', ' ') }}
                                    @endif
                                    @if($detailImpots['cnps'] > 0)
                                        / CNPS: {{ number_format($detailImpots['cnps'], 0, ',', ' ') }}
                                    @endif
                                    @if($detailImpots['irnc'] > 0)
                                        / IRNC: {{ number_format($detailImpots['irnc'], 0, ',', ' ') }}
                                    @endif
                                    )
                                </span>
                            @endif
                            <br><em class="xsm">TO BE DEDUCTED</em>
                        </td>
                        <td style="border:none; padding:0; text-align:right;">
                            <span class="val-box">{{ number_format($aPrecompter, 0, ',', ' ') }}</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr>
            {{-- ✅ Somme nette = montant brut - a précompter --}}
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
                    Arrêté par nous le présent ordre de payment à la somme de:<br>
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
                        <td
                            style="border:none; padding:2mm; width:50%; vertical-align:bottom; text-align:center;padding-top:10mm;">
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

        {{-- ── R8 : Paiement par + Compte à créditer ── --}}
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
            <td colspan="2" class="ba" style="width:30%; vertical-align:top; padding:2mm; text-align:center;">
                <div class="b sm">Le Contrôleur Financier</div>
                <div class="sm it">The Financial Controller</div>
                <div style="height:20mm;"></div>
            </td>
        </tr>
        <tr>
            <td class="ba" colspan="3" style="vertical-align:top; padding:2mm;">
                <div class="b sm">COMPTE A CREDITER</div>
                <div class="xsm it">ACCOUNT TO BE CREDITED</div>
                <div style="height:12mm;"></div>
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