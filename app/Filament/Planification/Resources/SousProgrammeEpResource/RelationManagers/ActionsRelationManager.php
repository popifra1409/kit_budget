<?php

namespace App\Filament\Planification\Resources\SousProgrammeEpResource\RelationManagers;

use App\Models\Action;
use App\Models\Exercice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActionsRelationManager extends RelationManager
{
    protected static string $relationship = 'actions';

    protected static ?string $title = 'Actions (classification budgétaire)';

    protected static ?string $recordTitleAttribute = 'libelle';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')->required()->maxLength(50),
            Forms\Components\TextInput::make('libelle')->required()->maxLength(255),
            Forms\Components\Textarea::make('description')->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            // Filtre explicite et prefixe sur l'exercice actif :
            // evite l'ambiguite "exercice_id" (jointure actions <-> programmes)
            ->modifyQueryUsing(fn(Builder $query) => $query
                ->withoutGlobalScope('exercice')
                ->where('actions.exercice_id', Exercice::getActif()?->id))

            ->description(function () {
                $programme = $this->getOwnerRecord()->programmeEp();

                return $programme
                    ? "Programme budgétaire de l'EP : {$programme->code} - {$programme->libelle} (exercice " . Exercice::getActif()?->annee . ')'
                    : null;
            })

            ->columns([
                Tables\Columns\TextColumn::make('code')->sortable(),
                Tables\Columns\TextColumn::make('libelle')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('activites_count')->counts('activites')->label('Nb. activités'),
            ])
            ->defaultSort('code')

            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Nouvelle action')
                    ->visible(fn() => auth()->user()->can('create_action')
                        && $this->getOwnerRecord()->programmeEp() !== null)
                    // Relation "a travers" le programme : on indique explicitement
                    // ou ranger l'action (programme EP de l'exercice actif)
                    ->using(function (array $data): Action {
                        $programme = $this->getOwnerRecord()->programmeEp();

                        return Action::create($data + [
                            'programme_id' => $programme->id,
                            'exercice_id'  => $programme->exercice_id,
                        ]);
                    }),
            ])

            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('gererActivites')
                        ->label('Gérer les activités')
                        ->icon('heroicon-o-bolt')
                        ->url(
                            fn(Action $record) =>
                            \App\Filament\Planification\Resources\ActiviteResource::getUrl('index', [
                                'tableFilters[action_id][value]' => $record->id,
                            ])
                        ),

                    Tables\Actions\EditAction::make()
                        ->visible(fn() => auth()->user()->can('update_action')),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')
                    ->button()
                    ->size('sm'),
            ], position: \Filament\Tables\Enums\ActionsPosition::BeforeColumns)

            // Suppression groupee retiree : supprimer une action budgetaire
            // (et tout ce qui en depend) releve du module Budget, pas de la Planification.
            ->bulkActions([])

            ->emptyStateHeading(fn() => $this->getOwnerRecord()->code_programme_ep
                ? 'Aucune action pour cet exercice'
                : "Sous-programme non rattaché à un programme de l'EP")
            ->emptyStateDescription(fn() => $this->getOwnerRecord()->code_programme_ep
                ? "Le programme {$this->getOwnerRecord()->code_programme_ep} n'a pas encore d'action sur l'exercice actif."
                : "Modifiez le sous-programme et choisissez son « Programme budgétaire de l'EP » (ex : SP-1) pour afficher ses actions.");
    }
}
