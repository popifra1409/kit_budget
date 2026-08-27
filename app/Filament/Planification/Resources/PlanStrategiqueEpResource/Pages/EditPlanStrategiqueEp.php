<?php
// app/Filament/Planification/Resources/PlanStrategiqueEpResource/Pages/EditPlanStrategiqueEp.php
namespace App\Filament\Planification\Resources\PlanStrategiqueEpResource\Pages;

use App\Filament\Planification\Resources\PlanStrategiqueEpResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPlanStrategiqueEp extends EditRecord
{
    protected static string $resource = PlanStrategiqueEpResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
