@extends('pdf.layouts.master', ['typeHeader' => 'op', 'typeFooter' => 'op'])

@php
    $ordonnance = $donnees['_raw'];
    $engagement = $ordonnance->engagement ?? null;
    $bonCommande = $engagement->bonCommande ?? null;

    // Essayer plusieurs façons de récupérer le bénéficiaire
    $reverseur = null;

    // Méthode 1 : Via la relation morphTo
    if ($ordonnance->beneficiaire) {
        $reverseur = $ordonnance->beneficiaire;
    }
    // Méthode 2 : Via le BC
    elseif ($bonCommande && $bonCommande->fournisseur) {
        $reverseur = $bonCommande->fournisseur;
    }
    // Méthode 3 : Charger manuellement si on a les IDs
    elseif ($ordonnance->beneficiaire_type && $ordonnance->beneficiaire_id) {
        $reverseur = $ordonnance->beneficiaire_type::find($ordonnance->beneficiaire_id);
    }

    $nomReverseur = $reverseur->raison_sociale ?? ($reverseur->name ?? 'PEC MEDICAL');

    // Pour l'OP Impôt, le bénéficiaire est toujours la Direction des Impôts
$nomBeneficiaire = 'LE DIRECTEUR DES IMPOTS';
@endphp

@section('title', 'Ordonnance de Paiement - Impôt')

@section('montant_lettres')
    {{ \App\Helpers\NombreEnLettres::montantCFA($ordonnance->montant_net ?? 0) }}
@endsection

@section('additional_styles')
    <style>
        .info-box {
            border: 1px solid #000;
            padding: 5px;
            margin: 5px 0;
            font-size: 9pt;
        }

        .montant-box {
            border: 2px solid #000;
            border-radius: 15px;
            padding: 8px 15px;
            display: inline-block;
            min-width: 120px;
            text-align: center;
            font-weight: bold;
        }

        .sous-tableau {
            width: 100%;
            border-collapse: collapse;
        }

        .sous-tableau td {
            border: 1px solid #000;
            padding: 5px;
            text-align: center;
        }
    </style>
@endsection

@section('content')
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 5px;">
        <tr>
            <td style="border: none; padding: 0; vertical-align: top; width: 65%;">
                <div style="font-size: 8.5pt; line-height: 1.1; margin-bottom: 2px;">
                    <div style="font-weight: bold;">OBJET DE LA DEPENSE:</div>
                    <div style="padding-left: 3px;">{{ $ordonnance->objet ?? 'Reversement AIR' }}</div>
                    <div style="font-style: italic; font-size: 8pt;">SUBJECT OF EXPENDITURE:</div>
                </div>
                <div style="text-align: center; font-weight: bold; font-size: 10pt; line-height: 1.2; margin-top: 4px;">
                    Reversement <span style="font-size: 11pt;">AIR</span>
                </div>
                <div style="text-align: center; font-weight: bold; font-size: 10pt; line-height: 1.2; margin-top: 4px;">
                    {{ $nomReverseur }}
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
                            {{ number_format($ordonnance->montant_ir, 0, ',', ' ') }}
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
                                {{ number_format($ordonnance->montant_impot, 0, ',', ' ') }}
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
                                0
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 2px 0; vertical-align: middle;">
                            <div style="font-weight: bold; line-height: 1.1;">
                                Somme nette à payer ou à virer(A)
                                <div style="font-weight: normal; font-style: italic; font-size: 8pt; line-height: 1.1;">
                                    Net sum to be paid or transfered(A)
                                </div>
                            </div>
                        </td>
                        <td style="padding: 2px 0; text-align: right;">
                            <div
                                style="border: 1px solid #000; padding: 1px 4px; text-align: center; font-weight: bold; font-size: 9pt; background-color: #f5f5f5; line-height: 1.2;">
                                {{ number_format($ordonnance->montant_ir, 0, ',', ' ') }}
                            </div>
                        </td>
                    </tr>
                </table>

                <div style="margin-top: 12px; text-align: center;">
                    <div style="line-height: 1.1;">
                        Arrêté par nous le présent ordre de paiement à la somme de:
                        <div style="font-style: italic; font-size: 8pt; line-height: 1.1;">
                            We hereby make up this order at the amount of:
                        </div>
                    </div>

                    <div
                        style="border: 1px solid #333; padding: 4px; margin: 6px 0; font-weight: bold; font-size: 9.5pt; min-height: 40px; line-height: 1.2;">
                        @yield('montant_lettres')
                    </div>

                    <div style="text-align: left; margin-bottom: 3px; line-height: 1.1;">
                        <span style="font-weight: bold;">émis à Yaoundé le</span><br>
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
                            <div style="font-style: italic; font-size: 8pt; line-height: 1.1;">(We hereby make up this
                                order at)</div>
                            <div style="margin-top: 5px; line-height: 1.1;">
                                <span style="font-weight: bold;">A Yaoundé, le</span><br>
                                <span style="font-style: italic; font-size: 8pt;">At Yaounde on</span><br>
                                <span
                                    style="border-bottom: 1px solid #333; display: inline-block; min-width: 120px; height: 14px;">&nbsp;</span>
                            </div>
                        </td>
                        <td style="width: 30%; text-align: center; vertical-align: top;">
                            <div style="font-weight: bold; line-height: 1.1;">Le Contrôleur Financier</div>
                            <div style="font-style: italic; font-size: 8pt; line-height: 1.1;">(The Financial Controller)
                            </div>
                        </td>
                        <td style="width: 30%; vertical-align: top;">
                            <div style="font-weight: bold; line-height: 1.1;">COMPTE A CREDITER</div>
                            <div style="font-style: italic; font-size: 8pt; line-height: 1.1;">ACCOUNT TO BE CREDITED
                            </div>
                            <div style="margin-top: 5px; border: 1px solid #333; min-height: 30px; padding: 2px;"></div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        {{-- <tr>
            <td colspan="2"
                style="border-top: 1px solid #000; padding: 3px; font-size: 8pt; background-color: #f9f9f9; line-height: 1.1;">
                <div style="font-weight: bold;">Note:</div>
                <div>(1) Nom, Prénom, Adresse complète. Pour les sociétés: Raisons sociales exactes.</div>
                <div style="font-style: italic;">(1) Surname, name and full address. Precise company name</div>
            </td>
        </tr> --}}
    </table>
@endsection
