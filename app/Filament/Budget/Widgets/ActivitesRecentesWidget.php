<?php

namespace App\Filament\Budget\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Spatie\Activitylog\Models\Activity;

class ActivitesRecentesWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('📋 Activités récentes')
            ->description('Les 10 dernières actions effectuées dans le système')
            ->query(
                Activity::query()
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->size('sm'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Action')
                    ->wrap()
                    ->weight('bold')
                    ->color('primary')
                    ->searchable(),

                Tables\Columns\TextColumn::make('causer.name')
                    ->label('Utilisateur')
                    ->default('Système')
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Entité')
                    ->formatStateUsing(fn($state) => $state ? class_basename($state) : '—')
                    ->badge()
                    ->color(fn($state) => match ($state ? class_basename($state) : null) {
                        'Exercice' => 'warning',
                        'BordereauEngagement' => 'info',
                        'Budget' => 'success',
                        'Engagement' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('event')
                    ->label('Type')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'created' => 'success',
                        'updated' => 'info',
                        'deleted' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => match ($state) {
                        'created' => 'Créé',
                        'updated' => 'Modifié',
                        'deleted' => 'Supprimé',
                        default => ucfirst($state),
                    })
                    ->size('sm'),
            ])
            ->paginated(false);
    }

    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole([
            'super_admin',
            'directeur_general',
            'sous_directeur_budget',
            'chef_service_budget',
        ]);
    }
}
