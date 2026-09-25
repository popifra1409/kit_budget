<?php

namespace App\Filament\Planification\Resources\PlanStrategiqueEpResource\RelationManagers;

use App\Models\Exercice;
use App\Models\Programme;
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

    /**
     * Programmes de l'exercice actif, sans le scope global 'exercice'
     * (evite les doublons P-410 2026 / P-410 2027 apres reconduction).
     */
    protected static function programmesExerciceActif()
    {
        return Programme::withoutGlobalScope('exercice')
            ->where('exercice_id', Exercice::getActif()?->id)
            ->orderBy('code');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')
                ->required()->unique(ignoreRecord: true)->maxLength(50),
            Forms\Components\TextInput::make('libelle')
                ->required()->maxLength(255),
            Forms\Components\Textarea::make('description')
                ->columnSpanFull(),
            Forms\Components\Select::make('type')
                ->label('Type de sous-programme')
                ->options([
                    'operationnel' => 'Opérationnel',
                    'support' => 'Support',
                ])
                ->default('operationnel')
                ->required()
                ->helperText('Maximum 4 sous-programmes par EP : 3 opérationnels + 1 support (Instruction du 22 janvier 2026).'),

            Forms\Components\Textarea::make('objectif')
                ->label('Objectif du sous-programme'),
            Forms\Components\Textarea::make('strategie')
                ->label('Stratégie du sous-programme')
                ->columnSpanFull(),
            Forms\Components\Textarea::make('cadre_institutionnel')
                ->label('Cadre institutionnel de mise en œuvre')
                ->columnSpanFull(),
            Forms\Components\Select::make('responsable_id')
                ->label('Responsable de mise en œuvre')
                ->options(fn() => User::orderBy('name')->pluck('name', 'id'))
                ->searchable(),

            // ── Rattachements : deux champs distincts ──────────────────
            Forms\Components\Select::make('programme_budgetaire_id')
                ->label('Programme de rattachement (ministériel)')
                ->helperText('Programme national de niveau « programme » (ex : 412 Renforcement du système de santé).')
                ->options(fn() => static::programmesExerciceActif()
                    ->where('niveau', 'programme')
                    ->get()
                    ->mapWithKeys(fn($p) => [$p->id => "{$p->code} — {$p->libelle}"]))
                ->searchable()
                ->live()
                // Si ce programme porte lui-meme les actions, on pre-remplit le programme EP
                ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                    if (!$state || filled($get('code_programme_ep'))) {
                        return;
                    }
                    $programme = Programme::withoutGlobalScope('exercice')->find($state);
                    if ($programme && \App\Models\Action::withoutGlobalScope('exercice')->where('programme_id', $programme->id)->exists()) {
                        $set('code_programme_ep', $programme->code);
                    }
                }),

            Forms\Components\Select::make('code_programme_ep')
                ->label('Programme qui porte les actions')
                ->helperText("Souvent le même que le programme de rattachement. Lien par code : il reste valable d'un exercice à l'autre.")
                ->options(fn() => static::programmesExerciceActif()
                    // Uniquement les programmes qui ont effectivement des actions
                    ->whereIn('id', \App\Models\Action::withoutGlobalScope('exercice')->select('programme_id'))
                    ->get()
                    ->mapWithKeys(fn($p) => [$p->code => "{$p->code} — {$p->libelle}"]))
                ->searchable()
                ->required(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')->label('N°')->searchable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->colors(['primary' => 'operationnel', 'warning' => 'support']),
                Tables\Columns\TextColumn::make('code')->searchable(),
                Tables\Columns\TextColumn::make('libelle')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('responsable.name')->label('Responsable'),
                Tables\Columns\TextColumn::make('programmeBudgetaire.code')
                    ->label('Rattachement')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('code_programme_ep')
                    ->label('Programme EP')
                    ->badge()
                    ->color(fn(?string $state) => $state ? 'success' : 'danger')
                    ->placeholder('Non rattaché'),
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
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('gererActions')
                        ->label('Gérer les actions')
                        ->icon('heroicon-o-squares-2x2')
                        ->url(
                            fn(SousProgrammeEp $record) =>
                            \App\Filament\Planification\Resources\SousProgrammeEpResource::getUrl('edit', ['record' => $record])
                        ),

                    Tables\Actions\Action::make('libelles')
                        ->label('Tableau des libellés')
                        ->icon('heroicon-o-list-bullet')
                        ->visible(fn() => auth()->user()->can('view_arborescence_libelles'))
                        ->url(fn(SousProgrammeEp $record) => \App\Filament\Planification\Pages\ArborescenceLibelles::getUrl([
                            'psp' => $record->plan_strategique_ep_id,
                            'sp'  => $record->id,
                        ]))
                        ->openUrlInNewTab(),

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
                                ->options(fn() => User::orderBy('name')->pluck('name', 'id'))
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
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')
                    ->button()
                    ->size('sm'),
            ], position: \Filament\Tables\Enums\ActionsPosition::BeforeColumns)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn() => auth()->user()->can('delete_sous_programme_ep')),
                ]),
            ]);
    }
}
