<?php

namespace App\Filament\Planification\Resources\ActionResource\RelationManagers;

use App\Models\Activite;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Le chaînon qui manquait à la hiérarchie : sous-programme → action → activité → tâche.
 *
 * L'onglet « Actions » du sous-programme permet de créer une action, l'onglet « Tâches » de la
 * fiche activité permet d'en créer une : mais une fois posé sur une action, aucune saisie
 * d'activité n'était possible. Le seul lien menait à la liste des activités filtrée sur
 * l'action, où le bouton « Nouvelle activité » obligeait à re-choisir l'action dans une liste.
 *
 * Trois points ne se lisent pas d'eux-mêmes dans le schéma :
 *  - `exercice_id` est repris de l'action et non de l'exercice ouvert en session : le hook
 *    `creating` de `HasExercice` prend l'exercice actif, ce qui détacherait l'activité de son
 *    action dès qu'on relit un exercice clôturé ;
 *  - `action_id` n'est pas un champ du formulaire : c'est le propriétaire qui le pose, et le
 *    proposer permettrait de déplacer l'activité sous une autre action sans quitter l'onglet ;
 *  - le code n'est pas saissé (ACT + suite, calculé sur l'exercice), comme sur l'écran
 *    « Activités ».
 */
class ActivitesRelationManager extends RelationManager
{
    protected static string $relationship = 'activites';

    protected static ?string $title = 'Activités';

    protected static ?string $recordTitleAttribute = 'libelle';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('libelle')
                ->label('Libellé')->required()->maxLength(255)->columnSpanFull()
                ->placeholder('Ex: Campagne de dépistage du diabète'),

            Forms\Components\Textarea::make('objectif')
                ->label('Objectif de l\'activité')->rows(2)->columnSpanFull(),

            Forms\Components\TextInput::make('zone_execution')
                ->label('Zone ou lieu d\'exécution'),

            \App\Filament\Planification\Components\ResponsableSelect::make(),

            Forms\Components\TextInput::make('code')
                ->label('Code')->disabled()->dehydrated(false)
                ->placeholder('Généré automatiquement à l\'enregistrement')
                ->helperText(fn(?Activite $record) => $record === null
                    ? 'ACT + numéro suivant de l\'exercice (ACT1, ACT2…).'
                    : null),

            Forms\Components\TextInput::make('ordre')
                ->label('Ordre d\'affichage')->numeric()->default(0),

            Forms\Components\Toggle::make('actif')->label('Actif')->default(true),

            Forms\Components\Textarea::make('description')
                ->label('Description')->rows(3)->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        $proprietaire = fn(): \App\Models\Action => $this->getOwnerRecord();

        return $table
            // L'onglet montre les activités DE cette action, même quand l'exercice consulté
            // n'est plus l'exercice actif : le scope global viderait la liste d'une action
            // relue après clôture, alors que son action, elle, reste affichée.
            ->modifyQueryUsing(fn(Builder $query) => $query->withoutGlobalScope('exercice'))

            ->description(function () use ($proprietaire) {
                $action = $proprietaire();

                return $action->estModifiable()
                    ? "Les activités de « {$action->code} » — exercice " . ($action->exercice?->annee ?? '—')
                    : "Exercice " . ($action->exercice?->annee ?? '—') . ' ' . ($action->exercice?->getBadgeStatut() ?? 'verrouillé') . ' : la hiérarchie se lit, elle ne se complète plus.';
            })

            ->columns([
                Tables\Columns\TextColumn::make('code')->label('Code')->weight('bold'),
                Tables\Columns\TextColumn::make('libelle')->label('Libellé')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('responsable.nom_complet')
                    ->label('Responsable')->placeholder('Aucun'),
                Tables\Columns\TextColumn::make('taches_count')->counts('taches')
                    ->label('Tâches')->badge()->color('success'),
                Tables\Columns\BadgeColumn::make('statut')->label('Statut'),
                Tables\Columns\IconColumn::make('actif')->label('Actif')->boolean(),
            ])
            ->defaultSort('ordre')

            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Nouvelle activité')
                    ->visible(fn() => ($proprietaire()->estModifiable())
                        && (auth()->user()?->can('create_activite') ?? false))
                    // Le rattachement et l'exercice viennent de l'action, pas d'un champ.
                    ->using(fn(array $data): Activite => Activite::create($data + [
                        'action_id'   => $proprietaire()->getKey(),
                        'exercice_id' => $proprietaire()->exercice_id,
                    ])),
            ])

            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make()
                        ->visible(fn(Activite $record) => $record->estModifiable()
                            && (auth()->user()?->can('update_activite') ?? false)),

                    // La tâche, le crédit et les extrants ne se saisissent pas ici : ils ont
                    // leur propre écran, atteint par la fiche.
                    Tables\Actions\Action::make('ouvrirFiche')
                        ->label('Ouvrir la fiche (tâches, crédits)')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->url(fn(Activite $record) => \App\Filament\Planification\Resources\ActiviteResource::getUrl('edit', [
                            'record' => $record->getKey(),
                        ])),

                    Tables\Actions\DeleteAction::make()
                        ->visible(fn(Activite $record) => $record->estModifiable()
                            && (auth()->user()?->can('delete_activite') ?? false)),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')
                    ->button()
                    ->size('sm'),
            ], position: Tables\Enums\ActionsPosition::BeforeColumns)

            // Pas de suppression groupée : supprimer une activité emporte ses tâches dans les
            // états déjà imprimés, et ce nettoyage relève de l'écran « Activités ».
            ->bulkActions([])

            ->emptyStateHeading('Aucune activité sous cette action')
            ->emptyStateDescription(fn() => $proprietaire()->estModifiable()
                ? 'Une action se décline en activités, et une activité en tâches : le bouton « Nouvelle activité » ci-dessus ouvre la suite de la chaîne.'
                : 'L\'exercice de cette action est verrouillé : ses activités ne peuvent plus être complétées.');
    }
}
