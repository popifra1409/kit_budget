<?php
// app/Filament/Planification/Resources/PlanStrategiqueEpResource.php

namespace App\Filament\Planification\Resources;

use App\Filament\Planification\Resources\PlanStrategiqueEpResource\Pages;
use App\Models\PlanStrategiqueEp;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PlanStrategiqueEpResource extends Resource
{
    protected static ?string $model = PlanStrategiqueEp::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationGroup = 'Plans Stratégiques EP';

    protected static ?string $navigationLabel = 'Plans Stratégiques';


    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_plan_strategique_ep') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_plan_strategique_ep') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('update_plan_strategique_ep')
            && $record->estModifiable();
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('delete_plan_strategique_ep') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Rattachement')
                ->schema([
                    Forms\Components\Select::make('csp_ministere_id')
                        ->label('Cadre Stratégique Pluriannuel (CSP)')
                        ->relationship('cspMinistere', 'libelle')
                        ->searchable()->preload()->required(),
                ]),

            Forms\Components\Section::make('Informations générales')
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->required()->unique(ignoreRecord: true)->maxLength(50),
                    Forms\Components\TextInput::make('libelle')
                        ->required()->maxLength(255),
                    Forms\Components\Textarea::make('description')->columnSpanFull(),
                    Forms\Components\DatePicker::make('periode_debut')->required(),
                    Forms\Components\DatePicker::make('periode_fin')
                        ->required()->afterOrEqual('periode_debut'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')->label('N°')->searchable(),
                Tables\Columns\TextColumn::make('libelle')->searchable(),
                Tables\Columns\TextColumn::make('cspMinistere.libelle')->label('CSP'),
                Tables\Columns\TextColumn::make('parametresStructure.nom_structure')->label('Établissement'),
                Tables\Columns\TextColumn::make('periode_debut')->date(),
                Tables\Columns\TextColumn::make('periode_fin')->date(),
                Tables\Columns\BadgeColumn::make('statut')->colors([
                    'gray' => 'brouillon',
                    'warning' => 'en_transmission',
                    'success' => ['valide', 'en_vigueur'],
                    'danger' => 'cloture',
                ]),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('statut')->options([
                    'brouillon' => 'Brouillon',
                    'en_transmission' => 'En transmission',
                    'valide' => 'Validé',
                    'en_vigueur' => 'En vigueur',
                    'cloture' => 'Clôturé',
                ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn(PlanStrategiqueEp $record) => $record->estModifiable()),

                Tables\Actions\Action::make('transmettre')
                    ->label('Transmettre')
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(
                        fn(PlanStrategiqueEp $record) =>
                        $record->peutEtreTransmis() && auth()->user()->can('transmettre_plan_strategique_ep')
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
                    ->action(function (PlanStrategiqueEp $record, array $data) {
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
                        fn(PlanStrategiqueEp $record) =>
                        $record->estDestinataireActuel() && auth()->user()->can('valider_plan_strategique_ep')
                    )
                    ->requiresConfirmation()
                    ->action(function (PlanStrategiqueEp $record) {
                        $record->cloturerTransmission('Validé');
                        $record->update(['statut' => 'valide']);
                    }),

                Tables\Actions\Action::make('retourner')
                    ->label('Retourner')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn(PlanStrategiqueEp $record) => $record->estDestinataireActuel())
                    ->form([Forms\Components\Textarea::make('motif')->required()])
                    ->action(
                        fn(PlanStrategiqueEp $record, array $data) =>
                        $record->retournerPourCorrection($data['motif'])
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Planification\Resources\PlanStrategiqueEpResource\RelationManagers\SousProgrammesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlanStrategiqueEps::route('/'),
            'create' => Pages\CreatePlanStrategiqueEp::route('/create'),
            'edit' => Pages\EditPlanStrategiqueEp::route('/{record}/edit'),
        ];
    }
}
