<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EngagementResource\Pages;
use App\Filament\Resources\EngagementResource\RelationManagers;
use App\Models\Engagement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EngagementResource extends Resource
{
    protected static ?string $model = Engagement::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('numero')
                    ->required()
                    ->maxLength(50),
                Forms\Components\Select::make('budget_id')
                    ->relationship('budget', 'id')
                    ->required(),
                Forms\Components\TextInput::make('engageable_type')
                    ->maxLength(255),
                Forms\Components\TextInput::make('engageable_id')
                    ->numeric(),
                Forms\Components\TextInput::make('beneficiaire_type')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('beneficiaire_id')
                    ->required()
                    ->numeric(),
                Forms\Components\DatePicker::make('date_engagement')
                    ->required(),
                Forms\Components\TextInput::make('exercice')
                    ->required()
                    ->numeric(),
                Forms\Components\Textarea::make('objet')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('montant_engage')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('statut')
                    ->required()
                    ->maxLength(255)
                    ->default('provisoire'),
                Forms\Components\TextInput::make('engage_par')
                    ->numeric(),
                Forms\Components\DateTimePicker::make('date_validation'),
                Forms\Components\Textarea::make('observations')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('type_engagement')
                    ->required()
                    ->maxLength(100),
                Forms\Components\Select::make('nomenclature_principale_id')
                    ->relationship('nomenclaturePrincipale', 'id'),
                Forms\Components\TextInput::make('reference_document')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->searchable(),
                Tables\Columns\TextColumn::make('budget.id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('engageable_type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('engageable_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('beneficiaire_type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('beneficiaire_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date_engagement')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('exercice')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('montant_engage')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('statut')
                    ->searchable(),
                Tables\Columns\TextColumn::make('engage_par')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date_validation')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('type_engagement')
                    ->searchable(),
                Tables\Columns\TextColumn::make('nomenclaturePrincipale.id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reference_document')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEngagements::route('/'),
            'create' => Pages\CreateEngagement::route('/create'),
            'edit' => Pages\EditEngagement::route('/{record}/edit'),
        ];
    }
}
