<x-filament-panels::page>
    <form wire:submit="generer">
        {{ $this->form }}
        
        <div class="mt-6 flex gap-2">
            <x-filament::button type="submit" color="primary" icon="heroicon-o-document-arrow-down">
                Générer le Cadre Logique
            </x-filament::button>
        </div>
    </form>
    
    <x-filament::section class="mt-6">
        <x-slot name="heading">
            Aperçu du contenu
        </x-slot>
        
        <div class="text-sm text-gray-600 dark:text-gray-400 space-y-2">
            <p>Le cadre logique généré contiendra :</p>
            <ul class="list-disc list-inside ml-4 space-y-1">
                <li>Programme(s) et objectifs principaux</li>
                <li>Actions et objectifs spécifiques</li>
                <li>Activités avec leurs tâches</li>
                <li>Nomenclatures budgétaires liées</li>
                <li>Montants AE et CP</li>
                <li>Délais, guichets et services responsables</li>
                <li>Résultats attendus et indicateurs</li>
            </ul>
        </div>
    </x-filament::section>
</x-filament-panels::page>