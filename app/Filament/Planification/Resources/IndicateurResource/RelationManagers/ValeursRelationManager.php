<?php

namespace App\Filament\Planification\Resources\IndicateurResource\RelationManagers;

use App\Models\ValeurIndicateur;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ValeursRelationManager extends RelationManager
{
    protected static string $relationship = 'valeurs';

    protected static ?string $title = 'Valeurs saisies';

    protected static ?string $recordTitleAttribute = 'periode';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('periode')
                ->required()->maxLength(20)
                ->helperText("Ex: 2026, 2026-T1, 2026-S1"),
            Forms\Components\TextInput::make('valeur_realisee')
                ->numeric()->required(),
            Forms\Components\Textarea::make('commentaire')->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('periode'),
                Tables\Columns\TextColumn::make('valeur_realisee'),
                Tables\Columns\TextColumn::make('saisiPar.name')->label('Saisi par'),
                Tables\Columns\TextColumn::make('validePar.name')->label('Validé par'),
                Tables\Columns\BadgeColumn::make('statut')->colors([
                    'gray' => 'saisi',
                    'success' => 'valide',
                    'danger' => 'rejete',
                ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn() => auth()->user()->can('saisir_valeur_indicateur')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(
                        fn(ValeurIndicateur $record) =>
                        $record->statut === 'saisi' && auth()->user()->can('saisir_valeur_indicateur')
                    ),

                Tables\Actions\Action::make('valider')
                    ->label('Valider')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(
                        fn(ValeurIndicateur $record) =>
                        $record->statut === 'saisi' && auth()->user()->can('valider_valeur_indicateur')
                    )
                    ->requiresConfirmation()
                    ->action(fn(ValeurIndicateur $record) => $record->valider()),

                Tables\Actions\Action::make('rejeter')
                    ->label('Rejeter')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(
                        fn(ValeurIndicateur $record) =>
                        $record->statut === 'saisi' && auth()->user()->can('valider_valeur_indicateur')
                    )
                    ->form([Forms\Components\Textarea::make('motif')->required()])
                    ->action(fn(ValeurIndicateur $record, array $data) => $record->rejeter($data['motif'])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
            ]);
    }
}
