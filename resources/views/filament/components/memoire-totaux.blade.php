    <div class="space-y-2">
        <div class="grid grid-cols-2 gap-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <div>
                <p class="text-sm text-gray-600 dark:text-gray-400">Montant HT</p>
                <p class="text-lg font-bold">{{ number_format($memoire->montant_ht, 0, ',', ' ') }} FCFA</p>
            </div>
            <div>
                <p class="text-sm text-gray-600 dark:text-gray-400">Montant TVA</p>
                <p class="text-lg font-bold">{{ number_format($memoire->montant_tva, 0, ',', ' ') }} FCFA</p>
            </div>
            <div>
                <p class="text-sm text-gray-600 dark:text-gray-400">Montant IR</p>
                <p class="text-lg font-bold">{{ number_format($memoire->montant_ir, 0, ',', ' ') }} FCFA</p>
            </div>
            <div>
                <p class="text-sm text-gray-600 dark:text-gray-400">Montant TTC</p>
                <p class="text-lg font-bold text-primary-600">{{ number_format($memoire->montant_ttc, 0, ',', ' ') }} FCFA
                </p>
            </div>
        </div>

        <div class="p-4 bg-primary-50 dark:bg-primary-900/20 rounded-lg">
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Net à payer</p>
            <p class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                {{ number_format($memoire->montant_net, 0, ',', ' ') }} FCFA
            </p>
        </div>

        @if ($memoire->montant_lettres)
            <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border-l-4 border-primary-500">
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Montant en lettres (TTC)</p>
                <p class="font-medium">{{ $memoire->montant_lettres }}</p>
            </div>
        @endif
    </div>
