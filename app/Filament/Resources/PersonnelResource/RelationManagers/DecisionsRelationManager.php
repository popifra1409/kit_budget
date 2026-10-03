<?php

namespace App\Filament\Resources\PersonnelResource\RelationManagers;

use App\Filament\Budget\Resources\DecisionAdministrativeResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fiche agent — onglet « Décisions administratives » : historique de toutes les DA
 * prises au nom de l'agent, tous exercices confondus. Lecture seule.
 */
class DecisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'decisionsAdministratives';
    protected static ?string $title = 'Décisions administratives';
    protected static ?string $icon = 'heroicon-o-document-text';

    /** id d'exercice => année (une seule requête par affichage). */
    protected static function annees(): array
    {
        static $annees = null;

        return $annees ??= \App\Models\Exercice::orderByDesc('annee')->pluck('annee', 'id')->all();
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            // Historique complet : sans le filtre d'exercice actif
            ->modifyQueryUsing(fn(Builder $query) => $query->withoutGlobalScope('exercice')->with(['typeDecision']))
            ->recordTitleAttribute('numero')
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('N° DA')->weight('bold')->copyable()->searchable()->sortable(),

                // Année de l'exercice (sans dépendre d'une relation 'exercice' sur la DA)
                Tables\Columns\TextColumn::make('exercice_id')
                    ->label('Exercice')->badge()->color('gray')->sortable()
                    ->formatStateUsing(fn($state) => static::annees()[$state] ?? '—'),

                Tables\Columns\TextColumn::make('typeDecision.libelle')
                    ->label('Type')->limit(25)->placeholder('—'),

                Tables\Columns\TextColumn::make('objet')
                    ->label('Objet')->limit(40)->tooltip(fn($record) => $record->objet)->searchable(),

                Tables\Columns\TextColumn::make('montant_brut')
                    ->label('Brut')->money('XAF')->sortable(),

                Tables\Columns\TextColumn::make('montant_net')
                    ->label('Net')->money('XAF')->color('success')->toggleable(),

                Tables\Columns\TextColumn::make('statut')
                    ->label('Statut')->badge()
                    ->formatStateUsing(fn($state) => ucfirst(str_replace('_', ' ', (string) $state)))
                    ->color(fn($state) => match ($state) {
                        'engagee'                     => 'success',
                        'validee', 'valide'           => 'info',
                        'brouillon'                   => 'gray',
                        'annulee', 'annule', 'rejete' => 'danger',
                        default                       => 'warning',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créée le')->date('d/m/Y')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('exercice_id')
                    ->label('Exercice')
                    ->options(fn() => static::annees()),
            ])
            ->actions([
                // Toujours ouverte dans le module Budget, quel que soit le panel de consultation
                Tables\Actions\Action::make('voir')
                    ->label('Voir')->icon('heroicon-o-eye')->color('info')
                    ->url(fn($record) => DecisionAdministrativeResource::getUrl('view', ['record' => $record], panel: 'budget'))
                    ->openUrlInNewTab(),
            ], position: ActionsPosition::BeforeColumns)
            ->headerActions([])
            ->bulkActions([]);
    }
}
