<?php

namespace App\Filament\Programmation\Resources\CdmtExerciceResource\RelationManagers;

use App\Models\Action;
use App\Models\Activite;
use App\Models\SousProgrammeEp;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LignesRelationManager extends RelationManager
{
    protected static string $relationship = 'lignes';

    protected static ?string $title = 'Programmation par activité (Annexe B/C)';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('sous_programme_ep_id')
                ->label('Sous-Programme')
                ->options(SousProgrammeEp::pluck('libelle', 'id'))
                ->searchable()->required()->live(),

            Forms\Components\Select::make('action_id')
                ->label('Action')
                ->options(fn(Forms\Get $get) => Action::pluck('libelle', 'id'))
                ->searchable()->live(),

            Forms\Components\Select::make('activite_id')
                ->label('Activité')
                ->options(fn(Forms\Get $get) => $get('action_id')
                    ? Activite::where('action_id', $get('action_id'))->pluck('libelle', 'id')
                    : [])
                ->searchable(),

            Forms\Components\TextInput::make('libelle')->required()->columnSpanFull(),

            Forms\Components\Select::make('nature')
                ->label('Ligne de Référence / Mesure Nouvelle')
                ->options(['LR' => 'Ligne de Référence (LR)', 'MN' => 'Mesure Nouvelle (MN)'])
                ->required(),

            Forms\Components\Toggle::make('est_investissement')
                ->label('Dépense d\'investissement')
                ->live()
                ->helperText('Si activé, un visa de maturité devient obligatoire (Guide 2025, Encadré 6).'),

            Forms\Components\Select::make('maturite')
                ->label('Maturité')
                ->options([
                    'etudes' => 'Études / faisabilité',
                    'dao' => 'Projet de DAO',
                    'en_cours' => 'En cours de réalisation',
                    'mature' => 'Mature (visa obtenu)',
                    'non_requise' => 'Non requise (hors investissement)',
                ])
                ->required(fn(Forms\Get $get) => $get('est_investissement'))
                ->helperText(fn(Forms\Get $get) => $get('est_investissement')
                    ? '⚠️ Obligatoire pour une dépense d\'investissement.'
                    : null),

            Forms\Components\Fieldset::make('Programmation N+1 à N+3 (AE/CP)')
                ->schema([
                    Forms\Components\TextInput::make('n_plus_1_ae')->label('N+1 AE')->numeric()->default(0),
                    Forms\Components\TextInput::make('n_plus_1_cp')->label('N+1 CP')->numeric()->default(0),
                    Forms\Components\TextInput::make('n_plus_2_ae')->label('N+2 AE')->numeric()->default(0),
                    Forms\Components\TextInput::make('n_plus_2_cp')->label('N+2 CP')->numeric()->default(0),
                    Forms\Components\TextInput::make('n_plus_3_ae')->label('N+3 AE')->numeric()->default(0),
                    Forms\Components\TextInput::make('n_plus_3_cp')->label('N+3 CP')->numeric()->default(0),
                ])->columns(3),

            Forms\Components\Textarea::make('commentaire')->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sousProgrammeEp.libelle')->label('Sous-Programme')->limit(20),
                Tables\Columns\TextColumn::make('libelle')->wrap(),
                Tables\Columns\BadgeColumn::make('nature')->colors(['success' => 'LR', 'warning' => 'MN']),
                Tables\Columns\IconColumn::make('est_investissement')
                    ->label('Invest.')
                    ->boolean(),

                Tables\Columns\TextColumn::make('alerte')
                    ->label('Alerte maturité')
                    ->getStateUsing(fn(\App\Models\CdmtLigne $record) => $record->getAlerteMaturite())
                    ->color(fn(\App\Models\CdmtLigne $record) => match (true) {
                        !$record->est_investissement => 'gray',
                        $record->maturite === 'mature' => 'success',
                        $record->maturite === 'en_cours' => 'info',
                        default => 'danger',
                    })
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('n_plus_1_ae')->label('N+1 AE')->numeric(0),
                Tables\Columns\TextColumn::make('n_plus_1_cp')->label('N+1 CP')->numeric(0),
                Tables\Columns\TextColumn::make('n_plus_2_ae')->label('N+2 AE')->numeric(0),
                Tables\Columns\TextColumn::make('n_plus_3_ae')->label('N+3 AE')->numeric(0),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
