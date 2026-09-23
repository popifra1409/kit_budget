<?php

namespace App\Filament\Planification\Resources\ActiviteResource\RelationManagers;

use App\Models\Extrant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Enums\ActionsPosition;

class ExtrantsRelationManager extends RelationManager
{
    protected static string $relationship = 'extrants';

    protected static ?string $title = 'Extrants (produits directs)';

    protected static ?string $recordTitleAttribute = 'libelle';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('libelle')
                ->label('Extrant produit')
                ->helperText("Ex: \"Équipements installés et fonctionnels\" (produit direct de l'activité, pas l'impact à long terme).")
                ->required()->maxLength(255)->columnSpanFull(),

            Forms\Components\Textarea::make('description')->columnSpanFull(),

            Forms\Components\Fieldset::make('Quantité / qualité')
                ->schema([
                    Forms\Components\TextInput::make('quantite_prevue')->numeric(),
                    Forms\Components\TextInput::make('unite_mesure')
                        ->placeholder('unités, lits, %, dossiers...'),
                    Forms\Components\TextInput::make('quantite_realisee')->numeric(),
                ])->columns(3),

            Forms\Components\Select::make('statut')
                ->options([
                    'prevu' => 'Prévu',
                    'en_cours' => 'En cours',
                    'realise' => 'Réalisé',
                    'partiel' => 'Partiellement réalisé',
                    'non_realise' => 'Non réalisé',
                ])
                ->default('prevu')->required(),

            Forms\Components\DatePicker::make('date_realisation_prevue'),
            Forms\Components\DatePicker::make('date_realisation_effective'),

            Forms\Components\Textarea::make('commentaire')->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('libelle')->wrap()->searchable(),
                Tables\Columns\TextColumn::make('quantite_prevue')->label('Prévu'),
                Tables\Columns\TextColumn::make('quantite_realisee')->label('Réalisé'),
                Tables\Columns\TextColumn::make('unite_mesure')->label('Unité'),
                Tables\Columns\TextColumn::make('taux')
                    ->label('Taux')
                    ->getStateUsing(fn(Extrant $record) => $record->getTauxRealisation() !== null
                        ? $record->getTauxRealisation() . '%' : '—'),
                Tables\Columns\BadgeColumn::make('statut')->colors([
                    'gray' => 'prevu',
                    'info' => 'en_cours',
                    'success' => 'realise',
                    'warning' => 'partiel',
                    'danger' => 'non_realise',
                ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn() => auth()->user()->can('create_extrant')),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),

                    Tables\Actions\Action::make('marquerRealise')
                        ->label('Marquer réalisé')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn(Extrant $record) => $record->statut !== 'realise')
                        ->form([
                            Forms\Components\TextInput::make('quantite_realisee')
                                ->numeric()
                                ->default(fn(Extrant $record) => $record->quantite_prevue),
                        ])
                        ->action(
                            fn(Extrant $record, array $data) =>
                            $record->marquerRealise($data['quantite_realisee'] ?? null)
                        ),

                    Tables\Actions\DeleteAction::make(),
                ])->label('Actions')->icon('heroicon-m-ellipsis-vertical')->color('gray')->button()->size('sm'),
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
            ]);
    }
}
