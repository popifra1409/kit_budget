<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProgrammeResource\Pages;
use App\Models\Programme;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProgrammeResource extends Resource
{
    protected static ?string $model = Programme::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Programmes';

    protected static ?string $modelLabel = 'Programme';

    protected static ?string $pluralModelLabel = 'Programmes';

    protected static ?string $navigationGroup = 'Cadre Logique';

    protected static ?int $navigationSort = 1;

    /**
     * Permissions - Gestion quotidienne par OB/CS
     */
    public static function canViewAny(): bool
    {
        return auth()->check() ? auth()->user()->hasAnyRole([
            'super_admin',
            // 'operateur_budget',
            'chef_service_budget',
            'sous_directeur_budget',
            'directeur_general',
            // 'controleur_financier',
            // 'agence_comptable'
        ]) : false;
    }

    public static function canCreate(): bool
    {
        return auth()->check() ? auth()->user()->hasAnyRole([
            'super_admin',
            // 'operateur_budget',
            'chef_service_budget'
        ]) : false;
    }

    public static function canEdit($record): bool
    {
        return auth()->check() ? auth()->user()->hasAnyRole([
            'super_admin',
            // 'operateur_budget',
            'chef_service_budget'
        ]) : false;
    }

    public static function canDelete($record): bool
    {
        return auth()->check() ? auth()->user()->hasRole('super_admin') : false;
    }

    public static function canView($record): bool
    {
        return auth()->check() ? auth()->user()->hasAnyRole([
            'super_admin',
            // 'operateur_budget',
            'chef_service_budget',
            'sous_directeur_budget',
            'directeur_general',
            // 'controleur_financier',
            // 'agence_comptable'
        ]) : false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Hiérarchie')
                    ->description('Définir si c\'est un programme principal ou un sous-programme')
                    ->schema([
                        Forms\Components\Select::make('niveau')
                            ->label('Niveau')
                            ->options([
                                'programme' => 'Programme principal',
                                'sous_programme' => 'Sous-programme',
                            ])
                            ->required()
                            ->default('programme')
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                // Si programme principal, pas de parent
                                if ($state === 'programme') {
                                    $set('parent_id', null);
                                }
                            })
                            ->helperText('Un sous-programme doit être rattaché à un programme principal'),

                        Forms\Components\Select::make('parent_id')
                            ->label('Programme parent')
                            ->searchable()
                            ->preload()
                            ->placeholder('Aucun (Programme principal)')
                            ->disabled(fn(callable $get) => $get('niveau') === 'programme')
                            ->required(fn(callable $get) => $get('niveau') === 'sous_programme')
                            ->helperText('Sélectionner le programme principal pour ce sous-programme')
                            ->getSearchResultsUsing(function (string $search) {
                                return \App\Models\Programme::where('niveau', 'programme')
                                    ->where(function ($q) use ($search) {
                                        $q->where('libelle', 'like', "%{$search}%")
                                            ->orWhere('code', 'like', "%{$search}%");
                                    })
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn($item) => [
                                        $item->id => "{$item->code} - {$item->libelle}"
                                    ]);
                            })
                            ->getOptionLabelUsing(
                                fn($value): ?string =>
                                \App\Models\Programme::find($value)?->code . ' - ' .
                                    \App\Models\Programme::find($value)?->libelle
                            ),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(fn($record) => $record !== null && $record->niveau === 'programme'),

                Forms\Components\Section::make('Informations du Programme')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->placeholder('Ex: P413 ou P413-01'),

                        Forms\Components\TextInput::make('libelle')
                            ->label('Libellé')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->placeholder('Ex: PRISE EN CHARGE DES CAS'),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('annee')
                            ->label('Année budgétaire')
                            ->required()
                            ->numeric()
                            ->default(now()->year)
                            ->minValue(2020)
                            ->maxValue(2050),

                        Forms\Components\Toggle::make('actif')
                            ->label('Actif')
                            ->default(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Objectif Principal')
                    ->description('Définissez l\'objectif principal de ce programme')
                    ->schema([
                        Forms\Components\Repeater::make('objectifsPrincipaux')
                            ->relationship('objectifsPrincipaux')
                            ->label('')
                            ->schema([
                                Forms\Components\Textarea::make('libelle')
                                    ->label('Objectif principal')
                                    ->required()
                                    ->rows(2)
                                    ->placeholder('Ex: Assurer une prise en charge curative et préventive...'),

                                Forms\Components\TextInput::make('ordre')
                                    ->label('Ordre')
                                    ->numeric()
                                    ->default(0),
                            ])
                            ->columnSpanFull()
                            ->defaultItems(1)
                            ->addActionLabel('Ajouter un objectif principal')
                            ->collapsible()
                            ->itemLabel(fn(array $state): ?string => \Str::limit($state['libelle'] ?? 'Nouvel objectif', 50)),
                    ])
                    ->collapsible()
                    ->collapsed(fn($record) => $record !== null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('libelle')
                    ->label('Libellé')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->wrap(),

                Tables\Columns\BadgeColumn::make('niveau')
                    ->label('Niveau')
                    ->colors([
                        'primary' => 'programme',
                        'success' => 'sous_programme',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'programme' => 'Programme',
                        'sous_programme' => 'Sous-programme',
                        default => ucfirst($state),
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('parent.code')
                    ->label('Programme parent')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->placeholder('—')
                    ->formatStateUsing(
                        fn($record) =>
                        $record->parent
                            ? "{$record->parent->code} - " . \Str::limit($record->parent->libelle, 30)
                            : '—'
                    ),

                Tables\Columns\TextColumn::make('annee')
                    ->label('Année')
                    ->sortable(),

                Tables\Columns\IconColumn::make('actif')
                    ->label('Actif')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('niveau')
                    ->label('Niveau')
                    ->options([
                        'programme' => 'Programme principal',
                        'sous_programme' => 'Sous-programme',
                    ]),

                Tables\Filters\SelectFilter::make('parent_id')
                    ->label('Programme parent')
                    ->relationship('parent', 'libelle')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('annee')
                    ->label('Année')
                    ->options(fn() => range(2020, 2050)),

                Tables\Filters\TernaryFilter::make('actif')
                    ->label('Actif'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('code');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProgrammes::route('/'),
            'create' => Pages\CreateProgramme::route('/create'),
            'edit' => Pages\EditProgramme::route('/{record}/edit'),
            'generer-cadre-logique' => Pages\GenerationCadreLogique::route('/generer-cadre-logique'),
        ];
    }
}
