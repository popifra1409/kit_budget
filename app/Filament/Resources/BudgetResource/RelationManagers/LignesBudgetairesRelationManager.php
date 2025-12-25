<?php

namespace App\Filament\Resources\BudgetResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\NomenclatureBudgetaire;

class LignesBudgetairesRelationManager extends RelationManager
{
    protected static string $relationship = 'lignesBudgetaires';

    protected static ?string $title = 'Lignes Budgétaires';

    protected static ?string $label = 'Ligne';

    protected static ?string $pluralLabel = 'Lignes budgétaires';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Nomenclature')
                    ->schema([
                        Forms\Components\Select::make('nomenclature_id')
                            ->label('Nomenclature budgétaire (Dépense)')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->getSearchResultsUsing(function (string $search) {
                                return NomenclatureBudgetaire::where('type', 'depense')
                                    ->where('actif', true)
                                    ->where(function ($query) use ($search) {
                                        $query->where('libelle', 'like', "%{$search}%")
                                            ->orWhere('code', 'like', "%{$search}%");
                                    })
                                    ->orderBy('code')
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn($item) => [$item->id => "{$item->code} - {$item->libelle}"]);
                            })
                            ->getOptionLabelUsing(
                                fn($value): ?string =>
                                NomenclatureBudgetaire::find($value)?->code . ' - ' .
                                    NomenclatureBudgetaire::find($value)?->libelle
                            )
                            ->helperText('Sélectionnez la ligne budgétaire'),
                    ]),

                Forms\Components\Section::make('Budget Initial')
                    ->schema([
                        Forms\Components\TextInput::make('budget_initial')
                            ->label('Budget initial alloué')
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->prefix('FCFA')
                            ->placeholder('0')
                            ->helperText('Budget voté/initial pour cette ligne'),
                    ]),

                Forms\Components\Section::make('Observations')
                    ->schema([
                        Forms\Components\Textarea::make('observations')
                            ->label('Observations')
                            ->rows(2),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nomenclature_id')
            ->columns([
                Tables\Columns\TextColumn::make('nomenclature.code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('nomenclature.libelle')
                    ->label('Nomenclature')
                    ->searchable()
                    ->wrap()
                    ->limit(40),

                Tables\Columns\TextColumn::make('budget_initial')
                    ->label('Budget Initial')
                    ->money('XAF')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('XAF')
                            ->label('Total'),
                    ]),

                Tables\Columns\TextColumn::make('virements_entrants')
                    ->label('Vir. Entrants')
                    ->money('XAF')
                    ->color('success')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('virements_sortants')
                    ->label('Vir. Sortants')
                    ->money('XAF')
                    ->color('danger')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('budget_rectifie')
                    ->label('Budget Rectifié')
                    ->money('XAF')
                    ->sortable()
                    ->weight('bold')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('XAF')
                            ->label('Total'),
                    ]),

                Tables\Columns\TextColumn::make('engage')
                    ->label('Engagé')
                    ->money('XAF')
                    ->sortable()
                    ->color('warning')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('XAF')
                            ->label('Total'),
                    ]),

                Tables\Columns\TextColumn::make('liquide')
                    ->label('Liquidé')
                    ->money('XAF')
                    ->sortable()
                    ->color('info')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('XAF')
                            ->label('Total'),
                    ]),

                Tables\Columns\TextColumn::make('disponible_engagement')
                    ->label('Disponible')
                    ->money('XAF')
                    ->sortable()
                    ->color(fn($record) => $record->disponible_engagement < 0 ? 'danger' : 'success')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('XAF')
                            ->label('Total'),
                    ]),

                Tables\Columns\TextColumn::make('taux_execution')
                    ->label('Taux exec.')
                    ->formatStateUsing(fn($record) => number_format($record->getTauxExecution(), 1) . '%')
                    ->color(fn($record) => $record->getTauxExecution() >= 80 ? 'success' : ($record->getTauxExecution() >= 50 ? 'warning' : 'danger'))
                    ->toggleable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Ajouter une ligne'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('nomenclature.code', 'asc');
    }
}
