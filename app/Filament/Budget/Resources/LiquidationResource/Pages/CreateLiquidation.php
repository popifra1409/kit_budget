<?php

namespace App\Filament\Budget\Resources\LiquidationResource\Pages;

use App\Filament\Budget\Resources\LiquidationResource;
use App\Models\Engagement;
use App\Services\Budget\LiquidationService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateLiquidation extends CreateRecord
{
    protected static string $resource = LiquidationResource::class;

    /** Création par le service : contrôles, numérotation, preuves attendues de la nature. */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(LiquidationService::class)->creer(Engagement::findOrFail($data['engagement_id']), $data);
        } catch (\DomainException $e) {
            Notification::make()->warning()->title('Liquidation impossible')->body($e->getMessage())->persistent()->send();
            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
