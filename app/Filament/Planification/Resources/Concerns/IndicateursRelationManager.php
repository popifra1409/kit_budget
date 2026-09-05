<?php

namespace App\Filament\Planification\Resources\Concerns;

use App\Models\Indicateur;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class IndicateursRelationManager extends RelationManager
{
    protected static string $relationship = 'indicateurs';

    protected static ?string $title = 'Indicateurs';

    protected static ?string $recordTitleAttribute = 'libelle';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')
                ->required()->unique(ignoreRecord: true)->maxLength(50),
            Forms\Components\TextInput::make('libelle')
                ->required()->maxLength(255)->columnSpanFull(),
            Forms\Components\TextInput::make('objectif_associe')
                ->label('Ce que l\'indicateur mesure')
                ->maxLength(255)->columnSpanFull(),
            Forms\Components\TextInput::make('unite_mesure')
                ->label('Unité de mesure'),
            Forms\Components\Select::make('periodicite_mesure')
                ->options([
                    'annuelle' => 'Annuelle',
                    'semestrielle' => 'Semestrielle',
                    'trimestrielle' => 'Trimestrielle',
                ])
                ->default('annuelle')->required(),
            Forms\Components\TextInput::make('mode_calcul')
                ->columnSpanFull(),

            Forms\Components\Fieldset::make('Référence et cible')
                ->schema([
                    Forms\Components\TextInput::make('valeur_reference')->numeric(),
                    Forms\Components\TextInput::make('annee_reference')->numeric()
                        ->label('Année de référence'),
                    Forms\Components\TextInput::make('valeur_cible')->numeric(),
                    Forms\Components\TextInput::make('annee_cible')->numeric()
                        ->label('Année cible'),
                ])->columns(4),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code'),
                Tables\Columns\TextColumn::make('libelle')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('unite_mesure')->label('Unité'),
                Tables\Columns\TextColumn::make('valeur_reference')->label('Référence')
                    ->formatStateUsing(fn($record) => $record->valeur_reference !== null
                        ? "{$record->valeur_reference} ({$record->annee_reference})" : '—'),
                Tables\Columns\TextColumn::make('valeur_cible')->label('Cible')
                    ->formatStateUsing(fn($record) => $record->valeur_cible !== null
                        ? "{$record->valeur_cible} ({$record->annee_cible})" : '—'),
                Tables\Columns\BadgeColumn::make('statut')->colors([
                    'gray' => 'brouillon',
                    'success' => 'valide',
                ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn() => auth()->user()->can('create_indicateur')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn() => auth()->user()->can('update_indicateur')),

                Tables\Actions\Action::make('gererValeurs')
                    ->label('Gérer les valeurs')
                    ->icon('heroicon-o-chart-bar')
                    ->color('gray')
                    ->url(
                        fn(Indicateur $record) =>
                        \App\Filament\Planification\Resources\IndicateurResource::getUrl('edit', ['record' => $record])
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
            ]);
    }
}
