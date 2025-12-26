<?php

namespace App\Filament\Resources\BonCommandeResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\LigneBudgetaire;

class LignesRelationManager extends RelationManager
{
    protected static string $relationship = 'lignes';

    protected static ?string $title = 'Lignes du Bon de Commande';

    protected static ?string $label = 'Ligne';

    protected static ?string $pluralLabel = 'Lignes';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('nomenclature_id')
                    ->label('Nomenclature budgétaire')
                    ->options(function () {
                        $budgetId = $this->getOwnerRecord()->budget_id;

                        return LigneBudgetaire::where('budget_id', $budgetId)
                            ->with('nomenclature')
                            ->get()
                            ->mapWithKeys(fn($lb) => [
                                $lb->nomenclature_id => "{$lb->nomenclature->code} - {$lb->nomenclature->libelle} (Dispo: " .
                                    number_format($lb->disponible_engagement, 0, ',', ' ') . " FCFA)"
                            ]);
                    })
                    ->required()
                    ->searchable()
                    ->preload(),

                Forms\Components\TextInput::make('designation')
                    ->label('Désignation')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Ex: Ordinateur portable HP EliteBook')
                    ->columnSpanFull(),

                Forms\Components\Grid::make(4)
                    ->schema([
                        Forms\Components\TextInput::make('unite')
                            ->label('Unité')
                            ->maxLength(255)
                            ->placeholder('pièce, kg, m, etc.')
                            ->default('pièce'),

                        Forms\Components\TextInput::make('quantite')
                            ->label('Quantité')
                            ->required()
                            ->numeric()
                            ->default(1)
                            ->minValue(0.001)
                            ->reactive(),

                        Forms\Components\TextInput::make('prix_unitaire_ht')
                            ->label('Prix Unitaire HT')
                            ->required()
                            ->numeric()
                            ->prefix('FCFA')
                            ->default(0)
                            ->reactive(),

                        Forms\Components\TextInput::make('taux_tva')
                            ->label('Taux TVA (%)')
                            ->numeric()
                            ->default(19.25)
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100),
                    ]),

                Forms\Components\Placeholder::make('calculs')
                    ->label('Montants calculés')
                    ->content(function (callable $get) {
                        $qte = (float) ($get('quantite') ?? 0);
                        $pu = (float) ($get('prix_unitaire_ht') ?? 0);
                        $tva = (float) ($get('taux_tva') ?? 19.25);

                        $ht = $qte * $pu;
                        $montantTva = $ht * ($tva / 100);
                        $ttc = $ht + $montantTva;

                        return "HT: " . number_format($ht, 0, ',', ' ') . " FCFA | " .
                            "TVA: " . number_format($montantTva, 0, ',', ' ') . " FCFA | " .
                            "TTC: " . number_format($ttc, 0, ',', ' ') . " FCFA";
                    })
                    ->columnSpanFull(),

                Forms\Components\Textarea::make('observations')
                    ->label('Observations')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('designation')
            ->columns([
                Tables\Columns\TextColumn::make('numero_ligne')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('nomenclature.code')
                    ->label('Nomenclature')
                    ->searchable()
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('designation')
                    ->label('Désignation')
                    ->searchable()
                    ->wrap()
                    ->limit(40),

                Tables\Columns\TextColumn::make('quantite')
                    ->label('Qté')
                    ->numeric(decimalPlaces: 2),

                Tables\Columns\TextColumn::make('unite')
                    ->label('Unité')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('prix_unitaire_ht')
                    ->label('PU HT')
                    ->money('XAF'),

                Tables\Columns\TextColumn::make('montant_ht')
                    ->label('Montant HT')
                    ->money('XAF')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('XAF')
                            ->label('Total HT'),
                    ]),

                Tables\Columns\TextColumn::make('montant_tva')
                    ->label('TVA')
                    ->money('XAF')
                    ->toggleable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('XAF')
                            ->label('Total TVA'),
                    ]),

                Tables\Columns\TextColumn::make('montant_ttc')
                    ->label('Montant TTC')
                    ->money('XAF')
                    ->sortable()
                    ->weight('bold')
                    ->color('success')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('XAF')
                            ->label('Total TTC'),
                    ]),

                Tables\Columns\TextColumn::make('quantite_livree')
                    ->label('Qté livrée')
                    ->numeric(decimalPlaces: 2)
                    ->color('info')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('quantite_restante')
                    ->label('Qté restante')
                    ->numeric(decimalPlaces: 2)
                    ->color(fn($record) => $record->quantite_restante > 0 ? 'warning' : 'success')
                    ->toggleable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Ajouter une ligne')
                    ->mutateFormDataUsing(function (array $data): array {
                        // Auto-incrémenter le numéro de ligne
                        $dernierNumero = $this->getOwnerRecord()
                            ->lignes()
                            ->max('numero_ligne') ?? 0;

                        $data['numero_ligne'] = $dernierNumero + 1;

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn() => $this->getOwnerRecord()->estModifiable()),

                Tables\Actions\Action::make('livrer')
                    ->label('Livrer')
                    ->icon('heroicon-o-truck')
                    ->color('info')
                    ->visible(fn($record) => $record->quantite_restante > 0)
                    ->form([
                        Forms\Components\TextInput::make('quantite_livree')
                            ->label('Quantité livrée')
                            ->required()
                            ->numeric()
                            ->minValue(0.001)
                            ->default(fn($record) => $record->quantite_restante)
                            ->helperText(
                                fn($record) =>
                                "Quantité restante : " . $record->quantite_restante
                            ),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            $record->enregistrerLivraison($data['quantite_livree']);
                            \Filament\Notifications\Notification::make()
                                ->title('Livraison enregistrée')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Erreur')
                                ->danger()
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn() => $this->getOwnerRecord()->estModifiable()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn() => $this->getOwnerRecord()->estModifiable()),
                ]),
            ])
            ->defaultSort('numero_ligne', 'asc');
    }
}
