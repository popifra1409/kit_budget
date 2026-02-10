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

// ✅ Récupérer les montants depuis l'engagement
    if ($engagement && $engagement->engageable) {
        $donneesEngagement = $engagement->extraireDonneesDocument();

        // ✅ CORRECTION : Montant HT selon le type de document
        if ($engagement->estBonCommande()) {
            // Pour BC : montant_ht du bon de commande
            $montantHT = $documentSource->montant_ht ?? 0;
        } else {
            // Pour DA : montant_brut de la décision administrative
            $montantHT = $documentSource->montant_brut ?? 0;
        }

        $montantBrut = $donneesEngagement['montant_ttc'] ?? 0; // Montant brut de l'ordonnance (TTC)
    $montantNet = $donneesEngagement['montant_net'] ?? 0; // Somme nette à payer

    $detailImpots = [
        'ir' => $donneesEngagement['montant_ir'] ?? 0,
        'tva' => $donneesEngagement['montant_tva'] ?? 0,
        'tsr' => $donneesEngagement['montant_tsr'] ?? 0,
        'cnps' => $donneesEngagement['montant_cnps'] ?? 0,
        'irnc' => $donneesEngagement['montant_irnc'] ?? 0,
        'autres' => $donneesEngagement['autres_retenues'] ?? 0,
    ];

    // ✅ A PRECOMPTER = Somme des taxes et impôts
    if ($engagement->estBonCommande()) {
        $montantTotalImpots = $detailImpots['ir'] + $detailImpots['tva'] + $detailImpots['tsr'];
    } else {
        $montantTotalImpots =
            $detailImpots['ir'] + $detailImpots['cnps'] + $detailImpots['irnc'] + $detailImpots['autres'];
    }
} else {
    // Fallback
    $montantHT = $ordonnance->montant_brut ?? 0;
    $montantBrut = $ordonnance->montant_net ?? 0;
    $montantNet = $ordonnance->montant_net ?? 0;
    $montantTotalImpots = 0;

    $detailImpots = [
        'ir' => 0,
        'tva' => 0,
        'tsr' => 0,
        'cnps' => 0,
        'irnc' => 0,
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
            margin: 15mm 12mm 12mm 18mm;
        }

        body {
            font-family: "Times New Roman", serif;
            font-size: 9pt;
            line-height: 1.12;
        }

        .info-box {
            border: 1px solid #000;
            padding: 5px;
            margin: 5px 0;
            font-size: 9pt;
        }
    </style>
@endsection

@section('content')
    {{-- ✅ Numéro d'émission --}}
    <div style="text-align: right; font-size: 9pt; font-weight: bold; margin-bottom: 5px;">
        N° EMISSION : {{ $ordonnance->numero_emission ?? 'NON ATTRIBUÉ' }}
    </div>

    <table style="width: 100%; border-collapse: collapse; margin: 0px;">
        <tr>
            <td style="border: none; padding: 0; vertical-align: top; width: 65%;">
                <div style="font-size: 8pt; line-height: 1.1; margin-bottom: 2px;">
                    <div style="font-weight: bold;">
                        OBJET DE LA DEPENSE:
                        <span style="padding-left: 26px;">
                            {{ $ordonnance->objet ?? ($documentSource?->objet ?? 'Paiement') }}
                        </span>
                    </div>
                    <div style="font-style: italic; font-size: 8pt;">SUBJECT OF EXPENDITURE:</div>
                </div>
                <div style="font-weight: bold; font-size: 10pt; line-height: 1.2; margin-top: 4px;">
                    Paiement selon
                    @if ($engagement && $documentSource)
                        @if ($engagement->estBonCommande())
                            le bon de commande
                        @else
                            la décision administrative
                        @endif
                        <span style="font-size: 10pt;">{{ $documentSource->numero }}</span>
                    @else
                        l'engagement <span style="font-size: 10pt;">{{ $engagement?->numero ?? 'N/A' }}</span>
                    @endif
                </div>
            </td>
            <td style="border: none; padding: 0; vertical-align: top; width: 35%;">
                <table style="width: 100%; border: 1px solid #333; border-collapse: collapse;">
                    <tr>
                        <td style="border-right: 1px solid #333; padding: 2px 4px; width: 50%;">
                            <div style="font-size: 8.5pt; font-weight: bold; line-height: 1.1;">Imputation</div>
                            <div style="font-size: 7.5pt; font-style: italic; line-height: 1.1;">Imputation</div>
                        </td>
                        <td style="padding: 2px 4px; width: 50%;">
                            <div style="font-size: 8.5pt; font-weight: bold; line-height: 1.1;">Montant:</div>
                            <div style="font-size: 7.5pt; font-style: italic; line-height: 1.1;">Amount</div>
                        </td>
                    </tr>
                    <tr>
                        <td
                            style="border-right: 1px solid #333; padding: 2px 4px; text-align: center; font-size: 10pt; font-weight: bold; line-height: 1.2;">
                            {{ $engagement->nomenclaturePrincipale->code ?? 'N/A' }}
                        </td>
                        <td
                            style="padding: 2px 4px; text-align: center; font-size: 10pt; font-weight: bold; line-height: 1.2;">
                            {{-- ✅ Montant HT : montant_ht pour BC, montant_brut pour DA --}}
                            {{ number_format($montantHT, 0, ',', ' ') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table style="width: 100%; border: 1px solid #000; border-collapse: collapse; font-size: 9pt; margin: 0px;">
        <tr>
            <td style="border-right: 1px solid #000; padding: 4px 6px; vertical-align: top; width: 55%;">
                <div style="font-weight: bold; margin-bottom: 2px; line-height: 1.1;">
                    DESIGNATION DU CREANCIER(1):
                    <div style="font-weight: normal; font-style: italic; font-size: 8pt; line-height: 1.1;">
                        DESIGNATION OF THE CREDITOR(1):
                    </div>
                </div>
                <div style="margin-top: 8px; font-size: 10pt; font-weight: bold; min-height: 30px; line-height: 1.2;">
                    {{ $nomBeneficiaire }}
                </div>

                <div style="font-weight: bold; margin-top: 12px; line-height: 1.1;">
                    PIECES JUSTIFICATIVES DE LA DEPENSE(1)
                    <div style="font-weight: normal; font-style: italic; font-size: 8pt; line-height: 1.1;">
                        RELEVANT OF THE CREDITOR(1)
                    </div>
                </div>

                <div style="margin-top: 5px; font-size: 7pt;">
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

                <div style="margin-top: 15px; line-height: 1.1;">
                    <div style="font-weight: bold;">L'AGENT COMPTABLE</div>
                    <div style="font-style: italic; font-size: 8pt;">(THE ACCOUNTING OFFICER)</div>
                </div>
            </td>
            <td style="padding: 4px 6px; vertical-align: top; width: 45%;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 2px 0; vertical-align: middle;">
                            <div style="font-weight: bold; line-height: 1.1;">
                                Montant brut de l'ordonnance
                                <div style="font-weight: normal; font-style: italic; font-size: 8pt; line-height: 1.1;">
                                    Gross amount of the order
                                </div>
                            </div>
                        </td>
                        <td style="padding: 2px 0; width: 35%; text-align: right;">
                            <div
                                style="border: 1px solid #000; padding: 1px 4px; text-align: center; font-weight: bold; font-size: 9pt; line-height: 1.2;">
                                {{-- ✅ Montant brut = Montant TTC --}}
                                {{ number_format($montantBrut, 0, ',', ' ') }}
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 2px 0; vertical-align: middle;">
                            <div style="font-weight: bold; line-height: 1.1;">
                                A PRECOMPTER
                                <div style="font-weight: normal; font-style: italic; font-size: 8pt; line-height: 1.1;">
                                    TO BE DEDUCED
                                </div>
                            </div>
                        </td>
                        <td style="padding: 2px 0; text-align: right;">
                            <div
                                style="border: 1px solid #000; padding: 1px 4px; text-align: center; font-weight: bold; font-size: 9pt; line-height: 1.2;">
                                {{-- ✅ A précompter = Somme des taxes et impôts --}}
                                {{ number_format($montantTotalImpots, 0, ',', ' ') }}
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 2px 0; vertical-align: middle;">
                            <div style="font-weight: bold; line-height: 1.1;">
                                Somme nette a payer ou a virer(A)
                                <div style="font-weight: normal; font-style: italic; font-size: 8pt; line-height: 1.1;">
                                    Net sum to be paid or transfered(A)
                                </div>
                            </div>
                        </td>
                        <td style="padding: 2px 0; text-align: right;">
                            <div
                                style="border: 1px solid #000; padding: 1px 4px; text-align: center; font-weight: bold; font-size: 9pt; background-color: #f5f5f5; line-height: 1.2;">
                                {{-- ✅ Somme nette = Montant brut - Somme des taxes et impôts --}}
                                {{ number_format($montantNet, 0, ',', ' ') }}
                            </div>
                        </td>
                    </tr>
                </table>

                <div style="margin-top: 12px; text-align: center;">
                    <div style="line-height: 1.1;">
                        Arrete par nous le present ordre de paiement a la somme de:
                        <div style="font-style: italic; font-size: 8pt; line-height: 1.1;">
                            We hereby make up this order at the amount of:
                        </div>
                    </div>

                    <div
                        style="border: 1px solid #333; padding: 4px; margin: 6px 0; font-weight: bold; font-size: 9.5pt; min-height: 40px; line-height: 1.2;">
                        @yield('montant_lettres')
                    </div>

                    <div style="text-align: left; margin-bottom: 3px; line-height: 1.1;">
                        <span style="font-weight: bold;">Emis a Yaounde le</span><br>
                        <span style="font-style: italic; font-size: 8pt;">Issued at Yaounde on</span><br>
                        <span style="text-decoration: underline; font-weight: bold;">
                            {{ $ordonnance->date_emission ? \Carbon\Carbon::parse($ordonnance->date_emission)->format('d/m/Y') : '................................' }}
                        </span>
                    </div>

                    <div style="text-align: right; margin-top: 20px; line-height: 1.1;">
                        <div style="font-weight: bold;">(Signature et timbre de l'ordonnateur)</div>
                        <div style="font-style: italic; font-size: 8pt;">(Signature and stamp of the Vote Holder)</div>
                    </div>
                </div>
            </td>
        </tr>
        <tr>
            <td colspan="2" style="border-top: 1px solid #000; padding: 0px; margin: 0px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 40%; vertical-align: top; padding-right: 10px;">
                            <div style="font-weight: bold; line-height: 1.1;">PAIEMENT PAR:</div>
                            <div style="font-style: italic; font-size: 8pt; line-height: 1.1;">(Payment by)</div>
                            <div style="margin-top: 5px; line-height: 1.1;">
                                <span style="font-weight: bold;">A Yaounde, le</span><br>
                                <span style="font-style: italic; font-size: 8pt;">At Yaounde on</span><br>
                                <span
                                    style="border-bottom: 1px solid #333; display: inline-block; min-width: 120px; height: 14px;">&nbsp;</span>
                            </div>
                        </td>
                        <td style="width: 30%; text-align: center; vertical-align: top;">
                            <div style="font-weight: bold; line-height: 1.1;">Le Controleur Financier</div>
                            <div style="font-style: italic; font-size: 8pt; line-height: 1.1;">(The Financial Controller)
                            </div>
                        </td>
                        <td style="width: 30%; vertical-align: top;">
                            <div style="font-weight: bold; line-height: 1.1;">COMPTE A CREDITER</div>
                            <div style="font-style: italic; font-size: 8pt; line-height: 1.1;">ACCOUNT TO BE CREDITED</div>
                            <div style="margin-top: 5px; border: 1px solid #333; min-height: 30px; padding: 2px;">
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
                style="border-top: 1px solid #000; padding: 3px; font-size: 7.5pt; background-color: #f9f9f9; line-height: 1.1;">
                <div style="font-weight: bold;">Note:</div>
                <div>(1) Nom, Prenom, Adresse complete. Pour les societes: Raisons sociales exactes.</div>
                <div style="font-style: italic;">(1) Surname, name and full address. Precise company name</div>
            </td>
        </tr>
    </table>
@endsection
