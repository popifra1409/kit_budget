<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\FicheControleEngagementsResource\Pages;
use App\Models\LigneBudgetaire;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FicheControleEngagementsResource extends Resource
{
    protected static ?string $model = LigneBudgetaire::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Fiches de Contrôle';

    protected static ?string $modelLabel = 'Fiche de Contrôle';

    protected static ?string $pluralModelLabel = 'Fiches de Contrôle';

    protected static ?string $navigationGroup = 'Contrôle & Suivi';

    protected static ?int $navigationSort = 5;

    public static function canViewAny(): bool
    {
        return auth()->check()
            && auth()->user()->can('view_any_fiche_controle_engagements');
    }

    public static function canView($record): bool
    {
        return auth()->check()
            && auth()->user()->can('view_fiche_controle_engagements');
    }

    public static function canCreate(): bool
    {
        return false; // Pas de création manuelle - généré automatiquement
    }

    public static function canEdit($record): bool
    {
        return false; // Pas d'édition - les fiches sont en lecture seule
    }

    public static function canDelete($record): bool
    {
        return false; // Pas de suppression
    }

    /**
     * Permission pour générer le PDF
     */
    public static function canGenererPdf($record): bool
    {
        return auth()->check()
            && auth()->user()->can('generer_pdf_fiche_controle_engagements');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('budget.exercice.annee')
                    ->label('Exercice')
                    ->badge()
                    ->color('success')
                    ->default('-'),

                Tables\Columns\TextColumn::make('budget.libelle')
                    ->label('Budget')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('nomenclature.code')
                    ->label('Code')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('nomenclature.libelle')
                    ->label('Libellé')
                    ->searchable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('budget_initial')
                    ->label('Dotation Initiale')
                    ->money('XAF')
                    ->sortable()
                    ->color('info'),

                Tables\Columns\TextColumn::make('total_engage')
                    ->label('Total Engagé')
                    ->getStateUsing(function ($record) {
                        return $record->engagements()->get()->sum('montant_engage');
                    })
                    ->money('XAF')
                    ->color('warning')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('disponible_engagement')
                    ->label('Disponible')
                    ->money('XAF')
                    ->sortable()
                    ->color(fn($state) => $state > 0 ? 'success' : 'danger')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('taux_consommation')
                    ->label('Taux Conso.')
                    ->getStateUsing(function ($record) {
                        if ($record->budget_initial == 0) return 0;
                        $totalEngage = $record->engagements()->get()->sum('montant_engage');
                        return ($totalEngage / $record->budget_initial) * 100;
                    })
                    ->formatStateUsing(fn($state) => number_format($state, 1) . '%')
                    ->badge()
                    ->color(function ($state) {
                        if ($state < 50) return 'success';
                        if ($state < 80) return 'warning';
                        if ($state < 100) return 'danger';
                        return 'danger';
                    }),

                Tables\Columns\TextColumn::make('nb_engagements')
                    ->label('Nb Engagements')
                    ->getStateUsing(fn($record) => $record->engagements()->get()->count())
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('budget_id')
                    ->label('Budget')
                    ->options(function () {
                        return \App\Models\Budget::orderBy('libelle')
                            ->pluck('libelle', 'id');
                    })
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('depassement')
                    ->label('Dépassements de crédits')
                    ->query(fn($query) => $query->where('disponible_engagement', '<', 0))
                    ->toggle(),

                Tables\Filters\Filter::make('dotation_positive')
                    ->label('Avec dotation > 0')
                    ->query(fn($query) => $query->where('budget_initial', '>', 0))
                    ->toggle(),
            ])
            ->actions([
                // Action PDF
                Tables\Actions\Action::make('generer_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('danger')
                    ->visible(fn($record) => static::canGenererPdf($record))
                    ->url(fn($record) => route('fiche-controle-engagements.pdf', $record->id))
                    ->openUrlInNewTab(),

                // Action Preview
                Tables\Actions\Action::make('preview')
                    ->label('Aperçu')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->visible(fn($record) => static::canView($record))
                    ->url(fn($record) => route('fiche-controle-engagements.preview', $record->id))
                    ->openUrlInNewTab(),

                // Action Voir les détails
                Tables\Actions\Action::make('details')
                    ->label('Détails')
                    ->icon('heroicon-o-information-circle')
                    ->color('gray')
                    ->visible(fn($record) => static::canView($record))
                    ->modalHeading('Détails de la Ligne Budgétaire')
                    ->modalContent(function ($record) {
                        $engagements = $record->engagements()->get();
                        $totalEngage = $engagements->sum('montant_engage');
                        $tauxConso = $record->budget_initial > 0
                            ? ($totalEngage / $record->budget_initial) * 100
                            : 0;

                        return view('filament.pages.fiche-controle-details', [
                            'record' => $record,
                            'engagements' => $engagements,
                            'totalEngage' => $totalEngage,
                            'tauxConso' => $tauxConso,
                        ]);
                    })
                    ->modalWidth('7xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer'),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFicheControleEngagements::route('/'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->with(['budget.exercice', 'nomenclature']);
    }
}
