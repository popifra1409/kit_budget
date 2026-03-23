<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\RecetteReelleResource\Pages;
use App\Models\RecetteReelle;
use App\Models\PrevisionRecetteMensuelle;
use App\Models\Exercice;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;

class RecetteReelleResource extends Resource
{
    protected static ?string $model = RecetteReelle::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Gestion Budgétaire';
    protected static ?int $navigationSort = 2;

    /**
     * Permissions - Recettes réelles
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_recette_reelle') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->can('view_recette_reelle') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_recette_reelle') ?? false;
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        if ($user->can('update_recette_reelle')) {
            // Ici tu peux ajouter une logique métier si nécessaire
            return true;
        }

        return false;
    }

    public static function canDelete($record): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        return $user->can('delete_recette_reelle');
    }


    // ====================================
    // FORMULAIRE
    // ====================================
    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('prevision_recette_mensuelle_id')
                    ->label('Prévision Mensuelle')
                    ->options(function () {
                        $exerciceActif = Exercice::getActif();
                        if (!$exerciceActif) return [];
                        return PrevisionRecetteMensuelle::query()
                            ->where('actif', true)
                            ->where('annee', $exerciceActif->annee)
                            ->with('lignePrevisionRecette')
                            ->get()
                            ->mapWithKeys(function ($prevision) {
                                $ligne = $prevision->lignePrevisionRecette;
                                $label = $prevision->periode
                                    . ' - '
                                    . ($ligne?->code_nomenclature ?? '')
                                    . ' '
                                    . ($ligne?->libelle_nomenclature ?? '');
                                return [$prevision->id => $label];
                            })
                            ->toArray();
                    })
                    ->searchable()
                    ->required(),

                Forms\Components\TextInput::make('libelle')
                    ->label('Libellé')
                    ->required(),

                Forms\Components\TextInput::make('montant')
                    ->label('Montant')
                    ->numeric()
                    ->required()
                    ->suffix('FCFA'),

                Forms\Components\DatePicker::make('date_recette')
                    ->label('Date Encaissement')
                    ->required()
                    ->default(now()),

                Forms\Components\TextInput::make('payeur')
                    ->label('Nom du Payeur')
                    ->required(),

                Forms\Components\Select::make('mode_paiement')
                    ->label('Mode de Paiement')
                    ->options([
                        'Espèces' => 'Espèces',
                        'Chèque' => 'Chèque',
                        'Virement' => 'Virement Bancaire',
                        'Carte' => 'Carte Bancaire',
                        'Mobile Money' => 'Mobile Money',
                    ])
                    ->required(),
            ]);
    }

    // ====================================
    // TABLEAU
    // ====================================
    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')->label('N°')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('previsionRecetteMensuelle.periode')->label('Période')->sortable(),
                Tables\Columns\TextColumn::make('previsionRecetteMensuelle.lignePrevisionRecette.code_nomenclature')
                    ->label('Code Nomenclature')
                    ->sortable(),
                Tables\Columns\TextColumn::make('previsionRecetteMensuelle.lignePrevisionRecette.libelle_nomenclature')
                    ->label('Nomenclature')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('libelle')->label('Libellé')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('montant')->money('XAF', locale: 'fr')->label('Montant')->sortable(),
                Tables\Columns\TextColumn::make('payeur')->label('Payeur')->sortable()->searchable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('annee')
                    ->label('Année')
                    ->options(fn() => PrevisionRecetteMensuelle::query()
                        ->distinct('annee')
                        ->pluck('annee', 'annee')
                        ->toArray()),
                Tables\Filters\SelectFilter::make('mois')
                    ->label('Mois')
                    ->options([
                        1 => 'Janvier',
                        2 => 'Février',
                        3 => 'Mars',
                        4 => 'Avril',
                        5 => 'Mai',
                        6 => 'Juin',
                        7 => 'Juillet',
                        8 => 'Août',
                        9 => 'Septembre',
                        10 => 'Octobre',
                        11 => 'Novembre',
                        12 => 'Décembre',
                    ]),
                Tables\Filters\SelectFilter::make('code_nomenclature')
                    ->label('Nomenclature')
                    ->options(fn() => \App\Models\LignePrevisionRecette::pluck('libelle_nomenclature', 'code_nomenclature')->toArray()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\ViewAction::make()->label('Voir'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('date_recette', 'desc');
    }

    // ====================================
    // PAGES
    // ====================================
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRecetteReelles::route('/'),
            'create' => Pages\CreateRecetteReelle::route('/create'),
            'edit' => Pages\EditRecetteReelle::route('/{record}/edit'),
        ];
    }
}
