{{-- Montant en lettres --}}
<div class="montant-lettres">
    Arrêté le présent bon de commande à la somme de
    <strong>@yield('montant_lettres')</strong>
</div>

{{-- Bas de page avec mentions et signature --}}
<div class="bas-page">
    <div class="mention-gauche">
        <div>Ref. Offre : __________________</div>
        <div style="margin-top:6px;">
            Conditions : voir au verso
        </div>
    </div>

    <div class="signature">
        <div class="signature-box">
            <div class="fonction">
                {{ $parametres->fonction_ordonnateur ?? 'LE DIRECTEUR GENERAL' }}
            </div>
            <div class="nom">
                {{ $parametres->nom_ordonnateur ?? '' }}
            </div>
        </div>
    </div>
</div>
