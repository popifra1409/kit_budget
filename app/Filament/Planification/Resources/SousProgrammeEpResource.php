<?php

namespace App\Filament\Planification\Resources;

use App\Filament\Planification\Resources\SousProgrammeEpResource\Pages;
use App\Filament\Planification\Resources\SousProgrammeEpResource\RelationManagers\ActionsRelationManager;
use App\Models\Exercice;
use App\Models\Programme;
use App\Models\SousProgrammeEp;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SousProgrammeEpResource extends Resource
{
    protected static ?string $model = SousProgrammeEp::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Plans Stratégiques EP';

    // Accessible uniquement depuis la fiche du Plan Stratégique (pas dans le menu principal)
    protected static bool $shouldRegisterNavigation = false;

    /**
     * Programmes budgetaires de l'exercice actif, sans le scope global 'exercice'
     * (evite une liste vide si l'exercice de session differe).
     */
    protected static function programmesExerciceActif()
    {
        return Programme::withoutGlobalScope('exercice')
            ->where('exercice_id', Exercice::getActif()?->id)
            ->orderBy('code')
            ->get();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identification')
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->required()->unique(ignoreRecord: true)->maxLength(50),
                    Forms\Components\TextInput::make('libelle')
                        ->required()->maxLength(255),
                    Forms\Components\Select::make('responsable_id')
                        ->label('Responsable')
                        ->options(fn() => User::orderBy('name')->pluck('name', 'id'))
                        ->searchable(),
                    Forms\Components\Textarea::make('description')->columnSpanFull(),
                ])
                ->columns(2),

            // ✅ AJOUTE : les deux rattachements, desormais distincts
            Forms\Components\Section::make('Rattachements')
                ->description("Le programme ministériel sert à l'affichage du rattachement ; le programme de l'EP porte les actions, activités, tâches et lignes budgétaires.")
                ->schema([
                    Forms\Components\Select::make('programme_budgetaire_id')
                        ->label('Programme de rattachement (ministériel)')
                        ->helperText('Ex : P-410 Prévention de la maladie')
                        ->options(fn() => static::programmesExerciceActif()
                            ->mapWithKeys(fn($p) => [$p->id => "{$p->code} - {$p->libelle}"]))
                        ->searchable(),

                    Forms\Components\Select::make('code_programme_ep')
                        ->label("Programme budgétaire de l'EP (porte les actions)")
                        ->helperText("Ex : SP-1 Prévention des pathologies. Lien par code : il reste valable d'un exercice à l'autre.")
                        ->options(fn() => static::programmesExerciceActif()
                            ->mapWithKeys(fn($p) => [$p->code => "{$p->code} - {$p->libelle}"]))
                        ->searchable()
                        ->required(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')->label('N°'),
                Tables\Columns\TextColumn::make('code'),
                Tables\Columns\TextColumn::make('libelle')->searchable(),

                // ✅ AJOUTE : visibilite immediate des deux rattachements
                Tables\Columns\TextColumn::make('programmeBudgetaire.code')
                    ->label('Rattachement')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('code_programme_ep')
                    ->label('Programme EP')
                    ->badge()
                    ->color(fn(?string $state) => $state ? 'success' : 'danger')
                    ->placeholder('Non rattaché'),

                Tables\Columns\TextColumn::make('planStrategiqueEp.libelle')->label('PSP'),
                Tables\Columns\BadgeColumn::make('statut')->colors([
                    'gray' => 'brouillon',
                    'warning' => 'en_transmission',
                    'success' => ['valide', 'en_vigueur'],
                    'danger' => 'cloture',
                ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('matrice')
                    ->label("Matrice d'arrimage")
                    ->icon('heroicon-o-squares-2x2')
                    ->visible(fn() => auth()->user()->can('view_matrice_arrimage'))
                    ->url(fn(SousProgrammeEp $record) => \App\Filament\SuiviEvaluation\Pages\MatriceArrimage::getUrl(
                        ['psp' => $record->plan_strategique_ep_id, 'sp' => $record->id],
                        panel: 'suivi-evaluation'
                    ))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('libelles')
                    ->label('Tableau des libellés')
                    ->icon('heroicon-o-list-bullet')
                    ->visible(fn() => auth()->user()->can('view_arborescence_libelles'))
                    ->url(fn(SousProgrammeEp $record) => \App\Filament\Planification\Pages\ArborescenceLibelles::getUrl([
                        'psp' => $record->plan_strategique_ep_id,
                        'sp'  => $record->id,
                    ]))
                    ->openUrlInNewTab(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ActionsRelationManager::class,
            \App\Filament\Planification\Resources\Concerns\IndicateursRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSousProgrammeEps::route('/'),
            'edit' => Pages\EditSousProgrammeEp::route('/{record}/edit'),
        ];
    }
}
