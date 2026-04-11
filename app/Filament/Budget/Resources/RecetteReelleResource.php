<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\RecetteReelleResource\Pages;
use App\Models\RecetteReelle;
use App\Models\PrevisionRecette;
use App\Models\PrevisionRecetteMensuelle;
use App\Models\Exercice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RecetteReelleResource extends Resource
{
    protected static ?string $model = RecetteReelle::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Recettes Réelles';
    protected static ?string $modelLabel      = 'Recette Réelle';
    protected static ?string $pluralModelLabel = 'Recettes Réelles';
    protected static ?string $navigationGroup = 'Contrôle & Suivi';
    protected static ?int    $navigationSort  = 10;

    private static array $moisLabels = [
        1 => 'Jan',
        2 => 'Fév',
        3 => 'Mar',
        4 => 'Avr',
        5 => 'Mai',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Aoû',
        9 => 'Sep',
        10 => 'Oct',
        11 => 'Nov',
        12 => 'Déc',
    ];

    private static array $moisOptions = [
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
    ];

    public static function getNavigationBadge(): ?string
    {
        try {
            $exercice = Exercice::getActif();
            if (!$exercice) return null;
            $count = RecetteReelle::where('exercice_id', $exercice->id)
                ->where('statut', 'comptabilisee')
                ->count();
            return $count > 0 ? (string) $count : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    // =========================================================================
    // PERMISSIONS
    // =========================================================================
    public static function canViewAny(): bool
    {
        return auth()->check()
            && auth()->user()->can('view_any_recette_reelle');
    }

    public static function canView($record): bool
    {
        return auth()->check()
            && auth()->user()->can('view_recette_reelle');
    }

    public static function canCreate(): bool
    {
        return auth()->check()
            && auth()->user()->can('create_recette_reelle');
    }

    public static function canEdit($record): bool
    {
        if (!auth()->check()) return false;
        return auth()->user()->can('update_recette_reelle');
    }

    public static function canDelete($record): bool
    {
        if (!auth()->check()) return false;
        if (!auth()->user()->can('delete_recette_reelle')) return false;
        return $record->statut !== 'validee';
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['previsionRecetteMensuelle.lignePrevisionRecette'])
            ->orderByDesc('date_recette');

        $exerciceActif = Exercice::getActif();
        if ($exerciceActif) {
            $query->where('exercice_id', $exerciceActif->id);
        }

        return $query;
    }

    // =========================================================================
    // FORMULAIRE
    // =========================================================================
    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('Identification')
                ->schema([
                    Forms\Components\Select::make('exercice_id')
                        ->label('Exercice')
                        ->options(fn() => Exercice::orderByDesc('annee')->pluck('annee', 'id'))
                        ->default(fn() => Exercice::getActif()?->id)
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn($set) => $set('prevision_recette_mensuelle_id', null)),

                    Forms\Components\Select::make('mois')
                        ->label('Mois')
                        ->options(self::$moisOptions)
                        ->default(now()->month)
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn($set) => $set('prevision_recette_mensuelle_id', null)),

                    Forms\Components\DatePicker::make('date_recette')
                        ->label("Date d'encaissement")
                        ->default(now())
                        ->required(),
                ])
                ->columns(3),

            Forms\Components\Section::make('Ligne de nomenclature')
                ->schema([
                    Forms\Components\Select::make('prevision_recette_mensuelle_id')
                        ->label('Ligne de prévision (nomenclature + mois)')
                        ->options(function (Get $get) {
                            $exerciceId = $get('exercice_id');
                            $mois       = $get('mois');
                            if (!$exerciceId || !$mois) return [];

                            return PrevisionRecetteMensuelle::with('lignePrevisionRecette')
                                ->where('exercice_id', $exerciceId)
                                ->where('mois', $mois)
                                ->get()
                                ->mapWithKeys(fn($pm) => [
                                    $pm->id => "[{$pm->lignePrevisionRecette?->code_nomenclature}] {$pm->lignePrevisionRecette?->libelle_nomenclature}",
                                ]);
                        })
                        ->getSearchResultsUsing(function (string $search, Get $get) {
                            $exerciceId = $get('exercice_id');
                            $mois       = $get('mois');
                            if (!$exerciceId || !$mois) return [];

                            return PrevisionRecetteMensuelle::with('lignePrevisionRecette')
                                ->where('exercice_id', $exerciceId)
                                ->where('mois', $mois)
                                ->whereHas(
                                    'lignePrevisionRecette',
                                    fn($q) =>
                                    $q->where('libelle_nomenclature', 'ilike', "%{$search}%")
                                        ->orWhere('code_nomenclature', 'ilike', "%{$search}%")
                                )
                                ->get()
                                ->mapWithKeys(fn($pm) => [
                                    $pm->id => "[{$pm->lignePrevisionRecette?->code_nomenclature}] {$pm->lignePrevisionRecette?->libelle_nomenclature}",
                                ]);
                        })
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, $set) {
                            if (!$state) return;
                            $pm = PrevisionRecetteMensuelle::with('lignePrevisionRecette')->find($state);
                            if ($pm) {
                                $set('code_nomenclature', $pm->lignePrevisionRecette?->code_nomenclature);
                                $set('libelle', $pm->lignePrevisionRecette?->libelle_nomenclature);
                                $set('_montant_restant', max(0, (float)$pm->montant_prevu - (float)$pm->montant_recouvre));
                                $set('_montant_prevu', (float) $pm->montant_prevu);
                            }
                        })
                        ->helperText("Sélectionnez l'exercice et le mois d'abord"),

                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Placeholder::make('_montant_prevu')
                            ->label('Montant prévu du mois')
                            ->content(fn($get) => $get('_montant_prevu')
                                ? number_format((float) $get('_montant_prevu'), 0, ',', ' ') . ' FCFA'
                                : '—'),

                        Forms\Components\Placeholder::make('_montant_restant')
                            ->label('Restant à recouvrer')
                            ->content(fn($get) => $get('_montant_restant') !== null
                                ? number_format((float) $get('_montant_restant'), 0, ',', ' ') . ' FCFA'
                                : '—'),

                        Forms\Components\Hidden::make('code_nomenclature'),
                    ]),
                ]),

            Forms\Components\Section::make('Montant et paiement')
                ->schema([
                    Forms\Components\TextInput::make('montant')
                        ->label('Montant encaissé (FCFA)')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->prefix('FCFA'),

                    Forms\Components\TextInput::make('payeur')
                        ->label('Payeur / Source')
                        ->maxLength(255),

                    Forms\Components\Select::make('mode_paiement')
                        ->label('Mode de paiement')
                        ->options([
                            'virement' => 'Virement bancaire',
                            'cheque'   => 'Chèque',
                            'especes'  => 'Espèces',
                            'mobile'   => 'Mobile Money',
                            'autre'    => 'Autre',
                        ])
                        ->default('virement'),

                    Forms\Components\TextInput::make('reference_paiement')
                        ->label('Référence paiement')
                        ->maxLength(100),

                    Forms\Components\Select::make('statut')
                        ->label('Statut')
                        ->options([
                            'encaissee'     => 'Encaissée',
                            'comptabilisee' => 'Comptabilisée',
                            'validee'       => 'Validée',
                        ])
                        ->default('encaissee')
                        ->required(),

                    Forms\Components\Textarea::make('libelle')
                        ->label('Libellé / Objet')
                        ->rows(2),

                    Forms\Components\Textarea::make('observations')
                        ->label('Observations')
                        ->rows(2),
                ])
                ->columns(2),
        ]);
    }

    // =========================================================================
    // TABLE
    // =========================================================================
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('N°')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('date_recette')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),

                // ✅ Fix : utiliser ->state() au lieu de ->getStateUsing()
                // et protéger contre null
                Tables\Columns\TextColumn::make('mois')
                    ->label('Mois')
                    ->formatStateUsing(fn($state) => self::$moisLabels[(int) $state] ?? '—')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('code_nomenclature')
                    ->label('Code')
                    ->searchable()
                    ->badge()
                    ->color('warning'),

                // ✅ Fix : relation imbriquée — utiliser une closure sécurisée
                Tables\Columns\TextColumn::make('libelle_nomenclature')
                    ->label('Nomenclature')
                    ->getStateUsing(
                        fn($record) =>
                        $record->previsionRecetteMensuelle
                            ?->lignePrevisionRecette
                            ?->libelle_nomenclature ?? '—'
                    )
                    ->limit(35)
                    ->tooltip(
                        fn($record) =>
                        $record->previsionRecetteMensuelle
                            ?->lignePrevisionRecette
                            ?->libelle_nomenclature
                    ),

                Tables\Columns\TextColumn::make('montant')
                    ->label('Montant')
                    ->money('XAF')
                    ->sortable()
                    ->weight('bold')
                    ->color('success')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()->money('XAF')->label('Total'),
                    ]),

                Tables\Columns\TextColumn::make('payeur')
                    ->label('Payeur')
                    ->searchable()
                    ->limit(25)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('mode_paiement')
                    ->label('Mode')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('statut')
                    ->label('Statut')
                    ->badge()
                    ->color(fn(string $state) => match ($state) {
                        'encaissee'     => 'warning',
                        'comptabilisee' => 'info',
                        'validee'       => 'success',
                        default         => 'gray',
                    })
                    ->formatStateUsing(fn(string $state) => match ($state) {
                        'encaissee'     => 'Encaissée',
                        'comptabilisee' => 'Comptabilisée',
                        'validee'       => 'Validée',
                        default         => $state,
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('mois')
                    ->label('Mois')
                    ->options(self::$moisOptions),

                Tables\Filters\SelectFilter::make('statut')
                    ->label('Statut')
                    ->options([
                        'encaissee'     => 'Encaissée',
                        'comptabilisee' => 'Comptabilisée',
                        'validee'       => 'Validée',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn($record) => $record->statut !== 'validee'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    // ✅ Fix BulkAction : ne pas appeler ->where() sur la collection Filament
                    Tables\Actions\BulkAction::make('comptabiliser')
                        ->label('Comptabiliser la sélection')
                        ->icon('heroicon-o-check-circle')
                        ->color('info')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                if ($record->statut === 'encaissee') {
                                    $record->update(['statut' => 'comptabilisee']);
                                }
                            }
                        }),
                ]),
            ])
            ->defaultSort('date_recette', 'desc');
    }

    public static function getPages(): array
    {
        $pages = [
            'index'  => Pages\ListRecetteReelles::route('/'),
            'create' => Pages\CreateRecetteReelle::route('/create'),
            'edit'   => Pages\EditRecetteReelle::route('/{record}/edit'),
        ];

        if (class_exists(Pages\SuiviRecettes::class)) {
            $pages['suivi'] = Pages\SuiviRecettes::route('/suivi');
        }

        return $pages;
    }
}
