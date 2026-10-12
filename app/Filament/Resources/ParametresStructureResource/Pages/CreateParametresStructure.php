<?php

namespace App\Filament\Resources\ParametresStructureResource\Pages;

use App\Filament\Resources\ParametresStructureResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateParametresStructure extends CreateRecord
{
    use \App\Filament\Budget\Concerns\HasAgentContext;
    protected static string $resource = ParametresStructureResource::class;
}
