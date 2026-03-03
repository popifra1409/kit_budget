@extends('pdf.layouts.master', ['typeHeader' => 'op', 'typeFooter' => 'op'])

@php
    $ordonnance = $donnees['_raw'];

    // Charger l'engagement avec sa relation polymorphique
if (!$ordonnance->relationLoaded('engagement')) {
    $ordonnance->load('engagement.engageable');
}

$engagement = $ordonnance->engagement;

// ✅ Récupérer le document source (BC ou DA)
$documentSource = $engagement?->engageable;

// ✅ Récupérer le bénéficiaire selon le type
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

$nomBeneficiaire = $beneficiaire->raison_sociale ?? ($beneficiaire->nom_complet ?? ($beneficiaire->name ?? 'N/A'));

// ✅ LOGIQUE CORRIGÉE : Montants selon le type de document
if ($engagement && $engagement->engageable) {
    $donneesEngagement = $engagement->extraireDonneesDocument();

    if ($engagement->estBonCommande()) {
        // ========================================
        // BON DE COMMANDE
        // ========================================
        // Imputation = Montant HT
        $montantImputation = $documentSource->montant_ht ?? 0;

        // Montant brut de l'ordonnance = Montant TTC
            $montantBrut = $donneesEngagement['montant_ttc'] ?? 0;

            // A précompter = IR + TVA + TSR
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
            $montantTotalImpots = $detailImpots['ir'] + $detailImpots['tva'] + $detailImpots['tsr'];

            // Somme nette = Montant net à percevoir
            $montantNet = $donneesEngagement['montant_net'] ?? 0;
        } else {
            // ========================================
            // DÉCISION ADMINISTRATIVE
            // ========================================
            // Imputation = Montant brut (avant retenues)
            $montantImputation = $donneesEngagement['montant_brut'] ?? 0;

            // Montant brut de l'ordonnance = Montant brut (même chose pour DA)
        $montantBrut = $donneesEngagement['montant_brut'] ?? 0;

        // ✅ A précompter = IR + CNPS + IRNC + FEICOM + Redevance AV + Autres retenues
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

        // ✅ Total des impôts incluant FEICOM et Redevance Audiovisuelle
        $montantTotalImpots =
            $detailImpots['ir'] +
            $detailImpots['tva'] +
            $detailImpots['cnps'] +
            $detailImpots['irnc'] +
            $detailImpots['feicom'] +
            $detailImpots['redevance_av'] +
            $detailImpots['autres'];

        // Somme nette = Montant brut - Retenues
        $montantNet = $donneesEngagement['montant_net'] ?? 0;
    }
} else {
    // Fallback si pas d'engagement
        $montantImputation = $ordonnance->montant_brut ?? 0;
        $montantBrut = $ordonnance->montant_brut ?? 0;
        $montantNet = $ordonnance->montant_net ?? 0;
        $montantTotalImpots = 0;

        $detailImpots = [
            'ir' => 0,
            'tva' => 0,
            'tsr' => 0,
            'cnps' => 0,
            'irnc' => 0,
            'feicom' => 0,
            'redevance_av' => 0,
            'autres' => 0,
        ];
    }
@endphp

@section('title', 'Ordonnance de Paiement')

@section('montant_lettres')
    {{ \App\Helpers\NombreEnLettres::montantCFA($montantNet) }}
@endsection

@section('additional_styles')
    <style>
        @page {
            size: A4 landscape;
            margin: 14mm 10mm 10mm 16mm;
        }

        body {
            font-family: "Times New Roman", serif;
            font-size: 10.5pt;
            line-height: 1.35;
        }

        .info-box {
            border: 1px solid #000;
            padding: 6px;
            margin: 6px 0;
            font-size: 10pt;
        }
    </style>
@endsection

@section('content')
    {{-- ✅ Numéro d'émission --}}
    <div style="text-align: right; font-size: 11pt; font-weight: bold; margin-bottom: 6px;">
        N° EMISSION : {{ $ordonnance->numero_emission ?? 'NON ATTRIBUÉ' }}
    </div>

    <table style="width: 100%; border-collapse: collapse; margin: 0px;">
        <tr>
            <td style="border: none; padding: 0; vertical-align: top; width: 65%;">
                <div style="font-size: 9.5pt; line-height: 1.3; margin-bottom: 3px;">
                    <div style="font-weight: bold;">
                        OBJET DE LA DEPENSE:
                        <span style="padding-left: 26px;">
                            {{ $ordonnance->objet ?? ($documentSource?->objet ?? 'Paiement') }}
                        </span>
                    </div>
                    <div style="font-style: italic; font-size: 9pt;">SUBJECT OF EXPENDITURE:</div>
                </div>
                <div style="font-weight: bold; font-size: 11pt; line-height: 1.3; margin-top: 5px;">
                    Paiement selon
                    @if ($engagement && $documentSource)
                        @if ($engagement->estBonCommande())
                            le bon de commande
                        @else
                            la décision administrative
                        @endif
                        <span style="font-size: 11pt;">{{ $documentSource->numero }}</span>
                    @else
                        l'engagement <span style="font-size: 11pt;">{{ $engagement?->numero ?? 'N/A' }}</span>
                    @endif
                </div>
            </td>
            <td style="border: none; padding: 0; vertical-align: top; width: 35%;">
                <table style="width: 100%; border: 1px solid #333; border-collapse: collapse;">
                    <tr>
                        <td style="border-right: 1px solid #333; padding: 3px 5px; width: 50%;">
                            <div style="font-size: 10pt; font-weight: bold; line-height: 1.3;">Imputation</div>
                            <div style="font-size: 9pt; font-style: italic; line-height: 1.3;">Imputation</div>
                        </td>
                        <td style="padding: 3px 5px; width: 50%;">
                            <div style="font-size: 10pt; font-weight: bold; line-height: 1.3;">Montant:</div>
                            <div style="font-size: 9pt; font-style: italic; line-height: 1.3;">Amount</div>
                        </td>
                    </tr>
                    <tr>
                        <td
                            style="border-right: 1px solid #333; padding: 3px 5px; text-align: center; font-size: 11pt; font-weight: bold; line-height: 1.3;">
                            {{ $engagement->nomenclaturePrincipale->code ?? 'N/A' }}
                        </td>
                        <td
                            style="padding: 3px 5px; text-align: center; font-size: 11pt; font-weight: bold; line-height: 1.3;">
                            {{-- ✅ IMPUTATION : montant_ht pour BC, montant_brut pour DA --}}
                            {{ number_format($montantImputation, 0, ',', ' ') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table style="width: 100%; border: 1px solid #000; border-collapse: collapse; font-size: 10pt; margin: 0px;">
        <tr>
            <td style="border-right: 1px solid #000; padding: 5px 7px; vertical-align: top; width: 55%;">
                <div style="font-weight: bold; margin-bottom: 3px; line-height: 1.3;">
                    DESIGNATION DU CREANCIER(1):
                    <div style="font-weight: normal; font-style: italic; font-size: 9pt; line-height: 1.3;">
                        DESIGNATION OF THE CREDITOR(1):
                    </div>
                </div>
                <div style="margin-top: 8px; font-size: 11pt; font-weight: bold; min-height: 32px; line-height: 1.3;">
                    {{ $nomBeneficiaire }}
                </div>

                <div style="font-weight: bold; margin-top: 12px; line-height: 1.3;">
                    PIECES JUSTIFICATIVES DE LA DEPENSE(1)
                    <div style="font-weight: normal; font-style: italic; font-size: 9pt; line-height: 1.3;">
                        RELEVANT OF THE CREDITOR(1)
                    </div>
                </div>

                <div style="margin-top: 6px; font-size: 8.5pt; line-height: 1.4;">
                    @if ($engagement && $documentSource)
                        @if ($engagement->estBonCommande())
                            - Bon de Commande Administratif N° {{ $documentSource->numero }}<br>
                            - Engagement Budgetaire N° {{ $engagement->numero }}<br>
                            - Facture Proforma <br>
                            - Expression de besoins <br>
                            - Certificat d'engagement <br>
                            - Facture définitive liquidée <br>
                            - Procès verbal de réception <br>
                            - Bordereau de Livraison<br>
                            - Attestation de non Redevance<br>
                        @else
                            - Décision Administrative N° {{ $documentSource->numero }}<br>
                            - Engagement Budgetaire N° {{ $engagement->numero }}<br>
                            - Pièces justificatives de la dépense<br>
                        @endif
                    @endif
                </div>

                <div style="margin-top: 16px; line-height: 1.3;">
                    <div style="font-weight: bold;">L'AGENT COMPTABLE</div>
                    <div style="font-style: italic; font-size: 9pt;">(THE ACCOUNTING OFFICER)</div>
                </div>
            </td>
            <td style="padding: 5px 7px; vertical-align: top; width: 45%;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 3px 0; vertical-align: middle;">
                            <div style="font-weight: bold; line-height: 1.3;">
                                Montant brut de l'ordonnance
                                <div style="font-weight: normal; font-style: italic; font-size: 9pt; line-height: 1.3;">
                                    Gross amount of the order
                                </div>
                            </div>
                        </td>
                        <td style="padding: 3px 0; width: 35%; text-align: right;">
                            <div
                                style="border: 1px solid #000; padding: 2px 5px; text-align: center; font-weight: bold; font-size: 10.5pt; line-height: 1.3;">
                                {{-- ✅ MONTANT BRUT : TTC pour BC, montant_brut pour DA --}}
                                {{ number_format($montantBrut, 0, ',', ' ') }}
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 3px 0; vertical-align: middle;">
                            <div style="font-weight: bold; line-height: 1.3;">
                                A PRECOMPTER
                                <div style="font-weight: normal; font-style: italic; font-size: 9pt; line-height: 1.3;">
                                    TO BE DEDUCED
                                </div>
                            </div>
                        </td>
                        <td style="padding: 3px 0; text-align: right;">
                            <div
                                style="border: 1px solid #000; padding: 2px 5px; text-align: center; font-weight: bold; font-size: 10.5pt; line-height: 1.3;">
                                {{-- ✅ A PRÉCOMPTER : IR+TVA+TSR pour BC, IR+CNPS+IRNC+FEICOM+Redevance AV+Autres pour DA --}}
                                {{ number_format($montantTotalImpots, 0, ',', ' ') }}
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 3px 0; vertical-align: middle;">
                            <div style="font-weight: bold; line-height: 1.3;">
                                Somme nette a payer ou a virer(A)
                                <div style="font-weight: normal; font-style: italic; font-size: 9pt; line-height: 1.3;">
                                    Net sum to be paid or transfered(A)
                                </div>
                            </div>
                        </td>
                        <td style="padding: 3px 0; text-align: right;">
                            <div
                                style="border: 1px solid #000; padding: 2px 5px; text-align: center; font-weight: bold; font-size: 10.5pt; background-color: #f5f5f5; line-height: 1.3;">
                                {{-- ✅ SOMME NETTE : Montant brut - Retenues (pour les deux types) --}}
                                {{ number_format($montantNet, 0, ',', ' ') }}
                            </div>
                        </td>
                    </tr>
                </table>

                <div style="margin-top: 12px; text-align: center;">
                    <div style="line-height: 1.3;">
                        Arrete par nous le present ordre de paiement a la somme de:
                        <div style="font-style: italic; font-size: 9pt; line-height: 1.3;">
                            We hereby make up this order at the amount of:
                        </div>
                    </div>

                    <div
                        style="border: 1px solid #333; padding: 5px; margin: 6px 0; font-weight: bold; font-size: 10.5pt; min-height: 42px; line-height: 1.3;">
                        @yield('montant_lettres')
                    </div>

                    <div style="text-align: left; margin-bottom: 4px; line-height: 1.3;">
                        <span style="font-weight: bold;">Emis a Yaounde le</span><br>
                        <span style="font-style: italic; font-size: 9pt;">Issued at Yaounde on</span><br>
                        <span style="text-decoration: underline; font-weight: bold;">
                            {{ $ordonnance->date_emission ? \Carbon\Carbon::parse($ordonnance->date_emission)->format('d/m/Y') : '................................' }}
                        </span>
                    </div>

                    <div style="text-align: right; margin-top: 20px; line-height: 1.3;">
                        <div style="font-weight: bold;">(Signature et timbre de l'ordonnateur)</div>
                        <div style="font-style: italic; font-size: 9pt;">(Signature and stamp of the Vote Holder)</div>
                    </div>
                </div>
            </td>
        </tr>
        <tr>
            <td colspan="2" style="border-top: 1px solid #000; padding: 0px; margin: 0px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 40%; vertical-align: top; padding-right: 10px;">
                            <div style="font-weight: bold; line-height: 1.3;">PAIEMENT PAR:</div>
                            <div style="font-style: italic; font-size: 9pt; line-height: 1.3;">(Payment by)</div>
                            <div style="margin-top: 6px; line-height: 1.3;">
                                <span style="font-weight: bold;">A Yaounde, le</span><br>
                                <span style="font-style: italic; font-size: 9pt;">At Yaounde on</span><br>
                                <span
                                    style="border-bottom: 1px solid #333; display: inline-block; min-width: 120px; height: 16px;">&nbsp;</span>
                            </div>
                        </td>
                        <td style="width: 30%; text-align: center; vertical-align: top;">
                            <div style="font-weight: bold; line-height: 1.3;">Le Controleur Financier</div>
                            <div style="font-style: italic; font-size: 9pt; line-height: 1.3;">(The Financial Controller)
                            </div>
                        </td>
                        <td style="width: 30%; vertical-align: top;">
                            <div style="font-weight: bold; line-height: 1.3;">COMPTE A CREDITER</div>
                            <div style="font-style: italic; font-size: 9pt; line-height: 1.3;">ACCOUNT TO BE CREDITED</div>
                            <div style="margin-top: 6px; border: 1px solid #333; min-height: 32px; padding: 3px;">
                                @if ($beneficiaire && isset($beneficiaire->compte_bancaire))
                                    {{ $beneficiaire->compte_bancaire }}
                                @endif
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td colspan="2"
                style="border-top: 1px solid #000; padding: 4px; font-size: 8.5pt; background-color: #f9f9f9; line-height: 1.3;">
                <div style="font-weight: bold;">Note:</div>
                <div>(1) Nom, Prenom, Adresse complete. Pour les societes: Raisons sociales exactes.</div>
                <div style="font-style: italic;">(1) Surname, name and full address. Precise company name</div>
            </td>
        </tr>
    </table>
@endsection
