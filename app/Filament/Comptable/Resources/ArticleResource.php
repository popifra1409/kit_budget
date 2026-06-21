<?php

namespace App\Filament\Comptable\Resources;

use App\Filament\Comptable\Resources\ArticleResource\Pages;
use App\Models\Article;
use App\Models\UniteMesure;
use App\Models\Conditionnement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
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

                        // ✅ Unité de mesure — désormais via paramétrage
                        Forms\Components\Select::make('unite_mesure_id')
                            ->label('Unité de mesure')
                            ->options(fn() => UniteMesure::actif()->pluck('libelle', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('libelle')
                                    ->label('Libellé')->required()
                                    ->placeholder('Ex: Boîte, Plaquette'),
                                Forms\Components\TextInput::make('symbole')
                                    ->label('Symbole')
                                    ->placeholder('Ex: bte, plq'),
                            ])
                            ->createOptionUsing(fn(array $data) => UniteMesure::create($data)->id),

                        // ✅ "Pharmacie" ajouté à la liste des catégories
                        Forms\Components\Select::make('categorie_id')
                            ->label('Catégorie')
                            ->options(fn() => \App\Models\CategorieArticle::actif()->pluck('libelle', 'id'))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('libelle')
                                    ->label('Libellé')->required(),
                                Forms\Components\Toggle::make('est_pharmacie')
                                    ->label('Nécessite un conditionnement'),
                            ])
                            ->createOptionUsing(fn(array $data) => \App\Models\CategorieArticle::create($data)->id),

                        Forms\Components\TextInput::make('marque')
                            ->label('Marque'),
                    ]),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('reference_fournisseur')
                            ->label('Référence fournisseur'),

                        Forms\Components\Select::make('fournisseur_id')
                            ->label('Fournisseur habituel')
                            ->relationship('fournisseur', 'raison_sociale')
                            ->searchable()
                            ->preload(),
                    ]),

                    // ✅ Conditionnement — uniquement si catégorie = Pharmacie
                    Forms\Components\Select::make('conditionnement_id')
                        ->label('Conditionnement par défaut')
                        ->options(fn() => Conditionnement::actif()->pluck('libelle', 'id'))
                        ->searchable()
                        ->visible(fn(Get $get) => static::categorieRequiertConditionnement($get('categorie_id')))
                        ->required(fn(Get $get) => static::categorieRequiertConditionnement($get('categorie_id')))
                        ->createOptionForm([
                            Forms\Components\TextInput::make('libelle')
                                ->label('Libellé')->required()
                                ->placeholder('Ex: Plaquette de 10 comprimés'),
                        ])
                        ->createOptionUsing(fn(array $data) => Conditionnement::create($data)->id)
                        ->helperText('Pourra être surchargé ligne par ligne lors d\'une expression de besoin')
                        ->columnSpanFull(),
                ])
                ->columns(1),

            Forms\Components\Section::make('Gestion de stock')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('prix_unitaire_moyen')
                            ->label('Prix unitaire moyen (FCFA)')
                            ->numeric()->default(0)->suffix('FCFA'),

                        Forms\Components\TextInput::make('seuil_alerte')
                            ->label('Seuil d\'alerte (stock min.)')
                            ->numeric()->default(0)
                            ->helperText('Alerte quand stock ≤ cette valeur'),
                    ]),
                ]),
        ]);
    }

    // ✅ Maintient la colonne legacy 'unite_mesure' (string) synchronisée
    // tant qu'elle n'est pas supprimée de la table, pour éviter tout
    // souci de contrainte NOT NULL ou d'affichage ailleurs dans l'app.
    public static function mutateFormDataBeforeCreate(array $data): array
    {
        return static::syncLegacyUniteMesure($data);
    }

    public static function mutateFormDataBeforeSave(array $data): array
    {
        return static::syncLegacyUniteMesure($data);
    }

    protected static function syncLegacyUniteMesure(array $data): array
    {
        if (!empty($data['unite_mesure_id'])) {
            $unite = UniteMesure::find($data['unite_mesure_id']);
            $data['unite_mesure'] = $unite?->libelle;
        }

        if (!empty($data['categorie_id'])) {
            $cat = \App\Models\CategorieArticle::find($data['categorie_id']);
            $data['categorie'] = $cat?->libelle;
        }

        return $data;
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

                Tables\Columns\TextColumn::make('categorieArticle.libelle')
                    ->label('Catégorie')
                    ->badge()
                    ->color(fn($record) => $record->categorieArticle?->est_pharmacie ? 'success' : 'gray')
                    ->formatStateUsing(
                        fn($state, $record) =>
                        $record->categorieArticle?->est_pharmacie ? "💊 {$state}" : ($state ?? '—')
                    ),

                Tables\Columns\TextColumn::make('uniteMesure.libelle')
                    ->label('Unité')->alignCenter()->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('conditionnement.libelle')
                    ->label('Conditionnement')->limit(25)->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

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
                Tables\Filters\SelectFilter::make('categorie_id')
                    ->label('Catégorie')
                    ->relationship('categorieArticle', 'libelle')
                    ->searchable()
                    ->preload(),
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

    protected static function categorieRequiertConditionnement(?int $categorieId): bool
    {
        if (!$categorieId) return false;
        return (bool) \App\Models\CategorieArticle::find($categorieId)?->est_pharmacie;
    }
}
