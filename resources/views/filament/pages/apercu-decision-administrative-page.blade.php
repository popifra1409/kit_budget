<x-filament-panels::page>
    @include('filament.modals.apercu-decision-administrative', [
        'da' => $record->load([
            'personnel', 'fournisseur', 'budget', 'typeDecision',
            'exercice', 'engagement.nomenclaturePrincipale',
        ])
    ])
</x-filament-panels::page>