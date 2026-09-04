<?php

namespace App\Filament\Planification\Resources\PlanStrategiqueEpResource\RelationManagers;

use App\Models\SousProgrammeEp;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SousProgrammesRelationManager extends RelationManager
{
    protected static string $relationship = 'sousProgrammes';

    protected static ?string $title = 'Sous-Programmes';

    protected static ?string $recordTitleAttribute = 'libelle';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')
                ->required()->unique(ignoreRecord: true)->maxLength(50),
            Forms\Components\TextInput::make('libelle')
                ->required()->maxLength(255),
            Forms\Components\Textarea::make('description')
                ->columnSpanFull(),
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
                Tables\Columns\TextColumn::make('numero')->label('N°')->searchable(),
                Tables\Columns\TextColumn::make('code')->searchable(),
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
                    ->visible(fn() => auth()->user()->can('create_sous_programme_ep')),
            ])
            ->actions([
                Tables\Actions\Action::make('gererActions')
                    ->label('Gérer les actions')
                    ->icon('heroicon-o-squares-2x2')
                    ->color('gray')
                    ->url(
                        fn(SousProgrammeEp $record) =>
                        \App\Filament\Planification\Resources\SousProgrammeEpResource::getUrl('edit', ['record' => $record])
                    ),

                Tables\Actions\EditAction::make()
                    ->visible(
                        fn(SousProgrammeEp $record) =>
                        auth()->user()->can('update_sous_programme_ep') && $record->estModifiable()
                    ),

                Tables\Actions\Action::make('transmettre')
                    ->label('Transmettre')
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(
                        fn(SousProgrammeEp $record) =>
                        $record->peutEtreTransmis() && auth()->user()->can('transmettre_sous_programme_ep')
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
                    ->action(function (SousProgrammeEp $record, array $data) {
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
                        fn(SousProgrammeEp $record) =>
                        $record->estDestinataireActuel() && auth()->user()->can('valider_sous_programme_ep')
                    )
                    ->requiresConfirmation()
                    ->action(function (SousProgrammeEp $record) {
                        $record->cloturerTransmission('Validé');
                        $record->update(['statut' => 'valide']);
                    }),

                Tables\Actions\Action::make('retourner')
                    ->label('Retourner')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(
                        fn(SousProgrammeEp $record) =>
                        $record->estDestinataireActuel() && auth()->user()->can('retourner_sous_programme_ep')
                    )
                    ->form([Forms\Components\Textarea::make('motif')->required()])
                    ->action(
                        fn(SousProgrammeEp $record, array $data) =>
                        $record->retournerPourCorrection($data['motif'])
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
            ]);
    }
}
