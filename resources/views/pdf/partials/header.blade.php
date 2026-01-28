{{-- Header avec République du Cameroun, Logo et Ministry --}}
<table class="header-table">
    <tr>
        <td class="header-left">
            <div class="republique">
                RÉPUBLIQUE DU CAMEROUN<br>
                <em>Paix – Travail – Patrie</em>
            </div>
            <div class="republique" style="margin-top:4px">
                MINISTERE DE LA SANTE PUBLIQUE
            </div>
        </td>

        <td class="header-center">
            @if ($parametres && $parametres->logo)
                <img src="{{ public_path('storage/' . $parametres->logo) }}" class="logo" alt="Logo">
            @endif
            <div class="structure">
                {{ $parametres->nom_structure ?? 'HGY' }}
            </div>
            <div class="adresse">
                {{ $parametres->adresse ?? '' }}<br>
                Tél : {{ $parametres->telephone ?? '' }}
            </div>
        </td>

        <td class="header-right">
            <div class="republique">
                REPUBLIC OF CAMEROON<br>
                <em>Peace – Work – Fatherland</em>
            </div>
            <div class="republique" style="margin-top:4px">
                <em>MINISTRY OF PUBLIC HEALTH</em>
            </div>
        </td>
    </tr>
</table>

<div style="border-bottom: 2px solid #000; margin-bottom: 10px;"></div>
