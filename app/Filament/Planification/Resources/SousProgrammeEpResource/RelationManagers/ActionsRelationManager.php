<?php

namespace App\Filament\Planification\Resources\SousProgrammeEpResource\RelationManagers;

use App\Models\ActionSousProgramme;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ActionsRelationManager extends RelationManager
{
    protected static string $relationship = 'actions';

    protected static ?string $title = 'Actions';

    protected static ?string $recordTitleAttribute = 'libelle';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')
                ->label('Code (ex: 01, 02)')
                ->required()->unique(ignoreRecord: true)->maxLength(10),
            Forms\Components\TextInput::make('libelle')
                ->required()->maxLength(255),
            Forms\Components\Textarea::make('description')->columnSpanFull(),
            Forms\Components\Select::make('responsable_id')
                ->label('Responsable')
                ->options(User::pluck('name', 'id'))
                ->searchable(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')->label('N°'),
                Tables\Columns\TextColumn::make('code'),
                Tables\Columns\TextColumn::make('libelle')->searchable(),
                Tables\Columns\TextColumn::make('responsable.name')->label('Responsable'),
                Tables\Columns\BadgeColumn::make('statut')->colors([
                    'gray' => 'brouillon',
                    'warning' => 'en_transmission',
                    'success' => ['valide', 'en_vigueur'],
                    'danger' => 'cloture',
                ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn() => auth()->user()->can('create_action_sous_programme')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(
                        fn(ActionSousProgramme $record) =>
                        auth()->user()->can('update_action_sous_programme') && $record->estModifiable()
                    ),

                Tables\Actions\Action::make('transmettre')
                    ->label('Transmettre')
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(
                        fn(ActionSousProgramme $record) =>
                        $record->peutEtreTransmis() && auth()->user()->can('transmettre_action_sous_programme')
                    )
                    ->form([
                        Forms\Components\Select::make('destinataire_id')
                            ->label('Destinataire')
                            ->options(User::pluck('name', 'id'))
                            ->searchable()->required(),
                        Forms\Components\Select::make('action_attendue')
                            ->options([
                                'validation' => 'Validation',
                                'avis' => 'Avis',
                                'correction' => 'Correction',
                            ])->required(),
                        Forms\Components\Textarea::make('commentaire'),
                    ])
                    ->action(function (ActionSousProgramme $record, array $data) {
                        $record->transmettreA(
                            User::findOrFail($data['destinataire_id']),
                            $data['action_attendue'],
                            $data['commentaire'] ?? null,
                        );
                        $record->update(['statut' => 'en_transmission']);
                    }),

                Tables\Actions\Action::make('valider')
                    ->label('Valider')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(
                        fn(ActionSousProgramme $record) =>
                        $record->estDestinataireActuel() && auth()->user()->can('valider_action_sous_programme')
                    )
                    ->requiresConfirmation()
                    ->action(function (ActionSousProgramme $record) {
                        $record->cloturerTransmission('Validé');
                        $record->update(['statut' => 'valide']);
                    }),

                Tables\Actions\Action::make('retourner')
                    ->label('Retourner')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(
                        fn(ActionSousProgramme $record) =>
                        $record->estDestinataireActuel() && auth()->user()->can('retourner_action_sous_programme')
                    )
                    ->form([Forms\Components\Textarea::make('motif')->required()])
                    ->action(
                        fn(ActionSousProgramme $record, array $data) =>
                        $record->retournerPourCorrection($data['motif'])
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
            ]);
    }
}
