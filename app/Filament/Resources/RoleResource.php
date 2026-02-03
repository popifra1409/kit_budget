<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationLabel = 'Rôles';
    protected static ?string $modelLabel = 'Rôle';
    protected static ?string $pluralModelLabel = 'Rôles';
    protected static ?string $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 1;

    /**
     * ✅ Badge dans la navigation - Nombre de rôles
     */
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    /**
     * ✅ Couleur du badge (optionnel)
     */
    public static function getNavigationBadgeColor(): ?string
    {
        return 'success'; // Options: primary, success, warning, danger, info, gray
    }

    /**
     * ✅ Tooltip du badge (optionnel)
     */
    public static function getNavigationBadgeTooltip(): ?string
    {
        $count = static::getModel()::count();
        return $count > 1 ? "{$count} rôles créés" : "{$count} rôle créé";
    }

    /**
     * Permissions - Gestion des rôles
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_role') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->can('view_role') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_role') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('update_role') ?? false;
    }

    public static function canDelete($record): bool
    {
        if (!auth()->user()?->can('delete_role')) {
            return false;
        }
        return $record->users()->count() === 0;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informations du rôle')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nom du rôle')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->placeholder('Ex: operateur_budget')
                            ->helperText('Nom technique du rôle (sans espaces)'),

                        Forms\Components\TextInput::make('niveau_hierarchique')
                            ->label('Niveau hiérarchique')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(100)
                            ->helperText('0 = plus bas niveau, 100 = plus haut niveau')
                            ->suffix('/100'),

                        Forms\Components\Placeholder::make('niveau_info')
                            ->label('Guide des niveaux')
                            ->content(function () {
                                return "• 10: Opérateurs (budget, recette)\n" .
                                    "• 30: Chefs de service\n" .
                                    "• 50: Sous-directeurs\n" .
                                    "• 60: Contrôleur financier, Agent comptable\n" .
                                    "• 70: DAAF\n" .
                                    "• 90: Directeur\n" .
                                    "• 100: Super administrateur";
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                // ✅ NOUVELLE INTERFACE : Permissions organisées par ressources avec recherche
                Forms\Components\Section::make('Gestion des Permissions')
                    ->description('Sélectionnez les permissions à attribuer à ce rôle')
                    ->schema([
                        // Onglets par ressource
                        Forms\Components\Tabs::make('permissions_tabs')
                            ->tabs([
                                // Onglet "Toutes les permissions" avec recherche
                                Forms\Components\Tabs\Tab::make('Toutes')
                                    ->icon('heroicon-o-list-bullet')
                                    ->schema([
                                        Forms\Components\CheckboxList::make('permissions')
                                            ->label('Rechercher et sélectionner')
                                            ->relationship('permissions', 'name')
                                            ->options(Permission::all()->pluck('name', 'id'))
                                            ->descriptions(function () {
                                                return Permission::all()->mapWithKeys(function ($perm) {
                                                    return [$perm->id => self::getPermissionDescription($perm->name)];
                                                });
                                            })
                                            ->columns(3)
                                            ->searchable()
                                            ->bulkToggleable()
                                            ->gridDirection('row'),
                                    ]),

                                // Onglet Budget
                                Forms\Components\Tabs\Tab::make('Budget')
                                    ->icon('heroicon-o-currency-dollar')
                                    ->badge(fn() => Permission::where('name', 'like', '%budget%')->count())
                                    ->schema([
                                        Forms\Components\CheckboxList::make('permissions')
                                            ->label('')
                                            ->relationship('permissions', 'name')
                                            ->options(
                                                Permission::where('name', 'like', '%budget%')
                                                    ->get()
                                                    ->pluck('name', 'id')
                                            )
                                            ->descriptions(function () {
                                                return Permission::where('name', 'like', '%budget%')
                                                    ->get()
                                                    ->mapWithKeys(function ($perm) {
                                                        return [$perm->id => self::getPermissionDescription($perm->name)];
                                                    });
                                            })
                                            ->columns(2)
                                            ->bulkToggleable(),
                                    ]),

                                // Onglet Bon de Commande
                                Forms\Components\Tabs\Tab::make('Bon de Commande')
                                    ->icon('heroicon-o-shopping-cart')
                                    ->badge(fn() => Permission::where('name', 'like', '%bon_commande%')->count())
                                    ->schema([
                                        Forms\Components\CheckboxList::make('permissions')
                                            ->label('')
                                            ->relationship('permissions', 'name')
                                            ->options(
                                                Permission::where('name', 'like', '%bon_commande%')
                                                    ->get()
                                                    ->pluck('name', 'id')
                                            )
                                            ->descriptions(function () {
                                                return Permission::where('name', 'like', '%bon_commande%')
                                                    ->get()
                                                    ->mapWithKeys(function ($perm) {
                                                        return [$perm->id => self::getPermissionDescription($perm->name)];
                                                    });
                                            })
                                            ->columns(2)
                                            ->bulkToggleable(),
                                    ]),

                                // Onglet Engagement
                                Forms\Components\Tabs\Tab::make('Engagement')
                                    ->icon('heroicon-o-document-check')
                                    ->badge(fn() => Permission::where('name', 'like', '%engagement%')->count())
                                    ->schema([
                                        Forms\Components\CheckboxList::make('permissions')
                                            ->label('')
                                            ->relationship('permissions', 'name')
                                            ->options(
                                                Permission::where('name', 'like', '%engagement%')
                                                    ->get()
                                                    ->pluck('name', 'id')
                                            )
                                            ->descriptions(function () {
                                                return Permission::where('name', 'like', '%engagement%')
                                                    ->get()
                                                    ->mapWithKeys(function ($perm) {
                                                        return [$perm->id => self::getPermissionDescription($perm->name)];
                                                    });
                                            })
                                            ->columns(2)
                                            ->bulkToggleable(),
                                    ]),

                                // Onglet Ordonnance de Paiement
                                Forms\Components\Tabs\Tab::make('Ordonnance de Paiement')
                                    ->icon('heroicon-o-banknotes')
                                    ->badge(fn() => Permission::where('name', 'like', '%ordonnance_paiement%')->count())
                                    ->schema([
                                        Forms\Components\CheckboxList::make('permissions')
                                            ->label('')
                                            ->relationship('permissions', 'name')
                                            ->options(
                                                Permission::where('name', 'like', '%ordonnance_paiement%')
                                                    ->get()
                                                    ->pluck('name', 'id')
                                            )
                                            ->descriptions(function () {
                                                return Permission::where('name', 'like', '%ordonnance_paiement%')
                                                    ->get()
                                                    ->mapWithKeys(function ($perm) {
                                                        return [$perm->id => self::getPermissionDescription($perm->name)];
                                                    });
                                            })
                                            ->columns(2)
                                            ->bulkToggleable(),
                                    ]),

                                // Onglet Fournisseur
                                Forms\Components\Tabs\Tab::make('Fournisseur')
                                    ->icon('heroicon-o-building-storefront')
                                    ->badge(fn() => Permission::where('name', 'like', '%fournisseur%')->count())
                                    ->schema([
                                        Forms\Components\CheckboxList::make('permissions')
                                            ->label('')
                                            ->relationship('permissions', 'name')
                                            ->options(
                                                Permission::where('name', 'like', '%fournisseur%')
                                                    ->get()
                                                    ->pluck('name', 'id')
                                            )
                                            ->descriptions(function () {
                                                return Permission::where('name', 'like', '%fournisseur%')
                                                    ->get()
                                                    ->mapWithKeys(function ($perm) {
                                                        return [$perm->id => self::getPermissionDescription($perm->name)];
                                                    });
                                            })
                                            ->columns(2)
                                            ->bulkToggleable(),
                                    ]),

                                // Onglet Utilisateurs
                                Forms\Components\Tabs\Tab::make('Utilisateurs')
                                    ->icon('heroicon-o-users')
                                    ->badge(fn() => Permission::where('name', 'like', '%user%')->count())
                                    ->schema([
                                        Forms\Components\CheckboxList::make('permissions')
                                            ->label('')
                                            ->relationship('permissions', 'name')
                                            ->options(
                                                Permission::where('name', 'like', '%user%')
                                                    ->get()
                                                    ->pluck('name', 'id')
                                            )
                                            ->descriptions(function () {
                                                return Permission::where('name', 'like', '%user%')
                                                    ->get()
                                                    ->mapWithKeys(function ($perm) {
                                                        return [$perm->id => self::getPermissionDescription($perm->name)];
                                                    });
                                            })
                                            ->columns(2)
                                            ->bulkToggleable(),
                                    ]),

                                // Onglet Rôles
                                Forms\Components\Tabs\Tab::make('Rôles')
                                    ->icon('heroicon-o-shield-check')
                                    ->badge(fn() => Permission::where('name', 'like', '%role%')->count())
                                    ->schema([
                                        Forms\Components\CheckboxList::make('permissions')
                                            ->label('')
                                            ->relationship('permissions', 'name')
                                            ->options(
                                                Permission::where('name', 'like', '%role%')
                                                    ->get()
                                                    ->pluck('name', 'id')
                                            )
                                            ->descriptions(function () {
                                                return Permission::where('name', 'like', '%role%')
                                                    ->get()
                                                    ->mapWithKeys(function ($perm) {
                                                        return [$perm->id => self::getPermissionDescription($perm->name)];
                                                    });
                                            })
                                            ->columns(2)
                                            ->bulkToggleable(),
                                    ]),

                                // Onglet Autres permissions
                                Forms\Components\Tabs\Tab::make('Autres')
                                    ->icon('heroicon-o-ellipsis-horizontal-circle')
                                    ->badge(function () {
                                        return Permission::whereNotIn('name', function ($query) {
                                            $query->select('name')
                                                ->from('permissions')
                                                ->where(function ($q) {
                                                    $q->where('name', 'like', '%budget%')
                                                        ->orWhere('name', 'like', '%bon_commande%')
                                                        ->orWhere('name', 'like', '%engagement%')
                                                        ->orWhere('name', 'like', '%ordonnance_paiement%')
                                                        ->orWhere('name', 'like', '%fournisseur%')
                                                        ->orWhere('name', 'like', '%user%')
                                                        ->orWhere('name', 'like', '%role%');
                                                });
                                        })->count();
                                    })
                                    ->schema([
                                        Forms\Components\CheckboxList::make('permissions')
                                            ->label('')
                                            ->relationship('permissions', 'name')
                                            ->options(
                                                Permission::whereNotIn('name', function ($query) {
                                                    $query->select('name')
                                                        ->from('permissions')
                                                        ->where(function ($q) {
                                                            $q->where('name', 'like', '%budget%')
                                                                ->orWhere('name', 'like', '%bon_commande%')
                                                                ->orWhere('name', 'like', '%engagement%')
                                                                ->orWhere('name', 'like', '%ordonnance_paiement%')
                                                                ->orWhere('name', 'like', '%fournisseur%')
                                                                ->orWhere('name', 'like', '%user%')
                                                                ->orWhere('name', 'like', '%role%');
                                                        });
                                                })->get()->pluck('name', 'id')
                                            )
                                            ->descriptions(function () {
                                                return Permission::whereNotIn('name', function ($query) {
                                                    $query->select('name')
                                                        ->from('permissions')
                                                        ->where(function ($q) {
                                                            $q->where('name', 'like', '%budget%')
                                                                ->orWhere('name', 'like', '%bon_commande%')
                                                                ->orWhere('name', 'like', '%engagement%')
                                                                ->orWhere('name', 'like', '%ordonnance_paiement%')
                                                                ->orWhere('name', 'like', '%fournisseur%')
                                                                ->orWhere('name', 'like', '%user%')
                                                                ->orWhere('name', 'like', '%role%');
                                                        });
                                                })->get()->mapWithKeys(function ($perm) {
                                                    return [$perm->id => self::getPermissionDescription($perm->name)];
                                                });
                                            })
                                            ->columns(2)
                                            ->bulkToggleable(),
                                    ]),
                            ])
                            ->columnSpanFull()
                            ->persistTabInQueryString(),
                    ])
                    ->collapsible()
                    ->collapsed(false),
            ]);
    }

    /**
     * ✅ Descriptions des permissions
     */
    protected static function getPermissionDescription(string $permissionName): string
    {
        $descriptions = [
            // Budget
            'view_any_budget' => '📋 Voir la liste des budgets',
            'view_budget' => '👁️ Voir le détail d\'un budget',
            'create_budget' => '➕ Créer un nouveau budget',
            'update_budget' => '✏️ Modifier un budget',
            'delete_budget' => '🗑️ Supprimer un budget',

            // Bon de commande
            'view_any_bon_commande' => '📋 Voir la liste des bons de commande',
            'view_bon_commande' => '👁️ Voir le détail d\'un bon de commande',
            'create_bon_commande' => '➕ Créer un bon de commande',
            'update_bon_commande' => '✏️ Modifier un bon de commande',
            'delete_bon_commande' => '🗑️ Supprimer un bon de commande',
            'engage_bon_commande' => '✅ Engager un bon de commande',

            // Engagement
            'view_any_engagement' => '📋 Voir la liste des engagements',
            'view_engagement' => '👁️ Voir le détail d\'un engagement',
            'create_engagement' => '➕ Créer un engagement',
            'update_engagement' => '✏️ Modifier un engagement',
            'delete_engagement' => '🗑️ Supprimer un engagement',
            'valider_engagement' => '✅ Valider un engagement',

            // Ordonnance de paiement
            'view_any_ordonnance_paiement' => '📋 Voir la liste des OP',
            'view_ordonnance_paiement' => '👁️ Voir le détail d\'une OP',
            'create_ordonnance_paiement' => '➕ Créer une OP',
            'update_ordonnance_paiement' => '✏️ Modifier une OP',
            'delete_ordonnance_paiement' => '🗑️ Supprimer une OP',
            'emettre_ordonnance_paiement' => '📤 Émettre une OP',
            'viser_ordonnance_paiement' => '✔️ Viser une OP',
            'payer_ordonnance_paiement' => '💰 Marquer une OP comme payée',

            // Fournisseurs
            'view_any_fournisseur' => '📋 Voir la liste des fournisseurs',
            'view_fournisseur' => '👁️ Voir le détail d\'un fournisseur',
            'create_fournisseur' => '➕ Créer un fournisseur',
            'update_fournisseur' => '✏️ Modifier un fournisseur',
            'delete_fournisseur' => '🗑️ Supprimer un fournisseur',

            // Utilisateurs
            'view_any_user' => '📋 Voir la liste des utilisateurs',
            'view_user' => '👁️ Voir le détail d\'un utilisateur',
            'create_user' => '➕ Créer un utilisateur',
            'update_user' => '✏️ Modifier un utilisateur',
            'delete_user' => '🗑️ Supprimer un utilisateur',

            // Rôles
            'view_any_role' => '📋 Voir la liste des rôles',
            'view_role' => '👁️ Voir le détail d\'un rôle',
            'create_role' => '➕ Créer un rôle',
            'update_role' => '✏️ Modifier un rôle',
            'delete_role' => '🗑️ Supprimer un rôle',
        ];

        return $descriptions[$permissionName] ?? ucfirst(str_replace('_', ' ', $permissionName));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('niveau_hierarchique')
                    ->label('Niveau')
                    ->sortable()
                    ->badge()
                    ->color(fn($record) => match (true) {
                        $record->niveau_hierarchique >= 90 => 'danger',
                        $record->niveau_hierarchique >= 60 => 'warning',
                        $record->niveau_hierarchique >= 30 => 'primary',
                        default => 'success',
                    })
                    ->formatStateUsing(fn($state) => "Niveau {$state}"),

                Tables\Columns\TextColumn::make('permissions_count')
                    ->label('Permissions')
                    ->counts('permissions')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('users_count')
                    ->label('Utilisateurs')
                    ->counts('users')
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn($record) => $record->users()->count() === 0),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('niveau_hierarchique', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
