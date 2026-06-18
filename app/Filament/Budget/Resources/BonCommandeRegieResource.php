<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\BonCommandeRegieResource\Pages;
use App\Filament\Budget\Resources\BonCommandeRegieResource\RelationManagers;
use App\Models\BonCommandeRegie;
use App\Models\RegieAvance;
use App\Models\ProvisionLigneRegie;
use App\Models\Fournisseur;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Filament\Tables\Enums\ActionsPosition;

class BonCommandeRegieResource extends Resource
{
    protected static ?string $model            = BonCommandeRegie::class;
    protected static ?string $navigationIcon   = 'heroicon-o-document-text';
    protected static ?string $navigationLabel  = 'BCR / BCM';
    protected static ?string $modelLabel       = 'Bon de Commande Régie';
    protected static ?string $pluralModelLabel = 'Bons de Commande Régie';
    protected static ?string $navigationGroup  = 'Régies & Menu Dépenses';
    protected static ?int    $navigationSort   = 3;
    protected static ?string $recordTitleAttribute = 'numero';

    // =========================================================
    // PERMISSIONS
    // =========================================================
    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_bon_commande_regie') ?? false;
    }
    public static function canView($record): bool
    {
        return auth()->user()?->can('view_bon_commande_regie') ?? false;
    }
    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_bon_commande_regie') ?? false;
    }
    public static function canEdit($record): bool
    {
        return auth()->user()?->can('update_bon_commande_regie')
            && $record->statut === 'brouillon';
    }
    public static function canDelete($record): bool
    {
        return auth()->user()?->can('delete_bon_commande_regie')
            && $record->statut === 'brouillon'
            && !$record->engage;
    }

    // =========================================================
    // RECALCUL LIGNE BCR
    // =========================================================
    protected static function recalculerLigneBcr(
        callable|Set $set,
        callable|Get $get
    ): void {
        $qte    = (float) ($get('quantite')         ?? 0);
        $pu     = (float) ($get('prix_unitaire_ht') ?? 0);
        $tauxTv = (float) ($get('taux_tva')         ?? 0);
        $tauxIr = (float) ($get('taux_ir')          ?? 0);

        $ht  = (int) number_format($qte * $pu,               0, '.', '');
        $tva = (int) number_format($ht * ($tauxTv / 100),    0, '.', '');
        $ttc = (int) number_format($ht + $tva,               0, '.', '');
        $ir  = (int) number_format($ht * ($tauxIr / 100),    0, '.', '');
        $net = $ht - $ir;

        $set('montant_ht',  $ht);
        $set('montant_tva', $tva);
        $set('montant_ttc', $ttc);
        $set('montant_ir',  $ir);
        $set('net_a_payer', $net);
    }

    // =========================================================
    // FORMULAIRE
    // =========================================================
    public static function form(Form $form): Form
    {
        return $form->schema([

            // ── Section 1 : Identification ──────────────────────
            Forms\Components\Section::make('Identification')
                ->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('numero')
                            ->label('Numéro')
                            ->disabled()->dehydrated()
                            ->placeholder('Généré automatiquement'),

                        Forms\Components\DatePicker::make('date_emission')
                            ->label('Date d\'émission')
                            ->default(now())->required(),

                        Forms\Components\Placeholder::make('statut_affiche')
                            ->label('Statut')
                            ->content(function ($record) {
                                if (!$record) return 'Brouillon';
                                return match ($record->statut) {
                                    'brouillon' => '🔵 Brouillon',
                                    'valide'    => '🟡 Validé',
                                    'livre'     => '🟢 Livré',
                                    'paye'      => '✅ Payé',
                                    'annule'    => '🔴 Annulé',
                                    default     => $record->statut,
                                };
                            }),
                    ]),
                ]),

            // ── Section 2 : Régie + Ligne budgétaire ────────────
            Forms\Components\Section::make('Régie source et ligne budgétaire')
                ->description('Sélectionnez la régie puis la ligne de nomenclature (provision) qui sera débitée.')
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
                                ->with('exercice')
                                ->get()
                                ->mapWithKeys(fn($r) => [
                                    $r->id => "{$r->numero} — {$r->libelle} ({$r->label_type})"
                                        . " | Dispo: "
                                        . number_format($r->montant_disponible, 0, ',', ' ')
                                        . " FCFA",
                                ]);
                        })
                        ->required()
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (Set $set) {
                            $set('provision_ligne_regie_id', null);
                            $set('ligne_regie_avance_id',   null);
                        })
                        ->columnSpanFull(),

                    Forms\Components\Select::make('provision_ligne_regie_id')
                        ->label('Ligne de nomenclature (Provision disponible)')
                        ->options(function (Get $get) {
                            $regieId = $get('regie_avance_id');
                            if (!$regieId) return [];

                            return ProvisionLigneRegie::whereHas(
                                'decaissement',
                                fn($q) => $q->where('regie_avance_id', $regieId)
                                    ->where('statut', 'verse')
                            )
                                ->where('montant_disponible', '>', 0)
                                ->with(['ligneRegie.nomenclature', 'decaissement'])
                                ->get()
                                ->mapWithKeys(fn($p) => [
                                    $p->id =>
                                    "{$p->ligneRegie->nomenclature->code} — "
                                        . "{$p->ligneRegie->nomenclature->libelle} "
                                        . "| Tranche: {$p->decaissement->libelle_tranche} "
                                        . "| Dispo: "
                                        . number_format($p->montant_disponible, 0, ',', ' ')
                                        . " FCFA",
                                ]);
                        })
                        ->required()
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set) {
                            if (!$state) return;
                            $provision = ProvisionLigneRegie::find($state);
                            $set('ligne_regie_avance_id', $provision?->ligne_regie_avance_id);
                        })
                        ->helperText('Seules les provisions versées et disponibles sont affichées')
                        ->columnSpanFull(),

                    Forms\Components\Hidden::make('ligne_regie_avance_id'),

                    Forms\Components\Placeholder::make('apercu_provision')
                        ->label('Situation de la provision sélectionnée')
                        ->content(function (Get $get) {
                            $provId = $get('provision_ligne_regie_id');
                            if (!$provId) return '← Sélectionnez une ligne de nomenclature';

                            $prov = ProvisionLigneRegie::with([
                                'ligneRegie.nomenclature',
                                'decaissement',
                            ])->find($provId);

                            if (!$prov) return '—';

                            return new \Illuminate\Support\HtmlString(
                                '<div style="background:#f1f5f9;padding:.75rem 1rem;border-radius:.5rem;font-size:.82rem;line-height:1.8;">'
                                    . "<strong>Nomenclature :</strong> {$prov->ligneRegie->nomenclature->code} — {$prov->ligneRegie->nomenclature->libelle}<br>"
                                    . "<strong>Tranche :</strong> {$prov->decaissement->libelle_tranche}<br>"
                                    . "<strong>Provisionné :</strong> " . number_format($prov->montant_provisionne, 0, ',', ' ') . " FCFA<br>"
                                    . "<strong>Consommé :</strong> "    . number_format($prov->montant_consomme,    0, ',', ' ') . " FCFA<br>"
                                    . "<strong style='color:green;'>Disponible :</strong> " . number_format($prov->montant_disponible, 0, ',', ' ') . " FCFA"
                                    . '</div>'
                            );
                        })
                        ->columnSpanFull(),
                ]),

            // ── Section 3 : Fournisseur et objet ────────────────
            Forms\Components\Section::make('Fournisseur et objet')
                ->schema([
                    Forms\Components\Select::make('fournisseur_id')
                        ->label('Fournisseur')
                        ->relationship('fournisseur', 'raison_sociale')
                        ->searchable()->preload()->required()
                        ->createOptionForm([
                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\TextInput::make('raison_sociale')
                                    ->label('Raison sociale')->required(),
                                Forms\Components\TextInput::make('numero_contribuable')
                                    ->label('N° Contribuable'),
                                Forms\Components\TextInput::make('telephone')
                                    ->label('Téléphone')->tel(),
                                Forms\Components\TextInput::make('email')
                                    ->label('Email')->email(),
                            ]),
                        ])
                        ->createOptionUsing(fn(array $data) => Fournisseur::create($data)->id),

                    Forms\Components\Textarea::make('objet')
                        ->label('Objet du bon de commande')
                        ->required()->rows(2)->columnSpanFull(),

                    Forms\Components\Textarea::make('observations')
                        ->label('Observations')->rows(2)->columnSpanFull(),
                ])
                ->columns(2),

            // ── Section 4 : Lignes de commande ──────────────────
            Forms\Components\Section::make('Lignes de commande')
                ->description('Ajoutez les articles/services de ce bon de commande.')
                ->schema([
                    Forms\Components\Grid::make(4)->schema([

                        Forms\Components\TextInput::make('tva_commune')
                            ->label('TVA commune (%)')
                            ->numeric()->default(0)->suffix('%')
                            ->live(debounce: 500)
                            ->dehydrated(false)
                            ->disabled(fn(Forms\Get $get) => (bool) $get('exonere_tva'))
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                if ($get('exonere_tva')) return;
                                $lignes = $get('lignes') ?? [];
                                foreach ($lignes as $index => $ligne) {
                                    $set("lignes.{$index}.taux_tva", (float) ($state ?? 0));
                                    static::recalculerLigneBcr(
                                        fn($k, $v) => $set("lignes.{$index}.{$k}", $v),
                                        fn($k)    => $get("lignes.{$index}.{$k}")
                                    );
                                }
                            })
                            ->helperText('0 = Sans TVA | 19,25 = Standard'),

                        Forms\Components\Toggle::make('exonere_tva')
                            ->label('Exonération TVA')
                            ->default(true)
                            ->live(debounce: 300)
                            ->afterStateHydrated(function ($state, Forms\Set $set, Forms\Get $get) {
                                if ($state) {
                                    $set('tva_commune', 0);
                                    foreach ($get('lignes') ?? [] as $index => $ligne) {
                                        $set("lignes.{$index}.taux_tva", 0);
                                    }
                                }
                            })
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                foreach ($get('lignes') ?? [] as $index => $ligne) {
                                    if ($state) {
                                        $set('tva_commune', 0);
                                        $set("lignes.{$index}.taux_tva", 0);
                                    } else {
                                        $set("lignes.{$index}.taux_tva", (float) ($get('tva_commune') ?? 0));
                                    }
                                    static::recalculerLigneBcr(
                                        fn($k, $v) => $set("lignes.{$index}.{$k}", $v),
                                        fn($k)    => $get("lignes.{$index}.{$k}")
                                    );
                                }
                            })
                            ->helperText('Forcer TVA à 0%'),

                        Forms\Components\TextInput::make('ir_commun')
                            ->label('IR commun (%)')
                            ->numeric()->default(0)->suffix('%')
                            ->live(debounce: 500)
                            ->dehydrated(false)
                            ->disabled(fn(Forms\Get $get) => (bool) $get('exonere_ir'))
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                if ($get('exonere_ir')) return;
                                foreach ($get('lignes') ?? [] as $index => $ligne) {
                                    $set("lignes.{$index}.taux_ir", (float) ($state ?? 0));
                                    static::recalculerLigneBcr(
                                        fn($k, $v) => $set("lignes.{$index}.{$k}", $v),
                                        fn($k)    => $get("lignes.{$index}.{$k}")
                                    );
                                }
                            })
                            ->helperText('0 = Aucun IR | 5,5 = Standard'),

                        Forms\Components\Toggle::make('exonere_ir')
                            ->label('Exonération IR')
                            ->default(true)
                            ->live(debounce: 300)
                            ->afterStateHydrated(function ($state, Forms\Set $set, Forms\Get $get) {
                                if ($state) {
                                    $set('ir_commun', 0);
                                    foreach ($get('lignes') ?? [] as $index => $ligne) {
                                        $set("lignes.{$index}.taux_ir", 0);
                                    }
                                }
                            })
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                foreach ($get('lignes') ?? [] as $index => $ligne) {
                                    if ($state) {
                                        $set('ir_commun', 0);
                                        $set("lignes.{$index}.taux_ir", 0);
                                    } else {
                                        $set("lignes.{$index}.taux_ir", (float) ($get('ir_commun') ?? 0));
                                    }
                                    static::recalculerLigneBcr(
                                        fn($k, $v) => $set("lignes.{$index}.{$k}", $v),
                                        fn($k)    => $get("lignes.{$index}.{$k}")
                                    );
                                }
                            })
                            ->helperText('Forcer IR à 0%'),
                    ]),

                    Forms\Components\Placeholder::make('total_commande')
                        ->label('Total TTC commande')
                        ->content(function (Forms\Get $get) {
                            $total = collect($get('lignes') ?? [])
                                ->sum(fn($l) => (float) ($l['montant_ttc'] ?? 0));
                            return number_format($total, 0, ',', ' ') . ' FCFA';
                        }),

                    Forms\Components\Repeater::make('lignes')
                        ->relationship('lignes')
                        ->schema([
                            Forms\Components\Grid::make(3)->schema([
                                Forms\Components\Select::make('reference_mercuriale_id')
                                    ->label('Référence Mercuriale')
                                    ->searchable()
                                    ->getSearchResultsUsing(function (string $search) {
                                        if (strlen($search) < 3) {
                                            return ['manual' => '➕ Saisie manuelle (tapez au moins 3 caractères)'];
                                        }
                                        $exercice = \App\Models\Exercice::getActif();
                                        if (!$exercice) return ['manual' => '➕ Saisie manuelle'];

                                        $results = \Cache::remember(
                                            "mercuriale_search_{$exercice->id}_" . md5($search),
                                            now()->addMinutes(5),
                                            fn() => \App\Models\ReferenceMercuriale::where('exercice_id', $exercice->id)
                                                ->where('actif', true)
                                                ->where(function ($q) use ($search) {
                                                    $q->where('code_reference', 'LIKE', "%{$search}%")
                                                        ->orWhere('designation',  'LIKE', "%{$search}%")
                                                        ->orWhere('rubrique',     'LIKE', "%{$search}%");
                                                })
                                                ->limit(50)
                                                ->get()
                                                ->mapWithKeys(fn($ref) => [
                                                    $ref->id => "{$ref->code_reference} — {$ref->designation} ({$ref->unite}) "
                                                        . number_format($ref->prix_reference, 0, ',', ' ') . " FCFA",
                                                ])
                                        );
                                        return ['manual' => '➕ Saisie manuelle'] + $results->toArray();
                                    })
                                    ->getOptionLabelUsing(function ($value) {
                                        if (!$value || $value === 'manual') return '➕ Saisie manuelle';
                                        return \Cache::remember(
                                            "mercuriale_label_{$value}",
                                            now()->addMinutes(10),
                                            function () use ($value) {
                                                $ref = \App\Models\ReferenceMercuriale::find($value);
                                                if (!$ref) return "Référence #{$value}";
                                                return "{$ref->code_reference} — {$ref->designation} ({$ref->unite}) "
                                                    . number_format($ref->prix_reference, 0, ',', ' ') . " FCFA";
                                            }
                                        );
                                    })
                                    ->live(debounce: 800)
                                    ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                                        if (!$state || $state === 'manual') {
                                            $set('reference_personnalisee', null);
                                            return;
                                        }
                                        $ref = \Cache::remember(
                                            "mercuriale_full_{$state}",
                                            now()->addMinutes(10),
                                            fn() => \App\Models\ReferenceMercuriale::find($state)
                                        );
                                        if ($ref) {
                                            $mapUnites = [
                                                'kg' => 'kg',
                                                'kilogramme' => 'kg',
                                                'kilo' => 'kg',
                                                'l' => 'litre',
                                                'litre' => 'litre',
                                                'litres' => 'litre',
                                                'm' => 'mètre',
                                                'mètre' => 'mètre',
                                                'metre' => 'mètre',
                                                'h' => 'heure',
                                                'heure' => 'heure',
                                                'heures' => 'heure',
                                                'j' => 'jour',
                                                'jour' => 'jour',
                                                'jours' => 'jour',
                                                'lot' => 'lot',
                                                'lots' => 'lot',
                                                'forfait' => 'forfait',
                                            ];
                                            $set('designation',             $ref->designation);
                                            $set('unite',                   $mapUnites[strtolower(trim($ref->unite ?? ''))] ?? 'pièce');
                                            $set('prix_unitaire_ht',        $ref->prix_reference);
                                            $set('reference_personnalisee', null);
                                            static::recalculerLigneBcr($set, $get);
                                        }
                                    })
                                    ->dehydrateStateUsing(fn($state) => $state === 'manual' ? null : $state)
                                    ->helperText('Tapez au moins 3 caractères pour rechercher')
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('reference_personnalisee')
                                    ->label('Réf. personnalisée')
                                    ->maxLength(100)
                                    ->placeholder('Ex: REF-001')
                                    ->visible(
                                        fn(Forms\Get $get) =>
                                        $get('reference_mercuriale_id') === 'manual'
                                            || !$get('reference_mercuriale_id')
                                    )
                                    ->columnSpan(1),
                            ])->columnSpanFull(),

                            Forms\Components\TextInput::make('designation')
                                ->label('Désignation')
                                ->required()
                                ->columnSpanFull(),

                            Forms\Components\Grid::make(8)->schema([
                                Forms\Components\TextInput::make('quantite')
                                    ->label('Qté')
                                    ->numeric()->default(1)->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(
                                        fn(Forms\Get $get, Forms\Set $set) =>
                                        static::recalculerLigneBcr($set, $get)
                                    )
                                    ->columnSpan(1),

                                Forms\Components\Select::make('unite')
                                    ->label('Unité')
                                    ->options([
                                        'pièce' => 'Pièce',
                                        'lot' => 'Lot',
                                        'kg' => 'Kg',
                                        'litre' => 'L',
                                        'mètre' => 'M',
                                        'heure' => 'H',
                                        'jour' => 'J',
                                        'forfait' => 'Forfait',
                                    ])
                                    ->default('pièce')->required()
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('prix_unitaire_ht')
                                    ->label('P.U HT')
                                    ->numeric()->required()->prefix('FCFA')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(
                                        fn(Forms\Get $get, Forms\Set $set) =>
                                        static::recalculerLigneBcr($set, $get)
                                    )
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('taux_tva')
                                    ->label('TVA %')
                                    ->numeric()->default(0)->suffix('%')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(
                                        fn(Forms\Get $get, Forms\Set $set) =>
                                        static::recalculerLigneBcr($set, $get)
                                    )
                                    ->disabled(fn(Forms\Get $get) => (bool) $get('../../exonere_tva'))
                                    ->dehydrated(true)
                                    ->afterStateHydrated(function ($state, Forms\Set $set, Forms\Get $get) {
                                        if ($get('../../exonere_tva')) $set('taux_tva', 0);
                                    })
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('taux_ir')
                                    ->label('IR %')
                                    ->numeric()->default(0)->suffix('%')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(
                                        fn(Forms\Get $get, Forms\Set $set) =>
                                        static::recalculerLigneBcr($set, $get)
                                    )
                                    ->disabled(fn(Forms\Get $get) => (bool) $get('../../exonere_ir'))
                                    ->dehydrated(true)
                                    ->afterStateHydrated(function ($state, Forms\Set $set, Forms\Get $get) {
                                        if ($get('../../exonere_ir')) $set('taux_ir', 0);
                                    })
                                    ->columnSpan(1),

                                Forms\Components\Placeholder::make('montant_ttc_affiche')
                                    ->label('TTC')
                                    ->content(
                                        fn(Forms\Get $get) =>
                                        number_format((float) ($get('montant_ttc') ?? 0), 0, ',', ' ') . ' F'
                                    )
                                    ->columnSpan(1),

                                Forms\Components\Placeholder::make('net_a_payer_affiche')
                                    ->label('Net à payer')
                                    ->content(
                                        fn(Forms\Get $get) =>
                                        number_format((float) ($get('net_a_payer') ?? 0), 0, ',', ' ') . ' F'
                                    )
                                    ->columnSpan(1),
                            ]),

                            Forms\Components\Hidden::make('montant_ht')->default(0),
                            Forms\Components\Hidden::make('montant_tva')->default(0),
                            Forms\Components\Hidden::make('montant_ttc')->default(0),
                            Forms\Components\Hidden::make('montant_ir')->default(0),
                            Forms\Components\Hidden::make('net_a_payer')->default(0),
                            Forms\Components\Hidden::make('numero_ligne')->default(1),

                            Forms\Components\Textarea::make('observations')
                                ->label('Observations')->rows(1)->columnSpanFull(),
                        ])
                        ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                            static $numero = 0;
                            $numero++;
                            $data['numero_ligne'] = $numero;
                            return $data;
                        })
                        ->orderColumn('numero_ligne')
                        ->defaultItems(1)
                        ->addActionLabel('➕ Ajouter une ligne')
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(
                            fn(array $state): ?string =>
                            !empty($state['designation'] ?? null)
                                ? "{$state['designation']} — "
                                . number_format((float) ($state['montant_ttc'] ?? 0), 0, ',', ' ')
                                . ' FCFA TTC'
                                : 'Nouvelle ligne'
                        )
                        ->live()
                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                            $totaux = collect($state ?? [])->reduce(function ($carry, $ligne) {
                                return [
                                    'montant_ht'  => $carry['montant_ht']  + (float) ($ligne['montant_ht']  ?? 0),
                                    'montant_tva' => $carry['montant_tva'] + (float) ($ligne['montant_tva'] ?? 0),
                                    'montant_ttc' => $carry['montant_ttc'] + (float) ($ligne['montant_ttc'] ?? 0),
                                    'montant_ir'  => $carry['montant_ir']  + (float) ($ligne['montant_ir']  ?? 0),
                                    'net_a_payer' => $carry['net_a_payer'] + (float) ($ligne['net_a_payer'] ?? 0),
                                ];
                            }, ['montant_ht' => 0, 'montant_tva' => 0, 'montant_ttc' => 0, 'montant_ir' => 0, 'net_a_payer' => 0]);

                            $set('montant_ht',  $totaux['montant_ht']);
                            $set('montant_tva', $totaux['montant_tva']);
                            $set('montant_ttc', $totaux['montant_ttc']);
                            $set('montant_ir',  $totaux['montant_ir']);
                            $set('net_a_payer', $totaux['net_a_payer']);
                        }),
                ])
                ->columns(1),

            // ── Section 5 : Totaux (lecture seule) ──────────────
            Forms\Components\Section::make('Totaux')
                ->schema([
                    Forms\Components\Grid::make(5)->schema([
                        Forms\Components\Placeholder::make('total_ht')
                            ->label('Total HT')
                            ->content(
                                fn($record) => $record
                                    ? number_format($record->montant_ht, 0, ',', ' ') . ' FCFA'
                                    : '—'
                            ),
                        Forms\Components\Placeholder::make('total_tva')
                            ->label('Total TVA')
                            ->content(
                                fn($record) => $record
                                    ? number_format($record->montant_tva, 0, ',', ' ') . ' FCFA'
                                    : '—'
                            ),
                        Forms\Components\Placeholder::make('total_ttc')
                            ->label('Total TTC')
                            ->content(
                                fn($record) => $record
                                    ? number_format($record->montant_ttc, 0, ',', ' ') . ' FCFA'
                                    : '—'
                            ),
                        Forms\Components\Placeholder::make('total_ir')
                            ->label('Total IR')
                            ->content(
                                fn($record) => $record
                                    ? number_format($record->montant_ir, 0, ',', ' ') . ' FCFA'
                                    : '—'
                            ),
                        Forms\Components\Placeholder::make('total_net')
                            ->label('Net à payer')
                            ->content(
                                fn($record) => $record
                                    ? number_format($record->net_a_payer, 0, ',', ' ') . ' FCFA'
                                    : '—'
                            ),
                    ]),
                ])
                ->visible(fn($record) => $record && $record->exists)
                ->collapsed(),
        ]);
    }

    // =========================================================
    // TABLEAU
    // =========================================================
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('N° BCR/BCM')
                    ->searchable()->sortable()->weight('bold')->copyable(),

                Tables\Columns\TextColumn::make('regieAvance.numero')
                    ->label('Régie')->badge()->color('info')->searchable(),

                Tables\Columns\TextColumn::make('regieAvance.type')
                    ->label('Type')
                    ->formatStateUsing(fn($state) => match ($state) {
                        'rav'          => 'RAV',
                        'menu_depense' => 'MD',
                        default        => $state,
                    })
                    ->badge()
                    ->color(fn($state) => $state === 'rav' ? 'primary' : 'warning'),

                Tables\Columns\TextColumn::make('ligneRegieAvance.nomenclature.code')
                    ->label('Nomenclature')
                    ->badge()->color('gray'),

                Tables\Columns\TextColumn::make('fournisseur.raison_sociale')
                    ->label('Fournisseur')->searchable()->limit(25),

                Tables\Columns\TextColumn::make('date_emission')
                    ->label('Date')->date('d/m/Y')->sortable(),

                Tables\Columns\TextColumn::make('montant_ttc')
                    ->label('TTC')
                    ->money('XAF')->sortable()->weight('bold'),

                Tables\Columns\TextColumn::make('montant_ir')
                    ->label('IR')->money('XAF')->color('warning'),

                Tables\Columns\TextColumn::make('net_a_payer')
                    ->label('Net')->money('XAF')->color('success'),

                // ✅ Statut engagement détaillé (partiel vs total)
                Tables\Columns\TextColumn::make('statut_engagement')
                    ->label('Engagement')
                    ->getStateUsing(function ($record) {
                        if (!$record || !$record->engage) return 'Non engagé';

                        $pct    = (float) ($record->pourcentage_engage ?? 100);
                        // ✅ montant_engage peut être 0 stocké — fallback si <= 0
                        $engage = (float) $record->montant_engage > 0
                            ? (float) $record->montant_engage
                            : (float) $record->montant_ttc;
                        $reste  = (float) ($record->reste_a_engager ?? 0);

                        if ($pct >= 100 || $reste <= 0) {
                            return '✅ Total — ' . number_format($engage, 0, ',', ' ') . ' F';
                        }

                        return "⚡ {$pct}% — " . number_format($engage, 0, ',', ' ') . " F engagé";
                    })
                    ->badge()
                    ->color(function ($record) {
                        if (!$record || !$record->engage) return 'gray';
                        $pct = (float) ($record->pourcentage_engage ?? 100);
                        return match (true) {
                            $pct >= 100 => 'success',
                            $pct >= 50  => 'warning',
                            default     => 'danger',
                        };
                    })
                    ->toggleable(),

                // ✅ Reste à engager — visible jusqu'au paiement total
                Tables\Columns\TextColumn::make('reste_a_engager')
                    ->label('Reste à engager')
                    ->formatStateUsing(function ($state, $record) {
                        if (!$record->engage) return '—';
                        $reste = (float) ($record->reste_a_engager ?? 0);
                        if ($reste <= 0) return '✅ Soldé';
                        return number_format($reste, 0, ',', ' ') . ' FCFA';
                    })
                    ->color(function ($record) {
                        if (!$record->engage) return 'gray';
                        return ((float) ($record->reste_a_engager ?? 0)) > 0 ? 'danger' : 'success';
                    })
                    ->badge()
                    ->visible(fn($record) => $record && (bool) $record->engage)
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('statut')
                    ->colors([
                        'gray'    => 'brouillon',
                        'warning' => 'valide',
                        'info'    => 'livre_partiellement',
                        'success' => fn($state) => in_array($state, ['livre', 'paye']),
                        'danger'  => 'annule',
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'brouillon'           => 'Brouillon',
                        'valide'              => 'Validé',
                        'livre_partiellement' => 'Livré part.',
                        'livre'               => 'Livré',
                        'paye'                => 'Payé',
                        'annule'              => 'Annulé',
                        default               => $state,
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('periode')
                    ->form([
                        Forms\Components\Select::make('periode')
                            ->label('Période')
                            ->options([
                                'today'        => "Aujourd'hui",
                                'yesterday'    => 'Hier',
                                'this_week'    => 'Cette semaine',
                                'last_week'    => 'Semaine dernière',
                                'this_month'   => 'Ce mois',
                                'last_month'   => 'Mois dernier',
                                'this_quarter' => 'Ce trimestre',
                                'last_quarter' => 'Trimestre dernier',
                                'this_year'    => 'Cette année',
                                'last_year'    => 'Année dernière',
                            ])
                            ->default('today')
                            ->placeholder('Toutes les périodes'),
                    ])
                    ->default(['periode' => 'today'])
                    ->query(function ($query, array $data) {
                        return $query->when(
                            $data['periode'] ?? null,
                            fn($q, $periode) => match ($periode) {
                                'today'        => $q->whereDate('date_emission', today()),
                                'yesterday'    => $q->whereDate('date_emission', today()->subDay()),
                                'this_week'    => $q->whereBetween('date_emission', [now()->startOfWeek(), now()->endOfWeek()]),
                                'last_week'    => $q->whereBetween('date_emission', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()]),
                                'this_month'   => $q->whereMonth('date_emission', now()->month)->whereYear('date_emission', now()->year),
                                'last_month'   => $q->whereMonth('date_emission', now()->subMonth()->month)->whereYear('date_emission', now()->subMonth()->year),
                                'this_quarter' => $q->whereBetween('date_emission', [now()->startOfQuarter(), now()->endOfQuarter()]),
                                'last_quarter' => $q->whereBetween('date_emission', [now()->subQuarter()->startOfQuarter(), now()->subQuarter()->endOfQuarter()]),
                                'this_year'    => $q->whereYear('date_emission', now()->year),
                                'last_year'    => $q->whereYear('date_emission', now()->subYear()->year),
                                default        => $q,
                            }
                        );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (!($data['periode'] ?? null)) return null;
                        $labels = [
                            'today'        => "Aujourd'hui",
                            'yesterday'    => 'Hier',
                            'this_week'    => 'Cette semaine',
                            'last_week'    => 'Semaine dernière',
                            'this_month'   => 'Ce mois',
                            'last_month'   => 'Mois dernier',
                            'this_quarter' => 'Ce trimestre',
                            'last_quarter' => 'Trimestre dernier',
                            'this_year'    => 'Cette année',
                            'last_year'    => 'Année dernière',
                        ];
                        return 'Période : ' . ($labels[$data['periode']] ?? $data['periode']);
                    }),

                // ── Filtres existants (inchangés) ───────────────────────
                Tables\Filters\SelectFilter::make('statut')
                    ->options([
                        'brouillon'           => 'Brouillon',
                        'valide'              => 'Validé',
                        'livre_partiellement' => 'Livré partiellement',
                        'livre'               => 'Livré',
                        'paye'                => 'Payé',
                        'annule'              => 'Annulé',
                    ]),

                Tables\Filters\TernaryFilter::make('engage')
                    ->label('Engagé')
                    ->trueLabel('Engagés')
                    ->falseLabel('Non engagés'),

                Tables\Filters\SelectFilter::make('regie_avance_id')
                    ->label('Régie')
                    ->options(fn() => RegieAvance::pluck('libelle', 'id'))
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([

                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),

                    // ── Valider ───────────────────────────────────────
                    Tables\Actions\Action::make('valider')
                        ->label('Valider')
                        ->icon('heroicon-o-check-circle')->color('success')
                        ->visible(
                            fn($record) =>
                            $record
                                && $record->statut === 'brouillon'
                                && $record->lignes()->count() > 0
                                && auth()->user()?->can('valider_bon_commande_regie')
                        )
                        ->requiresConfirmation()
                        ->modalDescription(
                            fn($record) =>
                            "Valider le {$record->numero} — "
                                . number_format($record->montant_ttc, 0, ',', ' ') . " FCFA TTC ?"
                        )
                        ->action(function ($record) {
                            $record->update(['statut' => 'valide']);
                            Notification::make()->title('✅ BCR/BCM validé')->success()->send();
                        }),

                    // ── Engager ───────────────────────────────────────
                    Tables\Actions\Action::make('engager')
                        ->label('Engager')
                        ->icon('heroicon-o-banknotes')->color('primary')
                        ->visible(
                            fn($record) =>
                            $record
                                && $record->statut === 'valide'
                                && !$record->engage
                                && auth()->user()?->can('valider_bon_commande_regie')
                        )
                        ->form(function ($record) {
                            $prov       = $record->provisionLigneRegie;
                            $montantTtc = (float) $record->montant_ttc;
                            $disponible = (float) ($prov?->montant_disponible ?? 0);

                            return [
                                Forms\Components\Placeholder::make('info_bcr')
                                    ->label('Bon de commande')
                                    ->content(new \Illuminate\Support\HtmlString(
                                        '<div style="background:#f1f5f9;padding:.75rem;border-radius:.5rem;font-size:.82rem;line-height:1.8;">'
                                            . "<strong>Montant TTC total :</strong> " . number_format($montantTtc, 0, ',', ' ') . " FCFA<br>"
                                            . "<strong>Provision disponible :</strong> " . number_format($disponible, 0, ',', ' ') . " FCFA<br>"
                                            . "<strong>Ligne :</strong> " . ($prov?->ligneRegie?->nomenclature?->code ?? '—')
                                            . " — " . ($prov?->ligneRegie?->nomenclature?->libelle ?? '—')
                                            . '</div>'
                                    ))
                                    ->columnSpanFull(),

                                Forms\Components\Radio::make('mode_engagement')
                                    ->label('Mode d\'engagement')
                                    ->options([
                                        'total'   => '💯 Total — engager la totalité du TTC',
                                        'partiel' => '📊 Partiel — engager un pourcentage ou un montant',
                                    ])
                                    ->default('total')
                                    ->live()
                                    ->columnSpanFull(),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Select::make('type_partiel')
                                            ->label('Calculer par')
                                            ->options([
                                                'pourcentage' => '% Pourcentage',
                                                'montant'     => '💵 Montant fixe',
                                            ])
                                            ->default('pourcentage')
                                            ->live()
                                            ->required(fn(Forms\Get $get) => $get('mode_engagement') === 'partiel'),

                                        Forms\Components\TextInput::make('pourcentage')
                                            ->label('Pourcentage (%)')
                                            ->numeric()->suffix('%')->default(40)
                                            ->minValue(1)->maxValue(100)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, Forms\Set $set) use ($montantTtc) {
                                                $set('montant_a_engager', round($montantTtc * ((float) $state / 100), 2));
                                            })
                                            ->visible(
                                                fn(Forms\Get $get) =>
                                                $get('mode_engagement') === 'partiel'
                                                    && $get('type_partiel') === 'pourcentage'
                                            ),

                                        Forms\Components\TextInput::make('montant_fixe')
                                            ->label('Montant à engager (FCFA)')
                                            ->numeric()->prefix('FCFA')
                                            ->minValue(1)->maxValue($montantTtc)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, Forms\Set $set) use ($montantTtc) {
                                                $pct = $montantTtc > 0 ? round(((float) $state / $montantTtc) * 100, 2) : 0;
                                                $set('montant_a_engager', (float) $state);
                                                $set('pourcentage', $pct);
                                            })
                                            ->visible(
                                                fn(Forms\Get $get) =>
                                                $get('mode_engagement') === 'partiel'
                                                    && $get('type_partiel') === 'montant'
                                            ),
                                    ])
                                    ->visible(fn(Forms\Get $get) => $get('mode_engagement') === 'partiel'),

                                Forms\Components\Placeholder::make('resume_engagement')
                                    ->label('Montant qui sera engagé')
                                    ->content(function (Forms\Get $get) use ($montantTtc, $disponible) {
                                        if ($get('mode_engagement') === 'total') {
                                            $montant = $montantTtc;
                                            $pct     = 100;
                                        } elseif ($get('type_partiel') === 'pourcentage') {
                                            $pct     = (float) ($get('pourcentage') ?? 40);
                                            $montant = round($montantTtc * ($pct / 100), 2);
                                        } else {
                                            $montant = (float) ($get('montant_fixe') ?? 0);
                                            $pct     = $montantTtc > 0
                                                ? round(($montant / $montantTtc) * 100, 2)
                                                : 0;
                                        }

                                        $reste     = $montantTtc - $montant;
                                        $suffisant = $montant <= $disponible;
                                        $couleur   = $suffisant ? 'green' : 'red';
                                        $alerte    = $suffisant ? '' : ' ⚠️ Provision insuffisante !';

                                        return new \Illuminate\Support\HtmlString(
                                            '<div style="background:#f8fafc;padding:.75rem;border-radius:.5rem;font-size:.85rem;line-height:2;">'
                                                . "<strong style='color:{$couleur};font-size:1rem;'>"
                                                . number_format($montant, 0, ',', ' ') . " FCFA ({$pct}%)</strong>{$alerte}<br>"
                                                . "<strong>Reste non engagé après :</strong> "
                                                . number_format($reste, 0, ',', ' ') . " FCFA<br>"
                                                . "<strong>Provision disponible :</strong> "
                                                . number_format($disponible, 0, ',', ' ') . " FCFA"
                                                . '</div>'
                                        );
                                    })
                                    ->columnSpanFull(),

                                Forms\Components\Textarea::make('commentaire')
                                    ->label('Commentaire (optionnel)')
                                    ->rows(2)->columnSpanFull(),
                            ];
                        })
                        ->modalHeading('Engager le bon de commande')
                        ->modalWidth('xl')
                        ->action(function ($record, array $data) {
                            try {
                                $montantTtc = (float) $record->montant_ttc;

                                if ($data['mode_engagement'] === 'total') {
                                    $montantAEngager = $montantTtc;
                                } elseif (($data['type_partiel'] ?? 'pourcentage') === 'pourcentage') {
                                    $montantAEngager = round(
                                        $montantTtc * ((float) ($data['pourcentage'] ?? 100) / 100),
                                        2
                                    );
                                } else {
                                    $montantAEngager = (float) ($data['montant_fixe'] ?? $montantTtc);
                                }

                                $pourcentage = $montantTtc > 0
                                    ? round(($montantAEngager / $montantTtc) * 100, 2)
                                    : 100;

                                $record->engager(
                                    montantPartiel: $montantAEngager,
                                    pourcentage: $pourcentage,
                                    commentaire: $data['commentaire'] ?? null
                                );

                                $record->forceFill([
                                    'montant_engage'     => $montantAEngager,
                                    'pourcentage_engage' => $pourcentage,
                                    'reste_a_engager'    => $montantTtc - $montantAEngager,
                                ])->save();

                                $estPartiel = $pourcentage < 100;
                                $msg = $estPartiel
                                    ? "⚡ BCR engagé partiellement à {$pourcentage}% ("
                                    . number_format($montantAEngager, 0, ',', ' ') . " FCFA sur "
                                    . number_format($montantTtc, 0, ',', ' ') . " FCFA TTC)"
                                    : '✅ BCR engagé totalement — provision débitée';

                                Notification::make()->title($msg)->success()->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('❌ Erreur engagement')
                                    ->danger()->body($e->getMessage())->persistent()->send();
                            }
                        }),

                    // ── Désengager ────────────────────────────────────
                    Tables\Actions\Action::make('desengager')
                        ->label('Désengager')
                        ->icon('heroicon-o-arrow-uturn-left')->color('warning')
                        ->visible(
                            fn($record) =>
                            $record
                                && $record->engage
                                && $record->statut === 'valide'
                                && auth()->user()?->can('annuler_bon_commande_regie')
                        )
                        ->requiresConfirmation()
                        ->action(function ($record) {
                            try {
                                $record->desengager();
                                $record->updateQuietly([
                                    'montant_engage'     => 0,
                                    'pourcentage_engage' => 0,
                                    'reste_a_engager'    => $record->montant_ttc,
                                ]);
                                Notification::make()
                                    ->title('↩ BCR désengagé — provision restituée')
                                    ->warning()->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('❌ Erreur')->danger()
                                    ->body($e->getMessage())->send();
                            }
                        }),

                    // ── Livré ─────────────────────────────────────────
                    Tables\Actions\Action::make('livrer')
                        ->label('Marquer livré')
                        ->icon('heroicon-o-truck')->color('info')
                        ->visible(
                            fn($record) =>
                            $record
                                && $record->statut === 'valide'
                                && $record->engage
                        )
                        ->requiresConfirmation()
                        ->action(fn($record) => $record->update(['statut' => 'livre'])),

                    // ── Payé ──────────────────────────────────────────
                    Tables\Actions\Action::make('payer')
                        ->label('Marquer payé')
                        ->icon('heroicon-o-banknotes')->color('success')
                        ->visible(fn($record) => $record && $record->statut === 'livre')
                        ->form(function ($record) {
                            $pct   = (float) ($record->pourcentage_engage ?? 100);
                            $reste = (float) ($record->reste_a_engager    ?? 0);

                            if ($pct >= 100 || $reste <= 0) return [];

                            return [
                                Forms\Components\Placeholder::make('alerte_partiel')
                                    ->label('')
                                    ->content(new \Illuminate\Support\HtmlString(
                                        '<div style="background:#fef9c3;border:1px solid #ca8a04;
                                border-radius:.5rem;padding:.75rem;font-size:.85rem;line-height:1.8;">'
                                            . "⚠️ <strong>Engagement partiel non soldé</strong><br>"
                                            . "Engagé : <strong>{$pct}% ("
                                            . number_format($record->montant_engage, 0, ',', ' ') . " FCFA)</strong><br>"
                                            . "Reste non engagé : <strong style='color:#dc2626;'>"
                                            . number_format($reste, 0, ',', ' ') . " FCFA (" . (100 - $pct) . "%)</strong><br>"
                                            . "Cochez ci-dessous pour solder automatiquement avant paiement."
                                            . '</div>'
                                    ))
                                    ->columnSpanFull(),

                                Forms\Components\Toggle::make('solder_engagement')
                                    ->label('Solder le reste avant paiement (' . number_format($reste, 0, ',', ' ') . ' FCFA)')
                                    ->default(true)
                                    ->helperText('Engagera automatiquement le montant restant depuis la provision')
                                    ->columnSpanFull(),
                            ];
                        })
                        ->requiresConfirmation(
                            fn($record) =>
                            !$record
                                || (float) ($record->pourcentage_engage ?? 100) >= 100
                                || (float) ($record->reste_a_engager ?? 0) <= 0
                        )
                        ->modalHeading('Marquer le BCR comme payé')
                        ->action(function ($record, array $data) {
                            DB::transaction(function () use ($record, $data) {
                                $pct   = (float) ($record->pourcentage_engage ?? 100);
                                $reste = (float) ($record->reste_a_engager    ?? 0);

                                if ($reste > 0 && ($data['solder_engagement'] ?? true)) {
                                    try {
                                        $record->engager(
                                            montantPartiel: $reste,
                                            pourcentage: 100 - $pct,
                                            commentaire: 'Solde automatique avant paiement'
                                        );
                                        $record->updateQuietly([
                                            'montant_engage'     => $record->montant_ttc,
                                            'pourcentage_engage' => 100,
                                            'reste_a_engager'    => 0,
                                        ]);
                                    } catch (\Exception $e) {
                                        \Log::warning('Solde engagement BCR: ' . $e->getMessage());
                                    }
                                }

                                $record->update(['statut' => 'paye']);

                                $prov = $record->provisionLigneRegie;
                                if ($prov?->decaissement) {
                                    $prov->decaissement->load('provisions');
                                    $prov->decaissement->recalculerDepenses();
                                }
                            });

                            Notification::make()
                                ->title('✅ BCR payé')
                                ->success()
                                ->body('Dépenses du décaissement recalculées.')
                                ->send();
                        }),

                    // ── Annuler ───────────────────────────────────────
                    Tables\Actions\Action::make('annuler')
                        ->label('Annuler')
                        ->icon('heroicon-o-x-circle')->color('danger')
                        ->visible(
                            fn($record) =>
                            $record
                                && in_array($record->statut, ['brouillon', 'valide'])
                                && auth()->user()?->can('annuler_bon_commande_regie')
                        )
                        ->requiresConfirmation()
                        ->form([
                            Forms\Components\Textarea::make('motif')
                                ->label('Motif')->rows(2)->required(),
                        ])
                        ->action(function ($record, array $data) {
                            if ($record->engage) {
                                $record->desengager();
                            }
                            $record->update([
                                'statut'         => 'annule',
                                'observations'   => ($record->observations ?? '')
                                    . "\n--- ANNULÉ " . now()->format('d/m/Y') . " ---\n"
                                    . $data['motif'],
                                'montant_engage'     => 0,
                                'pourcentage_engage' => 0,
                                'reste_a_engager'    => 0,
                            ]);
                            Notification::make()->title('BCR annulé')->warning()->send();
                        }),

                    // ── Sous-menu PDF (BCA) ─────────────────────────────
                    Tables\Actions\ActionGroup::make([
                        Tables\Actions\Action::make('apercu_bca')
                            ->label('Aperçu BCA')
                            ->icon('heroicon-o-eye')
                            ->color('info')
                            ->action(function ($record, $livewire) {
                                $livewire->dispatch(
                                    'open-url-new-tab',
                                    url: route('bcr.pdf.apercu', $record)
                                );
                            }),

                        Tables\Actions\Action::make('telecharger_bca')
                            ->label('Télécharger BCA')
                            ->icon('heroicon-o-arrow-down-tray')
                            ->color('success')
                            ->visible(fn($record) => $record->statut !== 'brouillon')
                            ->action(function ($record, $livewire) {
                                $livewire->dispatch(
                                    'open-url-new-tab',
                                    url: route('bcr.pdf.telecharger', $record)
                                );
                            }),
                    ])
                        ->label('📄 BCA')
                        ->icon('heroicon-o-document-text'),

                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')
                    ->button()
                    ->size('sm'),
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LignesBcrRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListBonsCommandeRegies::route('/'),
            'create' => Pages\CreateBonCommandeRegie::route('/create'),
            'edit'   => Pages\EditBonCommandeRegie::route('/{record}/edit'),
            'view'   => Pages\ViewBonCommandeRegie::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['regieAvance', 'fournisseur', 'lignes', 'ligneRegieAvance.nomenclature']);

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
}
