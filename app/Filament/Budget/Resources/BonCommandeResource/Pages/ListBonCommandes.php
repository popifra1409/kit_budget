<?php

namespace App\Filament\Budget\Resources\BonCommandeResource\Pages;

use App\Filament\Budget\Resources\BonCommandeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBonCommandes extends ListRecords
{
    use \App\Filament\Budget\Concerns\HasAgentContext;
    protected static string $resource = BonCommandeResource::class;

    public function mount(): void
    {
        // Assurez-vous que la propriété est un tableau
        $this->toggledTableColumns = (array) $this->toggledTableColumns;
        parent::mount();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nouveau bon de commande')
                ->icon('heroicon-o-plus'),
        ];
    }
}
