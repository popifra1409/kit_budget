<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityResource\Pages;
use Spatie\Activitylog\Models\Activity;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Infolists;
use Filament\Infolists\Infolist;


class ActivityResource extends Resource
{
    // protected static ?string $model = Activity::class;

    protected static ?string $model = \App\Models\ActivityLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Journal d\'activité';

    protected static ?string $modelLabel = 'Activité';

    protected static ?string $pluralModelLabel = 'Journal d\'activité';

    protected static ?string $navigationGroup = 'Audit';

    protected static ?int $navigationSort = 1;

    /**
     * ================================
     * Permissions – Logs (admin only)
     * ================================
     */

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_activity') ?? false;
    }

    public static function canCreate(): bool
    {
        return false; // Logs créés automatiquement
    }

    public static function canEdit($record): bool
    {
        return false; // Logs non modifiables
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('delete_activity') ?? false;
    }

    // public static function infolist(Infolist $infolist): Infolist
    // {
    //     return $infolist->schema([
    //         Infolists\Components\Section::make('🔍 Diagnostic temporaire')
    //             ->schema([
    //                 Infolists\Components\TextEntry::make('diag_created_at')
    //                     ->label('created_at')
    //                     ->getStateUsing(fn($record) => static::diag('created_at', fn() => $record->created_at)),

    //                 Infolists\Components\TextEntry::make('diag_event')
    //                     ->label('event')
    //                     ->getStateUsing(fn($record) => static::diag('event', fn() => $record->event)),

    //                 Infolists\Components\TextEntry::make('diag_description')
    //                     ->label('description')
    //                     ->getStateUsing(fn($record) => static::diag('description', fn() => $record->description)),

    //                 Infolists\Components\TextEntry::make('diag_causer')
    //                     ->label('causer')
    //                     ->getStateUsing(fn($record) => static::diag('causer (objet complet)', fn() => $record->causer)),

    //                 Infolists\Components\TextEntry::make('diag_causer_name')
    //                     ->label('causer.name')
    //                     ->getStateUsing(fn($record) => static::diag('causer.name', fn() => $record->causer?->name)),

    //                 Infolists\Components\TextEntry::make('diag_ip')
    //                     ->label('ip_address')
    //                     ->getStateUsing(fn($record) => static::diag('ip_address', fn() => $record->ip_address)),

    //                 Infolists\Components\TextEntry::make('diag_subject_type')
    //                     ->label('subject_type')
    //                     ->getStateUsing(fn($record) => static::diag('subject_type', fn() => $record->subject_type)),

    //                 Infolists\Components\TextEntry::make('diag_subject_id')
    //                     ->label('subject_id')
    //                     ->getStateUsing(fn($record) => static::diag('subject_id', fn() => $record->subject_id)),

    //                 Infolists\Components\TextEntry::make('diag_subject')
    //                     ->label('subject (relation complète)')
    //                     ->getStateUsing(fn($record) => static::diag('subject', fn() => $record->subject)),

    //                 Infolists\Components\TextEntry::make('diag_user_agent')
    //                     ->label('user_agent')
    //                     ->getStateUsing(fn($record) => static::diag('user_agent', fn() => $record->user_agent)),

    //                 Infolists\Components\TextEntry::make('diag_properties')
    //                     ->label('properties (objet brut)')
    //                     ->getStateUsing(fn($record) => static::diag('properties', fn() => $record->properties)),

    //                 Infolists\Components\TextEntry::make('diag_batch_uuid')
    //                     ->label('batch_uuid')
    //                     ->getStateUsing(fn($record) => static::diag('batch_uuid', fn() => $record->batch_uuid ?? null)),

    //                 Infolists\Components\TextEntry::make('diag_log_name')
    //                     ->label('log_name')
    //                     ->getStateUsing(fn($record) => static::diag('log_name', fn() => $record->log_name ?? null)),
    //             ])
    //             ->columns(2),
    //     ]);
    // }

    // protected static function diag($label, callable $cb): string
    // {
    //     try {
    //         $value = $cb();

    //         if (is_array($value)) {
    //             return "❌ ARRAY : " . json_encode($value, JSON_UNESCAPED_UNICODE);
    //         }
    //         if ($value instanceof \Illuminate\Support\Collection) {
    //             return "❌ COLLECTION : " . json_encode($value->toArray(), JSON_UNESCAPED_UNICODE);
    //         }
    //         if (is_object($value) && !method_exists($value, '__toString')) {
    //             return "❌ OBJET sans __toString : " . get_class($value) . ' → ' . json_encode($value);
    //         }
    //         if ($value === null) {
    //             return '(null)';
    //         }

    //         return '✅ OK (' . gettype($value) . ') : ' . (string) $value;
    //     } catch (\Throwable $e) {
    //         return "❌ EXCEPTION : " . $e->getMessage();
    //     }
    // }

    // protected static function extraireProprietes($record, string $cle): array
    // {
    //     $data = collect($record->properties ?? [])->get($cle, []);
    //     return collect($data)
    //         ->mapWithKeys(fn($v, $k) => [
    //             (string) $k => is_scalar($v) || $v === null ? (string) ($v ?? '') : json_encode($v),
    //         ])
    //         ->toArray();
    // }

    // // ✅ Cast universel — neutralise tout "Array to string conversion"
    // protected static function safeText($value): string
    // {
    //     if ($value === null) return '';
    //     if (is_array($value)) return json_encode($value, JSON_UNESCAPED_UNICODE);
    //     if ($value instanceof \Illuminate\Support\Collection) return json_encode($value->toArray(), JSON_UNESCAPED_UNICODE);
    //     if (is_object($value) && method_exists($value, '__toString')) return (string) $value;
    //     if (is_object($value)) return json_encode($value, JSON_UNESCAPED_UNICODE);
    //     return (string) $value;
    // }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Action')
                    ->searchable()
                    ->wrap()
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('causer.name')
                    ->label('Utilisateur')
                    ->searchable()
                    ->sortable()
                    ->default('Système')
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Entité')
                    ->formatStateUsing(fn($state) => class_basename($state))
                    ->badge()
                    ->color(fn($state) => match (class_basename($state)) {
                        'Exercice' => 'warning',
                        'BordereauEngagement' => 'info',
                        'Budget' => 'success',
                        'Engagement' => 'danger',
                        default => 'gray',
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('subject_id')
                    ->label('ID')
                    ->searchable(),

                Tables\Columns\TextColumn::make('event')
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
                        'created' => 'Créé',
                        'updated' => 'Modifié',
                        'deleted' => 'Supprimé',
                        'login'   => 'Connexion',
                        'logout'  => 'Déconnexion',
                        default   => ucfirst($state),
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('Adresse IP')
                    ->copyable()
                    ->default('—')
                    ->searchable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event')
                    ->label('Événement')
                    ->options([
                        'created' => 'Créé',
                        'updated' => 'Modifié',
                        'deleted' => 'Supprimé',
                        'login'   => 'Connexion',
                        'logout'  => 'Déconnexion',
                    ]),

                Tables\Filters\SelectFilter::make('causer_id')
                    ->label('Utilisateur')
                    ->options(function () {
                        $userIds = \Spatie\Activitylog\Models\Activity::query()
                            ->whereNotNull('causer_id')
                            ->distinct()
                            ->pluck('causer_id');

                        return \App\Models\User::whereIn('id', $userIds)
                            ->pluck('name', 'id');
                    })
                    ->searchable(),

                Tables\Filters\SelectFilter::make('subject_type')
                    ->label('Type d\'entité')
                    ->options([
                        'App\Models\Exercice' => 'Exercice',
                        'App\Models\BordereauEngagement' => 'Bordereau',
                        'App\Models\Budget' => 'Budget',
                        'App\Models\Engagement' => 'Engagement',
                        'App\Models\BonCommande' => 'Bon de commande',
                        'App\Models\DecisionAdministrative' => 'Decision',
                        'App\Models\Programme' => 'Programme',
                        'App\Models\Action' => 'Action',
                        'App\Models\Activite' => 'Activite',
                        'App\Models\Tache' => 'Tâche',
                    ]),

                Tables\Filters\SelectFilter::make('event')
                    ->label('Événement')
                    ->options([
                        'created' => 'Créé',
                        'updated' => 'Modifié',
                        'deleted' => 'Supprimé',
                    ]),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Du'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Au'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn() => auth()->user()->hasRole('super_admin')),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivities::route('/'),
            'view' => Pages\ViewActivity::route('/{record}'),
        ];
    }
}
