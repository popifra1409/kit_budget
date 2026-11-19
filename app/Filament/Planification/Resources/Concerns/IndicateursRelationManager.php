<?php

namespace App\Filament\Planification\Resources\Concerns;

use App\Models\Indicateur;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class IndicateursRelationManager extends RelationManager
{
    protected static string $relationship = 'indicateurs';

    protected static ?string $title = 'Indicateurs';

    protected static ?string $recordTitleAttribute = 'libelle';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')
                ->required()->unique(ignoreRecord: true)->maxLength(50),
            Forms\Components\TextInput::make('libelle')
                ->required()->maxLength(255)->columnSpanFull(),
            Forms\Components\TextInput::make('objectif_associe')
                ->label('Ce que l\'indicateur mesure')
                ->maxLength(255)->columnSpanFull(),
            Forms\Components\TextInput::make('unite_mesure')
                ->label('Unité de mesure'),
            Forms\Components\TextInput::make('source_verification')
                ->label('Source de vérification')
                ->placeholder('Ex: Registres, rapports d\'activités, RAP…')
                ->columnSpanFull(),
            Forms\Components\Select::make('periodicite_mesure')
                ->options(Indicateur::PERIODICITES)
                // La colonne est une chaîne libre, sans contrainte en base : la liste à l'écran
                // est donc SEULE à faire la loi. Elle valide sur ses propres options — pas sur une
                // seconde copie — pour qu'un état importé ou une requête manipulée soit refusée.
                ->in(fn (Forms\Components\Select $component) => array_keys($component->getOptions()))
                ->default('annuelle')->required(),
            Forms\Components\TextInput::make('mode_calcul')
                ->columnSpanFull(),

            Forms\Components\Fieldset::make('Référence et cible')
                ->schema([
                    Forms\Components\TextInput::make('valeur_reference')->numeric(),
                    Forms\Components\TextInput::make('annee_reference')->numeric()
                        ->label('Année de référence'),
                    Forms\Components\TextInput::make('valeur_cible')->numeric(),
                    Forms\Components\Radio::make('sens')
                        ->label("Sens d'évolution souhaité")
                        ->options(\App\Models\Indicateur::SENS)
                        ->default('hausse')
                        ->required()
                        ->helperText('Détermine le calcul du taux d\'atteinte. Ex. : mortalité, délais, ruptures de stock → « À réduire ».')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('annee_cible')->numeric()
                        ->label('Année cible'),
                ])->columns(4),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code'),
                Tables\Columns\TextColumn::make('libelle')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('unite_mesure')->label('Unité'),
                Tables\Columns\TextColumn::make('source_verification')->label('Source de vérification')->wrap(),
                Tables\Columns\TextColumn::make('valeur_reference')->label('Référence')
                    ->formatStateUsing(fn($record) => $record->valeur_reference !== null
                        ? "{$record->valeur_reference} ({$record->annee_reference})" : '—'),
                Tables\Columns\TextColumn::make('valeur_cible')->label('Cible')
                    ->formatStateUsing(fn($record) => $record->valeur_cible !== null
                        ? "{$record->valeur_cible} ({$record->annee_cible})" : '—'),
                Tables\Columns\TextColumn::make('sens')
                    ->label('Sens')
                    ->formatStateUsing(fn(?string $state) => $state === 'baisse' ? '↓ À réduire' : '↑ À augmenter')
                    ->badge()
                    ->color(fn(?string $state) => $state === 'baisse' ? 'warning' : 'info'),
                Tables\Columns\BadgeColumn::make('statut')->colors([
                    'gray' => 'brouillon',
                    'success' => 'valide',
                ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn() => auth()->user()->can('create_indicateur')),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make()
                        ->visible(fn() => auth()->user()->can('update_indicateur')),

                    Tables\Actions\Action::make('gererValeurs')
                        ->label('Gérer les valeurs')
                        ->icon('heroicon-o-chart-bar')
                        ->url(
                            fn(Indicateur $record) =>
                            \App\Filament\Planification\Resources\IndicateurResource::getUrl('edit', ['record' => $record])
                        ),

                    Tables\Actions\DeleteAction::make()
                        ->visible(fn() => auth()->user()?->can('delete_indicateur') ?? false)
                        ->modalDescription('Ses valeurs déjà saisies partent en corbeille avec lui, sous le même jeton, et reviennent par le même geste.')
                        ->successNotificationTitle('Indicateur mis en corbeille'),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')
                    ->button()
                    ->size('sm'),
            ], position: \Filament\Tables\Enums\ActionsPosition::BeforeColumns)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // La même permission que la ligne : sans elle, cocher puis jeter ne doit
                    // pas ouvrir une porte que le bouton de ligne refuse.
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn() => auth()->user()?->can('delete_indicateur') ?? false),
                ]),
            ]);
    }
}
