<?php

namespace App\Filament\Budget\Resources\CollectifBudgetaireResource\RelationManagers;

use App\Models\LigneBudgetaire;
use App\Models\LignePrevisionRecette;
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
                    ->afterStateUpdated(fn($set) => $set('ligne_depense_id', null) && $set('ligne_recette_id', null) && $set('creer_nouvelle_ligne', false)),

                // Sélection d'une ligne de dépense existante
                Forms\Components\Select::make('ligne_depense_id')
                    ->label('Ligne de dépense existante')
                    ->options(fn() => LigneBudgetaire::with('nomenclature')
                        ->get()
                        ->mapWithKeys(fn($item) => [$item->id => $item->nomenclature?->libelle ?? 'N/A'])
                        ->toArray())
                    ->searchable()
                    ->visible(fn($get) => $get('type') === 'depense')
                    ->required(fn($get) => $get('type') === 'depense' && !$get('creer_nouvelle_ligne')),

                // Sélection d'une ligne de recette existante
                Forms\Components\Select::make('ligne_recette_id')
                    ->label('Ligne de recette existante')
                    ->options(fn() => LignePrevisionRecette::with('nomenclature')
                        ->get()
                        ->mapWithKeys(fn($item) => [$item->id => $item->nomenclature?->libelle ?? 'N/A'])
                        ->toArray())
                    ->searchable()
                    ->visible(fn($get) => $get('type') === 'recette')
                    ->required(fn($get) => $get('type') === 'recette' && !$get('creer_nouvelle_ligne')),

                // Créer une nouvelle ligne
                Forms\Components\Toggle::make('creer_nouvelle_ligne')
                    ->label('Créer une nouvelle ligne')
                    ->reactive()
                    ->visible(fn($get) => in_array($get('type'), ['depense', 'recette'])),

                // Sous-formulaire pour nouvelle ligne de dépense
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('nouveau_code')->label('Code')->maxLength(50),
                        Forms\Components\TextInput::make('nouveau_libelle')->label('Libellé')->maxLength(255),
                        Forms\Components\TextInput::make('nouveau_montant')->label('Montant initial')->numeric()->prefix('FCFA'),
                        // Si vous avez besoin de la nomenclature, ajoutez un select
                    ])
                    ->visible(fn($get) => $get('creer_nouvelle_ligne') && $get('type') === 'depense'),

                // Sous-formulaire pour nouvelle ligne de recette
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('nouveau_code_recette')->label('Code')->maxLength(50),
                        Forms\Components\TextInput::make('nouveau_libelle_recette')->label('Libellé')->maxLength(255),
                        Forms\Components\TextInput::make('nouveau_montant_recette')->label('Montant initial')->numeric()->prefix('FCFA'),
                    ])
                    ->visible(fn($get) => $get('creer_nouvelle_ligne') && $get('type') === 'recette'),

                // Montant de la modification
                Forms\Components\TextInput::make('montant_modification')
                    ->label('Montant de la modification')
                    ->numeric()
                    ->prefix('FCFA')
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
                    ->colors(['warning' => 'depense', 'info' => 'recette']),

                Tables\Columns\TextColumn::make('ligneDepense.nomenclature.libelle')
                    ->label('Ligne dépense')
                    ->visible(fn($record) => $record && $record->type === 'depense' && $record->ligneDepense),

                Tables\Columns\TextColumn::make('ligneRecette.nomenclature.libelle')
                    ->label('Ligne recette')
                    ->visible(fn($record) => $record && $record->type === 'recette' && $record->ligneRecette),

                Tables\Columns\TextColumn::make('nouvelleLigneDepense.nomenclature.libelle')
                    ->label('Nouvelle ligne dépense')
                    ->visible(fn($record) => $record && $record->type === 'depense' && $record->nouvelleLigneDepense),

                Tables\Columns\TextColumn::make('nouvelleLigneRecette.nomenclature.libelle')
                    ->label('Nouvelle ligne recette')
                    ->visible(fn($record) => $record && $record->type === 'recette' && $record->nouvelleLigneRecette),

                Tables\Columns\TextColumn::make('montant_modification')
                    ->label('Montant')
                    ->money('XOF', true)
                    ->color(fn($record) => $record ? ($record->montant_modification < 0 ? 'danger' : 'success') : 'gray'),

                Tables\Columns\TextColumn::make('motif')
                    ->label('Motif')
                    ->limit(50),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->after(function ($record, array $data) {
                        $this->creerLigneSiNouvelle($record, $data);
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
            ]);
    }

    /**
     * Méthode utilitaire pour créer une nouvelle ligne (dépense ou recette)
     * lorsqu'elle est demandée dans le formulaire.
     */
    private function creerLigneSiNouvelle($record, array $data): void
    {
        if (!isset($data['creer_nouvelle_ligne']) || !$data['creer_nouvelle_ligne']) {
            return;
        }

        /** @var \App\Models\CollectifBudgetaire $collectif */
        $collectif = $this->getOwnerRecord();
        if (!$collectif) {
            throw new \Exception('Aucun collectif parent trouvé.');
        }

        $exercice = $collectif->exercice;
        if (!$exercice) {
            throw new \Exception('Aucun exercice associé à ce collectif.');
        }

        if ($data['type'] === 'depense') {
            // Récupérer le budget actif de l'exercice
            $budget = $exercice->budgets()->where('actif', true)->first();
            if (!$budget) {
                throw new \Exception('Aucun budget actif trouvé pour cet exercice.');
            }

            $nouvelleLigne = LigneBudgetaire::create([
                'budget_id'          => $budget->id,
                'code'               => $data['nouveau_code'] ?? null,
                'libelle'            => $data['nouveau_libelle'] ?? null,
                'budget_initial'     => $data['nouveau_montant'] ?? 0,
                'budget_rectifie'    => $data['nouveau_montant'] ?? 0,
                'est_issue_collectif' => true,
                'collectif_creation_id' => $collectif->id,
                // Ajoutez d'autres champs comme 'nomenclature_id' si nécessaire
            ]);

            $record->nouvelle_ligne_depense_id = $nouvelleLigne->id;
            $record->save();
        } elseif ($data['type'] === 'recette') {
            // Récupérer la prévision de recettes active de l'exercice
            $prevision = $exercice->previsionRecettes()->where('actif', true)->first();
            if (!$prevision) {
                throw new \Exception('Aucune prévision de recettes active trouvée pour cet exercice.');
            }

            $nouvelleLigne = LignePrevisionRecette::create([
                'prevision_recette_id' => $prevision->id,
                'code'                 => $data['nouveau_code_recette'] ?? null,
                'libelle'              => $data['nouveau_libelle_recette'] ?? null,
                'montant_initial'      => $data['nouveau_montant_recette'] ?? 0,
                'montant_rectifie'     => $data['nouveau_montant_recette'] ?? 0,
                'est_issue_collectif'  => true,
                'collectif_creation_id' => $collectif->id,
            ]);

            $record->nouvelle_ligne_recette_id = $nouvelleLigne->id;
            $record->save();
        }
    }
}
