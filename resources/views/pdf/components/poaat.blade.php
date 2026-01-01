{{-- 
    Composant : Programme / Objectif / Action / Activité / Tâche
    
    Variables requises:
    - $programme : Libellé du programme
    - $objectif : Libellé de l'objectif
    - $action : Libellé de l'action
    - $activite : Libellé de l'activité
    - $tache : Libellé de la tâche
--}}

<table class="poaat-table bordered">
    <tr>
        <td class="poaat-label">PROGRAMME</td>
        <td class="poaat-valeur">{{ $programme }}</td>
    </tr>
    <tr>
        <td class="poaat-label">OBJECTIF</td>
        <td class="poaat-valeur">{{ $objectif }}</td>
    </tr>
    <tr>
        <td class="poaat-label">ACTION:</td>
        <td class="poaat-valeur">{{ $action }}</td>
    </tr>
    <tr>
        <td class="poaat-label">ACTIVITE:</td>
        <td class="poaat-valeur">{{ $activite }}</td>
    </tr>
    <tr>
        <td class="poaat-label">TACHE:</td>
        <td class="poaat-valeur">{{ $tache }}</td>
    </tr>
</table>
