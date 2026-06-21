<?php

namespace App\Filament\Comptable\Resources;

use App\Filament\Comptable\Resources\CategorieArticleResource\Pages;
use App\Models\CategorieArticle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CategorieArticleResource extends Resource
{
    protected static ?string $model           = CategorieArticle::class;
    protected static ?string $navigationIcon  = 'heroicon-o-tag';
    protected static ?string $navigationLabel = 'Catégories d\'Articles';
    protected static ?string $modelLabel      = 'Catégorie d\'Article';
    protected static ?string $pluralModelLabel = 'Catégories d\'Articles';
    protected static ?string $navigationGroup = 'Paramétrage';
    protected static ?int    $navigationSort  = 9;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Catégorie')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('libelle')
                            ->label('Libellé')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ex: Pharmacie, Informatique, Mobilier'),

                        Forms\Components\TextInput::make('code')
                            ->label('Code (optionnel)')
                            ->maxLength(30),
                    ]),

                    Forms\Components\Toggle::make('est_pharmacie')
                        ->label('Nécessite un conditionnement')
                        ->helperText('Si activé, les articles de cette catégorie exigeront un conditionnement (plaquette, flacon, gel...) lors des expressions de besoins.')
                        ->default(false),

                    Forms\Components\Toggle::make('actif')
                        ->label('Actif')
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('libelle')
                    ->label('Libellé')->searchable()->sortable()->weight('bold'),

                Tables\Columns\TextColumn::make('code')
                    ->label('Code')->badge()->color('gray')->placeholder('—'),

                Tables\Columns\IconColumn::make('est_pharmacie')
                    ->label('Conditionnement requis')
                    ->boolean()
                    ->trueIcon('heroicon-o-beaker')
                    ->trueColor('success'),

                Tables\Columns\TextColumn::make('articles_count')
                    ->label('Articles liés')
                    ->counts('articles')
                    ->badge()->color('info'),

                Tables\Columns\IconColumn::make('actif')
                    ->label('Actif')->boolean()->sortable(),
            ])
            ->defaultSort('libelle')
            ->filters([
                Tables\Filters\TernaryFilter::make('actif')
                    ->label('Actif')->trueLabel('Actifs')->falseLabel('Inactifs'),

                Tables\Filters\TernaryFilter::make('est_pharmacie')
                    ->label('Conditionnement requis'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn($record) => $record->articles()->count() === 0),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCategorieArticles::route('/'),
            'create' => Pages\CreateCategorieArticle::route('/create'),
            'edit'   => Pages\EditCategorieArticle::route('/{record}/edit'),
        ];
    }
}
