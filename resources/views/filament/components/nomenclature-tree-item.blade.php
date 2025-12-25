@php
    $indentation = $niveau * 2; // 2rem par niveau
    $enfants = $item->enfants()->where('actif', true)->orderBy('code')->get();
    $hasEnfants = $enfants->count() > 0;

    $couleurNiveau = match ($item->niveau) {
        'classe' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        'compte' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        'sous_compte' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        'ligne' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
        default => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
    };
@endphp

<div class="border-l-2 border-gray-300 dark:border-gray-600" style="margin-left: {{ $indentation }}rem;"
    x-data="{ open: true }">
    <div class="flex items-start gap-2 p-2 hover:bg-gray-50 dark:hover:bg-gray-700 rounded transition">
        <!-- Indicateur d'enfants -->
        @if ($hasEnfants)
            <button type="button" @click="open = !open"
                class="mt-1 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                <svg x-show="!open" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
                <svg x-show="open" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
        @else
            <div class="w-4 mt-1"></div>
        @endif

        <!-- Icône selon le niveau -->
        <div class="mt-1">
            @if ($item->niveau === 'classe')
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                </svg>
            @elseif($item->niveau === 'compte')
                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                    </path>
                </svg>
            @elseif($item->niveau === 'sous_compte')
                <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z">
                    </path>
                </svg>
            @else
                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                    </path>
                </svg>
            @endif
        </div>

        <!-- Contenu -->
        <div class="flex-1">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="font-mono font-bold text-sm">{{ $item->code }}</span>
                <span class="px-2 py-0.5 text-xs rounded {{ $couleurNiveau }}">
                    {{ match ($item->niveau) {
                        'classe' => 'Classe',
                        'compte' => 'Compte',
                        'sous_compte' => 'Sous-compte',
                        'ligne' => 'Ligne',
                        default => $item->niveau,
                    } }}
                </span>
            </div>
            <p class="text-sm text-gray-700 dark:text-gray-300 mt-1">{{ $item->libelle }}</p>
        </div>

        <!-- Actions -->
        <div class="flex gap-1">
            <a href="{{ route('filament.admin.resources.nomenclature-budgetaires.edit', $item) }}"
                class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200" title="Modifier">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                    </path>
                </svg>
            </a>
        </div>
    </div>

    <!-- Enfants (récursif) -->
    @if ($hasEnfants)
        <div x-show="open" class="mt-1">
            @foreach ($enfants as $enfant)
                @include('filament.components.nomenclature-tree-item', [
                    'item' => $enfant,
                    'niveau' => $niveau + 1,
                ])
            @endforeach
        </div>
    @endif
</div>
