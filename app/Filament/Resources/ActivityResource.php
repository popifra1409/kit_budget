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

class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Détails de l\'activité')
                    ->schema([
                        Forms\Components\TextInput::make('description')
                            ->label('Description')
                            ->disabled(),

                        Forms\Components\TextInput::make('subject_type')
                            ->label('Type d\'entité')
                            ->disabled(),

                        Forms\Components\TextInput::make('subject_id')
                            ->label('ID entité')
                            ->disabled(),

                        Forms\Components\TextInput::make('causer_type')
                            ->label('Type auteur')
                            ->disabled(),

                        Forms\Components\TextInput::make('causer_id')
                            ->label('ID auteur')
                            ->disabled(),

                        Forms\Components\Textarea::make('properties')
                            ->label('Propriétés')
                            ->disabled()
                            ->formatStateUsing(fn($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
                    ])
                    ->columns(2),
            ]);
    }

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
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => match ($state) {
                        'created' => 'Créé',
                        'updated' => 'Modifié',
                        'deleted' => 'Supprimé',
                        default => ucfirst($state),
                    })
                    ->toggleable(),
            ])
            ->filters([
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
