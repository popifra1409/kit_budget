<?php

namespace App\Filament\Resources\PersonnelResource\RelationManagers;

use App\Filament\Budget\Resources\EngagementResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fiche agent — onglet « Ordonnances de paiement » : OP émises au nom de l'agent,
 * payées ou non, tous exercices confondus. Lecture seule.
 */
class OrdonnancesRelationManager extends RelationManager
{
    protected static string $relationship = 'ordonnancesBeneficiaire';
    protected static ?string $title = 'Ordonnances de paiement';
    protected static ?string $icon = 'heroicon-o-credit-card';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query->withoutGlobalScope('exercice')->with('engagement'))
            ->recordTitleAttribute('numero')
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('N° OP')->weight('bold')->copyable()->searchable()->sortable(),

                Tables\Columns\TextColumn::make('type_ordonnance')
                    ->label('Type')->badge()
                    ->formatStateUsing(fn($state) => $state === 'impot' ? 'OP impôt' : 'OP standard')
                    ->color(fn($state) => $state === 'impot' ? 'warning' : 'primary'),

                Tables\Columns\TextColumn::make('engagement.numero')
                    ->label('Engagement')->placeholder('—')->searchable(),

                Tables\Columns\TextColumn::make('objet')
                    ->label('Objet')->limit(35)->tooltip(fn($record) => $record->objet),

                Tables\Columns\TextColumn::make('montant_brut')
                    ->label('Brut')->money('XAF')->toggleable(),

                Tables\Columns\TextColumn::make('montant_net')
                    ->label('Net')->money('XAF')->sortable(),

                Tables\Columns\TextColumn::make('statut')
                    ->label('Statut')->badge()
                    ->formatStateUsing(fn($state) => match ($state) {
                        'emise'   => 'Émise',
                        'payee'   => 'Payée',
                        'annulee' => 'Annulée',
                        default   => ucfirst((string) $state),
                    })
                    ->color(fn($state) => match ($state) {
                        'payee'   => 'success',
                        'emise'   => 'warning',
                        'annulee' => 'danger',
                        default   => 'gray',
                    }),

                Tables\Columns\TextColumn::make('date_emission')
                    ->label('Émise le')->date('d/m/Y')->sortable(),

                Tables\Columns\TextColumn::make('date_paiement')
                    ->label('Payée le')->date('d/m/Y')->placeholder('—')->sortable(),
            ])
            ->defaultSort('date_emission', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('statut')
                    ->options(['emise' => 'Émise', 'payee' => 'Payée']),
                Tables\Filters\SelectFilter::make('type_ordonnance')
                    ->label('Type')
                    ->options(['standard' => 'OP standard', 'impot' => 'OP impôt']),
            ])
            ->actions([
                Tables\Actions\Action::make('voir_engagement')
                    ->label('Voir l\'engagement')->icon('heroicon-o-eye')->color('info')
                    ->visible(fn($record) => $record->engagement_id !== null)
                    ->url(fn($record) => EngagementResource::getUrl('view', ['record' => $record->engagement_id], panel: 'budget'))
                    ->openUrlInNewTab(),
            ], position: ActionsPosition::BeforeColumns)
            ->headerActions([])
            ->bulkActions([]);
    }
}
