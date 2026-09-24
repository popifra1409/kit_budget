<?php

namespace App\Filament\SuiviEvaluation\Resources;

use App\Filament\SuiviEvaluation\Resources\RapportAnnuelPerformanceResource\Pages;
use App\Models\PlanStrategiqueEp;
use App\Models\RapportAnnuelPerformance;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Enums\ActionsPosition;

class RapportAnnuelPerformanceResource extends Resource
{
    protected static ?string $model = RapportAnnuelPerformance::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationGroup = 'Rapport Annuel de Performance (RAP)';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('plan_strategique_ep_id')
                ->label('PSP')
                ->options(PlanStrategiqueEp::pluck('libelle', 'id'))
                ->searchable()->required(),

            Forms\Components\Select::make('exercice_id')
                ->label('Exercice évalué')
                ->options(fn() => \App\Models\Exercice::orderByDesc('annee')->pluck('annee', 'id'))
                ->searchable()->required(),

            Forms\Components\Select::make('ppa_exercice_id')
                ->label('PPA correspondant (optionnel)')
                ->options(\App\Models\PpaExercice::pluck('numero', 'id'))
                ->searchable(),

            Forms\Components\Textarea::make('note_explicative')->columnSpanFull(),
            Forms\Components\Textarea::make('contexte_mise_oeuvre')->columnSpanFull(),
            Forms\Components\Textarea::make('difficultes_solutions')->columnSpanFull(),
            Forms\Components\Textarea::make('bilan_strategique_perspectives')->columnSpanFull(),
            Forms\Components\Textarea::make('lecons_apprises')
                ->label('Leçons apprises (input pour le prochain cycle CDMT)')
                ->columnSpanFull(),
        ])->columns(2);
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
                    'success' => 'valide',
                ]),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make()
                        ->visible(fn(RapportAnnuelPerformance $record) => $record->estModifiable()),
                    Tables\Actions\Action::make('pdf')
                        ->label('Télécharger PDF')->icon('heroicon-o-document-arrow-down')->color('danger')
                        ->url(fn(RapportAnnuelPerformance $record) => route('suivi-evaluation.rapports.rap.pdf', $record))
                        ->openUrlInNewTab(),
                    Tables\Actions\Action::make('excel')
                        ->label('Télécharger Excel')->icon('heroicon-o-table-cells')->color('success')
                        ->url(fn(RapportAnnuelPerformance $record) => route('suivi-evaluation.rapports.rap.excel', $record))
                        ->openUrlInNewTab(),

                    Tables\Actions\Action::make('transmettre')
                        ->label('Transmettre')->icon('heroicon-o-paper-airplane')
                        ->visible(fn(RapportAnnuelPerformance $record) => $record->peutEtreTransmis())
                        ->form([
                            Forms\Components\Select::make('destinataire_id')->options(User::pluck('name', 'id'))->searchable()->required(),
                            Forms\Components\Select::make('action_attendue')
                                ->options(['validation' => 'Validation', 'avis' => 'Avis', 'correction' => 'Correction'])->required(),
                            Forms\Components\Textarea::make('commentaire'),
                        ])
                        ->action(function (RapportAnnuelPerformance $record, array $data) {
                            $record->transmettreA(User::findOrFail($data['destinataire_id']), $data['action_attendue'], $data['commentaire'] ?? null);
                            $record->update(['statut' => 'en_transmission']);
                        }),

                    Tables\Actions\Action::make('valider')
                        ->label('Valider')->icon('heroicon-o-check-circle')->color('success')
                        ->visible(fn(RapportAnnuelPerformance $record) => $record->estDestinataireActuel())
                        ->requiresConfirmation()
                        ->action(function (RapportAnnuelPerformance $record) {
                            $record->cloturerTransmission('Validé');
                            $record->update(['statut' => 'valide']);
                        }),

                    Tables\Actions\Action::make('retourner')
                        ->label('Retourner')->icon('heroicon-o-arrow-uturn-left')->color('danger')
                        ->visible(fn(RapportAnnuelPerformance $record) => $record->estDestinataireActuel())
                        ->form([Forms\Components\Textarea::make('motif')->required()])
                        ->action(fn(RapportAnnuelPerformance $record, array $data) => $record->retournerPourCorrection($data['motif'])),
                ])->label('Actions')->icon('heroicon-m-ellipsis-vertical')->color('gray')->button()->size('sm'),
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRapportAnnuelPerformances::route('/'),
            'create' => Pages\CreateRapportAnnuelPerformance::route('/create'),
            'edit' => Pages\EditRapportAnnuelPerformance::route('/{record}/edit'),
            'view' => Pages\ViewRapportAnnuelPerformance::route('/{record}'),
        ];
    }
}
