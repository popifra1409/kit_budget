<?php

namespace App\Filament\Budget\Resources\RegieAvanceResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Resources\RelationManagers\RelationManager;
use App\Models\LigneRegieAvance;
use App\Models\NomenclatureBudgetaire;
use App\Models\LigneBudgetaire;

class LignesRegieRelationManager extends RelationManager
{
    protected static string $relationship = 'lignes';
    protected static ?string $title       = 'Lignes budgétaires (Mini-Budget)';

    public function form(Forms\Form $form): Forms\Form
    {
        $regie = $this->getOwnerRecord();

        return $form->schema([

            Forms\Components\Select::make('nomenclature_id')
                ->label('Nomenclature budgétaire')
                ->searchable()
                ->getSearchResultsUsing(function (string $search) use ($regie) {
                    return NomenclatureBudgetaire::where('type', 'depense')
                        ->where('actif', true)
                        ->where(function ($q) use ($search) {
                            $q->where('libelle', 'like', "%{$search}%")
                                ->orWhere('code',   'like', "%{$search}%");
                        })
                        ->orderBy('code')->limit(50)->get()
                        ->mapWithKeys(fn($n) => [$n->id => "{$n->code} — {$n->libelle}"]);
                })
                ->getOptionLabelUsing(function ($value): ?string {
                    $n = NomenclatureBudgetaire::find($value);
                    return $n ? "{$n->code} — {$n->libelle}" : null;
                })
                ->live()
                ->afterStateUpdated(function ($state, Forms\Set $set) use ($regie) {
                    if (!$state) return;
                    $lb = LigneBudgetaire::where('budget_id',       $regie->budget_id)
                        ->where('nomenclature_id', $state)->first();
                    if ($lb) {
                        $set('ligne_budgetaire_id', $lb->id);
                    }
                })
                ->required()
                ->columnSpanFull(),

            Forms\Components\Hidden::make('ligne_budgetaire_id'),

            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('montant_alloue')
                    ->label('Montant alloué (FCFA)')
                    ->numeric()->required()->prefix('FCFA')
                    ->helperText(function ($get) use ($regie) {
                        $nomId = $get('nomenclature_id');
                        if (!$nomId) return null;
                        $lb = LigneBudgetaire::where('budget_id', $regie->budget_id)
                            ->where('nomenclature_id', $nomId)->first();
                        if (!$lb) return 'Ligne budgétaire non trouvée';
                        return 'Disponible sur ligne : '
                            . number_format($lb->disponible_engagement, 0, ',', ' ') . ' FCFA';
                    }),

                Forms\Components\Placeholder::make('montant_disponible_affiche')
                    ->label('Disponible après allocation')
                    ->content(function ($get) use ($regie) {
                        $nomId  = $get('nomenclature_id');
                        $alloue = (float) ($get('montant_alloue') ?? 0);
                        if (!$nomId || $alloue <= 0) return '—';
                        $lb = LigneBudgetaire::where('budget_id', $regie->budget_id)
                            ->where('nomenclature_id', $nomId)->first();
                        if (!$lb) return '—';
                        $restant = $lb->disponible_engagement - $alloue;
                        return number_format($restant, 0, ',', ' ') . ' FCFA'
                            . ($restant < 0 ? ' ⚠️ Insuffisant' : ' ✅');
                    }),
            ]),
        ]);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nomenclature.code')
                    ->label('Code')->weight('bold')->sortable(),
                Tables\Columns\TextColumn::make('nomenclature.libelle')
                    ->label('Nomenclature')->limit(40)->wrap(),
                Tables\Columns\TextColumn::make('montant_alloue')
                    ->label('Alloué')->money('XAF')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()->money('XAF')->label('Total'),
                    ]),
                Tables\Columns\TextColumn::make('montant_consomme')
                    ->label('Consommé')->money('XAF')->color('danger')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()->money('XAF')->label('Total'),
                    ]),
                Tables\Columns\TextColumn::make('montant_disponible')
                    ->label('Disponible')->money('XAF')->weight('bold')
                    ->color(
                        fn($record) =>
                        $record->montant_disponible < 0 ? 'danger' : 'success'
                    )
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()->money('XAF')->label('Total'),
                    ]),
                Tables\Columns\TextColumn::make('taux_consommation')
                    ->label('Taux')
                    ->formatStateUsing(
                        fn($record) =>
                        number_format($record->taux_consommation, 1) . '%'
                    )
                    ->badge()
                    ->color(fn($record) => match (true) {
                        $record->taux_consommation >= 90 => 'danger',
                        $record->taux_consommation >= 70 => 'warning',
                        default                          => 'success',
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Ajouter une ligne')
                    ->visible(fn() => $this->getOwnerRecord()->statut === 'actif'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn() => $this->getOwnerRecord()->statut === 'actif'),
                Tables\Actions\DeleteAction::make()
                    ->visible(
                        fn($record) =>
                        $this->getOwnerRecord()->statut === 'actif'
                            && $record->montant_consomme == 0
                    ),
            ]);
    }
}
