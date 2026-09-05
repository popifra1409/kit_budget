<?php

namespace App\Filament\Planification\Resources\ActionSousProgrammeResource\RelationManagers;

use App\Models\Activite;
use App\Models\ProjetStrategique;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ProjetsStrategiquesRelationManager extends RelationManager
{
    protected static string $relationship = 'projetsStrategiques';

    protected static ?string $title = 'Activités / Projets';

    protected static ?string $recordTitleAttribute = 'libelle';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')
                ->required()->maxLength(50),
            Forms\Components\TextInput::make('libelle')
                ->required()->maxLength(255),
            Forms\Components\Textarea::make('description')->columnSpanFull(),
            Forms\Components\DatePicker::make('date_debut_prevue'),
            Forms\Components\DatePicker::make('date_fin_prevue')->afterOrEqual('date_debut_prevue'),
            Forms\Components\Select::make('responsable_id')
                ->label('Responsable')
                ->options(User::pluck('name', 'id'))
                ->searchable(),
            Forms\Components\Select::make('activite_budgetaire_id')
                ->label('Activité budgétaire liée (optionnel)')
                ->helperText("Traduction de ce projet dans la nomenclature budgétaire du module Budget, si déjà codifiée.")
                ->options(Activite::pluck('libelle', 'id'))
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
                Tables\Columns\TextColumn::make('date_debut_prevue')->date()->label('Début prévu'),
                Tables\Columns\TextColumn::make('date_fin_prevue')->date()->label('Fin prévue'),
                Tables\Columns\BadgeColumn::make('statut')->colors([
                    'gray' => 'brouillon',
                    'warning' => 'en_transmission',
                    'info' => 'en_cours',
                    'success' => ['valide', 'realise'],
                    'danger' => 'abandonne',
                ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn() => auth()->user()->can('create_projet_strategique')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(
                        fn(ProjetStrategique $record) =>
                        auth()->user()->can('update_projet_strategique') && $record->estModifiable()
                    ),

                Tables\Actions\Action::make('transmettre')
                    ->label('Transmettre')
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(
                        fn(ProjetStrategique $record) =>
                        $record->peutEtreTransmis() && auth()->user()->can('transmettre_projet_strategique')
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
                    ->action(function (ProjetStrategique $record, array $data) {
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
                        fn(ProjetStrategique $record) =>
                        $record->estDestinataireActuel() && auth()->user()->can('valider_projet_strategique')
                    )
                    ->requiresConfirmation()
                    ->action(function (ProjetStrategique $record) {
                        $record->cloturerTransmission('Validé');
                        $record->update(['statut' => 'valide']);
                    }),

                Tables\Actions\Action::make('retourner')
                    ->label('Retourner')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(
                        fn(ProjetStrategique $record) =>
                        $record->estDestinataireActuel() && auth()->user()->can('retourner_projet_strategique')
                    )
                    ->form([Forms\Components\Textarea::make('motif')->required()])
                    ->action(
                        fn(ProjetStrategique $record, array $data) =>
                        $record->retournerPourCorrection($data['motif'])
                    ),

                Tables\Actions\Action::make('marquerRealise')
                    ->label('Marquer réalisé')
                    ->icon('heroicon-o-flag')
                    ->color('success')
                    ->visible(
                        fn(ProjetStrategique $record) =>
                        $record->statut === 'en_cours' && auth()->user()->can('update_projet_strategique')
                    )
                    ->requiresConfirmation()
                    ->action(fn(ProjetStrategique $record) => $record->update(['statut' => 'realise'])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
            ]);
    }
}
