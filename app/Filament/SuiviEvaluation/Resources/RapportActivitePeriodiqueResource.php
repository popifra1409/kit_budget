<?php

namespace App\Filament\SuiviEvaluation\Resources;

use App\Filament\SuiviEvaluation\Resources\RapportActivitePeriodiqueResource\Pages;
use App\Filament\SuiviEvaluation\Resources\RapportActivitePeriodiqueResource\RelationManagers\LignesRelationManager;
use App\Models\Activite;
use App\Models\RapportActivitePeriodique;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Enums\ActionsPosition;

class RapportActivitePeriodiqueResource extends Resource
{
    protected static ?string $model = RapportActivitePeriodique::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = "Rapports d'Activité";

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('activite_id')
                ->label('Activité')
                ->options(Activite::pluck('libelle', 'id'))
                ->searchable()->required(),

            Forms\Components\TextInput::make('periode')
                ->helperText("Ex: 2026-01 (mensuel) ou 2026-T1 (trimestriel)")
                ->required(),

            Forms\Components\Select::make('type_periode')
                ->options(['mensuel' => 'Mensuel', 'trimestriel' => 'Trimestriel'])
                ->default('mensuel')->required(),

            Forms\Components\TextInput::make('poids_activite')
                ->label("Poids de l'activité par rapport aux objectifs de l'action (%)")
                ->numeric()->suffix('%'),

            Forms\Components\Textarea::make('problemes_rencontres')->columnSpanFull(),
            Forms\Components\Textarea::make('solutions_proposees')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')->label('N°'),
                Tables\Columns\TextColumn::make('activite.libelle')->label('Activité')->limit(30),
                Tables\Columns\TextColumn::make('periode'),
                Tables\Columns\BadgeColumn::make('statut')->colors([
                    'gray' => 'brouillon',
                    'warning' => 'en_transmission',
                    'success' => 'valide',
                ]),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make()
                        ->visible(fn(RapportActivitePeriodique $record) => $record->estModifiable()),
                    Tables\Actions\Action::make('pdf')
                        ->label('Télécharger PDF')->icon('heroicon-o-document-arrow-down')->color('danger')
                        ->url(fn(RapportActivitePeriodique $record) => route('suivi-evaluation.rapports.activite.pdf', $record))
                        ->openUrlInNewTab(),
                    Tables\Actions\Action::make('excel')
                        ->label('Télécharger Excel')->icon('heroicon-o-table-cells')->color('success')
                        ->url(fn(RapportActivitePeriodique $record) => route('suivi-evaluation.rapports.activite.excel', $record))
                        ->openUrlInNewTab(),

                    Tables\Actions\Action::make('genererLignes')
                        ->label('Générer les lignes depuis les tâches')
                        ->icon('heroicon-o-arrow-path')
                        ->visible(fn(RapportActivitePeriodique $record) => $record->lignesTaches()->count() === 0)
                        ->requiresConfirmation()
                        ->action(fn(RapportActivitePeriodique $record) => $record->genererLignesDepuisTaches()),

                    Tables\Actions\Action::make('transmettre')
                        ->label('Transmettre')
                        ->icon('heroicon-o-paper-airplane')
                        ->visible(fn(RapportActivitePeriodique $record) => $record->peutEtreTransmis())
                        ->form([
                            Forms\Components\Select::make('destinataire_id')
                                ->options(User::pluck('name', 'id'))->searchable()->required(),
                            Forms\Components\Select::make('action_attendue')
                                ->options(['validation' => 'Validation', 'avis' => 'Avis', 'correction' => 'Correction'])->required(),
                            Forms\Components\Textarea::make('commentaire'),
                        ])
                        ->action(function (RapportActivitePeriodique $record, array $data) {
                            $record->transmettreA(User::findOrFail($data['destinataire_id']), $data['action_attendue'], $data['commentaire'] ?? null);
                            $record->update(['statut' => 'en_transmission']);
                        }),

                    Tables\Actions\Action::make('valider')
                        ->label('Valider')->icon('heroicon-o-check-circle')->color('success')
                        ->visible(fn(RapportActivitePeriodique $record) => $record->estDestinataireActuel())
                        ->requiresConfirmation()
                        ->action(function (RapportActivitePeriodique $record) {
                            $record->cloturerTransmission('Validé');
                            $record->update(['statut' => 'valide']);
                        }),

                    Tables\Actions\Action::make('retourner')
                        ->label('Retourner')->icon('heroicon-o-arrow-uturn-left')->color('danger')
                        ->visible(fn(RapportActivitePeriodique $record) => $record->estDestinataireActuel())
                        ->form([Forms\Components\Textarea::make('motif')->required()])
                        ->action(fn(RapportActivitePeriodique $record, array $data) => $record->retournerPourCorrection($data['motif'])),
                ])->label('Actions')->icon('heroicon-m-ellipsis-vertical')->color('gray')->button()->size('sm'),
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array
    {
        return [LignesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRapportActivitePeriodiques::route('/'),
            'create' => Pages\CreateRapportActivitePeriodique::route('/create'),
            'edit' => Pages\EditRapportActivitePeriodique::route('/{record}/edit'),
        ];
    }
}
