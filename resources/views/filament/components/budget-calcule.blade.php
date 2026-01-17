<div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 p-4">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Nombre de sous-tâches -->
        <div class="text-center">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                Sous-tâches
            </div>
            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                {{ $count ?? 0 }}
            </div>
        </div>

        <!-- Total AE -->
        <div class="text-center">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                Total AE
            </div>
            <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                {{ number_format($ae ?? 0, 0, ',', ' ') }} <span class="text-sm">FCFA</span>
            </div>
        </div>

        <!-- Total CP -->
        <div class="text-center">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                Total CP
            </div>
            <div class="text-2xl font-bold text-orange-600 dark:text-orange-400">
                {{ number_format($cp ?? 0, 0, ',', ' ') }} <span class="text-sm">FCFA</span>
            </div>
        </div>
    </div>

    @if (($ae ?? 0) > 0 || ($cp ?? 0) > 0)
        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <p class="text-xs text-gray-600 dark:text-gray-400 text-center">
                💡 Ces montants sont calculés automatiquement à partir des sous-tâches
            </p>
        </div>
    @endif
</div>
