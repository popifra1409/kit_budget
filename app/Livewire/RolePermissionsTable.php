<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

class RolePermissionsTable extends Component
{
    public Role $role;
    public array $selectedPermissions = [];
    public string $search = '';
    public string $filterResource = 'all';

    public function mount(Role $role)
    {
        $this->role = $role;
        $this->selectedPermissions = $role->permissions()->pluck('id')->toArray();
    }

    public function togglePermission($permissionId)
    {
        if (in_array($permissionId, $this->selectedPermissions)) {
            $this->selectedPermissions = array_diff($this->selectedPermissions, [$permissionId]);
        } else {
            $this->selectedPermissions[] = $permissionId;
        }
    }

    public function toggleAll($resource)
    {
        $permissions = $this->getPermissionsByResource($resource);
        $permissionIds = $permissions->pluck('id')->toArray();

        $allSelected = empty(array_diff($permissionIds, $this->selectedPermissions));

        if ($allSelected) {
            $this->selectedPermissions = array_diff($this->selectedPermissions, $permissionIds);
        } else {
            $this->selectedPermissions = array_unique(array_merge($this->selectedPermissions, $permissionIds));
        }
    }

    public function save()
    {
        $this->role->syncPermissions($this->selectedPermissions);

        Notification::make()
            ->success()
            ->title('Permissions mises à jour')
            ->body(count($this->selectedPermissions) . ' permission(s) assignée(s)')
            ->send();
    }

    public function getPermissionsByResource($resource): Collection
    {
        return Permission::all()->filter(function ($permission) use ($resource) {
            $parts = explode('_', $permission->name);
            if (count($parts) > 1) {
                array_shift($parts);
                $permResource = implode('_', $parts);
                return $resource === 'all' || $permResource === $resource;
            }
            return $resource === 'all';
        });
    }

    /**
     * ✅ Propriété computed pour les permissions groupées
     */
    #[Computed]
    public function groupedPermissions(): Collection
    {
        $permissions = Permission::all();

        $grouped = $permissions->groupBy(function ($permission) {
            $parts = explode('_', $permission->name);
            if (count($parts) > 1) {
                array_shift($parts);
                return implode('_', $parts);
            }
            return 'autres';
        });

        if ($this->search) {
            $grouped = $grouped->map(function ($perms) {
                return $perms->filter(function ($perm) {
                    return str_contains(strtolower($perm->name), strtolower($this->search));
                });
            })->filter(fn($perms) => $perms->isNotEmpty());
        }

        if ($this->filterResource !== 'all') {
            $grouped = $grouped->filter(function ($perms, $resource) {
                return $resource === $this->filterResource;
            });
        }

        return $grouped;
    }

    /**
     * ✅ Propriété computed pour les ressources
     */
    #[Computed]
    public function resources(): Collection
    {
        return Permission::all()->groupBy(function ($permission) {
            $parts = explode('_', $permission->name);
            if (count($parts) > 1) {
                array_shift($parts);
                return implode('_', $parts);
            }
            return 'autres';
        })->keys()->sort();
    }

    /**
     * ✅ Propriété computed pour le total
     */
    #[Computed]
    public function totalPermissions(): int
    {
        return Permission::count();
    }

    public function render()
    {
        return view('livewire.role-permissions-table');
    }
}
