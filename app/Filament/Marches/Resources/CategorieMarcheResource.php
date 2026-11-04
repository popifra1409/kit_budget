<?php

namespace App\Filament\Marches\Resources;

use App\Filament\Marches\Resources\CategorieMarcheResource\Pages;
use App\Models\CategorieMarche;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Concerns\AutorisationParPermissions;

/** Paramétrage : catégorie du marché — pluriannuel, annuel, mensuel. */
class CategorieMarcheResource extends Resource
{
    use AutorisationParPermissions;

    protected static ?string $model = CategorieMarche::class;
    // `heroicon-o-calendar-range` n'existe pas dans le jeu blade-heroicons installé, où
    // ce dessin s'appelle `calendar-days` : une icône de navigation inconnue lève
    // SvgNotFound au rendu du menu latéral, donc sur toutes les pages du panneau.
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'Paramétrage';
    protected static ?string $navigationLabel = 'Catégories de marché';
    protected static ?string $modelLabel = 'catégorie de marché';
    protected static ?string $pluralModelLabel = 'catégories de marché';
    protected static ?string $recordTitleAttribute = 'libelle';
    protected static ?int $navigationSort = 3;

    protected static function prefixePermission(): string
    {
        return 'categorie_marche';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Catégorie')->schema([
                Forms\Components\TextInput::make('libelle')->label('Libellé')->required()->maxLength(255)
                    ->placeholder('Marche pluriannuel'),
                Forms\Components\TextInput::make('code')->label('Code')->required()->maxLength(50)
                    ->unique(ignoreRecord: true)->placeholder('pluriannuel'),
                Forms\Components\TextInput::make('duree_mois')->label('Durée de référence')->numeric()
                    ->suffix('mois')->helperText('Proposée à la création du marché, jamais imposée'),
                Forms\Components\Toggle::make('pluri_annuel')->label('Pluriannuel')
                    ->helperText("Déclenche l'engagement par tranches d'exercice (étape « exécution pluriannuelle »)"),
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
                Tables\Columns\TextColumn::make('libelle')->label('Catégorie')->weight('bold')->searchable(),
                Tables\Columns\TextColumn::make('duree_mois')->label('Durée de référence')
                    ->formatStateUsing(fn (?string $state) => $state ? "$state mois" : '—')
                    ->placeholder('—')->sortable(),
                Tables\Columns\IconColumn::make('pluri_annuel')->label('Par tranches')->boolean(),
                Tables\Columns\IconColumn::make('actif')->label('Actif')->boolean(),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCategoriesMarche::route('/'),
            'create' => Pages\CreateCategorieMarche::route('/create'),
            'edit'   => Pages\EditCategorieMarche::route('/{record}/edit'),
        ];
    }
}
