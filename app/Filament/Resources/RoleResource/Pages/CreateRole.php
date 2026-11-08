<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use App\Models\ActivityLog;
use Filament\Resources\Pages\CreateRecord;
use Spatie\Permission\PermissionRegistrar;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    /**
     * Un rôle qui naît avec des cases cochées accordent des droits : c'est une écriture du
     * catalogue, pas une simple création de ligne.
     *
     * L'ancien `syncPermissions($this->data['permissions'])` est retiré : `permissions` est
     * un CheckboxList branché sur la relation, donc CreateRecord::create() a déjà
     * synchronisé le pivot sur le même état avant ce hook. Il ne faisait que rejouer la
     * liste — et laissait la création sans détail lisible dans le journal.
     */
    protected function afterCreate(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        ActivityLog::journaliserDroitsDeRole(
            $this->record,
            [],
            ['niveau_hierarchique' => $this->record->niveau_hierarchique],
            nouveau: true,
        );
    }
}
