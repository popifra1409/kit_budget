<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\Models\Permission;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected static string $view =
    'filament.resources.role-resource.pages.edit-role';

    public array $permissions = [];
    public array $permissionsParModule = [];

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->permissions = $this->record
            ->permissions
            ->pluck('id')
            ->toArray();

        $this->permissionsParModule = Permission::all()
            ->groupBy(fn($p) => explode('.', $p->name)[0])
            ->toArray();
    }

    protected function afterSave(): void
    {
        $this->record->syncPermissions($this->permissions);

        Notification::make()
            ->success()
            ->title('Rôle mis à jour')
            ->body('Permissions synchronisées avec succès')
            ->send();
    }
}
