<?php

namespace App\Filament\Planification\Resources\ActiviteResource\RelationManagers;

use App\Models\NomenclatureBudgetaire;
use App\Models\Service;
use App\Models\Tache;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TachesRelationManager extends RelationManager
{
    protected static string $relationship = 'taches';

    protected static ?string $title = 'Tâches';

    protected static ?string $recordTitleAttribute = 'libelle';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('niveau')
                ->options([
                    'tache' => 'Tâche principale',
                    'sous_tache' => 'Sous-tâche',
                ])
                ->default('tache')
                ->required()
                ->live(),

            Forms\Components\Select::make('parent_id')
                ->label('Tâche parente')
                ->options(fn(Forms\Get $get) => Tache::where('activite_id', $this->getOwnerRecord()->id)
                    ->where('niveau', 'tache')
                    ->pluck('libelle', 'id'))
                ->searchable()
                ->visible(fn(Forms\Get $get) => $get('niveau') === 'sous_tache')
                ->required(fn(Forms\Get $get) => $get('niveau') === 'sous_tache'),

            Forms\Components\TextInput::make('code')
                ->required()->maxLength(50),
            Forms\Components\TextInput::make('libelle')
                ->required()->maxLength(255)->columnSpanFull(),
            Forms\Components\Textarea::make('description')
                ->columnSpanFull(),

            Forms\Components\Select::make('nomenclature_id')
                ->label('Nomenclature budgétaire')
                ->relationship('nomenclature', 'libelle')
                ->searchable()->preload()
                ->visible(fn(Forms\Get $get) => $get('niveau') === 'sous_tache')
                ->helperText("La nomenclature ne se renseigne que sur les sous-tâches (niveau d'imputation le plus fin)."),

            Forms\Components\Select::make('service_id')
                ->label('Service responsable')
                ->relationship('service', 'libelle')
                ->searchable()->preload(),

            Forms\Components\Fieldset::make('Montants')
                ->schema([
                    Forms\Components\TextInput::make('ae')
                        ->label('AE (Autorisation d\'Engagement)')
                        ->numeric()
                        ->disabled(fn(Forms\Get $get) => $get('niveau') === 'tache')
                        ->helperText(fn(Forms\Get $get) => $get('niveau') === 'tache'
                            ? "Calculé automatiquement (somme des sous-tâches)."
                            : null),
                    Forms\Components\TextInput::make('cp')
                        ->label('CP (Crédit de Paiement)')
                        ->numeric()
                        ->disabled(fn(Forms\Get $get) => $get('niveau') === 'tache')
                        ->helperText(fn(Forms\Get $get) => $get('niveau') === 'tache'
                            ? "Calculé automatiquement (somme des sous-tâches)."
                            : null),
                ])->columns(2),

            Forms\Components\Fieldset::make('Suivi')
                ->schema([
                    Forms\Components\TextInput::make('delai')->label('Délai'),
                    Forms\Components\TextInput::make('guichet'),
                    Forms\Components\Textarea::make('resultat_attendu')->columnSpanFull(),
                    Forms\Components\Textarea::make('indicateur_resultat')
                        ->label('Indicateur de résultat (texte libre)')
                        ->columnSpanFull(),
                ])->columns(2),

            Forms\Components\TextInput::make('ordre')->numeric()->default(0),
            Forms\Components\Toggle::make('actif')->default(true),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('libelle')
            ->columns([
                Tables\Columns\TextColumn::make('code'),
                Tables\Columns\TextColumn::make('libelle')->searchable()->wrap(),
                Tables\Columns\BadgeColumn::make('niveau')->colors([
                    'primary' => 'tache',
                    'gray' => 'sous_tache',
                ]),
                Tables\Columns\TextColumn::make('parent.libelle')
                    ->label('Tâche parente')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('service.libelle')->label('Service'),
                Tables\Columns\TextColumn::make('nomenclature.libelle')
                    ->label('Nomenclature')
                    ->placeholder('—')
                    ->limit(30),
                Tables\Columns\TextColumn::make('ae_formatte')->label('AE'),
                Tables\Columns\TextColumn::make('cp_formatte')->label('CP'),
                Tables\Columns\IconColumn::make('actif')->boolean(),
            ])
            ->defaultSort('ordre')
            ->filters([
                Tables\Filters\SelectFilter::make('niveau')->options([
                    'tache' => 'Tâches principales',
                    'sous_tache' => 'Sous-tâches',
                ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn() => auth()->user()->can('create_activite_planification') || auth()->user()->hasRole(['super_admin', 'admin'])),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')
                    ->button()
                    ->size('sm'),
            ], position: \Filament\Tables\Enums\ActionsPosition::BeforeColumns)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
            ]);
    }
}
