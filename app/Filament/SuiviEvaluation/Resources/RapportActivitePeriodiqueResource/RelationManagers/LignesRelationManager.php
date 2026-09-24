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
                Tables\Columns\TextColumn::make('source_realisation')
                    ->label('Source')
                    ->badge()
                    ->formatStateUsing(fn(?string $state) => match ($state) {
                        'budget'   => 'Budget',
                        'manuelle' => 'Manuelle',
                        default    => 'Non renseignée',
                    })
                    ->color(fn(?string $state) => match ($state) {
                        'budget' => 'info',
                        'manuelle' => 'warning',
                        default => 'gray',
                    })
                    ->tooltip(fn(RapportActiviteLigne $r) => $r->realisation_actualisee_le
                        ? 'Actualisée le ' . $r->realisation_actualisee_le->format('d/m/Y H:i')
                        . ($r->quote_part && $r->quote_part < 1 ? ' — quote-part ' . round($r->quote_part * 100, 1) . ' %' : '')
                        : null),
                Tables\Columns\TextColumn::make('ecart')
                    ->getStateUsing(fn(RapportActiviteLigne $r) => number_format($r->ecart, 0, ',', ' '))
                    ->color(fn(RapportActiviteLigne $r) => $r->ecart < 0 ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('taux')
                    ->label('Taux')
                    ->getStateUsing(fn(RapportActiviteLigne $r) => $r->taux_realisation !== null ? "{$r->taux_realisation}%" : '—'),
            ])
            ->defaultSort('nature')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(fn(array $data) => $data + ['source_realisation' => 'manuelle']),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data, RapportActiviteLigne $record) {
                        // Seule une modification effective de la realisation la fait passer en "manuelle"
                        if ((float) $data['realisation'] !== (float) $record->realisation) {
                            $data['source_realisation'] = 'manuelle';
                            $data['realisation_actualisee_le'] = null;
                        }
                        return $data;
                    }),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
