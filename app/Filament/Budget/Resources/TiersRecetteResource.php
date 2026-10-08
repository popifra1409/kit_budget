<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\TiersRecetteResource\Pages;
use App\Models\RecetteReelle;
use App\Models\TiersRecette;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

/** Paramétrage : débiteurs / payeurs des recettes, avec leur reste à recouvrer. */
class TiersRecetteResource extends Resource
{
    protected static ?string $model = TiersRecette::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Paramétrage';
    protected static ?string $navigationLabel = 'Débiteurs / payeurs';
    protected static ?string $modelLabel = 'débiteur / payeur';
    protected static ?string $pluralModelLabel = 'débiteurs / payeurs';
    protected static ?int $navigationSort = 45;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_recette_reelle') ?? false;
    }
    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_recette_reelle') ?? false;
    }
    public static function canEdit($record): bool
    {
        return auth()->user()?->can('update_recette_reelle') ?? false;
    }

    /** Suppression seulement si aucune recette ne s'y rattache (sinon : désactiver). */
    public static function canDelete($record): bool
    {
        return static::canEdit($record) && !$record->recettes()->exists();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\TextInput::make('nom')->required()->maxLength(255)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn($rule) => $rule->whereNull('deleted_at')),
                Forms\Components\Select::make('categorie')->label('Catégorie')
                    ->options(TiersRecette::CATEGORIES)->default('autre')->required()->native(false),
                Forms\Components\TextInput::make('telephone')->label('Téléphone')->tel()->maxLength(50),
                Forms\Components\TextInput::make('email')->email()->maxLength(255),
                Forms\Components\TextInput::make('adresse')->maxLength(255)->columnSpanFull(),
                Forms\Components\Textarea::make('observations')->rows(2)->columnSpanFull(),
                Forms\Components\Toggle::make('actif')->default(true)
                    ->helperText('Un tiers inactif n\'est plus proposé à la saisie ; son historique est conservé.'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        $rar = RecetteReelle::sqlResteARecouvrer();

        return $table
            ->defaultSort('nom')
            ->modifyQueryUsing(fn($query) => $query
                ->withCount('recettes')
                ->addSelect([
                    'total_attendu'  => RecetteReelle::selectRaw('COALESCE(SUM(CAST(COALESCE(montant_constate, montant) AS FLOAT)), 0)')
                        ->whereColumn('tiers_recette_id', 'tiers_recettes.id')->where('statut', '!=', 'prevue'),
                    'total_encaisse' => RecetteReelle::selectRaw('COALESCE(SUM(CAST(montant AS FLOAT)), 0)')
                        ->whereColumn('tiers_recette_id', 'tiers_recettes.id')->where('statut', '!=', 'prevue'),
                    'total_rar'      => RecetteReelle::selectRaw("COALESCE(SUM({$rar}), 0)")
                        ->whereColumn('tiers_recette_id', 'tiers_recettes.id')->where('statut', '!=', 'prevue'),
                ]))
            ->columns([
                Tables\Columns\TextColumn::make('nom')->weight('bold')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('categorie')->label('Catégorie')->badge()
                    ->formatStateUsing(fn($state) => TiersRecette::CATEGORIES[$state] ?? $state),
                Tables\Columns\TextColumn::make('recettes_count')->label('Recettes')->sortable(),
                Tables\Columns\TextColumn::make('total_attendu')->label('Attendu')->numeric(0)->sortable(),
                Tables\Columns\TextColumn::make('total_encaisse')->label('Encaissé')->numeric(0)->sortable()->color('success'),
                Tables\Columns\TextColumn::make('total_rar')->label('Reste à recouvrer')->numeric(0)->sortable()
                    ->color(fn($state) => (float) $state > 0 ? 'warning' : 'gray')->weight('bold'),
                Tables\Columns\IconColumn::make('actif')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('categorie')->label('Catégorie')->options(TiersRecette::CATEGORIES),
                Tables\Filters\TernaryFilter::make('actif'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->visible(fn($record) => static::canDelete($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTiersRecettes::route('/'),
            'create' => Pages\CreateTiersRecette::route('/create'),
            'edit'   => Pages\EditTiersRecette::route('/{record}/edit'),
        ];
    }
}
