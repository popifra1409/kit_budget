<?php

namespace App\Filament\Marches\Resources;

use App\Filament\Marches\Resources\CommissionResource\Pages;
use App\Models\Commission;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Concerns\AutorisationParPermissions;

/**
 * Paramétrage : commissions des marchés et composition nominative.
 * Référentiel pérenne rattaché au département : un marché épinglera une commission,
 * il ne recréera pas ses membres.
 */
class CommissionResource extends Resource
{
    use AutorisationParPermissions;

    protected static ?string $model = Commission::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Paramétrage';
    protected static ?string $navigationLabel = 'Commissions';
    protected static ?string $modelLabel = 'commission';
    protected static ?string $pluralModelLabel = 'commissions';
    protected static ?string $recordTitleAttribute = 'libelle';
    protected static ?int $navigationSort = 5;

    protected static function prefixePermission(): string
    {
        return 'commission';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Commission')->schema([
                Forms\Components\TextInput::make('libelle')->label('Libellé')->required()->maxLength(255)
                    ->placeholder('Commission des marchés du département'),
                Forms\Components\TextInput::make('code')->label('Code')->required()->maxLength(50)
                    ->unique(ignoreRecord: true)->placeholder('cm_dpt_1'),
                Forms\Components\Select::make('service_id')->label('Département / service')
                    ->searchable()->preload()->relationship('service', 'nom')
                    ->helperText('Laisser vide pour une commission inter-services'),
                Forms\Components\Textarea::make('description')->rows(2)->columnSpanFull(),
            ])->columns(2),

            Forms\Components\Section::make('Composition')->schema([
                Forms\Components\Repeater::make('membres')
                    ->relationship()
                    ->schema([
                        Forms\Components\Select::make('user_id')->label('Utilisateur')
                            ->options(fn () => User::orderBy('name')->pluck('name', 'id'))
                            ->searchable()->nullable()->live()
                            ->helperText('Ou saisie directe ci-dessous pour un membre extérieur')
                            ->afterStateUpdated(function (?string $state, callable $set) {
                                if ($state && ($u = User::find($state))) {
                                    $set('nom', $u->name);
                                }
                            }),
                        Forms\Components\TextInput::make('nom')->label('Nom du membre')->required()->maxLength(255),
                        Forms\Components\Select::make('titre')->label('Titre du membre')->required()
                            ->options(config('marches.titres_membre'))->native(),
                        Forms\Components\TextInput::make('telephone')->label('N° téléphone')
                            ->maxLength(30)->tel(),
                    ])
                    ->columns(4)
                    ->orderColumn('ordre')
                    ->defaultItems(1)
                    ->addActionLabel('Ajouter un membre')
                    ->collapsible(),
            ])->description('Le titre fait autorité pour la fiche de présence et pour les signatures du PV.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('Code')->fontFamily('mono')->searchable(),
                Tables\Columns\TextColumn::make('libelle')->label('Commission')->weight('bold')->searchable(),
                Tables\Columns\TextColumn::make('service.nom')->label('Département')
                    ->placeholder('Non rattaché')->searchable(),
                Tables\Columns\TextColumn::make('membres_count')->counts('membres')->label('Membres')->sortable(),
                Tables\Columns\TextColumn::make('president.nom')->label('Président')
                    ->placeholder('Aucun président désigné'),
                Tables\Columns\IconColumn::make('actif')->label('Actif')->boolean(),
            ])
            // Le paramètre de la closure doit s'appeler $query : Filament résout ses
            // closures par nom, un autre nom lui fait injecter un Builder vide de modèle.
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['service', 'membres', 'president']))
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCommissions::route('/'),
            'create' => Pages\CreateCommission::route('/create'),
            'edit'   => Pages\EditCommission::route('/{record}/edit'),
        ];
    }
}
