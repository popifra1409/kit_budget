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

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
    public static function getNavigationBadgeTooltip(): ?string
    {
        $c = static::getModel()::count();
        return $c > 1 ? "{$c} rôles créés" : "{$c} rôle créé";
    }

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
        return (auth()->user()?->can('delete_role') ?? false)
            && $record->users()->count() === 0;
    }

    // =========================================================
    // HELPER — Requête permissions par mot-clé(s)
    // =========================================================
    private static function permsByKeywords(array $keywords): \Illuminate\Support\Collection
    {
        $q = Permission::query();
        $q->where(function ($sub) use ($keywords) {
            foreach ($keywords as $kw) {
                $sub->orWhere('name', 'like', "%{$kw}%");
            }
        });
        return $q->orderBy('name')->get();
    }

    private static function makeTab(string $label, string $icon, array $keywords): Forms\Components\Tabs\Tab
    {
        $perms = static::permsByKeywords($keywords);

        return Forms\Components\Tabs\Tab::make($label)
            ->icon($icon)
            ->badge($perms->count() ?: null)
            ->schema([
                Forms\Components\CheckboxList::make('permissions')
                    ->label('')
                    ->relationship('permissions', 'name')
                    ->options($perms->pluck('name', 'id'))
                    ->descriptions($perms->mapWithKeys(fn($p) => [$p->id => static::getPermissionDescription($p->name)]))
                    ->columns(2)
                    ->searchable()
                    ->bulkToggleable()
                    ->gridDirection('row'),
            ]);
    }

    // =========================================================
    // FORM
    // =========================================================
    public static function form(Form $form): Form
    {
        return $form->schema([

            // ── Informations ──────────────────────────────────
            Forms\Components\Section::make('Informations du rôle')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nom du rôle')->required()
                            ->unique(ignoreRecord: true)->maxLength(255)
                            ->placeholder('Ex: comptable_matieres')
                            ->helperText('Nom technique (sans espaces)'),

                        Forms\Components\TextInput::make('niveau_hierarchique')
                            ->label('Niveau hiérarchique')
                            ->numeric()->required()->default(0)
                            ->minValue(0)->maxValue(100)->suffix('/100')
                            ->helperText('0 = plus bas, 100 = plus haut'),
                    ]),

                    Forms\Components\Placeholder::make('niveau_info')
                        ->label('Guide des niveaux')
                        ->content(new \Illuminate\Support\HtmlString(
                            '<div style="font-size:.8rem;line-height:1.8;color:var(--color-text-secondary);">' .
                            '• <b>10</b> : Opérateurs, Service utilisateur<br>' .
                            '• <b>20</b> : Comptable-matières<br>' .
                            '• <b>30</b> : Chefs de service<br>' .
                            '• <b>40</b> : Ordonnateur-matières<br>' .
                            '• <b>60</b> : Contrôleur financier, Agence comptable<br>' .
                            '• <b>70</b> : DAAF<br>' .
                            '• <b>90</b> : Directeur Général<br>' .
                            '• <b>100</b> : Super administrateur</div>'
                        ))
                        ->columnSpanFull(),
                ])
                ->columns(1),

            // ── Permissions par module ─────────────────────────
            Forms\Components\Section::make('Permissions par module')
                ->description('Organisées par module — utilisez "Tout" pour une vue globale')
                ->schema([
                    Forms\Components\Tabs::make('permissions_tabs')
                        ->tabs([

                            // ── Tout ──────────────────────────
                            Forms\Components\Tabs\Tab::make('Tout')
                                ->icon('heroicon-o-list-bullet')
                                ->schema([
                                    Forms\Components\CheckboxList::make('permissions')
                                        ->label('Rechercher et sélectionner')
                                        ->relationship('permissions', 'name')
                                        ->options(Permission::orderBy('name')->get()->pluck('name', 'id'))
                                        ->descriptions(
                                            Permission::orderBy('name')->get()
                                                ->mapWithKeys(fn($p) => [$p->id => static::getPermissionDescription($p->name)])
                                        )
                                        ->columns(3)->searchable()->bulkToggleable()->gridDirection('row'),
                                ]),

                            // ── Accès modules ─────────────────
                            Forms\Components\Tabs\Tab::make('Accès Modules')
                                ->icon('heroicon-o-squares-2x2')
                                ->badge(fn() => Permission::where('name', 'like', 'access_module_%')->count())
                                ->schema([
                                    Forms\Components\CheckboxList::make('permissions')
                                        ->label('Modules autorisés')
                                        ->relationship('permissions', 'name')
                                        ->options(Permission::where('name', 'like', 'access_module_%')->get()->pluck('name', 'id'))
                                        ->descriptions(
                                            Permission::where('name', 'like', 'access_module_%')->get()
                                                ->mapWithKeys(fn($p) => [$p->id => static::getPermissionDescription($p->name)])
                                        )
                                        ->columns(1)->bulkToggleable()
                                        ->helperText('Cochez les modules accessibles pour ce rôle'),
                                ]),

                            // ════════════════════════════════════
                            // MODULE BUDGET
                            // ════════════════════════════════════
                            static::makeTab('Budget', 'heroicon-o-currency-dollar', ['budget']),
                            static::makeTab('Bon de Commande', 'heroicon-o-shopping-cart', ['bon_commande']),
                            static::makeTab('Engagement', 'heroicon-o-document-check', ['engagement']),
                            static::makeTab('Bordereau', 'heroicon-o-clipboard-document', ['bordereau_engagement']),
                            static::makeTab('Décision Admin.', 'heroicon-o-document-text', ['decision_administrative', 'type_decision']),
                            static::makeTab('Ordonnance', 'heroicon-o-banknotes', ['ordonnance_paiement']),
                            static::makeTab('Mémoire Dépense', 'heroicon-o-document-duplicate', ['memoire_depense']),
                            static::makeTab('Recettes', 'heroicon-o-arrow-trending-up', ['recette_reelle', 'prevision_recette']),
                            static::makeTab('Virements', 'heroicon-o-arrows-right-left', ['virement_budgetaire']),
                            static::makeTab('Fournisseurs', 'heroicon-o-building-storefront', ['fournisseur', 'dossier_fournisseur', 'piece_dossier']),
                            static::makeTab('Transmissions', 'heroicon-o-paper-airplane', ['transmission']),

                            // ════════════════════════════════════
                            // MODULE COMPTABILITÉ MATIÈRES
                            // ════════════════════════════════════
                            Forms\Components\Tabs\Tab::make('🗃 Articles & Stock')
                                ->badge(fn() => static::permsByKeywords(['article', 'stock', 'fiche_stock'])->count())
                                ->schema([
                                    Forms\Components\CheckboxList::make('permissions')
                                        ->label('')->relationship('permissions', 'name')
                                        ->options(static::permsByKeywords(['article', 'stock', 'fiche_stock'])->pluck('name', 'id'))
                                        ->descriptions(static::permsByKeywords(['article', 'stock', 'fiche_stock'])->mapWithKeys(fn($p) => [$p->id => static::getPermissionDescription($p->name)]))
                                        ->columns(2)->searchable()->bulkToggleable(),
                                ]),

                            Forms\Components\Tabs\Tab::make('📋 Expression Besoins')
                                ->badge(fn() => static::permsByKeywords(['expression_besoin'])->count())
                                ->schema([
                                    Forms\Components\CheckboxList::make('permissions')
                                        ->label('')->relationship('permissions', 'name')
                                        ->options(static::permsByKeywords(['expression_besoin'])->pluck('name', 'id'))
                                        ->descriptions(static::permsByKeywords(['expression_besoin'])->mapWithKeys(fn($p) => [$p->id => static::getPermissionDescription($p->name)]))
                                        ->columns(2)->bulkToggleable(),
                                ]),

                            Forms\Components\Tabs\Tab::make('🚛 Réceptions & OE')
                                ->badge(fn() => static::permsByKeywords(['reception', 'ordre_entree'])->count())
                                ->schema([
                                    Forms\Components\CheckboxList::make('permissions')
                                        ->label('')->relationship('permissions', 'name')
                                        ->options(static::permsByKeywords(['reception', 'ordre_entree'])->pluck('name', 'id'))
                                        ->descriptions(static::permsByKeywords(['reception', 'ordre_entree'])->mapWithKeys(fn($p) => [$p->id => static::getPermissionDescription($p->name)]))
                                        ->columns(2)->bulkToggleable(),
                                ]),

                            Forms\Components\Tabs\Tab::make('📤 BSF / BSP / OS')
                                ->badge(fn() => static::permsByKeywords(['bon_sortie', 'ordre_sortie'])->count())
                                ->schema([
                                    Forms\Components\CheckboxList::make('permissions')
                                        ->label('')->relationship('permissions', 'name')
                                        ->options(static::permsByKeywords(['bon_sortie', 'ordre_sortie'])->pluck('name', 'id'))
                                        ->descriptions(static::permsByKeywords(['bon_sortie', 'ordre_sortie'])->mapWithKeys(fn($p) => [$p->id => static::getPermissionDescription($p->name)]))
                                        ->columns(2)->bulkToggleable(),
                                ]),

                            Forms\Components\Tabs\Tab::make('📁 Détenteurs & Conso.')
                                ->badge(fn() => static::permsByKeywords(['fiche_detenteur', 'registre_consommation'])->count())
                                ->schema([
                                    Forms\Components\CheckboxList::make('permissions')
                                        ->label('')->relationship('permissions', 'name')
                                        ->options(static::permsByKeywords(['fiche_detenteur', 'registre_consommation'])->pluck('name', 'id'))
                                        ->descriptions(static::permsByKeywords(['fiche_detenteur', 'registre_consommation'])->mapWithKeys(fn($p) => [$p->id => static::getPermissionDescription($p->name)]))
                                        ->columns(2)->bulkToggleable(),
                                ]),

                            // ════════════════════════════════════
                            // MODULE MARCHÉS PUBLICS
                            // ════════════════════════════════════
                            Forms\Components\Tabs\Tab::make('🏗 Marchés Publics')
                                ->badge(fn() => static::permsByKeywords(['marche', 'appel_offre', 'offre', 'caution', 'avenant'])->count() ?: null)
                                ->schema([
                                    Forms\Components\CheckboxList::make('permissions')
                                        ->label('')->relationship('permissions', 'name')
                                        ->options(static::permsByKeywords(['marche', 'appel_offre', 'offre', 'caution', 'avenant'])->pluck('name', 'id'))
                                        ->descriptions(static::permsByKeywords(['marche', 'appel_offre', 'offre', 'caution', 'avenant'])->mapWithKeys(fn($p) => [$p->id => static::getPermissionDescription($p->name)]))
                                        ->columns(2)->bulkToggleable(),
                                ]),

                            // ════════════════════════════════════
                            // ADMINISTRATION
                            // ════════════════════════════════════
                            static::makeTab('Utilisateurs', 'heroicon-o-users', ['user']),
                            static::makeTab('Rôles', 'heroicon-o-shield-check', ['role']),
                            static::makeTab('Personnel', 'heroicon-o-identification', ['personnel', 'service']),
                            static::makeTab('Paramètres', 'heroicon-o-cog', ['parametres', 'exercice', 'etat_config', 'regime_fiscal']),

                            // ── Autres ────────────────────────
                            Forms\Components\Tabs\Tab::make('Autres')
                                ->icon('heroicon-o-ellipsis-horizontal-circle')
                                ->schema([
                                    Forms\Components\CheckboxList::make('permissions')
                                        ->label('')->relationship('permissions', 'name')
                                        ->options(
                                            Permission::where(function ($q) {
                                                foreach ([
                                                    'budget',
                                                    'bon_commande',
                                                    'engagement',
                                                    'bordereau_engagement',
                                                    'decision_administrative',
                                                    'type_decision',
                                                    'ordonnance_paiement',
                                                    'memoire_depense',
                                                    'recette_reelle',
                                                    'prevision_recette',
                                                    'virement_budgetaire',
                                                    'fournisseur',
                                                    'dossier_fournisseur',
                                                    'piece_dossier',
                                                    'transmission',
                                                    'article',
                                                    'stock',
                                                    'fiche_stock',
                                                    'expression_besoin',
                                                    'reception',
                                                    'ordre_entree',
                                                    'bon_sortie',
                                                    'ordre_sortie',
                                                    'fiche_detenteur',
                                                    'registre_consommation',
                                                    'marche',
                                                    'appel_offre',
                                                    'offre',
                                                    'caution',
                                                    'avenant',
                                                    'user',
                                                    'role',
                                                    'personnel',
                                                    'service',
                                                    'parametres',
                                                    'exercice',
                                                    'etat_config',
                                                    'regime_fiscal',
                                                ] as $kw) {
                                                    $q->where('name', 'not like', "%{$kw}%");
                                                }
                                                $q->where('name', 'not like', 'access_module_%');
                                            })
                                                ->orderBy('name')->get()->pluck('name', 'id')
                                        )
                                        ->columns(2)->searchable()->bulkToggleable(),
                                ]),
                        ])
                        ->columnSpanFull()
                        ->persistTabInQueryString(),
                ])
                ->collapsible(),
        ]);
    }

    // =========================================================
    // TABLE
    // =========================================================
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')->searchable()->sortable()->weight('bold'),

                Tables\Columns\TextColumn::make('niveau_hierarchique')
                    ->label('Niveau')->sortable()->badge()
                    ->color(fn($record) => match (true) {
                        $record->niveau_hierarchique >= 90 => 'danger',
                        $record->niveau_hierarchique >= 60 => 'warning',
                        $record->niveau_hierarchique >= 30 => 'primary',
                        default => 'success',
                    })
                    ->formatStateUsing(fn($state) => "Niv. {$state}"),

                Tables\Columns\TextColumn::make('permissions_count')
                    ->label('Permissions')->counts('permissions')->badge()->color('info'),

                Tables\Columns\TextColumn::make('users_count')
                    ->label('Utilisateurs')->counts('users')->badge()->color('success'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')->dateTime('d/m/Y H:i')->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('niveau_hierarchique', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn($record) => $record->users()->count() === 0),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    // =========================================================
    // DESCRIPTIONS
    // =========================================================
    protected static function getPermissionDescription(string $name): string
    {
        // Préfixes connus
        $prefixes = [
            'view_any_' => '📋 Voir la liste',
            'view_' => '👁 Voir le détail',
            'create_' => '➕ Créer',
            'update_' => '✏️ Modifier',
            'delete_' => '🗑️ Supprimer',
        ];

        foreach ($prefixes as $prefix => $label) {
            if (str_starts_with($name, $prefix)) {
                $resource = ucfirst(str_replace('_', ' ', substr($name, strlen($prefix))));
                return "{$label} : {$resource}";
            }
        }

        // Permissions spéciales
        return match ($name) {
            // Accès modules
            'access_module_portal' => '🏠 Accès portail principal',
            'access_module_budget' => '💰 Accès module Budget',
            'access_module_comptable' => '🗃️ Accès module Comptabilité Matières',
            'access_module_marches' => '🏗️ Accès module Marchés Publics',
            // Budget
            'valider_bon_commande' => '✅ Valider bon de commande',
            'engager_bon_commande' => '📌 Engager bon de commande',
            'valider_engagement' => '✅ Valider engagement',
            'valider_decision_administrative' => '✅ Valider décision administrative',
            'engager_decision_administrative' => '📌 Engager décision administrative',
            'emettre_ordonnance_paiement' => '📤 Émettre ordonnance paiement',
            'viser_ordonnance_paiement' => '✔️ Viser ordonnance paiement',
            'valider_ordonnance_paiement' => '✅ Valider ordonnance paiement',
            'payer_ordonnance_paiement' => '💰 Payer ordonnance',
            'valider_memoire_depense' => '✅ Valider mémoire de dépense',
            'transformer_memoire_depense_en_da' => '🔄 Transformer mémoire en DA',
            'transmettre_bordereau_engagement' => '📤 Transmettre bordereau',
            'valider_bordereau_engagement' => '✅ Valider bordereau',
            'rejeter_bordereau_engagement' => '❌ Rejeter bordereau',
            // Comptabilité matières
            'valider_expression_besoin' => '✅ Valider expression besoins',
            'soumettre_expression_besoin' => '📤 Soumettre expression besoins',
            'signer_pv_reception' => '✍️ Signer PV réception',
            'integrer_reception_stock' => '📥 Intégrer réception en stock',
            'signer_ordre_entree' => '✍️ Signer ordre d\'entrée',
            'transmettre_ordre_entree' => '📤 Transmettre OE au budget',
            'signer_bsp_demandeur' => '✍️ Signer BSP (demandeur)',
            'signer_bsp_comptable' => '✍️ Signer BSP (comptable)',
            'signer_bsp_ordonnateur' => '✍️ Signer BSP (ordonnateur)',
            'executer_bon_sortie_provisoire' => '▶️ Exécuter sortie provisoire',
            'signer_ordre_sortie' => '✍️ Signer ordre de sortie',
            'retourner_fiche_detenteur' => '↩️ Retourner bien au magasin',
            'ajuster_stock' => '⚖️ Ajustement inventaire',
            'inventorier_stock' => '📊 Faire l\'inventaire',
            default => ucfirst(str_replace('_', ' ', $name)),
        };
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