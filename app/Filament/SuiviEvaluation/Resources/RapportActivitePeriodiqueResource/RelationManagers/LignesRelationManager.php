<?php

namespace App\Filament\SuiviEvaluation\Resources\RapportActivitePeriodiqueResource\RelationManagers;

use App\Models\RapportActiviteLigne;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LignesRelationManager extends RelationManager
{
    protected static string $relationship = 'lignes';

    protected static ?string $title = 'Tâches et Moyens (Annexe 9)';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('nature')
                ->options(['tache' => 'Tâche', 'moyen' => 'Moyen / Ressource'])
                ->required(),
            Forms\Components\TextInput::make('libelle')->required()->columnSpanFull(),
            Forms\Components\TextInput::make('unite'),
            Forms\Components\TextInput::make('prevision')->numeric()->default(0),
            Forms\Components\TextInput::make('realisation')->numeric()->default(0),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\BadgeColumn::make('nature')->colors(['primary' => 'tache', 'warning' => 'moyen']),
                Tables\Columns\TextColumn::make('libelle')->wrap(),
                Tables\Columns\TextColumn::make('prevision')->numeric(0),
                Tables\Columns\TextColumn::make('realisation')->numeric(0),
                Tables\Columns\TextColumn::make('ecart')
                    ->getStateUsing(fn(RapportActiviteLigne $r) => number_format($r->ecart, 0, ',', ' '))
                    ->color(fn(RapportActiviteLigne $r) => $r->ecart < 0 ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('taux')
                    ->label('Taux')
                    ->getStateUsing(fn(RapportActiviteLigne $r) => $r->taux_realisation !== null ? "{$r->taux_realisation}%" : '—'),
            ])
            ->defaultSort('nature')
            ->headerActions([Tables\Actions\CreateAction::make()])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }
}
