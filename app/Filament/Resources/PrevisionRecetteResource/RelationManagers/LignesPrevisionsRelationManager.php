<?php

namespace App\Filament\Resources\PrevisionRecetteResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\NomenclatureBudgetaire;

class LignesPrevisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'lignesPrevisions';

    protected static ?string $title = 'Lignes de Prévisions de Recettes';

    protected static ?string $recordTitleAttribute = 'libelle_nomenclature';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('nomenclature_id')
                    ->label('Nomenclature (Recette - Classe 7)')
                    ->options(function () {
                        return NomenclatureBudgetaire::where('type', 'recette')
                            ->where('classe', '7')
                            ->where('actif', true)
                            ->orderBy('code')
                            ->pluck('libelle', 'id');
                    })
                    ->required()
                    ->searchable()
                    ->preload()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state) {
                            $nomenclature = NomenclatureBudgetaire::find($state);
                            if ($nomenclature) {
                                $set('code_nomenclature', $nomenclature->code);
                                $set('libelle_nomenclature', $nomenclature->libelle);
                            }
                        }
                    })
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('code_nomenclature')
                    ->label('Code')
                    ->disabled()
                    ->dehydrated()
                    ->placeholder('Auto-rempli'),

                Forms\Components\TextInput::make('libelle_nomenclature')
                    ->label('Libellé')
                    ->disabled()
                    ->dehydrated()
                    ->placeholder('Auto-rempli')
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('montant_prevu_initial')
                    ->label('Montant Prévu Initial')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->suffix('FCFA')
                    ->default(0)
                    ->helperText('Montant prévu à l\'origine'),

                Forms\Components\TextInput::make('montant_rectifie')
                    ->label('Montant Rectifié')
                    ->numeric()
                    ->minValue(0)
                    ->suffix('FCFA')
                    ->default(0)
                    ->helperText('Montant après modifications budgétaires')
                    ->reactive()
                    ->afterStateUpdated(fn($state, callable $set) => $state == 0 ? $set('montant_rectifie', $this->getOwnerRecord()->montant_prevu_initial) : null),

                Forms\Components\Placeholder::make('montant_recouvre_display')
                    ->label('Montant Recouvré')
                    ->content(fn($record) => $record ? number_format($record->montant_recouvre, 0, ',', ' ') . ' FCFA' : '0 FCFA')
                    ->helperText('Calculé automatiquement depuis les recettes réelles')
                    ->hidden(fn($record) => !$record),

                Forms\Components\Placeholder::make('taux_recouvrement_display')
                    ->label('Taux de Recouvrement')
                    ->content(fn($record) => $record ? number_format($record->taux_recouvrement, 2) . ' %' : '0 %')
                    ->hidden(fn($record) => !$record),

                Forms\Components\TextInput::make('ordre')
                    ->label('Ordre d\'Affichage')
                    ->numeric()
                    ->default(0)
                    ->helperText('Pour trier les lignes'),

                Forms\Components\Textarea::make('observations')
                    ->label('Observations')
                    ->rows(2)
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('actif')
                    ->label('Actif')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('libelle_nomenclature')
            ->columns([
                Tables\Columns\TextColumn::make('code_nomenclature')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('libelle_nomenclature')
                    ->label('Libellé')
                    ->searchable()
                    ->limit(50)
                    ->wrap(),

                Tables\Columns\TextColumn::make('montant_prevu_initial')
                    ->label('Prévu Initial')
                    ->money('XAF', locale: 'fr')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('montant_rectifie')
                    ->label('Prévu Rectifié')
                    ->money('XAF', locale: 'fr')
                    ->sortable()
                    ->weight('bold')
                    ->color('info'),

                Tables\Columns\TextColumn::make('montant_recouvre')
                    ->label('Recouvré')
                    ->money('XAF', locale: 'fr')
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\TextColumn::make('ecart')
                    ->label('Écart')
                    ->money('XAF', locale: 'fr')
                    ->sortable()
                    ->color(fn($record) => $record->ecart >= 0 ? 'success' : 'danger')
                    ->weight('bold'),

                Tables\Columns\BadgeColumn::make('taux_recouvrement')
                    ->label('Taux')
                    ->formatStateUsing(fn($state) => number_format($state, 1) . '%')
                    ->colors([
                        'danger' => fn($state) => $state < 70,
                        'warning' => fn($state) => $state >= 70 && $state < 90,
                        'success' => fn($state) => $state >= 90,
                    ]),

                Tables\Columns\TextColumn::make('ordre')
                    ->label('Ordre')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('actif')
                    ->label('Actif')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('actif')
                    ->label('Actif')
                    ->placeholder('Tous')
                    ->trueLabel('Actifs')
                    ->falseLabel('Inactifs'),

                Tables\Filters\Filter::make('sous_performance')
                    ->label('Sous-performance')
                    ->query(fn($query) => $query->where('taux_recouvrement', '<', 90))
                    ->toggle(),

                Tables\Filters\Filter::make('surperformance')
                    ->label('Surperformance')
                    ->query(fn($query) => $query->where('taux_recouvrement', '>', 100))
                    ->toggle(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Nouvelle Ligne')
                    ->icon('heroicon-o-plus')
                    ->modalWidth('3xl'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->modalWidth('3xl'),
                Tables\Actions\DeleteAction::make(),

                Tables\Actions\Action::make('recalculer')
                    ->label('Recalculer')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->action(fn($record) => $record->recalculer())
                    ->successNotificationTitle('Ligne recalculée')
                    ->requiresConfirmation(false),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('ordre')
            ->reorderable('ordre')
            ->paginated([10, 25, 50, 100]);
    }
}
