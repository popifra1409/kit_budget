<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    /**
     * ✅ Synchroniser les permissions après création
     */
    protected function afterCreate(): void
    {
        $permissions = $this->data['permissions'] ?? [];

        $this->record->syncPermissions($permissions);
    }
}
