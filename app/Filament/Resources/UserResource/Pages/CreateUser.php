<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        $user      = $this->record;
        $personnelId = $this->data['personnel_id'] ?? null;

        if ($personnelId) {
            // ✅ Lier le user_id dans Personnel
            \App\Models\Personnel::where('id', $personnelId)
                ->update(['user_id' => $user->id]);
        }
    }
}
