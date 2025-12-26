@php
    $activite = $tache->activite;
    $action = $activite->action;
    $programme = $action->programme;
@endphp

<div class="space-y-4">
    <!-- Programme -->
    <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
        <h3 class="font-bold text-blue-800 dark:text-blue-200 mb-2">📊 Programme</h3>
        <p class="text-sm"><span class="font-semibold">Code:</span> {{ $programme->code }}</p>
        <p class="text-sm"><span class="font-semibold">Libellé:</span> {{ $programme->libelle }}</p>
        <p class="text-sm"><span class="font-semibold">Objectif principal:</span>
            {{ $programme->objectifsPrincipaux->first()?->libelle ?? 'Non défini' }}</p>
    </div>

    <!-- Action -->
    <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg">
        <h3 class="font-bold text-green-800 dark:text-green-200 mb-2">⚡ Action</h3>
        <p class="text-sm"><span class="font-semibold">Code:</span> {{ $action->code }}</p>
        <p class="text-sm"><span class="font-semibold">Libellé:</span> {{ $action->libelle }}</p>
        <p class="text-sm"><span class="font-semibold">Objectif spécifique:</span>
            {{ $action->objectifsSpecifiques->first()?->libelle ?? 'Non défini' }}</p>
    </div>

    <!-- Activité -->
    <div class="bg-yellow-50 dark:bg-yellow-900/20 p-4 rounded-lg">
        <h3 class="font-bold text-yellow-800 dark:text-yellow-200 mb-2">📋 Activité</h3>
        <p class="text-sm"><span class="font-semibold">Code:</span> {{ $activite->code }}</p>
        <p class="text-sm"><span class="font-semibold">Libellé:</span> {{ $activite->libelle }}</p>
    </div>

    <!-- Tâche -->
    <div class="bg-purple-50 dark:bg-purple-900/20 p-4 rounded-lg">
        <h3 class="font-bold text-purple-800 dark:text-purple-200 mb-2">✓ Tâche</h3>
        <p class="text-sm"><span class="font-semibold">Code:</span> {{ $tache->code }}</p>
        <p class="text-sm"><span class="font-semibold">Libellé:</span> {{ $tache->libelle }}</p>
        @if ($tache->description)
            <p class="text-sm mt-2"><span class="font-semibold">Description:</span> {{ $tache->description }}</p>
        @endif
    </div>

    <!-- Informations de gestion -->
    <div class="bg-gray-50 dark:bg-gray-900/20 p-4 rounded-lg">
        <h3 class="font-bold text-gray-800 dark:text-gray-200 mb-2">📌 Gestion</h3>
        <div class="grid grid-cols-2 gap-2 text-sm">
            <div>
                <span class="font-semibold">Délai:</span> {{ $tache->delai ?? 'Non défini' }}
            </div>
            <div>
                <span class="font-semibold">Guichet:</span> {{ $tache->guichet ?? 'Non défini' }}
            </div>
            <div class="col-span-2">
                <span class="font-semibold">Service responsable:</span> {{ $tache->service?->nom ?? 'Non défini' }}
            </div>
        </div>
    </div>

    <!-- Budget -->
    <div class="bg-orange-50 dark:bg-orange-900/20 p-4 rounded-lg">
        <h3 class="font-bold text-orange-800 dark:text-orange-200 mb-2">💰 Budget</h3>
        <div class="grid grid-cols-2 gap-2 text-sm">
            <div>
                <span class="font-semibold">AE:</span> {{ number_format($tache->ae, 0, ',', ' ') }} FCFA
            </div>
            <div>
                <span class="font-semibold">CP:</span> {{ number_format($tache->cp, 0, ',', ' ') }} FCFA
            </div>
        </div>
    </div>

    <!-- Résultats -->
    <div class="bg-pink-50 dark:bg-pink-900/20 p-4 rounded-lg">
        <h3 class="font-bold text-pink-800 dark:text-pink-200 mb-2">🎯 Résultats</h3>
        <p class="text-sm mb-2"><span class="font-semibold">Résultat attendu:</span></p>
        <p class="text-sm ml-4">{{ $tache->resultat_attendu ?? 'Non défini' }}</p>

        <p class="text-sm mt-3 mb-2"><span class="font-semibold">Indicateur de résultat:</span></p>
        <p class="text-sm ml-4">{{ $tache->indicateur_resultat ?? 'Non défini' }}</p>
    </div>
</div>
