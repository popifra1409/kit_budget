<?php

namespace App\Filament\Marches\Resources;

use App\Filament\Marches\Resources\TypeProcedureResource\Pages;
use App\Models\TypeProcedure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Concerns\AutorisationParPermissions;

/** Paramétrage : procédures de passation proposées à un marché. */
class TypeProcedureResource extends Resource
{
    use AutorisationParPermissions;

    protected static ?string $model = TypeProcedure::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationGroup = 'Paramétrage';
    protected static ?string $navigationLabel = 'Types de procédure';
    protected static ?string $modelLabel = 'type de procédure';
    protected static ?string $pluralModelLabel = 'types de procédure';
    protected static ?int $navigationSort = 1;

    protected static function prefixePermission(): string
    {
        return 'type_procedure';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Procédure de passation')->schema([
                Forms\Components\TextInput::make('libelle')->label('Libellé')->required()->maxLength(255)
                    ->placeholder("Appel d'offres ouvert"),
                Forms\Components\TextInput::make('code')->label('Code')->required()->maxLength(50)
                    ->unique(ignoreRecord: true)->placeholder('ao_ouvert')
                    ->helperText('Identifiant court, sans espace'),
                Forms\Components\Select::make('famille')->label('Regroupement')->required()
                    ->options(config('marches.familles_procedure'))->native()
                    ->helperText('Le regroupement affiché ; la ligne saisie sur le marché reste la feuille'),
                Forms\Components\Textarea::make('description')->rows(2)->columnSpanFull(),
                Forms\Components\TextInput::make('ordre')->numeric()->default(0),
                Forms\Components\Toggle::make('actif')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('ordre')
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('Code')->fontFamily('mono')->searchable(),
                Tables\Columns\TextColumn::make('libelle')->label('Procédure')->weight('bold')->searchable(),
                Tables\Columns\TextColumn::make('famille')->label('Regroupement')->badge()
                    ->formatStateUsing(fn (?string $state) => config("marches.familles_procedure.$state") ?? $state)
                    ->sortable(),
                Tables\Columns\IconColumn::make('actif')->label('Actif')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('famille')->label('Regroupement')
                    ->options(config('marches.familles_procedure')),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTypeProcedures::route('/'),
            'create' => Pages\CreateTypeProcedure::route('/create'),
            'edit'   => Pages\EditTypeProcedure::route('/{record}/edit'),
        ];
    }
}
