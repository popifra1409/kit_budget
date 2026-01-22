<?php

namespace App\Filament\Resources\PrevisionRecetteResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\RelationManagers\RelationManager;
use App\Models\NomenclatureBudgetaire;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class LignesPrevisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'lignesPrevisions';

    protected static ?string $title = 'Lignes de prévision';

    protected static ?string $modelLabel = 'ligne de prévision';

    protected static ?string $pluralModelLabel = 'lignes de prévision';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('nomenclature_id')
                    ->label('Compte de recette')
                    ->relationship(
                        name: 'nomenclature',
                        titleAttribute: 'libelle',
                        modifyQueryUsing: function ($query) {
                            $ownerRecord = $this->getOwnerRecord();
                            $exerciceId = $ownerRecord?->exercice_id;

                            return $query->where('type', 'recette')
                                ->where('actif', true)
                                ->when(
                                    $exerciceId,
                                    fn($q) => $q->where('exercice_id', $exerciceId)
                                )
                                ->orderBy('code');
                        }
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn($record) => "{$record->code} - {$record->libelle}"
                    )
                    ->searchable(['code', 'libelle'])
                    ->required()
                    ->rules([
                        fn($record) => Rule::unique('lignes_previsions_recettes', 'nomenclature_id')
                            ->where('prevision_recette_id', $this->getOwnerRecord()->id)
                            ->ignore($record?->id),
                    ])
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        $nomenclature = \App\Models\NomenclatureBudgetaire::find($state);

                        if ($nomenclature) {
                            $set('code_nomenclature', $nomenclature->code);
                            $set('libelle_nomenclature', $nomenclature->libelle);
                        }
                    })
                    ->columnSpanFull(),


                // Champs cachés pour les données dénormalisées
                Forms\Components\Hidden::make('code_nomenclature')
                    ->dehydrated(),
                Forms\Components\Hidden::make('libelle_nomenclature')
                    ->dehydrated(),

                Forms\Components\TextInput::make('montant_prevu_initial')
                    ->label('Montant initial prévu')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->prefix('FCFA')
                    ->default(0),

                Forms\Components\TextInput::make('montant_rectifie')
                    ->label('Montant rectifié')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('FCFA')
                    ->default(fn($get) => $get('montant_prevu_initial') ?: 0),

                Forms\Components\Textarea::make('observations')
                    ->label('Observations')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Remplir automatiquement code_nomenclature et libelle_nomenclature
        if (!empty($data['nomenclature_id'])) {
            $nomenclature = NomenclatureBudgetaire::find($data['nomenclature_id']);
            if ($nomenclature) {
                $data['code_nomenclature'] = $nomenclature->code;
                $data['libelle_nomenclature'] = $nomenclature->libelle;
            } else {
                \Log::error('Nomenclature not found with ID: ' . $data['nomenclature_id']);
            }
        } else {
            \Log::error('nomenclature_id is empty in mutateFormDataBeforeCreate');
        }

        // Initialiser montant_rectifie si vide
        if (empty($data['montant_rectifie']) && isset($data['montant_prevu_initial'])) {
            $data['montant_rectifie'] = $data['montant_prevu_initial'];
        }

        return $data;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nomenclature.libelle')
            ->columns([
                Tables\Columns\TextColumn::make('code_nomenclature')
                    ->label('Code')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('libelle_nomenclature')
                    ->label('Libellé')
                    ->wrap()
                    ->limit(50),

                Tables\Columns\TextColumn::make('montant_prevu_initial')
                    ->label('Initial')
                    ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ') . ' FCFA')
                    ->color('info'),

                Tables\Columns\TextColumn::make('montant_rectifie')
                    ->label('Rectifié')
                    ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ') . ' FCFA')
                    ->color('warning'),

                Tables\Columns\TextColumn::make('montant_recouvre')
                    ->label('Recouvré')
                    ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ') . ' FCFA')
                    ->color('success'),
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
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }
}
