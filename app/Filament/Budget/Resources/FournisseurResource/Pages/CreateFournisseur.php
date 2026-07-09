<?php

namespace App\Filament\Budget\Resources\FournisseurResource\Pages;

use App\Filament\Budget\Resources\FournisseurResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateFournisseur extends CreateRecord
{
    use \App\Filament\Budget\Concerns\HasAgentContext;
    protected static string $resource = FournisseurResource::class;
}
