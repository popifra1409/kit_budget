<?php

namespace App\Filament\Budget\Resources\BudgetResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\NomenclatureBudgetaire;
use App\Models\LigneBudgetaire;

class LignesBudgetairesRelationManager extends RelationManager
{
    protected static string $relationship = 'lignesBudgetaires';
    protected static ?string $title       = 'Lignes Budgétaires';
    protected static ?string $label       = 'Ligne';
    protected static ?string $pluralLabel = 'Lignes budgétaires';

    public function form(Form $form): Form
    {
        // ✅ Récupérer le budget UNE FOIS — accessible dans toutes les closures
        $budget = $this->getOwnerRecord();

        return $form
            ->schema([
                Forms\Components\Section::make('Nomenclature')
                    ->schema([
                        Forms\Components\Select::make('nomenclature_id')
                            ->label('Nomenclature budgétaire (Dépense)')
                            ->required()
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search) {
                                return NomenclatureBudgetaire::where('type', 'depense')
                                    ->where('actif', true)
                                    ->where(function ($query) use ($search) {
                                        $query->where('libelle', 'like', "%{$search}%")
                                            ->orWhere('code',   'like', "%{$search}%");
                                    })
                                    ->orderBy('code')
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn($item) => [
                                        $item->id => "{$item->code} - {$item->libelle}"
                                    ]);
                            })
                            ->getOptionLabelUsing(function ($value): ?string {
                                $n = NomenclatureBudgetaire::find($value);
                                return $n ? "{$n->code} - {$n->libelle}" : null;
                            })
                            // ✅ Pas de ->rules() — le using() gère tout
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
        $budget = $this->getOwnerRecord();

        return $table
            ->recordTitleAttribute('nomenclature_id')
            ->columns([
                Tables\Columns\TextColumn::make('nomenclature.code')
                    ->label('Code')->searchable()->sortable()->weight('bold'),

                Tables\Columns\TextColumn::make('nomenclature.libelle')
                    ->label('Nomenclature')->searchable()->wrap()->limit(40),

                Tables\Columns\TextColumn::make('budget_initial')
                    ->label('Budget Initial')->money('XAF')->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()->money('XAF')->label('Total'),
                    ]),

                Tables\Columns\TextColumn::make('virements_entrants')
                    ->label('Vir. Entrants')->money('XAF')->color('success')->toggleable(),

                Tables\Columns\TextColumn::make('virements_sortants')
                    ->label('Vir. Sortants')->money('XAF')->color('danger')->toggleable(),

                Tables\Columns\TextColumn::make('budget_rectifie')
                    ->label('Budget Rectifié')->money('XAF')->sortable()->weight('bold')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()->money('XAF')->label('Total'),
                    ]),

                Tables\Columns\TextColumn::make('engage')
                    ->label('Engagé')->money('XAF')->sortable()->color('warning')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()->money('XAF')->label('Total'),
                    ]),

                Tables\Columns\TextColumn::make('liquide')
                    ->label('Liquidé')->money('XAF')->sortable()->color('info')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()->money('XAF')->label('Total'),
                    ]),

                Tables\Columns\TextColumn::make('disponible_engagement')
                    ->label('Disponible')->money('XAF')->sortable()
                    ->color(fn($record) => $record->disponible_engagement < 0 ? 'danger' : 'success')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()->money('XAF')->label('Total'),
                    ]),

                Tables\Columns\TextColumn::make('taux_execution')
                    ->label('Taux exec.')
                    ->formatStateUsing(fn($record) => number_format($record->getTauxExecution(), 1) . '%')
                    ->color(
                        fn($record) => $record->getTauxExecution() >= 80
                            ? 'success'
                            : ($record->getTauxExecution() >= 50 ? 'warning' : 'danger')
                    )
                    ->toggleable(),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Ajouter une ligne')
                    ->using(function (array $data) use ($budget): LigneBudgetaire {

                        $donneesMAJ = [
                            'budget_initial'            => $data['budget_initial'],
                            'budget_rectifie'           => $data['budget_initial'],
                            'disponible_engagement'     => $data['budget_initial'],
                            'disponible_ordonnancement' => 0,
                            'observations'              => $data['observations'] ?? null,
                        ];

                        // ── Cas 1 : ligne soft-deleted → restaurer ─────────
                        $ligneCorbeille = LigneBudgetaire::withTrashed()
                            ->where('budget_id',       $budget->id)
                            ->where('nomenclature_id', $data['nomenclature_id'])
                            ->whereNotNull('deleted_at')
                            ->first();

                        if ($ligneCorbeille) {
                            $ligneCorbeille->restore();
                            $ligneCorbeille->update($donneesMAJ);

                            \Filament\Notifications\Notification::make()
                                ->title('Ligne restaurée')
                                ->info()
                                ->body('La ligne supprimée a été restaurée avec les nouvelles valeurs.')
                                ->send();

                            return $ligneCorbeille->fresh();
                        }

                        // ── Cas 2 : ligne active existante → mettre à jour ─
                        $ligneActive = LigneBudgetaire::where('budget_id',       $budget->id)
                            ->where('nomenclature_id', $data['nomenclature_id'])
                            ->first();

                        if ($ligneActive) {
                            $ligneActive->update($donneesMAJ);

                            \Filament\Notifications\Notification::make()
                                ->title('Ligne mise à jour')
                                ->warning()
                                ->body('Cette nomenclature existait déjà — les valeurs ont été mises à jour.')
                                ->send();

                            return $ligneActive->fresh();
                        }

                        // ── Cas 3 : aucun doublon → création normale ────────
                        return LigneBudgetaire::create(array_merge(
                            ['budget_id' => $budget->id, 'nomenclature_id' => $data['nomenclature_id']],
                            $donneesMAJ
                        ));
                    }),
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
