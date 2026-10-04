<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\NatureServiceFaitResource\Pages;
use App\Models\NatureServiceFait;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/** Paramétrage : natures de service fait et preuves attendues (tableau à deux entrées). */
class NatureServiceFaitResource extends Resource
{
    protected static ?string $model = NatureServiceFait::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'Paramétrage';
    protected static ?string $navigationLabel = 'Natures de service fait';
    protected static ?string $modelLabel = 'nature de service fait';
    protected static ?string $pluralModelLabel = 'natures de service fait';
    protected static ?int $navigationSort = 40;

    public static function canViewAny(): bool
    {
        $u = auth()->user();
        return $u && ($u->hasAnyRole(['super_admin', 'admin']) || $u->can('gerer_natures_service_fait'));
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Nature')->schema([
                Forms\Components\TextInput::make('libelle')->label('Nature')->required()->maxLength(255),
                Forms\Components\TextInput::make('code')->label('Code')->required()->maxLength(40)
                    ->unique(ignoreRecord: true)->helperText('Identifiant court, ex. medicaments_fournitures'),
                Forms\Components\Textarea::make('description')->rows(2)->columnSpanFull(),
                Forms\Components\TextInput::make('ordre')->numeric()->default(0),
                Forms\Components\Toggle::make('actif')->default(true),
            ])->columns(2),

            Forms\Components\Section::make('Preuves attendues')->schema([
                Forms\Components\Repeater::make('preuves')
                    ->relationship()
                    ->schema([
                        Forms\Components\TextInput::make('libelle')->label('Preuve')->required()->columnSpan(3),
                        Forms\Components\Toggle::make('obligatoire')->default(true)->inline(false),
                    ])
                    ->columns(4)
                    ->orderColumn('ordre')
                    ->defaultItems(1)
                    ->addActionLabel('Ajouter une preuve'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('ordre')
            ->columns([
                Tables\Columns\TextColumn::make('libelle')->label('Nature')->weight('bold')->searchable(),
                Tables\Columns\TextColumn::make('preuves.libelle')->label('Preuves attendues')->listWithLineBreaks()->bulleted(),
                Tables\Columns\IconColumn::make('actif')->boolean(),
            ])
            ->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListNaturesServiceFait::route('/'),
            'create' => Pages\CreateNatureServiceFait::route('/create'),
            'edit'   => Pages\EditNatureServiceFait::route('/{record}/edit'),
        ];
    }
}
