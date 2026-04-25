{{-- Header pour Ordonnance de Paiement --}}
@php
    $ordonnance = $donnees['_raw'] ?? null;
    $engagement = $ordonnance->engagement ?? null;
    $bonCommande = $engagement->bonCommande ?? null;

    // Déterminer le label selon le type
    $labelOP = $ordonnance && $ordonnance->type_ordonnance === 'impot' ? 'N° OPT:' : 'N° OP:';
    $labelOPEn = $ordonnance && $ordonnance->type_ordonnance === 'impot' ? 'N° of OPT' : 'N° of OP';
@endphp

{{-- Header pour Ordonnance de Paiement --}}
@php
    $ordonnance = $donnees['_raw'] ?? null;
    $engagement = $ordonnance->engagement ?? null;
    $bonCommande = $engagement->bonCommande ?? null;

    // Déterminer le label selon le type
    $labelOP = $ordonnance && $ordonnance->type_ordonnance === 'impot' ? 'N° OPT:' : 'N° OP:';
    $labelOPEn = $ordonnance && $ordonnance->type_ordonnance === 'impot' ? 'N° of OPT' : 'N° of OP';
@endphp

<table style="width: 100%; border: none; margin-top: 5px;">
    <tr>
        <td style="width: 30%; padding: 5px; text-align: center; vertical-align: top;">
            <div style="font-weight: bold; font-size: 10pt; text-align: center; margin-top: 5px; line-height: 1.1;">
                <strong>Visa du </strong><br>
                {{ strtoupper($parametres->sous_direction) }}
            </div>
        </td>
        <td style="width: 40%; padding-top:3px; text-align: center; vertical-align: top;">
            <div style="font-weight: bold; font-size: 11pt;">
                {{ strtoupper($parametres->nom_structure ?? 'HÔPITAL GÉNÉRAL DE YAOUNDÉ') }}
            </div>
            <div style="font-size: 9pt; font-style: italic; margin-bottom: 3px;">
                {{ strtoupper($parametres->nom_structure_en ?? 'YAOUNDE GENERAL HOSPITAL') }}
            </div>

            <div style="font-size: 8pt; margin-bottom: 50px;">
                B.P {{ $parametres->boite_postale ?? '5408' }} {{ $parametres->ville ?? 'YAOUNDE' }}.
                Tel: {{ $parametres->telephone ?? '(237) 222 21 20 18' }}
                Fax: {{ $parametres->fax ?? '(237) 222 21 20 15' }}
            </div>
            <div style="margin-bottom: 0px;">
                <div style="font-weight: 300; font-size: 12pt;">ORDONNANCE DE PAIEMENT - <span
                        style="font-style: italic; font-size: 11pt;">PAYMENT ORDER</span></div>

            </div>
        </td>
        <td style="width: 30%; border: none; padding: 0px; vertical-align: top; text-align: left;">
            <div style="border: 1px solid #000; padding: 5px; font-size: 7.5pt; line-height: 1;">
                <p style="margin: 0 0 5px 0;">
                    <strong>Mois et exercice d'émission:</strong>
                    {{ $ordonnance->mois_emission ?? now()->format('m') }}/{{ $ordonnance->exercice->annee ?? now()->year }}
                    <br><i>Month and budgetary</i>
                </p>

                <p style="margin: 0 0 5px 0;">
                    <strong>Exercice budgétaire:</strong>
                    {{ $ordonnance->exercice->annee ?? now()->year }}
                    <br><i>Budgetary Year</i>
                </p>

                <p style="margin: 0 0 5px 0;">
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
    <tr>
        <td>
            <div style="font-size: 8.5pt; line-height: 1.1; text-align:center; border-bottom:0px;">
                <div style="font-weight: bold; margin-bottom: 10px">
                    @if ($engagement->estBonCommande())
                        {{ strtoupper($engagement->engageable?->typeEngagement?->libelle ?? $engagement->type_engagement ?? 'BON DE COMMANDE') }}
                    @elseif ($engagement->estDecision())
                        {{ strtoupper($engagement->engageable?->typeDecision?->libelle ?? $engagement->type_engagement ?? 'DÉCISION ADMINISTRATIVE') }}
                    @else
                        {{ strtoupper($engagement->type_engagement ?? 'ENGAGEMENT') }}
                    @endif
                    - <span style="font-style: italic; font-weight:300;">Du</span>
                    __ __ __ __ __ __ __ __
                </div>
                <div style="font-weight: bold;">
                    NUMERO - <span style="font-style: italic; font-weight:300;">NUMBER</span>
                </div>
                <div style="margin-top: 3px; font-size:9pt; line-height: 1.1;">
                    @if ($engagement && $documentSource)
                        {{ $documentSource->numero }}<br>
                    @endif
                </div>
            </div>
        </td>
        <td>
            <div style="min-height: 35px; font-size: 8.5pt; line-height: 1.1; text-align:center;">
                <div style="font-weight: bold;">
                    Situation des crédits
                </div>
                <div style="font-style: italic; text-align:center;">
                    Vote Postionmitments
                </div>
            </div>
        </td>
        <td style="width: 30%; padding: 5px; vertical-align: middle; text-align: left;">
            <div style="margin-bottom: 5px;">
                <div class="font-bold" style="font-size: 8pt;">L'AGENT COMPTABLE</div>
                <div class="font-tiny" style="font-style: italic;">THE ACCOUNTING OFFICER</div>
            </div>
        </td>
    </tr>
</table>
{{-- Ligne de séparation --}}
{{-- <div style="border-bottom: 2px solid #000; margin: 10px 0;"></div> --}}