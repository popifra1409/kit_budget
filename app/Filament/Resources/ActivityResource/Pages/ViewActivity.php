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
        return $infolist->schema([

            Components\Section::make('Informations générales')
                ->schema([
                    Components\TextEntry::make('description')
                        ->label('Description')
                        ->size('lg')
                        ->weight('bold')
                        ->color('primary'),

                    Components\Grid::make(4)
                        ->schema([
                            Components\TextEntry::make('created_at')
                                ->label('Date et heure')
                                ->dateTime('d/m/Y H:i:s'),

                            Components\TextEntry::make('causer.name')
                                ->label('Effectué par')
                                ->getStateUsing(fn($record) => $record->causer?->name ?: 'Système')
                                ->badge()
                                ->color('success'),

                            Components\TextEntry::make('event')
                                ->label('Événement')
                                ->badge()
                                ->color(fn($state) => match ($state) {
                                    'created' => 'success',
                                    'updated' => 'info',
                                    'deleted' => 'danger',
                                    'login'   => 'primary',
                                    'logout'  => 'gray',
                                    default   => 'gray',
                                })
                                ->formatStateUsing(fn($state) => match ($state) {
                                    'created' => 'Création',
                                    'updated' => 'Modification',
                                    'deleted' => 'Suppression',
                                    'login'   => 'Connexion',
                                    'logout'  => 'Déconnexion',
                                    default   => ucfirst((string) ($state ?? '—')),
                                }),

                            Components\TextEntry::make('ip_address')
                                ->label('Adresse IP')
                                ->default('—')
                                ->copyable(),
                        ]),
                ]),

            Components\Section::make('Entité concernée')
                ->schema([
                    Components\Grid::make(2)
                        ->schema([
                            Components\TextEntry::make('subject_type')
                                ->label('Type')
                                ->formatStateUsing(
                                    fn($state) => $state ? class_basename($state) : '—'
                                )
                                ->badge(),

                            Components\TextEntry::make('subject_id')
                                ->label('ID')
                                ->placeholder('—'),
                        ]),
                ])
                ->visible(fn($record) => !empty($record->subject_type)),

            Components\Section::make('Propriétés')
                ->schema([
                    Components\TextEntry::make('properties')
                        ->label('')
                        ->html()
                        ->getStateUsing(function ($record) {
                            $raw = $record->getAttributes()['properties'] ?? null;

                            if (is_string($raw)) {
                                $decoded = json_decode($raw, true);
                                $state = is_array($decoded) ? $decoded : [];
                            } else {
                                $state = [];
                            }

                            if (empty($state)) {
                                return 'Aucune propriété enregistrée';
                            }

                            $html = '<div class="space-y-2">';

                            foreach ($state as $key => $value) {
                                $html .= '<div class="flex gap-2">';
                                $html .= '<strong>' . htmlspecialchars(ucfirst(str_replace('_', ' ', (string) $key))) . ':</strong>';
                                $html .= '<span>' . static::stringifyValeur($value) . '</span>';
                                $html .= '</div>';
                            }

                            $html .= '</div>';

                            return $html;
                        })
                        ->columnSpanFull(),
                ])
                ->collapsible(),

            Components\Section::make('Détails techniques')
                ->schema([
                    Components\TextEntry::make('user_agent')
                        ->label('Navigateur / Appareil')
                        ->default('—')
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->collapsed(),

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

    /**
     * ✅ Convertit n'importe quelle valeur (array, Collection, objet, null,
     * scalaire) en chaîne HTML-safe, peu importe la profondeur d'imbrication.
     */
    protected static function stringifyValeur($value): string
    {
        if ($value === null) {
            return '<em class="text-gray-400">—</em>';
        }

        if (is_bool($value)) {
            return $value ? 'Oui' : 'Non';
        }

        if (is_array($value) || $value instanceof \Illuminate\Support\Collection) {
            $array = $value instanceof \Illuminate\Support\Collection ? $value->toArray() : $value;
            $json  = json_encode($array, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            return '<pre class="text-xs whitespace-pre-wrap">' . htmlspecialchars($json !== false ? $json : '—') . '</pre>';
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return htmlspecialchars((string) $value);
            }
            $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            return '<pre class="text-xs whitespace-pre-wrap">' . htmlspecialchars($json !== false ? $json : '—') . '</pre>';
        }

        return htmlspecialchars((string) $value);
    }
}
