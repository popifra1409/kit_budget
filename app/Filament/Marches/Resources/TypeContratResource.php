<?php

namespace App\Filament\Marches\Resources;

use App\Filament\Marches\Resources\TypeContratResource\Pages;
use App\Models\TypeContrat;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Concerns\AutorisationParPermissions;

/**
 * Paramétrage : seuils montant → type de contrat (marché / lettre de commande).
 * L'ordre des lignes fait autorité : la première dont le test réussit est retenue.
 */
class TypeContratResource extends Resource
{
    use AutorisationParPermissions;

    protected static ?string $model = TypeContrat::class;
    protected static ?string $navigationIcon = 'heroicon-o-scale';
    protected static ?string $navigationGroup = 'Paramétrage';
    protected static ?string $navigationLabel = 'Types de contrat et seuils';
    protected static ?string $modelLabel = 'type de contrat';
    protected static ?string $pluralModelLabel = 'types de contrat';
    protected static ?string $recordTitleAttribute = 'libelle';
    protected static ?int $navigationSort = 2;

    protected static function prefixePermission(): string
    {
        return 'type_contrat';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Contrat et seuil')->schema([
                Forms\Components\TextInput::make('libelle')->label('Contrat')->required()->maxLength(255)
                    ->placeholder('Marché'),
                Forms\Components\TextInput::make('code')->label('Code')->required()->maxLength(50)
                    ->unique(ignoreRecord: true)->placeholder('marche'),
                Forms\Components\TextInput::make('seuil_min')->label('Seuil (montant)')->numeric()
                    ->required()->default(0)->suffix('FCFA'),
                Forms\Components\Select::make('operateur')->label('Le montant est…')->required()
                    ->options(config('marches.operateurs_seuil'))->native()
                    ->helperText('Toutes les lignes sont testées dans l\'ordre : la première qui réussit donne le contrat'),
                Forms\Components\Textarea::make('description')->rows(2)->columnSpanFull(),
                Forms\Components\TextInput::make('ordre')->numeric()->default(0),
                Forms\Components\Toggle::make('actif')->default(true),
            ])->columns(2),

            Forms\Components\Section::make('Tester la règle')->schema([
                Forms\Components\TextInput::make('montant_test')
                    ->label('Montant simulé')->numeric()->suffix('FCFA')->live()->dehydrated(false)
                    ->placeholder('Ex. 50000000'),
                Forms\Components\Placeholder::make('simulation')
                    ->label('Contrat obtenu')
                    ->content(function (callable $get): string {
                        $montant = $get('montant_test');
                        if ($montant === null || $montant === '') {
                            return 'Saisir un montant pour tester la règle.';
                        }

                        $obtenu = TypeContrat::pourMontant((float) $montant);

                        return $obtenu
                            ? number_format((float) $montant, 0, ',', ' ') . ' FCFA → ' . $obtenu->libelle
                            : 'Aucun type de contrat actif ne couvre ce montant — vérifier les seuils et l\'ordre.';
                    }),
            ])->columns(2)
              ->description('Test sur le paramétrage déjà enregistré, pas sur les modifications non enregistrées de ce formulaire.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('ordre')
            ->columns([
                Tables\Columns\TextColumn::make('ordre')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('libelle')->label('Contrat')->weight('bold')->searchable(),
                Tables\Columns\TextColumn::make('code')->label('Code')->fontFamily('mono')->searchable(),
                Tables\Columns\TextColumn::make('seuil_min')->label('Seuil')->money('XAF')->sortable(),
                Tables\Columns\TextColumn::make('operateur')->label('Test')
                    ->formatStateUsing(fn (?string $state) => $state === 'lt' ? '< seuil' : '≥ seuil'),
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
            'index'  => Pages\ListTypeContrats::route('/'),
            'create' => Pages\CreateTypeContrat::route('/create'),
            'edit'   => Pages\EditTypeContrat::route('/{record}/edit'),
        ];
    }
}
