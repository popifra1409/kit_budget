<?php

namespace App\Filament\Planification\Resources\SousProgrammeEpResource\RelationManagers;

use App\Models\Action;
use App\Models\Exercice;
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
        // Champs et rattachement partagés avec la ligne « Ajouter une action » de la liste des
        // sous-programmes : un seul de ces deux écrans ne doit savoir ranger une action.
        return $form->schema(\App\Filament\Planification\Resources\Concerns\CreationActionSousProgramme::champs())
            ->columns(2);
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
                    ? "Programme du MINSANTE de rattachement : {$programme->code} - {$programme->libelle} (exercice " . Exercice::getActif()?->annee . ')'
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
                    // Relation « à travers » le programme de la tutelle : on indique explicitement
                    // où ranger l'action (le programme rattaché, sur l'exercice actif)
                    ->using(fn(array $data): Action =>
                        \App\Filament\Planification\Resources\Concerns\CreationActionSousProgramme::creer(
                            $this->getOwnerRecord(), $data)),
            ])

            ->actions([
                Tables\Actions\ActionGroup::make([
                    // La fiche de l'action porte maintenant l'onglet « Activités » : c'est par
                    // là que la chaîne continue (une activité se décline en tâches).
                    Tables\Actions\Action::make('ouvrirFiche')
                        ->label('Fiche de l\'action (créer ses activités)')
                        ->icon('heroicon-o-document-text')
                        ->url(fn(Action $record) => \App\Filament\Planification\Resources\ActionResource::getUrl('edit', [
                            'record' => $record->getKey(),
                        ])),

                    Tables\Actions\Action::make('gererActivites')
                        ->label('Lister les activités de l\'action')
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

            // Le rattachement est obligatoire à la saisie : ce texte ne s'affiche plus que sur
            // une ligne antérieure à la règle, qu'il faut renvoyer à « Modifier ».
            ->emptyStateHeading(fn() => $this->getOwnerRecord()->code_programme_ep
                ? 'Aucune action pour cet exercice'
                : "Sous-programme non rattaché à un programme du MINSANTE")
            ->emptyStateDescription(fn() => $this->getOwnerRecord()->code_programme_ep
                ? "Le programme {$this->getOwnerRecord()->code_programme_ep} n'a pas encore d'action sur l'exercice actif."
                : "Modifiez ce sous-programme et choisissez son « Programme budgétaire du MINSANTE » (ex : 412) pour afficher ses actions.");
    }
}
