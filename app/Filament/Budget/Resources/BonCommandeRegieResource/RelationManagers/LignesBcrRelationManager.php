<?php

namespace App\Filament\Budget\Resources\BonCommandeRegieResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Tables;
use Filament\Resources\RelationManagers\RelationManager;

class LignesBcrRelationManager extends RelationManager
{
    protected static string $relationship = 'lignes';
    protected static ?string $title       = 'Lignes du bon de commande';

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Grid::make(6)->schema([

                Forms\Components\TextInput::make('designation')
                    ->label('Désignation')->required()->columnSpan(2),

                Forms\Components\TextInput::make('quantite')
                    ->label('Qté')->numeric()->default(1)->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(
                        fn(Get $get, Set $set) =>
                        static::calculer($get, $set)
                    )->columnSpan(1),

                Forms\Components\Select::make('unite')
                    ->label('Unité')
                    ->options([
                        'pièce'   => 'Pièce',
                        'lot'     => 'Lot',
                        'kg'      => 'Kg',
                        'litre'   => 'L',
                        'mètre'   => 'M',
                        'heure'   => 'H',
                        'jour'    => 'J',
                        'forfait' => 'Forfait',
                    ])
                    ->default('pièce')->required()->columnSpan(1),

                Forms\Components\TextInput::make('prix_unitaire_ht')
                    ->label('P.U HT')->numeric()->required()->prefix('FCFA')
                    ->live(onBlur: true)
                    ->afterStateUpdated(
                        fn(Get $get, Set $set) =>
                        static::calculer($get, $set)
                    )->columnSpan(2),
            ]),

            Forms\Components\Grid::make(4)->schema([
                Forms\Components\TextInput::make('taux_tva')
                    ->label('TVA %')->numeric()->default(19.25)->suffix('%')
                    ->live(onBlur: true)
                    ->afterStateUpdated(
                        fn(Get $get, Set $set) =>
                        static::calculer($get, $set)
                    ),

                Forms\Components\TextInput::make('taux_ir')
                    ->label('IR %')->numeric()->default(0)->suffix('%')
                    ->live(onBlur: true)
                    ->afterStateUpdated(
                        fn(Get $get, Set $set) =>
                        static::calculer($get, $set)
                    ),

                Forms\Components\Placeholder::make('montant_ttc')
                    ->label('TTC')
                    ->content(
                        fn(Get $get) =>
                        number_format((float) ($get('montant_ttc') ?? 0), 0, ',', ' ') . ' FCFA'
                    ),

                Forms\Components\Placeholder::make('net_a_payer')
                    ->label('Net')
                    ->content(
                        fn(Get $get) =>
                        number_format((float) ($get('net_a_payer') ?? 0), 0, ',', ' ') . ' FCFA'
                    ),
            ]),

            // Champs cachés
            Forms\Components\Hidden::make('montant_ht')->default(0),
            Forms\Components\Hidden::make('montant_tva')->default(0),
            Forms\Components\Hidden::make('montant_ttc')->default(0),
            Forms\Components\Hidden::make('montant_ir')->default(0),
            Forms\Components\Hidden::make('net_a_payer')->default(0),

            Forms\Components\Textarea::make('observations')
                ->label('Observations')->rows(2)->columnSpanFull(),
        ]);
    }

    protected static function calculer(Get $get, Set $set): void
    {
        $qte    = (float) ($get('quantite')        ?? 0);
        $pu     = (float) ($get('prix_unitaire_ht') ?? 0);
        $tauxTv = (float) ($get('taux_tva')         ?? 19.25);
        $tauxIr = (float) ($get('taux_ir')          ?? 0);

        $ht  = round($qte * $pu, 2);
        $tva = round($ht * ($tauxTv / 100), 2);
        $ttc = round($ht + $tva, 2);
        $ir  = round($ht * ($tauxIr / 100), 2);
        $net = round($ht - $ir, 2);

        $set('montant_ht',  $ht);
        $set('montant_tva', $tva);
        $set('montant_ttc', $ttc);
        $set('montant_ir',  $ir);
        $set('net_a_payer', $net);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero_ligne')
                    ->label('#')->width('50px'),
                Tables\Columns\TextColumn::make('designation')
                    ->label('Désignation')->limit(40)->wrap(),
                Tables\Columns\TextColumn::make('quantite')
                    ->label('Qté')->alignCenter(),
                Tables\Columns\TextColumn::make('unite')
                    ->label('Unité')->alignCenter(),
                Tables\Columns\TextColumn::make('prix_unitaire_ht')
                    ->label('P.U HT')->money('XAF'),
                Tables\Columns\TextColumn::make('montant_ht')
                    ->label('MHT')->money('XAF'),
                Tables\Columns\TextColumn::make('taux_tva')
                    ->label('TVA%')->suffix('%'),
                Tables\Columns\TextColumn::make('montant_ttc')
                    ->label('TTC')->money('XAF')->weight('bold')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()->money('XAF')->label('Total TTC'),
                    ]),
                Tables\Columns\TextColumn::make('montant_ir')
                    ->label('IR')->money('XAF')->color('warning')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()->money('XAF')->label('Total IR'),
                    ]),
                Tables\Columns\TextColumn::make('net_a_payer')
                    ->label('Net')->money('XAF')->color('success')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()->money('XAF')->label('Total Net'),
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Ajouter une ligne')
                    ->visible(fn() => $this->getOwnerRecord()->statut === 'brouillon')
                    ->mutateFormDataUsing(function (array $data): array {
                        $bc = $this->getOwnerRecord();
                        $data['numero_ligne'] = $bc->lignes()->count() + 1;
                        return $data;
                    })
                    ->after(fn() => $this->getOwnerRecord()->recalculerTotaux()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn() => $this->getOwnerRecord()->statut === 'brouillon')
                    ->after(fn() => $this->getOwnerRecord()->recalculerTotaux()),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn() => $this->getOwnerRecord()->statut === 'brouillon')
                    ->after(fn() => $this->getOwnerRecord()->recalculerTotaux()),
            ])
            ->defaultSort('numero_ligne');
    }
}
