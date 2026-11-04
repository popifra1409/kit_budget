<?php

namespace App\Filament\Marches\Resources;

use App\Filament\Marches\Resources\ElementEvaluationDaoResource\Pages;
use App\Models\ElementEvaluationDao;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Exceptions\Halt;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use App\Filament\Concerns\AutorisationParPermissions;

/**
 * Paramétrage : étapes d'évaluation du DAO, regroupées par volet
 * (pièce administrative, pièce financière, description technique).
 */
class ElementEvaluationDaoResource extends Resource
{
    use AutorisationParPermissions;

    protected static ?string $model = ElementEvaluationDao::class;
    // `heroicon-o-check-list` n'existe pas dans le jeu blade-heroicons installé : le
    // menu latéral lève SvgNotFound, donc chaque page du panneau tombe avec lui.
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'Paramétrage';
    protected static ?string $navigationLabel = "Éléments d'évaluation du DAO";
    protected static ?string $modelLabel = "élément d'évaluation";
    protected static ?string $pluralModelLabel = "éléments d'évaluation du DAO";
    protected static ?string $recordTitleAttribute = 'libelle';
    protected static ?int $navigationSort = 4;

    protected static function prefixePermission(): string
    {
        return 'element_evaluation_dao';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make("Étape d'évaluation")->schema([
                Forms\Components\Select::make('famille')->label('Volet du DAO')->required()
                    ->options(config('marches.familles_evaluation'))->native(),
                Forms\Components\TextInput::make('libelle')->label('Étape')->required()->maxLength(255)
                    ->placeholder("Registre de commerce (RCCM)"),
                Forms\Components\TextInput::make('code')->label('Code')->required()->maxLength(50)
                    ->unique(ignoreRecord: true)->placeholder('admin_rccm'),
                Forms\Components\TextInput::make('bareme_max')->label('Note maximale')->numeric()
                    ->required()->default(20),
                Forms\Components\Textarea::make('description')->rows(2)->columnSpanFull()
                    ->helperText('Pièces attendues, points de contrôle...'),
                Forms\Components\TextInput::make('ordre')->numeric()->default(0)
                    ->helperText('Numéro de l\'étape dans son volet'),
                Forms\Components\Toggle::make('obligatoire')->default(true),
                Forms\Components\Toggle::make('actif')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('famille', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('famille')->label('Volet')
                    ->formatStateUsing(fn (?string $state) => config("marches.familles_evaluation.$state") ?? $state)
                    ->searchable()->sortable(),
                Tables\Columns\TextColumn::make('ordre')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('libelle')->label('Étape')->weight('bold')->searchable(),
                Tables\Columns\TextColumn::make('code')->label('Code')->fontFamily('mono')->searchable(),
                Tables\Columns\TextColumn::make('bareme_max')->label('Barème')->sortable(),
                Tables\Columns\IconColumn::make('obligatoire')->label('Obligatoire')->boolean(),
                Tables\Columns\IconColumn::make('actif')->label('Actif')->boolean(),
                // Une étape déjà notée ne se supprime plus (étape D) : le nombre de
                // notes se voit avant le clic, sinon la ligne verrouillée ne se
                // devine qu'à l'échec.
                Tables\Columns\TextColumn::make('evaluations_count')->label('Notes')
                    ->counts('evaluations')->badge()->color('gray')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('famille')->label('Volet')
                    ->options(config('marches.familles_evaluation')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->before(fn (ElementEvaluationDao $record) => static::refuserSuppression($record)),
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
     * Le verrou de l'étape D : une ligne du référentiel qui a servi de barème à des
     * notes ne se supprime pas, elle se désactive. Le modèle le refuse déjà (hook
     * deleting + clé étrangère restrictOnDelete) ; ce refus à l'écran existe pour
     * que l'administrateur lise pourquoi, et non une page d'exception.
     *
     * La suppression reste visible au lieu d'être masquée : c'est l'écran qui dit
     * ce que la base refuse, et la désactivation est atteignable d'ici.
     */
    public static function refuserSuppression(ElementEvaluationDao $record): void
    {
        $notes = $record->evaluations()->count();
        if ($notes === 0) {
            return;
        }

        Notification::make()
            ->title('❌ Suppression refusée')
            ->body(sprintf(
                '« %s » a reçu %s note(s) d\'évaluation : le rapport s\'appuie sur cette ligne. '
                    . 'Désactivez-la si elle ne doit plus être notée — les notes existantes restent au dossier.',
                $record->libelle,
                $notes,
            ))
            ->danger()->persistent()->send();

        throw new Halt;
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListElementsEvaluationDao::route('/'),
            'create' => Pages\CreateElementEvaluationDao::route('/create'),
            'edit'   => Pages\EditElementEvaluationDao::route('/{record}/edit'),
        ];
    }
}
