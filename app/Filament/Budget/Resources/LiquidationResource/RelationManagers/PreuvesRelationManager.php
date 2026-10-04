<?php

namespace App\Filament\Budget\Resources\LiquidationResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/** Preuves du service fait. Modifiables tant que la liquidation est en brouillon. */
class PreuvesRelationManager extends RelationManager
{
    protected static string $relationship = 'preuves';
    protected static ?string $title = 'Preuves du service fait';

    protected function modifiable(): bool
    {
        return $this->getOwnerRecord()->statut === 'brouillon';
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('libelle')->label('Preuve')->required()
                ->disabled(fn($record) => $record?->preuve_service_fait_id !== null)->columnSpanFull(),
            Forms\Components\TextInput::make('reference_document')->label('Référence du document')
                ->placeholder('Ex. : BL n° 2026-145, PV du 12/09/2026'),
            Forms\Components\FileUpload::make('fichier')->label('Pièce jointe')
                ->disk('public')->directory('liquidations')->maxSize(10240)
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png']),
            Forms\Components\Toggle::make('fourni')->label('Fournie')
                ->helperText('Cochée automatiquement si une référence ou une pièce est renseignée.'),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('libelle')->label('Preuve')->wrap(),
                Tables\Columns\IconColumn::make('obligatoire')->boolean(),
                Tables\Columns\IconColumn::make('fourni')->label('Fournie')->boolean(),
                Tables\Columns\TextColumn::make('reference_document')->label('Référence')->placeholder('—'),
                Tables\Columns\TextColumn::make('fichier')->label('Pièce')
                    ->formatStateUsing(fn($state) => $state ? '📎 Ouvrir' : '—')
                    ->url(fn($record) => $record->fichier ? \Storage::disk('public')->url($record->fichier) : null, true),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Ajouter une pièce')->visible(fn() => $this->modifiable()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Renseigner')->visible(fn() => $this->modifiable()),
                Tables\Actions\DeleteAction::make()->visible(fn($record) => $this->modifiable() && !$record->obligatoire),
            ]);
    }
}
