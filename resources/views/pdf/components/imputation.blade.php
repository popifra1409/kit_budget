{{-- 
    Composant : Imputation budgétaire
    
    Variables requises:
    - $chapitre : Chapitre budgétaire
    - $article : Article budgétaire
    - $paragraphe : Paragraphe budgétaire
    
    Variables optionnelles:
    - $titre : Titre personnalisé
    - $typeDocument : Type de document (autorisation, engagement, etc.)
--}}

<div class="imputation-section">
    <div class="imputation-titre">
        {{ $titre ?? 'Cette ' . ($typeDocument ?? 'autorisation') . ' est imputée de la manière suivante:' }}
    </div>

    <table class="simple">
        <tr>
            <td class="label"><strong>Chapitre:</strong></td>
            <td class="valeur">{{ $chapitre }}</td>
        </tr>
        <tr>
            <td class="label"><strong>Article:</strong></td>
            <td class="valeur">{{ $article }}</td>
        </tr>
        <tr>
            <td class="label"><strong>Paragraphe:</strong></td>
            <td class="valeur">{{ $paragraphe }}</td>
        </tr>
    </table>
</div>
