{{-- 
    Composant : Affichage du montant
    
    Variables requises:
    - $montant : Montant en chiffres
    - $montantLettres : Montant en lettres
    
    Variables optionnelles:
    - $titre : Titre personnalisé (défaut: "Montant")
--}}

<div class="montant-box">
    @if (isset($titre))
        <div class="font-bold mb-10">{{ $titre }}</div>
    @endif

    <table class="simple">
        <tr>
            <td class="label"><strong>Montant en chiffres:</strong></td>
            <td class="valeur montant-chiffres">{{ $montant }}</td>
        </tr>
        <tr>
            <td class="label"><strong>En lettres:</strong></td>
            <td class="valeur montant-lettres">{{ $montantLettres }}</td>
        </tr>
    </table>
</div>
