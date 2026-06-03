<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\AchatDirectResource\Pages;
use App\Models\DepenseRegie;
use App\Models\RegieAvance;
use App\Models\ProvisionLigneRegie;
use App\Models\ParametresStructure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class AchatDirectResource extends Resource
{
    protected static ?string $model            = DepenseRegie::class;
    protected static ?string $navigationIcon   = 'heroicon-o-shopping-bag';
    protected static ?string $navigationLabel  = 'Achats Directs';
    protected static ?string $modelLabel       = 'Achat Direct';
    protected static ?string $pluralModelLabel = 'Achats Directs';
    protected static ?string $navigationGroup  = 'Régies & Menu Dépenses';
    protected static ?int    $navigationSort   = 4;
    protected static ?string $slug             = 'achats-directs';
    protected static ?string $recordTitleAttribute = 'numero';

    // =========================================================
    // PERMISSIONS
    // =========================================================
    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_depense_regie') ?? false;
    }
    public static function canView($record): bool
    {
        return auth()->user()?->can('view_depense_regie') ?? false;
    }
    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_depense_regie') ?? false;
    }
    public static function canEdit($record): bool
    {
        return auth()->user()?->can('update_depense_regie') && $record->statut === 'brouillon';
    }
    public static function canDelete($record): bool
    {
        return auth()->user()?->can('delete_depense_regie') && $record->statut === 'brouillon';
    }

    // =========================================================
    // ✅ RECALCUL LIGNE — appelé depuis afterStateUpdated
    // Met à jour les champs d'affichage via $set
    // =========================================================
    protected static function recalculerLigne(Get $get, Set $set): void
    {
        $seuil  = (float) (ParametresStructure::where('actif', true)
            ->value('seuil_achat_direct_regie') ?? 500000);

        $mode   = $get('../../mode_saisie_global')       ?? 'montant_nap';
        $qte    = floatval($get('quantite')              ?? 1);
        $tauxTv = floatval($get('../../taux_tva_global') ?? 19.25);
        $tauxIr = floatval($get('../../taux_ir_global')  ?? 5.5);

        if ($qte <= 0) return;

        if ($mode === 'montant_nap') {
            $napUnit = floatval($get('montant_nap_input') ?? 0);
            if ($napUnit <= 0 || $tauxIr >= 100) return;

            $napTotal = $napUnit * $qte;
            $mht      = $napTotal / (1 - ($tauxIr / 100));
            $tva      = $mht * ($tauxTv / 100);
            $ttc      = $mht + $tva;
            $ir       = $mht * ($tauxIr / 100);
            $nap      = $napTotal;

            $set('prix_unitaire', round($mht / $qte, 4));
        } else {
            $pu = floatval($get('prix_unitaire') ?? 0);
            if ($pu <= 0) return;

            $mht = $qte * $pu;
            $tva = $mht * ($tauxTv / 100);
            $ttc = $mht + $tva;
            $ir  = $mht * ($tauxIr / 100);
            $nap = $mht - $ir;
        }

        $set('_affiche_mht', number_format(round($mht, 0), 0, ',', ' ') . ' F');
        $set('_affiche_tva', number_format(round($tva, 0), 0, ',', ' ') . ' F');
        $set('_affiche_ir',  number_format(round($ir, 0),  0, ',', ' ') . ' F');
        $set('_affiche_nap', number_format(round($nap, 0), 0, ',', ' ') . ' F');

        // ✅ TTC avec alerte si seuil dépassé
        $depasse = $ttc >= $seuil;
        $set(
            '_affiche_ttc',
            number_format(round($ttc, 0), 0, ',', ' ') . ' F'
                . ($depasse ? ' ⚠️' : '')
        );
    }

    // =========================================================
    // PRÉPARER DONNÉES LIGNE — persistence avant save
    // =========================================================
    protected static function preparerDonneesLigne(array $data, Get $get): array
    {
        $tauxTva = floatval($get('taux_tva_global') ?? 19.25);
        $tauxIr  = floatval($get('taux_ir_global')  ?? 5.5);

        $data['taux_tva'] = $tauxTva;
        $data['taux_ir']  = $tauxIr;

        $qte = floatval($data['quantite']          ?? 1);
        $nap = floatval($data['montant_nap_input'] ?? 0);
        $pu  = floatval($data['prix_unitaire']     ?? 0);

        if ($nap > 0 && $qte > 0 && $tauxIr < 100) {
            $napTotal              = $nap * $qte;
            $mht                   = $napTotal / (1 - ($tauxIr / 100));
            $data['prix_unitaire'] = round($mht / $qte, 4);
            $pu                    = $data['prix_unitaire'];
        } elseif ($pu <= 0) {
            $data['prix_unitaire'] = 0;
            $pu = 0;
        }

        $mht = $qte * $pu;
        $tva = round($mht * ($tauxTva / 100), 2);
        $ttc = round($mht + $tva, 2);
        $ir  = round($mht * ($tauxIr / 100), 2);

        $data['montant_ht']  = round($mht, 2);
        $data['montant_tva'] = $tva;
        $data['montant_ttc'] = $ttc;
        $data['montant_ir']  = $ir;
        $data['montant_net'] = ($nap > 0)
            ? round($nap * $qte, 2)
            : round($mht - $ir, 2);

        // ✅ montant_nap_input N'EST PLUS unset — stocké en DB pour l'édition

        // ✅ Nettoyer uniquement les champs d'affichage temporaires
        unset(
            $data['_affiche_mht'],
            $data['_affiche_tva'],
            $data['_affiche_ttc'],
            $data['_affiche_ir'],
            $data['_affiche_nap'],
        );

        return $data;
    }

    // =========================================================
    // FORMULAIRE
    // =========================================================
    public static function form(Form $form): Form
    {
        $seuil = (float) (ParametresStructure::where('actif', true)
            ->value('seuil_achat_direct_regie') ?? 500000);

        return $form->schema([

            // ── Section 1 : Identification ────────────────────
            Forms\Components\Section::make('Identification')
                ->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('numero')
                            ->label('Numéro')->disabled()->dehydrated()
                            ->placeholder('Généré automatiquement'),

                        Forms\Components\DatePicker::make('date_depense')
                            ->label('Date')->default(now())->required(),

                        Forms\Components\Hidden::make('type_depense')
                            ->default('achat_direct'),
                    ]),

                    Forms\Components\TextInput::make('objet')
                        ->label('Objet de la dépense')
                        ->required()->maxLength(255)->columnSpanFull(),
                ]),

            // ── Section 2 : Régie et ligne budgétaire ─────────
            Forms\Components\Section::make('Régie et ligne budgétaire')
                ->schema([
                    Forms\Components\Select::make('regie_avance_id')
                        ->label('Régie / Menu Dépense')
                        ->options(function () {
                            return RegieAvance::where('statut', 'actif')
                                ->when(
                                    !auth()->user()?->hasAnyRole([
                                        'super_admin',
                                        'admin',
                                        'daaf',
                                        'agence_comptable',
                                    ]),
                                    fn($q) => $q->where('responsable_id', auth()->id())
                                )
                                ->get()
                                ->mapWithKeys(fn($r) => [
                                    $r->id => "{$r->numero} — {$r->libelle} ({$r->label_type})"
                                ]);
                        })
                        ->required()->searchable()->live()
                        ->afterStateUpdated(function (Set $set) {
                            $set('provision_ligne_regie_id', null);
                            $set('ligne_regie_avance_id',   null);
                        })
                        ->columnSpanFull(),

                    Forms\Components\Select::make('provision_ligne_regie_id')
                        ->label('Provision disponible')
                        ->options(function (Get $get, $record) {
                            // ✅ Fallback sur $record si $get retourne null (cas édition)
                            $regieId = $get('regie_avance_id')
                                ?? $record?->regie_avance_id;

                            if (!$regieId) return [];

                            return ProvisionLigneRegie::whereHas(
                                'decaissement',
                                fn($q) => $q->where('regie_avance_id', $regieId)
                                    ->where('statut', 'verse')
                            )
                                ->where(function ($q) use ($record) {
                                    // ✅ Toujours inclure la provision actuelle même si montant = 0
                                    $q->where('montant_disponible', '>', 0);
                                    if ($record?->provision_ligne_regie_id) {
                                        $q->orWhere('id', $record->provision_ligne_regie_id);
                                    }
                                })
                                ->with(['ligneRegie.nomenclature', 'decaissement'])
                                ->get()
                                ->mapWithKeys(fn($p) => [
                                    $p->id =>
                                    "{$p->ligneRegie->nomenclature->code} — "
                                        . "{$p->ligneRegie->nomenclature->libelle} "
                                        . "| {$p->decaissement->libelle_tranche} "
                                        . "| Dispo: "
                                        . number_format($p->montant_disponible, 0, ',', ' ')
                                        . " FCFA"
                                ]);
                        })
                        // ✅ Afficher le libellé de la valeur sélectionnée même si pas dans les options
                        ->getOptionLabelUsing(function ($value) {
                            if (!$value) return null;
                            $p = ProvisionLigneRegie::with([
                                'ligneRegie.nomenclature',
                                'decaissement',
                            ])->find($value);
                            if (!$p) return "Provision #{$value}";
                            return "{$p->ligneRegie->nomenclature->code} — "
                                . "{$p->ligneRegie->nomenclature->libelle} "
                                . "| {$p->decaissement->libelle_tranche} "
                                . "| Dispo: "
                                . number_format($p->montant_disponible, 0, ',', ' ')
                                . " FCFA";
                        })
                        ->required()
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set) {
                            if (!$state) return;
                            $prov = ProvisionLigneRegie::find($state);
                            $set('ligne_regie_avance_id', $prov?->ligne_regie_avance_id);
                        })
                        ->columnSpanFull(),

                    Forms\Components\Hidden::make('ligne_regie_avance_id'),

                    Forms\Components\Placeholder::make('apercu_provision')
                        ->label('Situation de la provision')
                        ->content(function (Get $get) {
                            $provId = $get('provision_ligne_regie_id');
                            if (!$provId) return '← Sélectionnez une provision';
                            $prov = ProvisionLigneRegie::with([
                                'ligneRegie.nomenclature',
                                'decaissement',
                            ])->find($provId);
                            if (!$prov) return '—';
                            return new \Illuminate\Support\HtmlString(
                                '<div class="rounded-lg p-3 text-sm leading-loose '
                                    . 'bg-slate-100 dark:bg-slate-800 '
                                    . 'text-slate-800 dark:text-slate-200">'
                                    . '<table class="w-full">'
                                    . '<tr><td class="font-semibold pr-4 w-32">Nomenclature :</td>'
                                    . '<td>' . $prov->ligneRegie->nomenclature->code
                                    . ' — ' . $prov->ligneRegie->nomenclature->libelle . '</td></tr>'
                                    . '<tr><td class="font-semibold pr-4">Tranche :</td>'
                                    . '<td>' . $prov->decaissement->libelle_tranche . '</td></tr>'
                                    . '<tr><td class="font-semibold pr-4">Disponible :</td>'
                                    . '<td class="text-green-600 dark:text-green-400 font-bold">'
                                    . number_format($prov->montant_disponible, 0, ',', ' ')
                                    . ' FCFA</td></tr>'
                                    . '</table></div>'
                            );
                        })
                        ->columnSpanFull(),
                ]),

            // ── Section 3 : Fournisseur ───────────────────────
            Forms\Components\Section::make('Fournisseur')
                ->schema([
                    Forms\Components\Select::make('fournisseur_id')
                        ->label('Fournisseur référencé')
                        ->relationship('fournisseur', 'raison_sociale')
                        ->searchable()->preload()->nullable()->columnSpan(2),

                    Forms\Components\TextInput::make('fournisseur_libre')
                        ->label('Ou fournisseur libre')
                        ->maxLength(255)->columnSpan(1),
                ])
                ->columns(3),

            // ── Section 4 : Lignes de dépenses ────────────────
            Forms\Components\Section::make('Lignes de dépenses')
                ->description('Choisissez le mode de saisie pour toutes les lignes.')
                ->schema([

                    Forms\Components\Grid::make(6)->schema([

                        Forms\Components\ToggleButtons::make('mode_saisie_global')
                            ->label('Mode de saisie')
                            ->options([
                                'montant_nap'   => '📊 Montant NAP',
                                'prix_unitaire' => '💰 Prix Unitaire HT',
                            ])
                            ->default('montant_nap')
                            ->inline()->live()->dehydrated(false)
                            ->afterStateHydrated(function ($component, $record) {
                                $component->state($record?->mode_saisie ?? 'montant_nap');
                            })
                            ->afterStateUpdated(function ($state, Set $set) {
                                $set('mode_saisie', $state);
                            })
                            ->columnSpan(4),

                        // ✅ Indicateur NAP max — affiché au-dessus du repeater
                        Forms\Components\Placeholder::make('info_nap_max')
                            ->label('')
                            ->content(function (Get $get) {
                                $seuil  = (float) (ParametresStructure::where('actif', true)
                                    ->value('seuil_achat_direct_regie') ?? 500000);
                                $tauxTv = floatval($get('taux_tva_global') ?? 19.25);
                                $tauxIr = floatval($get('taux_ir_global')  ?? 5.5);

                                // NAP_max = (seuil - 1) × (1 - IR/100) / (1 + TVA/100)
                                $napMax = ($seuil - 1) * (1 - $tauxIr / 100) / (1 + $tauxTv / 100);

                                return new \Illuminate\Support\HtmlString(
                                    '<div class="rounded-lg p-3 text-sm leading-loose '
                                        . 'bg-amber-50 dark:bg-amber-900/30 '
                                        . 'text-amber-800 dark:text-amber-200 '
                                        . 'border border-amber-300 dark:border-amber-700">'
                                        . '<div class="font-bold mb-1">⚠️ Limites Achat Direct</div>'
                                        . '<table class="w-full text-xs">'
                                        . '<tr>'
                                        . '<td class="pr-4">Seuil TTC maximum :</td>'
                                        . '<td class="font-bold text-red-600 dark:text-red-400">'
                                        . '< ' . number_format($seuil, 0, ',', ' ') . ' FCFA (strictement)</td>'
                                        . '</tr>'
                                        . '<tr>'
                                        . '<td class="pr-4">NAP unitaire maximum :</td>'
                                        . '<td class="font-bold text-green-700 dark:text-green-400">'
                                        . '< ' . number_format($napMax, 0, ',', ' ') . ' FCFA</td>'
                                        . '</tr>'
                                        . '<tr>'
                                        . '<td class="pr-4">Taux appliqués :</td>'
                                        . '<td>TVA ' . $tauxTv . '% | IR ' . $tauxIr . '%</td>'
                                        . '</tr>'
                                        . '</table>'
                                        . '</div>'
                                );
                            })
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('taux_tva_global')
                            ->label('TVA globale (%)')->numeric()->default(19.25)->suffix('%')
                            ->required()->live()->dehydrated(false)
                            ->afterStateHydrated(function ($component, $record) {
                                if ($record && $record->lignes->isNotEmpty()) {
                                    $component->state($record->lignes->first()->taux_tva ?? 19.25);
                                }
                            })
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('taux_ir_global')
                            ->label('IR global (%)')->numeric()->default(5.5)->suffix('%')
                            ->required()->live()->dehydrated(false)
                            ->afterStateHydrated(function ($component, $record) {
                                if ($record && $record->lignes->isNotEmpty()) {
                                    $component->state($record->lignes->first()->taux_ir ?? 5.5);
                                }
                            })
                            ->columnSpan(1),
                    ])->columnSpanFull(),

                    Forms\Components\Hidden::make('mode_saisie')
                        ->default('montant_nap')->dehydrated(true),

                    // ── Repeater lignes ───────────────────────
                    Forms\Components\Repeater::make('lignes')
                        ->relationship('lignes')
                        ->mutateRelationshipDataBeforeFillUsing(function (array $data): array {

                            // ✅ montant_nap_input déjà en DB maintenant — pas besoin de recalculer
                            // Mais on garde un fallback au cas où de vieilles lignes n'auraient pas la valeur
                            if (empty($data['montant_nap_input']) || $data['montant_nap_input'] == 0) {
                                $qte = floatval($data['quantite'] ?? 1);
                                $nap = floatval($data['montant_net'] ?? 0);
                                if ($qte > 0 && $nap > 0) {
                                    $data['montant_nap_input'] = round($nap / $qte, 2);
                                }
                            }

                            // ✅ Pré-remplir les champs _affiche_* depuis les valeurs stockées
                            $data['_affiche_mht'] = number_format(
                                floatval($data['montant_ht']  ?? 0),
                                0,
                                ',',
                                ' '
                            ) . ' F';
                            $data['_affiche_tva'] = number_format(
                                floatval($data['montant_tva'] ?? 0),
                                0,
                                ',',
                                ' '
                            ) . ' F';
                            $data['_affiche_ttc'] = number_format(
                                floatval($data['montant_ttc'] ?? 0),
                                0,
                                ',',
                                ' '
                            ) . ' F';
                            $data['_affiche_ir']  = number_format(
                                floatval($data['montant_ir']  ?? 0),
                                0,
                                ',',
                                ' '
                            ) . ' F';
                            $data['_affiche_nap'] = number_format(
                                floatval($data['montant_net'] ?? 0),
                                0,
                                ',',
                                ' '
                            ) . ' F';

                            return $data;
                        })
                        ->schema([
                            // ── Ligne 1 : Désignation ─────────
                            Forms\Components\TextInput::make('nature_depense')
                                ->label('Désignation / Nature de la dépense')
                                ->required()->columnSpanFull(),

                            // ── Ligne 2 : Saisie des montants ─
                            Forms\Components\Grid::make(5)->schema([

                                Forms\Components\TextInput::make('quantite')
                                    ->label('Quantité')
                                    ->numeric()->default(1)->required()
                                    ->live(onBlur: true)
                                    // ✅ afterStateUpdated sur quantite
                                    ->afterStateUpdated(
                                        fn(Get $get, Set $set) =>
                                        static::recalculerLigne($get, $set)
                                    )
                                    ->columnSpan(1),

                                // Mode Prix Unitaire HT
                                Forms\Components\TextInput::make('prix_unitaire')
                                    ->label('Prix Unitaire HT (FCFA)')
                                    ->numeric()->suffix('FCFA')->default(0)
                                    ->hidden(
                                        fn(Get $get) =>
                                        $get('../../mode_saisie_global') === 'montant_nap'
                                    )
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(
                                        fn(Get $get, Set $set) =>
                                        static::recalculerLigne($get, $set)
                                    )
                                    ->columnSpan(2),

                                // Mode NAP unitaire
                                Forms\Components\TextInput::make('montant_nap_input')
                                    ->label('NAP unitaire (FCFA)')
                                    ->numeric()->suffix('FCFA')
                                    ->required(
                                        fn(Get $get) =>
                                        $get('../../mode_saisie_global') === 'montant_nap'
                                    )
                                    ->hidden(
                                        fn(Get $get) =>
                                        $get('../../mode_saisie_global') !== 'montant_nap'
                                    )
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(
                                        fn(Get $get, Set $set) =>
                                        static::recalculerLigne($get, $set)
                                    )
                                    ->dehydrated(true)
                                    ->helperText('Net à payer par unité — base du calcul')
                                    ->columnSpan(2),

                                Forms\Components\Textarea::make('observations')
                                    ->label('Obs.')->rows(1)->columnSpan(1),
                            ]),

                            // ── Ligne 3 : Résultats calculés ──
                            // ✅ TextInput désactivés mis à jour via $set dans recalculerLigne
                            Forms\Components\Grid::make(5)->schema([

                                Forms\Components\TextInput::make('_affiche_mht')
                                    ->label('Montant HT')
                                    ->disabled()->dehydrated(false)
                                    ->default('0 F')
                                    ->extraInputAttributes(['class' => 'text-right font-mono'])
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('_affiche_tva')
                                    ->label('TVA')
                                    ->disabled()->dehydrated(false)
                                    ->default('0 F')
                                    ->extraInputAttributes(['class' => 'text-right font-mono'])
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('_affiche_ttc')
                                    ->label('Montant TTC')
                                    ->disabled()->dehydrated(false)
                                    ->default('0 F')
                                    ->extraInputAttributes([
                                        'class' => 'text-right font-mono font-bold',
                                    ])
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('_affiche_ir')
                                    ->label('IR / AC')
                                    ->disabled()->dehydrated(false)
                                    ->default('0 F')
                                    ->extraInputAttributes([
                                        'class' => 'text-right font-mono text-red-600',
                                    ])
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('_affiche_nap')
                                    ->label('✅ NAP Total')
                                    ->disabled()->dehydrated(false)
                                    ->default('0 F')
                                    ->extraInputAttributes([
                                        'class' =>
                                        'text-right font-mono font-bold text-green-700',
                                    ])
                                    ->columnSpan(1),
                            ]),
                        ])
                        ->mutateRelationshipDataBeforeCreateUsing(
                            fn(array $data, Get $get) =>
                            static::preparerDonneesLigne($data, $get)
                        )
                        ->mutateRelationshipDataBeforeSaveUsing(
                            fn(array $data, Get $get) =>
                            static::preparerDonneesLigne($data, $get)
                        )
                        ->orderColumn('numero_ligne')
                        ->defaultItems(1)
                        ->addActionLabel('➕ Ajouter une ligne')
                        ->reorderable()->collapsible()
                        ->itemLabel(
                            fn(array $state): ?string =>
                            !empty($state['nature_depense'])
                                ? $state['nature_depense']
                                : 'Nouvelle ligne'
                        ),

                    // ── Totaux après sauvegarde ───────────────
                    Forms\Components\Placeholder::make('totaux_apercu')
                        ->label('📊 Totaux')
                        ->content(function ($record) use ($seuil) {
                            if (!$record || !$record->exists) {
                                return 'Totaux affichés après la première sauvegarde.';
                            }
                            $lignes = $record->lignes;
                            $ttc    = $lignes->sum('montant_ttc');
                            $ht     = $lignes->sum('montant_ht');
                            $tva    = $lignes->sum('montant_tva');
                            $ir     = $lignes->sum('montant_ir');
                            $nap    = $lignes->sum('montant_net');

                            $alerte = $ttc > $seuil
                                ? '<div class="mt-2 rounded p-2 text-xs font-semibold '
                                . 'bg-red-100 text-red-700 '
                                . 'dark:bg-red-900/40 dark:text-red-300">'
                                . '⚠️ TTC dépasse le seuil achat direct ('
                                . number_format($seuil, 0, ',', ' ')
                                . ' FCFA) — utilisez un BCR</div>'
                                : '<div class="mt-1 rounded p-1 text-xs font-semibold '
                                . 'bg-green-100 text-green-700 '
                                . 'dark:bg-green-900/40 dark:text-green-300">'
                                . '✅ Dans la limite achat direct</div>';

                            return new \Illuminate\Support\HtmlString(
                                '<div class="rounded-lg p-3 text-sm leading-loose '
                                    . 'bg-slate-50 dark:bg-slate-900 '
                                    . 'text-slate-800 dark:text-slate-200 '
                                    . 'border border-slate-200 dark:border-slate-700">'
                                    . '<table class="w-full">'
                                    . '<tr><td>Total HT :</td><td class="text-right">'
                                    . number_format($ht, 0, ',', ' ') . ' FCFA</td></tr>'
                                    . '<tr><td>Total TVA :</td><td class="text-right">'
                                    . number_format($tva, 0, ',', ' ') . ' FCFA</td></tr>'
                                    . '<tr class="border-t border-slate-300 dark:border-slate-600">'
                                    . '<td class="font-semibold">Total TTC :</td>'
                                    . '<td class="text-right font-bold">'
                                    . number_format($ttc, 0, ',', ' ') . ' FCFA</td></tr>'
                                    . '<tr><td>Total IR :</td>'
                                    . '<td class="text-right text-red-600 dark:text-red-400">'
                                    . number_format($ir, 0, ',', ' ') . ' FCFA</td></tr>'
                                    . '<tr class="border-t-2 border-green-500">'
                                    . '<td class="font-bold text-green-700 dark:text-green-400">'
                                    . 'Total NAP :</td>'
                                    . '<td class="text-right font-bold text-base '
                                    . 'text-green-700 dark:text-green-400">'
                                    . number_format($nap, 0, ',', ' ') . ' FCFA</td></tr>'
                                    . '</table>' . $alerte . '</div>'
                            );
                        })
                        ->columnSpanFull(),
                ])
                ->columns(1),

            // ── Section 5 : Justificatif ──────────────────────
            Forms\Components\Section::make('Justificatif')
                ->schema([
                    Forms\Components\FileUpload::make('justificatif_fichier')
                        ->label('Pièce justificative')
                        ->disk('public')->directory('justificatifs-regies')
                        ->acceptedFileTypes(['application/pdf', 'image/*'])->nullable(),

                    Forms\Components\Textarea::make('observations')
                        ->label('Observations')->rows(2),
                ])
                ->columns(2)->collapsible()->collapsed(),
        ]);
    }

    // =========================================================
    // TABLEAU
    // =========================================================
    public static function table(Table $table): Table
    {
        $seuil = (float) (ParametresStructure::where('actif', true)
            ->value('seuil_achat_direct_regie') ?? 500000);

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('N°')->weight('bold')->copyable()->searchable(),

                Tables\Columns\TextColumn::make('regieAvance.numero')
                    ->label('Régie')->badge()->color('info'),

                Tables\Columns\TextColumn::make('regieAvance.type')
                    ->label('Type')
                    ->formatStateUsing(fn($state) => match ($state) {
                        'rav'          => 'RAV',
                        'menu_depense' => 'MD',
                        default        => $state,
                    })
                    ->badge()
                    ->color(fn($state) => $state === 'rav' ? 'primary' : 'warning'),

                Tables\Columns\TextColumn::make('date_depense')
                    ->label('Date')->date('d/m/Y')->sortable(),

                Tables\Columns\TextColumn::make('objet')
                    ->label('Objet')->limit(35)
                    ->tooltip(fn($record) => $record->objet),

                Tables\Columns\TextColumn::make('ligneRegieAvance.nomenclature.code')
                    ->label('Nomenclature')->badge()->color('gray'),

                Tables\Columns\TextColumn::make('fournisseur_affiche')
                    ->label('Fournisseur')
                    ->getStateUsing(
                        fn($record) =>
                        $record->fournisseur?->raison_sociale
                            ?? $record->fournisseur_libre ?? '—'
                    )->limit(20),

                Tables\Columns\TextColumn::make('lignes_count')
                    ->label('Lignes')->counts('lignes')->badge()->color('gray'),

                Tables\Columns\TextColumn::make('montant_ttc_total')
                    ->label('TTC')
                    ->getStateUsing(fn($record) => $record->lignes->sum('montant_ttc'))
                    ->money('XAF')->weight('bold')
                    ->color(
                        fn($record) =>
                        $record->lignes->sum('montant_ttc') > $seuil ? 'danger' : 'success'
                    ),

                Tables\Columns\TextColumn::make('montant_ir_total')
                    ->label('IR')
                    ->getStateUsing(fn($record) => $record->lignes->sum('montant_ir'))
                    ->money('XAF')->color('warning'),

                Tables\Columns\TextColumn::make('net_a_payer_total')
                    ->label('Net à Payer')
                    ->getStateUsing(fn($record) => $record->lignes->sum('montant_net'))
                    ->money('XAF')->color('success')->weight('bold'),

                Tables\Columns\BadgeColumn::make('statut')
                    ->colors([
                        'gray'    => 'brouillon',
                        'warning' => 'valide',
                        'success' => 'paye',
                        'danger'  => 'annule',
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'brouillon' => '🔵 Brouillon',
                        'valide'    => '🟡 Validé',
                        'paye'      => '✅ Payé',
                        'annule'    => '🔴 Annulé',
                        default     => $state,
                    }),
            ])
            ->defaultSort('date_depense', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('statut')
                    ->options([
                        'brouillon' => 'Brouillon',
                        'valide'    => 'Validé',
                        'paye'      => 'Payé',
                        'annule'    => 'Annulé',
                    ]),

                Tables\Filters\SelectFilter::make('regie_avance_id')
                    ->label('Régie')
                    ->options(fn() => RegieAvance::pluck('libelle', 'id'))
                    ->searchable(),

                Tables\Filters\Filter::make('periode')
                    ->form([
                        Forms\Components\DatePicker::make('du')->label('Du'),
                        Forms\Components\DatePicker::make('au')->label('Au'),
                    ])
                    ->query(
                        fn($query, array $data) => $query
                            ->when($data['du'], fn($q, $v) =>
                            $q->whereDate('date_depense', '>=', $v))
                            ->when($data['au'], fn($q, $v) =>
                            $q->whereDate('date_depense', '<=', $v))
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => $record->statut === 'brouillon'),

                // ── Aperçu ───────────────────────────────────
                Tables\Actions\Action::make('apercu')
                    ->label('Aperçu')
                    ->icon('heroicon-o-eye')->color('info')
                    ->modalHeading(fn($record) => 'Aperçu — ' . $record->numero)
                    ->modalWidth('7xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer')
                    ->modalContent(function ($record) {
                        $record->load([
                            'lignes',
                            'regieAvance.responsable',
                            'fournisseur',
                            'ligneRegieAvance.nomenclature',
                            'provisionLigneRegie.decaissement',
                        ]);
                        return view('filament.modals.apercu-achat-direct', [
                            'depense' => $record,
                        ]);
                    }),

                // ── Valider : brouillon → valide ─────────────
                Tables\Actions\Action::make('valider')
                    ->label('Valider')
                    ->icon('heroicon-o-check-circle')->color('success')
                    ->visible(
                        fn($record) =>
                        $record->statut === 'brouillon'
                            && auth()->user()?->can('valider_depense_regie')
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Valider l\'achat direct')
                    ->modalDescription(function ($record) {
                        $record->load('lignes');
                        $ttc = $record->lignes->sum('montant_ttc');
                        $nap = $record->lignes->sum('montant_net');
                        return "TTC : " . number_format($ttc, 0, ',', ' ')
                            . " FCFA | NAP : " . number_format($nap, 0, ',', ' ') . " FCFA";
                    })
                    ->action(function ($record) {
                        try {
                            $record->load('lignes');
                            $totalTtc = $record->lignes->sum('montant_ttc');
                            $totalNap = $record->lignes->sum('montant_net');
                            $totalIr  = $record->lignes->sum('montant_ir');
                            $totalTva = $record->lignes->sum('montant_tva');
                            $totalHt  = $record->lignes->sum('montant_ht');

                            if ($record->provision_ligne_regie_id) {
                                ProvisionLigneRegie::findOrFail(
                                    $record->provision_ligne_regie_id
                                )->debiter($totalTtc);
                            }

                            $record->update([
                                'statut'      => 'valide',
                                'montant_ht'  => $totalHt,
                                'montant_tva' => $totalTva,
                                'montant_ttc' => $totalTtc,
                                'montant_ir'  => $totalIr,
                                'net_a_payer' => $totalNap,
                            ]);

                            Notification::make()
                                ->title('✅ Achat direct validé')->success()
                                ->body("TTC : " . number_format($totalTtc, 0, ',', ' ')
                                    . " | NAP : " . number_format($totalNap, 0, ',', ' ') . " FCFA")
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('❌ Erreur')
                                ->danger()->body($e->getMessage())->persistent()->send();
                        }
                    }),

                // ── Retour brouillon : valide → brouillon ────
                Tables\Actions\Action::make('retour_brouillon')
                    ->label('Retour brouillon')
                    ->icon('heroicon-o-arrow-uturn-left')->color('warning')
                    ->visible(
                        fn($record) =>
                        $record->statut === 'valide'
                            && auth()->user()?->can('valider_depense_regie')
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Retourner en brouillon')
                    ->modalDescription('La provision sera créditée et l\'achat repassera en brouillon pour correction.')
                    ->form([
                        Forms\Components\Textarea::make('motif_retour')
                            ->label('Motif du retour')->rows(2)->required(),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            // ✅ Créditer la provision
                            if ($record->provision_ligne_regie_id) {
                                ProvisionLigneRegie::find($record->provision_ligne_regie_id)
                                    ?->crediter($record->montant_ttc);
                            }

                            $record->update([
                                'statut'       => 'brouillon',
                                'montant_ht'   => 0,
                                'montant_tva'  => 0,
                                'montant_ttc'  => 0,
                                'montant_ir'   => 0,
                                'net_a_payer'  => 0,
                                'observations' => ($record->observations ?? '')
                                    . "\n--- RETOUR BROUILLON LE " . now()->format('d/m/Y H:i')
                                    . " par " . auth()->user()->name . " ---\n"
                                    . $data['motif_retour'],
                            ]);

                            Notification::make()
                                ->title('↩ Retourné en brouillon')
                                ->warning()
                                ->body('La provision a été créditée — corrigez et revalidez.')
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('❌ Erreur')
                                ->danger()->body($e->getMessage())->send();
                        }
                    }),

                // ── Payer : valide → paye ────────────────────
                Tables\Actions\Action::make('payer')
                    ->label('Marquer payé')
                    ->icon('heroicon-o-banknotes')->color('success')
                    ->visible(
                        fn($record) =>
                        $record->statut === 'valide'
                            && auth()->user()?->can('valider_depense_regie')
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Confirmer le paiement')
                    ->modalDescription(
                        fn($record) =>
                        "NAP : " . number_format($record->net_a_payer, 0, ',', ' ') . " FCFA"
                    )
                    ->form([
                        Forms\Components\DatePicker::make('date_paiement')
                            ->label('Date de paiement')->default(now())->required(),
                        Forms\Components\TextInput::make('reference_paiement')
                            ->label('Référence paiement')->maxLength(100),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'statut'       => 'paye',
                            'observations' => ($record->observations ?? '')
                                . "\n--- PAYÉ LE " . now()->format('d/m/Y')
                                . " (réf: " . ($data['reference_paiement'] ?? '—') . ") ---",
                        ]);
                        Notification::make()->title('✅ Marqué comme payé')->success()->send();
                    }),

                // ── Annuler : brouillon → annule (définitif) ─
                Tables\Actions\Action::make('annuler')
                    ->label('Annuler définitivement')
                    ->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(
                        fn($record) =>
                        $record->statut === 'brouillon'
                            && auth()->user()?->can('annuler_depense_regie')
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Annuler définitivement')
                    ->modalDescription('⚠️ Cette action est irréversible. L\'achat sera annulé.')
                    ->form([
                        Forms\Components\Textarea::make('motif')
                            ->label('Motif d\'annulation')->rows(2)->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'statut'       => 'annule',
                            'observations' => ($record->observations ?? '')
                                . "\n--- ANNULÉ LE " . now()->format('d/m/Y')
                                . " par " . auth()->user()->name . " ---\n"
                                . $data['motif'],
                        ]);
                        Notification::make()->title('🔴 Achat annulé')->warning()->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery()
            ->where('type_depense', 'achat_direct')
            ->with([
                'regieAvance',
                'fournisseur',
                'ligneRegieAvance.nomenclature',
                'lignes'
            ]);

        $user = auth()->user();
        if ($user && !$user->hasAnyRole([
            'super_admin',
            'admin',
            'daaf',
            'agence_comptable',
            'controleur_financier',
        ])) {
            $query->whereHas(
                'regieAvance',
                fn($q) => $q->where('responsable_id', $user->id)
            );
        }

        return $query;
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAchatsDirects::route('/'),
            'create' => Pages\CreateAchatDirect::route('/create'),
            'edit'   => Pages\EditAchatDirect::route('/{record}/edit'),
            'view'   => Pages\ViewAchatDirect::route('/{record}'),
        ];
    }
}
