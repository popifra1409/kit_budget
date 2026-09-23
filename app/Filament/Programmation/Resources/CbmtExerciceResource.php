<?php

namespace App\Filament\Programmation\Resources;

use App\Filament\Programmation\Resources\CbmtExerciceResource\Pages;
use App\Filament\Programmation\Resources\CbmtExerciceResource\RelationManagers\LignesRelationManager;
use App\Models\CbmtExercice;
use App\Models\PlanStrategiqueEp;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Enums\ActionsPosition;

class CbmtExerciceResource extends Resource
{
    protected static ?string $model = CbmtExercice::class;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationGroup = 'Cadrage Pluriannuel (CBMT/CDMT)';

    protected static ?string $navigationLabel = 'CBMT';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Rattachement')
                ->schema([
                    Forms\Components\Select::make('plan_strategique_ep_id')
                        ->label('PSP')
                        ->options(PlanStrategiqueEp::pluck('libelle', 'id'))
                        ->searchable()->required(),
                    Forms\Components\Select::make('exercice_reference_id')
                        ->label('Exercice de référence (N)')
                        ->options(fn() => \App\Models\Exercice::orderByDesc('annee')->pluck('annee', 'id'))
                        ->default(fn() => \App\Models\Exercice::getActif()?->id)
                        ->searchable()
                        ->required(),
                    Forms\Components\DatePicker::make('date_lettre_cadrage')
                        ->label('Date lettre de cadrage')
                        ->helperText('Limite réglementaire : 15 juin N (Instruction du 22 janvier 2026)'),
                ])->columns(3),

            Forms\Components\Section::make('Hypothèses et soutenabilité')
                ->schema([
                    Forms\Components\Textarea::make('hypotheses_ressources')
                        ->label('Hypothèses de projection des ressources')
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('commentaire_soutenabilite')
                        ->label('Commentaire sur la soutenabilité du cadrage')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')->label('N°'),
                Tables\Columns\TextColumn::make('planStrategiqueEp.libelle')->label('PSP')->limit(30),
                Tables\Columns\TextColumn::make('exerciceReference.annee')->label('Exercice N'),
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
                        ->visible(fn(CbmtExercice $record) => $record->estModifiable()),

                    Tables\Actions\Action::make('transmettre')
                        ->label('Transmettre')
                        ->icon('heroicon-o-paper-airplane')
                        ->visible(fn(CbmtExercice $record) => $record->peutEtreTransmis())
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
                        ->action(function (CbmtExercice $record, array $data) {
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
                        ->visible(fn(CbmtExercice $record) => $record->estDestinataireActuel())
                        ->requiresConfirmation()
                        ->action(function (CbmtExercice $record) {
                            $record->cloturerTransmission('Validé');
                            $record->update(['statut' => 'valide']);
                        }),

                    Tables\Actions\Action::make('retourner')
                        ->label('Retourner')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('danger')
                        ->visible(fn(CbmtExercice $record) => $record->estDestinataireActuel())
                        ->form([Forms\Components\Textarea::make('motif')->required()])
                        ->action(
                            fn(CbmtExercice $record, array $data) =>
                            $record->retournerPourCorrection($data['motif'])
                        ),
                ])
                    ->label('Actions')->icon('heroicon-m-ellipsis-vertical')->color('gray')->button()->size('sm'),
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
            ]);
    }

    public static function getRelations(): array
    {
        return [LignesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCbmtExercices::route('/'),
            'create' => Pages\CreateCbmtExercice::route('/create'),
            'edit' => Pages\EditCbmtExercice::route('/{record}/edit'),
        ];
    }
}
