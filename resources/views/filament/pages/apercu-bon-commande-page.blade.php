<x-filament-panels::page>
    @include('filament.modals.apercu-bon-commande', ['bc' => $record->load([
        'fournisseur', 'budget', 'lignes.nomenclature',
        'serviceDemandeur', 'typeEngagement', 'exercice', 'engagement',
    ])])
</x-filament-panels::page>