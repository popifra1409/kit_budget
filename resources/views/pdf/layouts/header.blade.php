{{-- En-tête du document PDF --}}
<div class="header">
    <table class="header-row">
        <tr>
            {{-- Partie gauche : République + Ministère --}}
            <td class="header-left">
                <div class="institution">REPUBLIQUE DU CAMEROUN</div>
                <div style="font-size: 8pt;">Paix – Travail – Patrie</div>
                <div style="margin-top: 3px; font-size: 8pt;">------------------</div>
                <div class="institution" style="margin-top: 3px;">
                    {{ $config->entete_config['institution'] ?? 'MINISTERE DE LA SANTE PUBLIQUE' }}
                </div>
                <div style="margin-top: 3px; font-size: 8pt;">------------------</div>
                <div class="etablissement" style="margin-top: 3px;">
                    {{ $config->entete_config['etablissement'] ?? 'HOPITAL GENERAL DE YAOUNDE' }}
                </div>
                <div style="margin-top: 3px; font-size: 8pt;">------------------</div>
                <div class="adresse" style="margin-top: 3px;">
                    {!! nl2br($config->entete_config['adresse'] ?? "B.P. 5408 – Yaoundé\nTél.: (237) 221.31.81 - 221.20.18") !!}
                </div>
            </td>

            {{-- Partie centrale : Logo (si présent) --}}
            <td class="header-center">
                @if (isset($config->entete_config['afficher_logo']) && $config->entete_config['afficher_logo'])
                    {{-- Le logo sera ajouté plus tard --}}
                @endif
            </td>

            {{-- Partie droite : Version anglaise --}}
            <td class="header-right">
                <div class="institution">REPUBLIC OF CAMEROON</div>
                <div style="font-size: 8pt;">Peace – Work – Fatherland</div>
                <div style="margin-top: 3px; font-size: 8pt;">------------------</div>
                <div class="institution" style="margin-top: 3px;">MINISTRY OF PUBLIC HEALTH</div>
                <div style="margin-top: 3px; font-size: 8pt;">------------------</div>
                <div class="etablissement" style="margin-top: 3px;">GENERAL HOSPITAL OF YAOUNDE</div>
                <div style="margin-top: 3px; font-size: 8pt;">------------------</div>
            </td>
        </tr>
    </table>
</div>
