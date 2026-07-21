<?php

namespace App\Filament\Budget\Resources\CollectifBudgetaireResource\RelationManagers;

use App\Models\LigneBudgetaire;
use App\Models\LignePrevision;
use App\Models\MouvementCollectif;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class MouvementsRelationManager extends RelationManager
{
    protected static string $relationship = 'mouvements';

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->label('Type')
                    ->options([
                        'depense' => 'Dépense',
                        'recette' => 'Recette',
                    ])
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(fn($set) => $set('ligne_depense_id', null) + $set('ligne_recette_id', null) + $set('nouvelle_ligne_depense_id', null) + $set('nouvelle_ligne_recette_id', null)),

                // Si type = depense
                Forms\Components\Select::make('ligne_depense_id')
                    ->label('Ligne de dépense existante')
                    ->relationship('ligneDepense', 'libelle')
                    ->searchable()
                    ->preload()
                    ->visible(fn($get) => $get('type') === 'depense')
                    ->helperText('Sélectionner une ligne existante à modifier, ou laissez vide pour créer une nouvelle ligne.'),

                // Si type = recette
                Forms\Components\Select::make('ligne_recette_id')
                    ->label('Ligne de recette existante')
                    ->relationship('ligneRecette', 'libelle')
                    ->searchable()
                    ->preload()
                    ->visible(fn($get) => $get('type') === 'recette'),

                // Création d'une nouvelle ligne de dépense (si aucune ligne existante sélectionnée)
                Forms\Components\Fieldset::make('Nouvelle ligne de dépense')
                    ->schema([
                        Forms\Components\TextInput::make('nouvelle_ligne_depense.code')
                            ->label('Code')
                            ->required()
                            ->maxLength(50),
                        Forms\Components\TextInput::make('nouvelle_ligne_depense.libelle')
                            ->label('Libellé')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('nouvelle_ligne_depense.nomenclature_id')
                            ->label('Nomenclature')
                            ->relationship('nouvelleLigneDepense.nomenclature', 'libelle')
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('nouvelle_ligne_depense.montant_initial')
                            ->label('Montant initial')
                            ->numeric()
                            ->required()
                            ->default(0),
                        // On peut ajouter d'autres champs nécessaires
                    ])
                    ->visible(fn($get) => $get('type') === 'depense' && empty($get('ligne_depense_id'))),

                // Création d'une nouvelle ligne de recette
                Forms\Components\Fieldset::make('Nouvelle ligne de recette')
                    ->schema([
                        Forms\Components\TextInput::make('nouvelle_ligne_recette.code')
                            ->label('Code')
                            ->required()
                            ->maxLength(50),
                        Forms\Components\TextInput::make('nouvelle_ligne_recette.libelle')
                            ->label('Libellé')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('nouvelle_ligne_recette.nomenclature_id')
                            ->label('Nomenclature')
                            ->relationship('nouvelleLigneRecette.nomenclature', 'libelle')
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('nouvelle_ligne_recette.montant_initial')
                            ->label('Montant initial')
                            ->numeric()
                            ->required()
                            ->default(0),
                    ])
                    ->visible(fn($get) => $get('type') === 'recette' && empty($get('ligne_recette_id'))),

                Forms\Components\TextInput::make('montant_modification')
                    ->label('Montant de la modification')
                    ->numeric()
                    ->required()
                    ->helperText('Positif pour une augmentation, négatif pour une réduction.'),

                Forms\Components\TextInput::make('motif')
                    ->label('Motif')
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\BadgeColumn::make('type')
                    ->label('Type')
                    ->colors([
                        'warning' => 'depense',
                        'info' => 'recette',
                    ]),

                Tables\Columns\TextColumn::make('ligneDepense.libelle')
                    ->label('Dépense (existante)')
                    ->limit(30),

                Tables\Columns\TextColumn::make('ligneRecette.libelle')
                    ->label('Recette (existante)')
                    ->limit(30),

                Tables\Columns\TextColumn::make('nouvelleLigneDepense.libelle')
                    ->label('Nouvelle dépense')
                    ->limit(30),

                Tables\Columns\TextColumn::make('nouvelleLigneRecette.libelle')
                    ->label('Nouvelle recette')
                    ->limit(30),

                Tables\Columns\TextColumn::make('montant_modification')
                    ->label('Montant')
                    ->money('XOF', true)
                    ->color(fn($record) => $record->montant_modification >= 0 ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('motif')
                    ->label('Motif')
                    ->limit(30)
                    ->toggleable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn($livewire) => $livewire->getOwnerRecord()->estModifiable()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => $record->collectif->estModifiable()),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn($record) => $record->collectif->estModifiable()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn($livewire) => $livewire->getOwnerRecord()->estModifiable()),
                ]),
            ]);
    }

    // Pour gérer la création d'une nouvelle ligne lors de l'enregistrement du mouvement
    public static function getRecordTitle(?Model $record): string
    {
        return 'Mouvement #' . $record?->id;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Si création d'une nouvelle ligne de dépense
        if ($data['type'] === 'depense' && empty($data['ligne_depense_id']) && isset($data['nouvelle_ligne_depense'])) {
            $ligneData = $data['nouvelle_ligne_depense'];
            // Créer la ligne de dépense
            $ligne = LigneBudgetaire::create([
                'budget_id' => $this->getOwnerRecord()->exercice->budgets()->first()?->id, // À adapter
                'code' => $ligneData['code'],
                'libelle' => $ligneData['libelle'],
                'nomenclature_id' => $ligneData['nomenclature_id'] ?? null,
                'montant_initial' => $ligneData['montant_initial'],
                'budget_rectifie' => $ligneData['montant_initial'], // initialement égal
                'est_issue_collectif' => true,
                'collectif_creation_id' => $this->getOwnerRecord()->id,
            ]);
            $data['nouvelle_ligne_depense_id'] = $ligne->id;
            unset($data['nouvelle_ligne_depense']);
        }

        // Même chose pour recette
        if ($data['type'] === 'recette' && empty($data['ligne_recette_id']) && isset($data['nouvelle_ligne_recette'])) {
            $ligneData = $data['nouvelle_ligne_recette'];
            $ligne = LignePrevision::create([
                'prevision_recette_id' => $this->getOwnerRecord()->exercice->previsionRecettes()->first()?->id,
                'code' => $ligneData['code'],
                'libelle' => $ligneData['libelle'],
                'nomenclature_id' => $ligneData['nomenclature_id'] ?? null,
                'montant_initial' => $ligneData['montant_initial'],
                'montant_rectifie' => $ligneData['montant_initial'],
                'est_issue_collectif' => true,
                'collectif_creation_id' => $this->getOwnerRecord()->id,
            ]);
            $data['nouvelle_ligne_recette_id'] = $ligne->id;
            unset($data['nouvelle_ligne_recette']);
        }

        return $data;
    }
}
