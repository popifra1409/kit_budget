<?php

namespace App\Filament\Programmation\Resources;

use App\Filament\Programmation\Resources\CdmtExerciceResource\Pages;
use App\Filament\Programmation\Resources\CdmtExerciceResource\RelationManagers\LignesRelationManager;
use App\Models\CbmtExercice;
use App\Models\CdmtExercice;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Enums\ActionsPosition;

class CdmtExerciceResource extends Resource
{
    protected static ?string $model = CdmtExercice::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Cadrage Pluriannuel (CBMT/CDMT)';

    protected static ?string $navigationLabel = 'CDMT';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('cbmt_exercice_id')
                ->label('CBMT de rattachement')
                ->options(fn() => CbmtExercice::with('exerciceReference')->get()
                    ->mapWithKeys(fn($c) => [$c->id => "{$c->numero} ({$c->exerciceReference?->annee})"]))
                ->searchable()->required(),

            Forms\Components\Select::make('version')
                ->options(['initial' => 'CDMT initial', 'final' => 'CDMT final'])
                ->default('initial')->required(),

            Forms\Components\DatePicker::make('date_cdmt_initial')
                ->helperText('Limite : 31 mars de chaque année'),
            Forms\Components\DatePicker::make('date_cdmt_final')
                ->helperText('Limite : 20 novembre de chaque année'),

            Forms\Components\Textarea::make('note_arbitrages')
                ->label('Notes sur les arbitrages (si version finale)')
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')->label('N°'),
                Tables\Columns\TextColumn::make('cbmtExercice.numero')->label('CBMT'),
                Tables\Columns\BadgeColumn::make('version')->colors(['gray' => 'initial', 'success' => 'final']),
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
                        ->visible(fn(CdmtExercice $record) => $record->estModifiable()),

                    Tables\Actions\Action::make('transmettre')
                        ->label('Transmettre')
                        ->icon('heroicon-o-paper-airplane')
                        ->visible(fn(CdmtExercice $record) => $record->peutEtreTransmis())
                        ->form([
                            Forms\Components\Select::make('destinataire_id')
                                ->label('Destinataire')->options(User::pluck('name', 'id'))
                                ->searchable()->required(),
                            Forms\Components\Select::make('action_attendue')
                                ->options(['validation' => 'Validation', 'avis' => 'Avis', 'correction' => 'Correction'])
                                ->required(),
                            Forms\Components\Textarea::make('commentaire'),
                        ])
                        ->action(function (CdmtExercice $record, array $data) {
                            $record->transmettreA(
                                User::findOrFail($data['destinataire_id']),
                                $data['action_attendue'],
                                $data['commentaire'] ?? null,
                            );
                            $record->update(['statut' => 'en_transmission']);
                        }),

                    Tables\Actions\Action::make('valider')
                        ->label('Valider')->icon('heroicon-o-check-circle')->color('success')
                        ->visible(fn(CdmtExercice $record) => $record->estDestinataireActuel())
                        ->requiresConfirmation()
                        ->action(function (CdmtExercice $record) {
                            $record->cloturerTransmission('Validé');
                            $record->update(['statut' => 'valide']);
                        }),

                    Tables\Actions\Action::make('retourner')
                        ->label('Retourner')->icon('heroicon-o-arrow-uturn-left')->color('danger')
                        ->visible(fn(CdmtExercice $record) => $record->estDestinataireActuel())
                        ->form([Forms\Components\Textarea::make('motif')->required()])
                        ->action(fn(CdmtExercice $record, array $data) => $record->retournerPourCorrection($data['motif'])),
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
            'index' => Pages\ListCdmtExercices::route('/'),
            'create' => Pages\CreateCdmtExercice::route('/create'),
            'edit' => Pages\EditCdmtExercice::route('/{record}/edit'),
        ];
    }
}
