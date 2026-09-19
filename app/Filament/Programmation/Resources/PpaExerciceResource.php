<?php

namespace App\Filament\Programmation\Resources;

use App\Filament\Programmation\Resources\PpaExerciceResource\Pages;
use App\Models\PlanStrategiqueEp;
use App\Models\PpaExercice;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Enums\ActionsPosition;

class PpaExerciceResource extends Resource
{
    protected static ?string $model = PpaExercice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationGroup = 'Programme de Performance Annuel';

    protected static ?string $navigationLabel = 'PPA';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Rattachement')
                ->schema([
                    Forms\Components\Select::make('plan_strategique_ep_id')
                        ->label('Plan Stratégique de Performance (PSP)')
                        ->options(PlanStrategiqueEp::pluck('libelle', 'id'))
                        ->searchable()->required(),
                    Forms\Components\Select::make('exercice_id')
                        ->label('Exercice budgétaire')
                        ->relationship('exercice', 'annee')
                        ->options(fn() => \App\Models\Exercice::orderByDesc('annee')->pluck('annee', 'id'))
                        ->default(fn() => \App\Models\Exercice::getActif()?->id)
                        ->searchable()
                        ->required(),
                ])->columns(2),

            Forms\Components\Section::make('Synthèse stratégique')
                ->schema([
                    Forms\Components\Textarea::make('contexte_introduction')
                        ->label('Contexte d\'élaboration (introduction)')
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('performances_anterieures')
                        ->label('Performances antérieures')
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('bilan_technique')
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('bilan_financier')
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
                Tables\Columns\TextColumn::make('exercice.annee')->label('Exercice'),
                Tables\Columns\BadgeColumn::make('statut')->colors([
                    'gray' => 'brouillon',
                    'warning' => 'en_transmission',
                    'success' => ['valide', 'publie'],
                ]),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make()
                        ->visible(fn(PpaExercice $record) => $record->estModifiable()),

                    Tables\Actions\Action::make('transmettre')
                        ->label('Transmettre')
                        ->icon('heroicon-o-paper-airplane')
                        ->visible(
                            fn(PpaExercice $record) =>
                            $record->peutEtreTransmis() && auth()->user()->can('transmettre_ppa_exercice')
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
                        ->action(function (PpaExercice $record, array $data) {
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
                            fn(PpaExercice $record) =>
                            $record->estDestinataireActuel() && auth()->user()->can('valider_ppa_exercice')
                        )
                        ->requiresConfirmation()
                        ->action(function (PpaExercice $record) {
                            $record->cloturerTransmission('Validé');
                            $record->update(['statut' => 'valide']);
                        }),

                    Tables\Actions\Action::make('retourner')
                        ->label('Retourner')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('danger')
                        ->visible(
                            fn(PpaExercice $record) =>
                            $record->estDestinataireActuel() && auth()->user()->can('retourner_ppa_exercice')
                        )
                        ->form([Forms\Components\Textarea::make('motif')->required()])
                        ->action(
                            fn(PpaExercice $record, array $data) =>
                            $record->retournerPourCorrection($data['motif'])
                        ),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')
                    ->button()->size('sm'),
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPpaExercices::route('/'),
            'create' => Pages\CreatePpaExercice::route('/create'),
            'edit' => Pages\EditPpaExercice::route('/{record}/edit'),
            'view' => Pages\ViewPpaExercice::route('/{record}'),
        ];
    }
}
