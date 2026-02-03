@extends('pdf.layouts.master', ['typeHeader' => 'op', 'typeFooter' => 'op'])

@php
    $ordonnance = $donnees['_raw'];

    // Charger l'engagement avec sa relation polymorphique
if (!$ordonnance->relationLoaded('engagement')) {
    $ordonnance->load('engagement.engageable');
}

$engagement = $ordonnance->engagement;
$bonCommande = $engagement?->bonCommande;

// Récupérer le bénéficiaire (fournisseur)
$beneficiaire = null;

if ($ordonnance->beneficiaire) {
    $beneficiaire = $ordonnance->beneficiaire;
} elseif ($bonCommande && $bonCommande->fournisseur) {
    $beneficiaire = $bonCommande->fournisseur;
} elseif ($ordonnance->beneficiaire_type && $ordonnance->beneficiaire_id) {
    $beneficiaire = $ordonnance->beneficiaire_type::find($ordonnance->beneficiaire_id);
}

$nomBeneficiaire = $beneficiaire->raison_sociale ?? ($beneficiaire->name ?? 'N/A');

// ✅ CALCULS CORRECTS
// Montant HT
$montantHT = $bonCommande ? $bonCommande->montant_ht : $ordonnance->montant_brut;

// Montant TVA
$montantTVA = $bonCommande ? $bonCommande->montant_tva : 0;

// Montant brut = HT + TVA (TTC)
$montantBrut = $montantHT + $montantTVA;

// ✅ CORRECTION : À précompter = SOMME DE TOUS LES IMPÔTS ET TAXES
$montantTotalImpots = $bonCommande ? $bonCommande->calculerMontantTotalImpots() : $ordonnance->montant_impot;

// ✅ CORRECTION : Net à payer = Brut - TOTAL des impôts
$montantNet = $montantBrut - $montantTotalImpots;

// Détail des impôts pour affichage
$detailImpots = $bonCommande
    ? [
        'tva' => $bonCommande->montant_tva ?? 0,
        'ir' => $bonCommande->montant_ir ?? 0,
        'tsr' => $bonCommande->montant_tsr ?? 0,
        'cnps' => $bonCommande->montant_cnps ?? 0,
        'irnc' => $bonCommande->montant_irnc ?? 0,
        'autres' => $bonCommande->montant_autres_taxes ?? 0,
        'total' => $montantTotalImpots,
        ]
        : null;
@endphp

@section('title', 'Ordonnance de Paiement')

@section('montant_lettres')
    {{ \App\Helpers\NombreEnLettres::montantCFA($montantNet) }}
@endsection

@section('additional_styles')
    <style>
        .info-box {
            border: 1px solid #000;
            padding: 5px;
            margin: 5px 0;
            font-size: 9pt;
        }

        .detail-montants {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0;
            font-size: 8.5pt;
        }

        .detail-montants td {
            border: 1px solid #333;
            padding: 3px 5px;
        }

        .detail-montants .label {
            font-weight: bold;
            width: 60%;
        }

        .detail-montants .montant {
            text-align: right;
            width: 40%;
        }

        .total-row {
            background-color: #f0f0f0;
            font-weight: bold;
        }

        .net-row {
            background-color: #e8f5e9;
            font-weight: bold;
        }
    </style>
@endsection

@section('content')
    <table style="width: 100%; border-collapse: collapse; margin: 0px;">
        <tr>
            <td style="border: none; padding: 0; vertical-align: top; width: 65%;">
                <div style="font-size: 8pt; line-height: 1.1; margin-bottom: 2px;">
                    <div style="font-weight: bold;">OBJET DE LA DEPENSE:<span
                            style="padding-left: 26px;">{{ $ordonnance->objet ?? ($bonCommande?->objet ?? 'Paiement fournisseur') }}</span>
                    </div>
                    <div style="font-style: italic; font-size: 8pt;">SUBJECT OF EXPENDITURE:</div>
                </div>
                <div style="font-weight: bold; font-size: 10pt; line-height: 1.2; margin-top: 4px;">
                    Paiement selon le bon <span
                        style="font-size: 10pt;">{{ $bonCommande?->numero ?? ($engagement?->numero ?? 'N/A') }}</span>
                </div>

                {{-- ✅ Détail des montants - SANS HT, TVA et TTC --}}
                @if ($bonCommande && $detailImpots)
                    <div style="margin-top: 8px; font-size: 7.5pt;">
                        <div style="font-weight: bold; margin-bottom: 0px;">Detail du paiement:</div>
                        <table class="detail-montants">
                            <tr>
                                <td colspan="2" style="padding: 4px 5px; font-weight: bold; background-color: #fff3e0;">A
                                    PRECOMPTER (Impots et Taxes):</td>
                            </tr>
                            @if ($detailImpots['tva'] > 0)
                                <tr>
                                    <td class="label" style="padding-left: 15px;">• TVA (19.25%)</td>
                                    <td class="montant">{{ number_format($detailImpots['tva'], 0, ',', ' ') }} FCFA</td>
                                </tr>
                            @endif
                            @if ($detailImpots['ir'] > 0)
                                <tr>
                                    <td class="label" style="padding-left: 15px;">• Impot sur le Revenu (IR)</td>
                                    <td class="montant">{{ number_format($detailImpots['ir'], 0, ',', ' ') }} FCFA</td>
                                </tr>
                            @endif
                            @if ($detailImpots['tsr'] > 0)
                                <tr>
                                    <td class="label" style="padding-left: 15px;">• TSR</td>
                                    <td class="montant">{{ number_format($detailImpots['tsr'], 0, ',', ' ') }} FCFA</td>
                                </tr>
                            @endif
                            @if ($detailImpots['cnps'] > 0)
                                <tr>
                                    <td class="label" style="padding-left: 15px;">• CNPS</td>
                                    <td class="montant">{{ number_format($detailImpots['cnps'], 0, ',', ' ') }} FCFA</td>
                                </tr>
                            @endif
                            @if ($detailImpots['irnc'] > 0)
                                <tr>
                                    <td class="label" style="padding-left: 15px;">• IRNC</td>
                                    <td class="montant">{{ number_format($detailImpots['irnc'], 0, ',', ' ') }} FCFA</td>
                                </tr>
                            @endif
                            @if ($detailImpots['autres'] > 0)
                                <tr>
                                    <td class="label" style="padding-left: 15px;">• Autres taxes</td>
                                    <td class="montant">{{ number_format($detailImpots['autres'], 0, ',', ' ') }} FCFA</td>
                                </tr>
                            @endif
                            <tr style="background-color: #fff3e0; font-weight: bold;">
                                <td class="label">TOTAL A PRECOMPTER</td>
                                <td class="montant">{{ number_format($montantTotalImpots, 0, ',', ' ') }} FCFA</td>
                            </tr>
                            {{-- <tr class="net-row">
                                <td class="label">NET A PAYER AU FOURNISSEUR</td>
                                <td class="montant">{{ number_format($montantNet, 0, ',', ' ') }} FCFA</td>
                            </tr> --}}
                        </table>
                    </div>
                @endif
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
                            {{-- ✅ Montant HT --}}
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

                @if ($bonCommande)
                    <div style="margin-top: 5px; font-size: 7pt;">
                        - Bon de Commande Administratif N° {{ $bonCommande->numero }}<br>
                        - Engagement Budgetaire N° {{ $engagement->numero ?? 'N/A' }}<br>
                        - Facture Proforma <br>
                        - Expression de besoins <br>
                        - Certificat d'engagement <br>
                        - Facture définitive liquidée <br>
                        - Procès verbal de réception <br>
                        - Bordereau de Livraison<br>
                        - Attestation de non Redevance<br>
                    </div>
                @endif

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
                                {{-- ✅ Montant brut = HT + TVA (TTC) --}}
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
                                {{-- ✅ CORRECTION : TOTAL de tous les impôts et taxes --}}
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
                                {{-- ✅ CORRECTION : Net = Brut - TOTAL impôts --}}
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
                            <div style="font-style: italic; font-size: 8pt; line-height: 1.1;">(We hereby make up this order
                                at)</div>
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
