<?php

namespace App\Filament\Resources\ActivityResource\Pages;

use App\Filament\Resources\ActivityResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;

class ViewActivity extends ViewRecord
{
    protected static string $resource = ActivityResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Informations générales')
                    ->schema([
                        Components\TextEntry::make('description')
                            ->label('Description')
                            ->size('lg')
                            ->weight('bold')
                            ->color('primary'),

                        Components\Grid::make(3)
                            ->schema([
                                Components\TextEntry::make('created_at')
                                    ->label('Date et heure')
                                    ->dateTime('d/m/Y H:i:s'),

                                Components\TextEntry::make('causer.name')
                                    ->label('Effectué par')
                                    ->default('Système')
                                    ->badge()
                                    ->color('success'),

                                Components\TextEntry::make('event')
                                    ->label('Événement')
                                    ->badge()
                                    ->color(fn($state) => match ($state) {
                                        'created' => 'success',
                                        'updated' => 'info',
                                        'deleted' => 'danger',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn($state) => match ($state) {
                                        'created' => 'Création',
                                        'updated' => 'Modification',
                                        'deleted' => 'Suppression',
                                        default => ucfirst($state),
                                    }),
                            ]),
                    ]),

                Components\Section::make('Entité concernée')
                    ->schema([
                        Components\Grid::make(2)
                            ->schema([
                                Components\TextEntry::make('subject_type')
                                    ->label('Type')
                                    ->formatStateUsing(fn($state) => class_basename($state))
                                    ->badge(),

                                Components\TextEntry::make('subject_id')
                                    ->label('ID'),
                            ]),
                    ]),

                Components\Section::make('Propriétés')
                    ->schema([
                        Components\TextEntry::make('properties')
                            ->label('')
                            ->formatStateUsing(function ($state) {
                                if (empty($state)) {
                                    return 'Aucune propriété enregistrée';
                                }

                                $html = '<div class="space-y-2">';

                                foreach ($state as $key => $value) {
                                    if (is_array($value)) {
                                        $value = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                                    }

                                    $html .= '<div class="flex gap-2">';
                                    $html .= '<strong>' . ucfirst(str_replace('_', ' ', $key)) . ':</strong>';
                                    $html .= '<span>' . htmlspecialchars($value) . '</span>';
                                    $html .= '</div>';
                                }

                                $html .= '</div>';

                                return new \Illuminate\Support\HtmlString($html);
                            })
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Components\Section::make('Métadonnées')
                    ->schema([
                        Components\Grid::make(2)
                            ->schema([
                                Components\TextEntry::make('log_name')
                                    ->label('Nom du log')
                                    ->default('default'),

                                Components\TextEntry::make('batch_uuid')
                                    ->label('Batch UUID')
                                    ->placeholder('Aucun')
                                    ->visible(fn($record) => !empty($record->batch_uuid)),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
                                                                                                                                    