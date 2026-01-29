{{-- Footer pour Ordonnances de Paiement --}}
<div class="footer-op" style="margin-top: 30px; page-break-inside: avoid;">
    {{-- Montant en lettres --}}
    <div style="text-align: center; font-weight: bold; font-size: 10pt; margin-bottom: 30px; padding: 10px;">
        @yield('montant_lettres')
    </div>

    {{-- Signatures --}}
    <table style="width: 100%; border: none;">
        <tr>
            <td style="width: 33%; border: none; padding: 5px; text-align: center; font-size: 8pt;">
                <div style="font-weight: bold;">L'AGENT COMPTABLE</div>
                <div style="font-style: italic;">(THE ACCOUNTING OFFICER)</div>
            </td>
            <td style="width: 33%; border: none; padding: 5px; text-align: center; font-size: 8pt;">
                <div style="font-weight: bold;">PAIEMENT PAR:</div>
                <div style="font-style: italic;">(Payment made by...)</div>
            </td>
            <td style="width: 33%; border: none; padding: 5px; text-align: center; font-size: 8pt;">
                <div style="font-weight: bold;">Le Contrôleur Financier</div>
                <div style="font-style: italic;">(The Financial Controller)</div>
            </td>
        </tr>
    </table>

    {{-- Compte à créditer --}}
    <div style="margin-top: 20px; font-size: 8pt;">
        <strong>COMPTE A CREDITER</strong><br>
        <div style="font-style: italic; font-size: 7pt;">ACCOUNT TO BE</div>
    </div>
</div>
