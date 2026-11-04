<?php

namespace App\Filament\Marches\Resources;

use App\Filament\Concerns\AutorisationParPermissions;
use App\Filament\Marches\Resources\TypeOrdreServiceResource\Pages;
use App\Models\TypeOrdreService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Exceptions\Halt;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

/**
 * Paramétrage : types d'ordre de service — la cinquième ligne du scan 1/4.
 *
 * Le scan la nommait parmi les référentiels à tenir (« Type de procédure · Contrat
 * du marché · Ordre de service · Composition de la Commission · Élément d'évaluation
 * du DAO ») ; elle restait à construire. Une ligne plate, comme les éléments
 * d'évaluation : un code, un libellé, un ordre, un drapeau actif — plus la case qui
 * porte la règle.
 *
 * `demarre_lexecution` est la seule colonne qui ait des conséquences : elle dit quels
 * types mettent le marché en cours d'exécution à l'émission. Elle se règle ici et
 * nulle part ailleurs, parce que c'est une règle de l'EP — le code, lui, ne devine
 * pas un libellé.
 */
class TypeOrdreServiceResource extends Resource
{
    use AutorisationParPermissions;

    protected static ?string $model = TypeOrdreService::class;
    // Même raison que pour les ordres de service : le pluriel de Filament écrirait
    // « type-ordre-services » dans l'URL et dans les noms de route.
    protected static ?string $slug = 'types-ordre-service';
    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-up';
    protected static ?string $navigationGroup = 'Paramétrage';
    protected static ?string $navigationLabel = "Types d'ordre de service";
    protected static ?string $modelLabel = "type d'ordre de service";
    protected static ?string $pluralModelLabel = "types d'ordre de service";
    protected static ?string $recordTitleAttribute = 'libelle';
    protected static ?int $navigationSort = 6;

    protected static function prefixePermission(): string
    {
        return 'type_ordre_service';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Type d\'ordre de service')->schema([
                Forms\Components\TextInput::make('code')->label('Code')->required()->maxLength(50)
                    ->unique(ignoreRecord: true)->placeholder('demarrage')
                    ->helperText('Identifiant technique, en majuscules ou non : ce que lit la base'),
                Forms\Components\TextInput::make('libelle')->label('Libellé')->required()->maxLength(255)
                    ->placeholder('Ordre de service de démarrage'),
                Forms\Components\Textarea::make('description')->rows(2)->columnSpanFull()
                    ->helperText('Ce que cet ordre de service commande, et dans quel cas on l\'émet'),
                Forms\Components\TextInput::make('ordre')->numeric()->default(0)
                    ->helperText('Numéro de la ligne dans la liste proposée à l\'émission'),

                // La case qui fait la règle : affichée avec sa conséquence écrite, pour
                // que l'agent sache ce qu'il est en train d'autoriser.
                Forms\Components\Toggle::make('demarre_lexecution')
                    ->label('Met le marché en cours d\'exécution')
                    ->default(false)->live()
                    ->helperText(fn ($state): string => (bool) $state
                        ? 'Émettre ce type sur un marché homologué le fait passer en « En cours d\'exécution ». Un marché ne reçoit qu\'un seul ordre de ce type.'
                        : 'Émettre ce type ne fait pas avancer le marché : il s\'ajoute à la chronologie du service (arrêt, reprise).')
                    ->onColor('success')->inlineLabel(false),

                Forms\Components\Toggle::make('actif')->default(true)
                    ->label('Proposé à l\'émission'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('ordre', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('ordre')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('libelle')->label('Libellé')->weight('bold')->searchable(),
                Tables\Columns\TextColumn::make('code')->label('Code')->fontFamily('mono')->searchable(),
                // Un type qui démarre le service n'est pas un type comme un autre :
                // la colonne se voit avant l'émission, pas après une décision refusée.
                Tables\Columns\IconColumn::make('demarre_lexecution')
                    ->label('Ouvre l\'exécution')->boolean()
                    ->trueIcon('heroicon-o-play')->falseIcon('heroicon-o-minus'),
                // `counts()` est écarté ici : Filament reconstruit l'agrégat depuis
                // `getRelated()->newQuery()`, ce qui perd le `withTrashed()` de la
                // relation. Un ordre retiré en douceur continuerait de retenir la
                // ligne par sa clé étrangère, alors que la colonne afficherait 0 —
                // un « 0 » qui promet une suppression que le modèle refuse.
                // Le référentiel tient en quelques lignes : ce calcul coûte rien, et
                // la liste est déjà triée par `ordre` — trier sur un nombre calculé
                // n'apporterait rien, et le SQL ne connaît pas cette colonne.
                Tables\Columns\TextColumn::make('ordres_service_count')->label('Émis')
                    ->getStateUsing(fn (TypeOrdreService $record) => $record->ordresService()->count())
                    ->badge()->color('gray'),
                Tables\Columns\IconColumn::make('actif')->label('Actif')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('demarre_lexecution')
                    ->label('Ouvre l\'exécution du marché'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->before(fn (TypeOrdreService $record) => static::refuserSuppression($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (Collection $records): void {
                            foreach ($records as $record) {
                                static::refuserSuppression($record);
                            }
                        }),
                ]),
            ]);
    }

    /**
     * Le verrou du référentiel qui a servi, même mécanisme que pour les éléments
     * d'évaluation du DAO : un type qui a été utilisé pour émettre est une ligne de
     * la trace d'exécution des marchés. Le modèle la refuse (hook deleting + clé
     * étrangère restrictOnDelete) ; ce refus à l'écran existe pour que l'agent lise
     * pourquoi, et non une page d'exception.
     */
    public static function refuserSuppression(TypeOrdreService $record): void
    {
        $emis = $record->ordresService()->count();
        if ($emis === 0) {
            return;
        }

        Notification::make()
            ->title('❌ Suppression refusée')
            ->body(sprintf(
                '« %s » a servi à émettre %s ordre(s) de service : la fiche d\'exécution s\'appuie sur '
                    . 'cette ligne. Désactivez-la si elle ne doit plus être proposée — les ordres déjà '
                    . 'émis restent au dossier.',
                $record->libelle,
                $emis,
            ))
            ->danger()->persistent()->send();

        throw new Halt;
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTypesOrdreService::route('/'),
            'create' => Pages\CreateTypeOrdreService::route('/create'),
            'edit'   => Pages\EditTypeOrdreService::route('/{record}/edit'),
        ];
    }
}
