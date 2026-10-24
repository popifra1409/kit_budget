<?php

namespace App\Filament\Financier\Pages;

use App\Models\CompteComptable;
use App\Models\EcritureComptable;
use App\Models\JournalComptable;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Schema;

class FinancierDashboard extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-calculator';
    protected static ?string $navigationLabel = 'Tableau de Bord';
    protected static ?string $title           = 'Tableau de Bord — Comptabilité & Flux Financiers';
    protected static ?int    $navigationSort  = 0;
    protected static string  $view            = 'filament.financier.pages.financier-dashboard';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('access_module_financier') ?? false;
    }

    /**
     * État du socle, supportant le panel avant que les migrations soient jouées.
     */
    public function etatSocle(): array
    {
        $pret = Schema::hasTable('comptes_comptables')
            && Schema::hasTable('journaux_comptables')
            && Schema::hasTable('ecritures_comptables');

        if (!$pret) {
            return [
                'pret'      => false,
                'comptes'   => null,
                'tiers'     => null,
                'journaux'  => null,
                'ecritures' => null,
            ];
        }

        return [
            'pret'      => true,
            'comptes'   => CompteComptable::count(),
            'tiers'     => CompteComptable::classe('4')->imputables()->count(),
            'journaux'  => JournalComptable::count(),
            'ecritures' => EcritureComptable::count(),
        ];
    }
}
