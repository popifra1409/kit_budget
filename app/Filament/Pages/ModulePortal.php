<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class ModulePortal extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static string  $view           = 'filament.pages.module-portal';
    protected static ?string $title          = 'Portail';
    protected static ?string $slug           = '/';
    protected static bool    $shouldRegisterNavigation = false;

    public function getTitle(): string
    {
        return '';
    }
}
