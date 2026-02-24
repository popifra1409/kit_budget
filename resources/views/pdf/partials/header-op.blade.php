{{-- Header pour Ordonnance de Paiement --}}
@php
    $ordonnance = $donnees['_raw'] ?? null;
    $engagement = $ordonnance->engagement ?? null;
    $bonCommande = $engagement->bonCommande ?? null;

    // Déterminer le label selon le type
    $labelOP = $ordonnance && $ordonnance->type_ordonnance === 'impot' ? 'N° OPT:' : 'N° OP:';
    $labelOPEn = $ordonnance && $ordonnance->type_ordonnance === 'impot' ? 'N° of OPT' : 'N° of OP';
@endphp

<table style="width: 100%; border: none; margin-top: 10px;">
    <tr>
        <td style="width: 25%; border-right: 1px solid #000; padding: 0; vertical-align: top;">
            {{-- Logo --}}
            @if ($parametres->logo && file_exists(public_path('storage/' . $parametres->logo)))
                <img src="{{ public_path('storage/' . $parametres->logo) }}"
                    style="height: 100px; width: auto; display: block; margin: 0 auto;">
            @endif
            <div style="font-weight: bold; font-size: 8pt; text-align: center; margin-top: 25px; line-height: 1.3;">
                <strong>Visa du Directeur des</strong><br>
                <strong>Affaires Administratives</strong><br>
                <strong>et Financières</strong>
            </div>
        </td>

        <td style="width: 45%; border: none; padding: 0; text-align: center; vertical-align: middle;">
            <div style="font-weight: bold; font-size: 11pt;">
                {{ strtoupper($parametres->nom_structure ?? 'HÔPITAL GÉNÉRAL DE YAOUNDÉ') }}
            </div>
            <div style="font-size: 9pt; font-style: italic; margin-bottom: 5px;">
                {{ strtoupper($parametres->nom_structure_en ?? 'YAOUNDE GENERAL HOSPITAL') }}
            </div>

            <div style="font-size: 8pt; margin-bottom: 5px;">
                B.P {{ $parametres->boite_postale ?? '5408' }} {{ $parametres->ville ?? 'YAOUNDE' }}.
                Tel: {{ $parametres->telephone ?? '(237) 222 21 20 18' }}
                Fax: {{ $parametres->fax ?? '(237) 222 21 20 15' }}
            </div>

            <div style="margin-bottom: 5px;">
                <div style="font-weight: bold; font-size: 11pt;">ORDONNANCE DE PAIEMENT</div>
                <div style="font-style: italic; font-size: 9pt;">PAYMENT ORDER</div>
            </div>

            <div style="font-size: 8.5pt; line-height: 1.1;">
                <div style="font-weight: bold;">
                    L'Agent comptable de l'{{ strtoupper($parametres->sigle ?? 'HGY') }} est autorisé à payer la
                </div>
                <div style="font-style: italic;">
                    The accounting officer of the {{ strtoupper($parametres->sigle ?? 'HGY') }} is hereby autorized to
                    pay the debit
                </div>
            </div>
        </td>

        <td style="width: 30%; border: none; padding: 5px; vertical-align: top; text-align: left;">
            <div style="border: 1px solid #000; padding: 5px; font-size: 7.5pt; line-height: 1.4;">
                <p style="margin: 0 0 8px 0;">
                    <strong>Mois et exercice d'émission:</strong>
                    {{ $ordonnance->mois_emission ?? now()->format('m') }}/{{ $ordonnance->exercice->annee ?? now()->year }}
                    <br><i>Month and budgetary</i>
                </p>

                <p style="margin: 0 0 8px 0;">
                    <strong>Exercice budgétaire:</strong>
                    {{ $ordonnance->exercice->annee ?? now()->year }}
                    <br><i>Budgetary Year</i>
                </p>

                <p style="margin: 0 0 8px 0;">
                    <strong>N° de bon de caisse:</strong>
                    {{-- {{ $ordonnance->numero_bon ?? ($bonCommande->numero ?? '-') }} --}}
                    {{ $engagement->numero }}   
                    <br><i>N° of the cash voucher</i>
                </p>

                <p style="margin: 0 0 8px 0;">
                    <strong>N° Emission:</strong>
                    {{ $ordonnance->numero_emission ?? ($engagement->numero ?? '-') }}
                    <br><i>N° of emission</i>
                </p>

                <p style="margin: 0;">
                    <strong>{{ $labelOP }}</strong>
                    {{ $ordonnance->numero_op ?? $ordonnance->numero }}
                    <br><i>{{ $labelOPEn }}</i>
                </p>
            </div>
        </td>
    </tr>
</table>

{{-- Ligne de séparation --}}
<div style="border-bottom: 2px solid #000; margin: 10px 0;"></div>
