<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $user        = $this->record;
        $personnelId = $this->data['personnel_id'] ?? null;

        if ($personnelId) {
            // ✅ Mettre à jour le user_id dans Personnel
            \App\Models\Personnel::where('id', $personnelId)
                ->update(['user_id' => $user->id]);

            // ✅ Dissocier l'ancien Personnel si différent
            \App\Models\Personnel::where('user_id', $user->id)
                ->where('id', '!=', $personnelId)
                ->update(['user_id' => null]);
        }
    }
}
