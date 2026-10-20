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
use \App\Filament\Concerns\AutorisationParPermissions;

class RoleResource extends Resource
{
    protected static function prefixePermission(): string
    {
        return 'user';
    }

    use \App\Filament\Budget\Concerns\HasAgentContext;
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
    // CATALOGUE (config/permission.php (clé « catalogue »))
    // =========================================================

    /** Permissions d'une rubrique (crud × actions + spéciales), existantes en base. */
    protected static function permissionsRubrique(array $rubrique): \Illuminate\Support\Collection
    {
        $actions = config('permission.catalogue.actions_crud', ['view_any', 'view', 'create', 'update', 'delete']);
        $noms = [];
        foreach ($rubrique['crud'] ?? [] as $ressource) {
            foreach ($actions as $action) {
                $noms[] = "{$action}_{$ressource}";
            }
        }
        $noms = array_merge($noms, $rubrique['speciales'] ?? []);

        return Permission::whereIn('name', $noms)->get()
            ->sortBy(fn($p) => array_search($p->name, $noms))
            ->values();
    }

    /** Toutes les permissions rangées dans le catalogue (accès compris). */
    protected static function nomsCatalogue(): array
    {
        $actions = config('permission.catalogue.actions_crud', ['view_any', 'view', 'create', 'update', 'delete']);
        $noms = [];
        foreach (config('permission.catalogue.modules', []) as $module) {
            $noms[] = $module['acces'];
            foreach ($module['rubriques'] as $rubrique) {
                foreach ($rubrique['crud'] ?? [] as $ressource) {
                    foreach ($actions as $action) {
                        $noms[] = "{$action}_{$ressource}";
                    }
                }
                array_push($noms, ...($rubrique['speciales'] ?? []));
            }
        }

        return array_values(array_unique($noms));
    }

    /** Liste de cases d'une rubrique (même état « permissions » pour toutes les listes). */
    protected static function casesPermissions(\Illuminate\Support\Collection $permissions, int $colonnes = 2): Forms\Components\CheckboxList
    {
        return Forms\Components\CheckboxList::make('permissions')
            ->label('')
            ->relationship('permissions', 'name')
            ->options($permissions->pluck('name', 'id'))
            ->descriptions($permissions->mapWithKeys(fn($p) => [$p->id => static::getPermissionDescription($p->name)]))
            ->columns($colonnes)
            ->gridDirection('row')
            ->bulkToggleable();
    }

    // =========================================================
    // FORM
    // =========================================================
    public static function form(Form $form): Form
    {
        $modules = config('permission.catalogue.modules', []);

        // ── Accès aux modules (panneaux) ──
        $acces = Permission::whereIn('name', collect($modules)->pluck('acces'))->get()
            ->sortBy(fn($p) => array_search($p->name, collect($modules)->pluck('acces')->all()))->values();

        // ── Un onglet par module, une section par rubrique ──
        $onglets = [];
        foreach ($modules as $cle => $module) {
            $sections = [];
            foreach ($module['rubriques'] as $titre => $rubrique) {
                $perms = static::permissionsRubrique($rubrique);
                if ($perms->isEmpty()) {
                    continue;
                }
                $sections[] = Forms\Components\Section::make($titre)
                    ->description($perms->count() . ' permission(s)')
                    ->schema([static::casesPermissions($perms)])
                    ->collapsible()
                    ->compact();
            }
            if (empty($sections)) {
                continue;
            }
            $onglets[] = Forms\Components\Tabs\Tab::make($module['libelle'])
                ->icon($module['icone'] ?? null)
                ->schema([
                    Forms\Components\Placeholder::make("rappel_acces_{$cle}")
                        ->label('')
                        ->content(new \Illuminate\Support\HtmlString(
                            '<span style="font-size:.85rem;color:#6b7280;">Ces droits ne servent que si le rôle a aussi '
                                . '<strong>l\'accès au module « ' . e($module['libelle']) . ' »</strong> (bloc « Accès aux modules » ci-dessus).</span>'
                        )),
                    ...$sections,
                ]);
        }

        // ── Permissions hors catalogue ──
        $autres = Permission::whereNotIn('name', static::nomsCatalogue())->orderBy('name')->get();
        if ($autres->isNotEmpty()) {
            $onglets[] = Forms\Components\Tabs\Tab::make('Autres')
                ->icon('heroicon-o-question-mark-circle')
                ->badge($autres->count())
                ->schema([
                    Forms\Components\Placeholder::make('info_autres')
                        ->label('')
                        ->content('Permissions existantes non rangées dans le catalogue (config/permission.php (clé « catalogue »)) : anciennes permissions, ou permissions détectées dans le code.'),
                    static::casesPermissions($autres),
                ]);
        }

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

            // ── Accès aux modules ─────────────────────────────
            Forms\Components\Section::make('Accès aux modules')
                ->description('Un module non coché est invisible pour ce rôle : son panneau n\'apparaît pas et son adresse est refusée.')
                ->icon('heroicon-o-squares-2x2')
                ->schema([static::casesPermissions($acces, 4)]),

            // ── Droits par module ─────────────────────────────
            Forms\Components\Section::make('Droits par module')
                ->description('Un onglet par module, une rubrique par domaine ; « Tout sélectionner » agit sur la rubrique.')
                ->schema([
                    Forms\Components\Tabs::make('modules')->tabs($onglets)->persistTabInQueryString(),
                ]),
        ]);
    }

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

                Tables\Columns\TextColumn::make('modules')
                    ->label('Modules accessibles')
                    ->getStateUsing(fn($record) => collect(config('permission.catalogue.modules', []))
                        ->filter(fn($m) => $record->permissions->contains('name', $m['acces']))
                        ->pluck('libelle')->all())
                    ->badge()->color('primary')->wrap(),

                Tables\Columns\TextColumn::make('permissions_count')
                    ->label('Permissions')->counts('permissions')->badge()->color('info'),

                Tables\Columns\TextColumn::make('users_count')
                    ->label('Utilisateurs')->counts('users')->badge()->color('success'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')->dateTime('d/m/Y H:i')->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->modifyQueryUsing(fn($query) => $query->with('permissions'))
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
            'access_module_planification' => '🗺️ Accès module Planification stratégique',
            'access_module_programmation' => '📅 Accès module Programmation (PPA, CBMT, CDMT)',
            'access_module_suivi_evaluation' => '📊 Accès module Suivi et évaluation',
            'access_module_admin' => '⚙️ Accès panneau Administration',
            'view_compte_administratif' => '📘 Consulter et éditer le compte administratif',
            'supprimer_ligne_budgetaire_adoptee' => '⚠️ Supprimer une ligne budgétaire (budget adopté, exceptionnel)',
            'supprimer_ligne_prevision_adoptee' => '⚠️ Supprimer une ligne de prévision de recettes (exceptionnel)',
            'operer_hors_periode_execution' => '⏱️ Opérer hors période d\'exécution (dérogation)',
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
