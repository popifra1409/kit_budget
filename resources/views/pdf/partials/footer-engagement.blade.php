{{-- Footer pour documents d'engagement --}}
<div class="footer-engagement" style="margin-top: 10px; page-break-inside: avoid;">
    {{-- Montant en lettres --}}
    {{-- <div style="margin-bottom: 20px; padding: 10px; border: 1px solid #000;">
        <strong>Arrêté le présent engagement à la somme de :</strong><br>
        @yield('montant_lettres')
    </div> --}}

    {{-- Signatures --}}
    <table style="width: 100%; border: none; margin-top: 20px;">
        <tr>
            <td style="width: 50%; border: none; padding: 0; vertical-align: top;">
                {{-- <div style="text-align: center; font-weight: bold; font-size: 9pt;">
                    VISA DU RESPONSABLE DE LA TACHE
                </div> --}}
            </td>
            <td style="width: 50%; border: none; padding: 0; vertical-align: top;">
                <div style="text-align: center; font-weight: 400; font-size: 9pt; margin-bottom:20px">
                    YAOUNDE, Le _______________
                </div>
                <div style="text-align: center; font-weight: bold; font-size: 9pt;">
                    VISA DE L'ORDONNATEUR.
                </div>
            </td>
        </tr>
    </table>
</div>
