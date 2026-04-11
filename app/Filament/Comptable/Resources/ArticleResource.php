<?php

namespace App\Filament\Comptable\Resources;

use App\Filament\Comptable\Resources\ArticleResource\Pages;
use App\Models\Article;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class ArticleResource extends Resource
{
    protected static ?string $model          = Article::class;
    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationLabel = 'Articles / Catalogue';
    protected static ?string $modelLabel      = 'Article';
    protected static ?string $pluralModelLabel = 'Articles';
    protected static ?string $navigationGroup = 'Comptabilité Matières';
    protected static ?int    $navigationSort  = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('Identification')
                ->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Code')
                            ->default(fn() => Article::genererCode())
                            ->disabled()->dehydrated()
                            ->unique(ignoreRecord: true),

                        Forms\Components\ToggleButtons::make('type')
                            ->label('Type de bien')
                            ->options([
                                'durable'      => '🏷️ Durable',
                                'consomptible' => '📦 Consomptible',
                            ])
                            ->inline()->required()
                            ->colors(['durable' => 'primary', 'consomptible' => 'warning']),

                        Forms\Components\Toggle::make('actif')
                            ->label('Actif')->default(true)->inline(false),
                    ]),

                    Forms\Components\TextInput::make('designation')
                        ->label('Désignation')->required()->columnSpanFull(),

                    Forms\Components\Textarea::make('description')
                        ->label('Description')->rows(2)->columnSpanFull(),
                ])
                ->columns(1),

            Forms\Components\Section::make('Caractéristiques')
                ->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('unite_mesure')
                            ->label('Unité de mesure')
                            ->default('unité')->required(),

                        Forms\Components\Select::make('categorie')
                            ->label('Catégorie')
                            ->options([
                                'mobilier'         => 'Mobilier',
                                'informatique'     => 'Informatique',
                                'medical'          => 'Médical',
                                'fournitures'      => 'Fournitures bureau',
                                'vehicule'         => 'Véhicule',
                                'equipement'       => 'Équipement',
                                'consommables_it'  => 'Consommables IT',
                                'produits_entretien' => 'Produits entretien',
                                'autre'            => 'Autre',
                            ])
                            ->searchable(),

                        Forms\Components\TextInput::make('sous_categorie')
                            ->label('Sous-catégorie'),
                    ]),

                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('marque')
                            ->label('Marque'),

                        Forms\Components\TextInput::make('reference')
                            ->label('Référence fabricant'),

                        Forms\Components\TextInput::make('emplacement_magasin')
                            ->label('Emplacement magasin'),
                    ]),
                ]),

            Forms\Components\Section::make('Gestion de stock')
                ->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('prix_unitaire_moyen')
                            ->label('Prix unitaire moyen (FCFA)')
                            ->numeric()->default(0)->suffix('FCFA'),

                        Forms\Components\TextInput::make('seuil_alerte')
                            ->label('Seuil d\'alerte (stock min.)')
                            ->numeric()->default(0)
                            ->helperText('Alerte quand stock ≤ cette valeur'),

                        Forms\Components\TextInput::make('duree_vie_annees')
                            ->label('Durée de vie (années)')
                            ->numeric()->nullable()
                            ->visible(fn($get) => $get('type') === 'durable'),
                    ]),
                ]),

            Forms\Components\Section::make('Observations')
                ->schema([
                    Forms\Components\Textarea::make('observations')
                        ->label('Observations')->rows(2),
                ])
                ->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')->searchable()->sortable()
                    ->weight('bold')->copyable(),

                Tables\Columns\TextColumn::make('designation')
                    ->label('Désignation')->searchable()->limit(35)
                    ->tooltip(fn($record) => $record->designation),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Type')
                    ->colors(['primary' => 'durable', 'warning' => 'consomptible'])
                    ->formatStateUsing(fn($state) => $state === 'durable' ? 'Durable' : 'Consomptible'),

                Tables\Columns\TextColumn::make('categorie')
                    ->label('Catégorie')->badge()->color('gray'),

                Tables\Columns\TextColumn::make('unite_mesure')
                    ->label('Unité')->alignCenter(),

                Tables\Columns\TextColumn::make('stock.quantite_disponible')
                    ->label('En stock')
                    ->alignCenter()
                    ->badge()
                    ->color(fn($record) => $record->stock_critique ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('prix_unitaire_moyen')
                    ->label('PUM')->money('XAF')->alignEnd(),

                Tables\Columns\IconColumn::make('actif')
                    ->label('Actif')->boolean()->alignCenter(),
            ])
            ->defaultSort('designation')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(['durable' => 'Durable', 'consomptible' => 'Consomptible']),

                Tables\Filters\SelectFilter::make('categorie')
                    ->options([
                        'mobilier' => 'Mobilier',
                        'informatique' => 'Informatique',
                        'medical' => 'Médical',
                        'fournitures' => 'Fournitures bureau',
                    ]),

                Tables\Filters\TernaryFilter::make('actif')->label('Actif'),

                Tables\Filters\Filter::make('stock_critique')
                    ->label('⚠️ Stock critique')
                    ->query(fn($query) => $query->whereHas(
                        'stock',
                        fn($q) =>
                        $q->whereColumn('quantite_disponible', '<=', 'articles.seuil_alerte')
                    )),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('voir_stock')
                    ->label('Stock')
                    ->icon('heroicon-o-chart-bar')
                    ->color('info')
                    ->url(fn($record) => static::getUrl('view', ['record' => $record])),
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
            'index'  => Pages\ListArticles::route('/'),
            'create' => Pages\CreateArticle::route('/create'),
            'view'   => Pages\ViewArticle::route('/{record}'),
            'edit'   => Pages\EditArticle::route('/{record}/edit'),
        ];
    }
}
