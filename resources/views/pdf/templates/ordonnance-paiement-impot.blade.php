@extends('pdf.layouts.master', ['typeHeader' => 'op', 'typeFooter' => 'op'])

@php
    $ordonnance = $donnees['_raw'];

    // Charger l'engagement avec sa relation polymorphique
if (!$ordonnance->relationLoaded('engagement')) {
    $ordonnance->load('engagement.engageable');
}

$engagement = $ordonnance->engagement;
$bonCommande = $engagement?->bonCommande;

// Récupérer le reverseur (fournisseur)
$reverseur = null;
$documentSource = null;

if ($engagement && $engagement->engageable) {
    $documentSource = $engagement->engageable;

    // Si c'est un Bon de Commande
        if ($engagement->estBonCommande()) {
            $reverseur = $documentSource->fournisseur;
        }
        // Si c'est une Décision Administrative
    elseif ($engagement->estDecision()) {
        $reverseur = $documentSource->personnel;
    }
}
// Fallback : utiliser le bénéficiaire de l'ordonnance
    if (!$reverseur && $ordonnance->beneficiaire) {
        $reverseur = $ordonnance->beneficiaire;
    }

    // Nom du reverseur
    $nomReverseur = $reverseur->raison_sociale ?? ($reverseur->nom_complet ?? ($reverseur->name ?? 'N/A'));

    $nomBeneficiaire = 'LE DIRECTEUR DES IMPOTS';

    // ✅ Calculer le total des impôts
    $detailImpots = $ordonnance->getDetailImpots();
    $montantTotalImpots = $detailImpots['total'];

@endphp

@section('title', 'Ordonnance de Paiement - Impot')

{{-- ✅ CORRECTION : Utiliser le montant total des impôts --}}
@section('montant_lettres')
    {{ \App\Helpers\NombreEnLettres::montantCFA($montantTotalImpots) }}
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

        .detail-impots {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0;
            font-size: 9.5pt;
        }

        .detail-impots td {
            border: 1px solid #333;
            padding: 3px 5px;
        }

        .detail-impots .label {
            font-weight: bold;
            width: 60%;
        }

        .detail-impots .montant {
            text-align: right;
            width: 40%;
        }

        .detail-impots .total-row {
            background-color: #f0f0f0;
            font-weight: bold;
        }

        {{-- ✅ Style pour les lignes exonérées --}} .detail-impots .ligne-exoneree {
            opacity: 0.5;
        }
    </style>
@endsection

@section('content')
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 5px;">
        <tr>
            <td style="border: none; padding: 0; vertical-align: top; width: 65%;">
                <div style="font-size: 8.5pt; line-height: 1.1; margin-bottom: 2px;">
                    <div style="font-weight: bold;">OBJET DE LA DEPENSE:</div>
                    <div style="padding-left: 3px;">{{ $ordonnance->objet ?? 'Reversement des impots et taxes' }}</div>
                    <div style="font-style: italic; font-size: 8pt;">SUBJECT OF EXPENDITURE:</div>
                    <div style="text-align: center; font-weight: bold; font-size: 10pt; line-height: 1.2; margin-top: 4px;">
                        Reversement Impots et Taxes
                    </div>
                    <div style="text-align: center; font-weight: bold; font-size: 10pt; line-height: 1.2; margin-top: 4px;">
                        {{ $nomReverseur }}
                    </div>
                </div>

                {{-- ✅ Détail des impôts - Afficher pour BC ET DA --}}
                <div style="margin-top: 8px; font-size: 8.5pt;">
                    <div style="font-weight: bold; margin-bottom: 3px;">Detail des impots et taxes:</div>
                    <table class="detail-impots">
                        {{-- =============================================
                            ✅ RETENUES - BON DE COMMANDE
                            ============================================= --}}
                        @if ($engagement && $engagement->estBonCommande())
                            <tr class="{{ $detailImpots['ir'] == 0 ? 'ligne-exoneree' : '' }}">
                                <td class="label">
                                    Impot sur le Revenu (IR)
                                    @if ($detailImpots['ir'] == 0)
                                        <span
                                            style="font-weight: normal; font-style: italic; font-size: 8pt;">(exonéré)</span>
                                    @endif
                                </td>
                                <td class="montant">{{ number_format($detailImpots['ir'], 0, ',', ' ') }} FCFA</td>
                            </tr>

                            <tr class="{{ $detailImpots['tva'] == 0 ? 'ligne-exoneree' : '' }}">
                                <td class="label">
                                    TVA (19.25%)
                                    @if ($detailImpots['tva'] == 0)
                                        <span style="font-weight: normal; font-style: italic; font-size: 8pt;">(non
                                            applicable)</span>
                                    @endif
                                </td>
                                <td class="montant">{{ number_format($detailImpots['tva'], 0, ',', ' ') }} FCFA</td>
                            </tr>

                            <tr class="{{ $detailImpots['tsr'] == 0 ? 'ligne-exoneree' : '' }}">
                                <td class="label">
                                    Taxe Statistique Regionale (TSR)
                                    @if ($detailImpots['tsr'] == 0)
                                        <span
                                            style="font-weight: normal; font-style: italic; font-size: 8pt;">(exonéré)</span>
                                    @endif
                                </td>
                                <td class="montant">{{ number_format($detailImpots['tsr'], 0, ',', ' ') }} FCFA</td>
                            </tr>

                            {{-- =============================================
                            ✅ RETENUES - DÉCISION ADMINISTRATIVE (CORRIGÉ)
                            Avec TVA, Redevance audiovisuelle, FEICOM
                            ============================================= --}}
                        @else
                            {{-- IR --}}
                            @if (($detailImpots['ir'] ?? 0) > 0)
                                <tr>
                                    <td class="label">Impot sur le Revenu (IR)</td>
                                    <td class="montant">{{ number_format($detailImpots['ir'], 0, ',', ' ') }} FCFA</td>
                                </tr>
                            @endif

                            {{-- CNPS --}}
                            @if (($detailImpots['cnps'] ?? 0) > 0)
                                <tr>
                                    <td class="label">Cotisations CNPS</td>
                                    <td class="montant">{{ number_format($detailImpots['cnps'], 0, ',', ' ') }} FCFA</td>
                                </tr>
                            @endif

                            {{-- IRNC --}}
                            @if (($detailImpots['irnc'] ?? 0) > 0)
                                <tr>
                                    <td class="label">IR Non Commercial (IRNC)</td>
                                    <td class="montant">{{ number_format($detailImpots['irnc'], 0, ',', ' ') }} FCFA</td>
                                </tr>
                            @endif

                            {{-- ✅ NOUVELLE TAXE : TVA --}}
                            @if (($detailImpots['tva'] ?? 0) > 0)
                                <tr>
                                    <td class="label">TVA</td>
                                    <td class="montant">{{ number_format($detailImpots['tva'], 0, ',', ' ') }} FCFA</td>
                                </tr>
                            @endif

                            {{-- ✅ NOUVELLE TAXE : Redevance audiovisuelle --}}
                            @if (($detailImpots['redevance'] ?? 0) > 0)
                                <tr>
                                    <td class="label">Redevance audiovisuelle</td>
                                    <td class="montant">{{ number_format($detailImpots['redevance'], 0, ',', ' ') }} FCFA
                                    </td>
                                </tr>
                            @endif

                            {{-- ✅ NOUVELLE TAXE : FEICOM --}}
                            @if (($detailImpots['feicom'] ?? 0) > 0)
                                <tr>
                                    <td class="label">FEICOM</td>
                                    <td class="montant">{{ number_format($detailImpots['feicom'], 0, ',', ' ') }} FCFA</td>
                                </tr>
                            @endif

                            {{-- Autres retenues --}}
                            @if (($detailImpots['autres'] ?? 0) > 0)
                                <tr>
                                    <td class="label">Autres retenues</td>
                                    <td class="montant">{{ number_format($detailImpots['autres'], 0, ',', ' ') }} FCFA</td>
                                </tr>
                            @endif
                        @endif

                        {{-- Ligne de total --}}
                        <tr class="total-row">
                            <td class="label">TOTAL IMPOTS ET TAXES</td>
                            <td class="montant">{{ number_format($montantTotalImpots, 0, ',', ' ') }} FCFA</td>
                        </tr>
                    </table>
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
                            {{ $engagement->nomenclaturePrincipale->code ?? '610300' }}
                        </td>
                        <td
                            style="padding: 2px 4px; text-align: center; font-size: 10pt; font-weight: bold; line-height: 1.2;">
                            {{-- ✅ CORRECTION : Afficher le total des impôts --}}
                            {{ number_format($montantTotalImpots, 0, ',', ' ') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table style="width: 100%; border: 1px solid #000; border-collapse: collapse; font-size: 9pt;">
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
                    <div style="margin-top: 8px; font-size: 8.5pt;">
                        - Bon de Commande N° {{ $bonCommande->numero }}<br>
                        - Engagement Budgetaire N° {{ $engagement->numero ?? 'N/A' }}<br>
                        - Facture Fournisseur
                    </div>
                @elseif ($engagement && $engagement->estDecision())
                    <div style="margin-top: 8px; font-size: 8.5pt;">
                        - Decision Administrative N° {{ $documentSource->numero ?? 'N/A' }}<br>
                        - Engagement Budgetaire N° {{ $engagement->numero ?? 'N/A' }}<br>
                        - Etat de paiement
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
                                0
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
                                {{-- ✅ CORRECTION : Afficher le total des impôts --}}
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
                                {{-- ✅ CORRECTION : Afficher le total des impôts (net = brut car pas de précompte) --}}
                                {{ number_format($montantTotalImpots, 0, ',', ' ') }}
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
                        <span style="font-weight: bold;">emis a Yaounde le</span><br>
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
            <td colspan="2" style="border-top: 1px solid #000; padding: 4px 5px;">
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
                                Compte du Tresor Public
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
@endsection
