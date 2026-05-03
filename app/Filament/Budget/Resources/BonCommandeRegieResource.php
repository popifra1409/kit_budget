<?php
// app/Filament/Budget/Resources/BonCommandeRegieResource.php

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

class BonCommandeRegieResource extends Resource
{
    protected static ?string $model           = BonCommandeRegie::class;
    protected static ?string $navigationIcon  = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'BCR / BCM';
    protected static ?string $modelLabel      = 'Bon de Commande Régie';
    protected static ?string $pluralModelLabel = 'Bons de Commande Régie';
    protected static ?string $navigationGroup = 'Régies & Menu Dépenses';
    protected static ?int    $navigationSort  = 3;
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

    protected static function recalculerLigneBcr(
        callable|\Filament\Forms\Set $set,
        callable|\Filament\Forms\Get $get
    ): void {
        $qte    = (float) ($get('quantite')         ?? 0);
        $pu     = (float) ($get('prix_unitaire_ht') ?? 0);
        $tauxTv = (float) ($get('taux_tva')         ?? 19.25);
        $tauxIr = (float) ($get('taux_ir')          ?? 0);

        $ht  = round($qte * $pu, 2);
        $tva = round($ht * ($tauxTv / 100), 2);
        $ttc = round($ht + $tva, 2);
        $ir  = round($ht * ($tauxIr / 100), 2);
        $net = round($ht - $ir, 2);

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

            // ── Section 1 : En-tête ───────────────────────────
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

            // ── Section 2 : Régie + Ligne de nomenclature ─────
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
                                        'agence_comptable'
                                    ]),
                                    fn($q) => $q->where('responsable_id', auth()->id())
                                )
                                ->with('exercice')
                                ->get()
                                ->mapWithKeys(fn($r) => [
                                    $r->id => "{$r->numero} — {$r->libelle} ({$r->label_type})"
                                        . " | Dispo: "
                                        . number_format($r->montant_disponible, 0, ',', ' ')
                                        . " FCFA"
                                ]);
                        })
                        ->required()
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (Set $set) {
                            // Réinitialiser les sélections dépendantes
                            $set('provision_ligne_regie_id', null);
                            $set('ligne_regie_avance_id',   null);
                        })
                        ->columnSpanFull(),

                    // ── Provision (ligne dérivée approvisionnée) ──
                    Forms\Components\Select::make('provision_ligne_regie_id')
                        ->label('Ligne de nomenclature (Provision disponible)')
                        ->options(function (Get $get) {
                            $regieId = $get('regie_avance_id');
                            if (!$regieId) return [];

                            return ProvisionLigneRegie::whereHas(
                                'decaissement',
                                fn($q) =>
                                $q->where('regie_avance_id', $regieId)
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
                                        . " FCFA"
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

                    // Champ caché — ligne régie
                    Forms\Components\Hidden::make('ligne_regie_avance_id'),

                    // ── Aperçu provision sélectionnée ─────────────
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
                                    . "<strong style='color:green;'>Disponible :</strong> "  . number_format($prov->montant_disponible,  0, ',', ' ') . " FCFA"
                                    . '</div>'
                            );
                        })
                        ->columnSpanFull(),
                ]),

            // ── Section 3 : Fournisseur et objet ──────────────
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

            Forms\Components\Section::make('Lignes de commande')
                ->description('Ajoutez les articles/services de ce bon de commande.')
                ->schema([

                    // ── Taux communs applicables à toutes les lignes ──
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('tva_commune')
                            ->label('TVA commune (%)')
                            ->numeric()->default(19.25)->suffix('%')
                            ->live(debounce: 500)
                            ->dehydrated(false)
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                $lignes = $get('lignes') ?? [];
                                foreach ($lignes as $index => $ligne) {
                                    $set("lignes.{$index}.taux_tva", (float) ($state ?? 19.25));
                                    static::recalculerLigneBcr(
                                        fn($k, $v) => $set("lignes.{$index}.{$k}", $v),
                                        fn($k)    => $get("lignes.{$index}.{$k}")
                                    );
                                }
                            })
                            ->helperText('Appliquée à toutes les lignes'),

                        Forms\Components\TextInput::make('ir_commun')
                            ->label('IR commun (%)')
                            ->numeric()->default(0)->suffix('%')
                            ->live(debounce: 500)
                            ->dehydrated(false)
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                $lignes = $get('lignes') ?? [];
                                foreach ($lignes as $index => $ligne) {
                                    $set("lignes.{$index}.taux_ir", (float) ($state ?? 0));
                                    static::recalculerLigneBcr(
                                        fn($k, $v) => $set("lignes.{$index}.{$k}", $v),
                                        fn($k)    => $get("lignes.{$index}.{$k}")
                                    );
                                }
                            })
                            ->helperText('Appliqué à toutes les lignes'),

                        Forms\Components\Placeholder::make('total_commande')
                            ->label('Total TTC commande')
                            ->content(function (Forms\Get $get) {
                                $total = collect($get('lignes') ?? [])
                                    ->sum(fn($l) => (float) ($l['montant_ttc'] ?? 0));
                                return number_format($total, 0, ',', ' ') . ' FCFA';
                            }),
                    ]),

                    // ── Repeater lignes ───────────────────────────────
                    Forms\Components\Repeater::make('lignes')
                        ->relationship('lignes')
                        ->schema([
                            Forms\Components\Grid::make(12)->schema([

                                Forms\Components\TextInput::make('designation')
                                    ->label('Désignation')
                                    ->required()
                                    ->columnSpan(4),

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
                                        'pièce'   => 'Pièce',
                                        'lot'     => 'Lot',
                                        'kg'      => 'Kg',
                                        'litre'   => 'L',
                                        'mètre'   => 'M',
                                        'heure'   => 'H',
                                        'jour'    => 'J',
                                        'forfait' => 'Forfait',
                                    ])
                                    ->default('pièce')->required()
                                    ->columnSpan(1),

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
                                    ->numeric()->default(19.25)->suffix('%')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(
                                        fn(Forms\Get $get, Forms\Set $set) =>
                                        static::recalculerLigneBcr($set, $get)
                                    )
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('taux_ir')
                                    ->label('IR %')
                                    ->numeric()->default(0)->suffix('%')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(
                                        fn(Forms\Get $get, Forms\Set $set) =>
                                        static::recalculerLigneBcr($set, $get)
                                    )
                                    ->columnSpan(1),

                                Forms\Components\Placeholder::make('montant_ttc')
                                    ->label('TTC')
                                    ->content(
                                        fn(Forms\Get $get) =>
                                        number_format((float) ($get('montant_ttc') ?? 0), 0, ',', ' ') . ' F'
                                    )
                                    ->columnSpan(1),

                                Forms\Components\Placeholder::make('net_a_payer')
                                    ->label('Net')
                                    ->content(
                                        fn(Forms\Get $get) =>
                                        number_format((float) ($get('net_a_payer') ?? 0), 0, ',', ' ') . ' F'
                                    )
                                    ->columnSpan(1),
                            ]),

                            // Champs cachés calculés
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
                            // Numérotation automatique
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
                            $state['designation']
                                ? "{$state['designation']} — "
                                . number_format((float) ($state['montant_ttc'] ?? 0), 0, ',', ' ')
                                . ' FCFA TTC'
                                : null
                        )
                        ->live()
                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                            // Recalculer totaux BCR après chaque changement de ligne
                            $totaux = collect($state ?? [])->reduce(function ($carry, $ligne) {
                                return [
                                    'montant_ht'  => $carry['montant_ht']  + (float) ($ligne['montant_ht']  ?? 0),
                                    'montant_tva' => $carry['montant_tva'] + (float) ($ligne['montant_tva'] ?? 0),
                                    'montant_ttc' => $carry['montant_ttc'] + (float) ($ligne['montant_ttc'] ?? 0),
                                    'montant_ir'  => $carry['montant_ir']  + (float) ($ligne['montant_ir']  ?? 0),
                                    'net_a_payer' => $carry['net_a_payer'] + (float) ($ligne['net_a_payer'] ?? 0),
                                ];
                            }, [
                                'montant_ht' => 0,
                                'montant_tva' => 0,
                                'montant_ttc' => 0,
                                'montant_ir' => 0,
                                'net_a_payer' => 0
                            ]);

                            $set('montant_ht',  $totaux['montant_ht']);
                            $set('montant_tva', $totaux['montant_tva']);
                            $set('montant_ttc', $totaux['montant_ttc']);
                            $set('montant_ir',  $totaux['montant_ir']);
                            $set('net_a_payer', $totaux['net_a_payer']);
                        }),
                ])
                ->columns(1),
            // ── Section 4 : Totaux (lecture seule) ────────────
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
                    ->label('TTC')->money('XAF')->sortable()->weight('bold'),

                Tables\Columns\TextColumn::make('montant_ir')
                    ->label('IR')->money('XAF')->color('warning'),

                Tables\Columns\TextColumn::make('net_a_payer')
                    ->label('Net')->money('XAF')->color('success'),

                Tables\Columns\IconColumn::make('engage')
                    ->label('Engagé')->boolean()
                    ->trueColor('success')->falseColor('gray'),

                Tables\Columns\BadgeColumn::make('statut')
                    ->colors([
                        'gray'    => 'brouillon',
                        'warning' => 'valide',
                        'info'    => 'livre_partiellement',
                        'success' => fn($state) => in_array($state, ['livre', 'paye']),
                        'danger'  => 'annule',
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'brouillon'          => 'Brouillon',
                        'valide'             => 'Validé',
                        'livre_partiellement' => 'Livré part.',
                        'livre'              => 'Livré',
                        'paye'               => 'Payé',
                        'annule'             => 'Annulé',
                        default              => $state,
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('statut')
                    ->options([
                        'brouillon'          => 'Brouillon',
                        'valide'             => 'Validé',
                        'livre_partiellement' => 'Livré partiellement',
                        'livre'              => 'Livré',
                        'paye'               => 'Payé',
                        'annule'             => 'Annulé',
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
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                // ── Valider ───────────────────────────────────
                Tables\Actions\Action::make('valider')
                    ->label('Valider')
                    ->icon('heroicon-o-check-circle')->color('success')
                    ->visible(
                        fn($record) =>
                        $record->statut === 'brouillon'
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

                // ── Engager (débite la provision) ─────────────
                Tables\Actions\Action::make('engager')
                    ->label('Engager')
                    ->icon('heroicon-o-banknotes')->color('primary')
                    ->visible(
                        fn($record) =>
                        $record->statut === 'valide'
                            && !$record->engage
                            && auth()->user()?->can('valider_bon_commande_regie')
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Engager le bon de commande')
                    ->modalDescription(function ($record) {
                        $prov = $record->provisionLigneRegie;
                        return new \Illuminate\Support\HtmlString(
                            "<p>Le montant TTC de <strong>"
                                . number_format($record->montant_ttc, 0, ',', ' ')
                                . " FCFA</strong> sera débité sur :</p>"
                                . "<p>Ligne : <strong>"
                                . ($prov?->ligneRegie?->nomenclature?->code ?? '—')
                                . " — "
                                . ($prov?->ligneRegie?->nomenclature?->libelle ?? '—')
                                . "</strong></p>"
                                . "<p>Provision disponible : <strong>"
                                . number_format($prov?->montant_disponible ?? 0, 0, ',', ' ')
                                . " FCFA</strong></p>"
                        );
                    })
                    ->action(function ($record) {
                        try {
                            $record->engager();
                            Notification::make()
                                ->title('✅ BCR engagé — provision débitée')
                                ->success()->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('❌ Erreur engagement')
                                ->danger()->body($e->getMessage())->persistent()->send();
                        }
                    }),

                // ── Désengager ────────────────────────────────
                Tables\Actions\Action::make('desengager')
                    ->label('Désengager')
                    ->icon('heroicon-o-arrow-uturn-left')->color('warning')
                    ->visible(
                        fn($record) =>
                        $record->engage
                            && $record->statut === 'valide'
                            && auth()->user()?->can('annuler_bon_commande_regie')
                    )
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        try {
                            $record->desengager();
                            Notification::make()
                                ->title('↩ BCR désengagé — provision restituée')
                                ->warning()->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('❌ Erreur')->danger()
                                ->body($e->getMessage())->send();
                        }
                    }),

                // ── Livré ─────────────────────────────────────
                Tables\Actions\Action::make('livrer')
                    ->label('Marquer livré')
                    ->icon('heroicon-o-truck')->color('info')
                    ->visible(
                        fn($record) =>
                        $record->statut === 'valide'
                            && $record->engage
                    )
                    ->requiresConfirmation()
                    ->action(fn($record) => $record->update(['statut' => 'livre'])),

                // ── Payé ──────────────────────────────────────
                Tables\Actions\Action::make('payer')
                    ->label('Marquer payé')
                    ->icon('heroicon-o-banknotes')->color('success')
                    ->visible(fn($record) => $record->statut === 'livre')
                    ->requiresConfirmation()
                    ->action(fn($record) => $record->update(['statut' => 'paye'])),

                // ── Annuler ───────────────────────────────────
                Tables\Actions\Action::make('annuler')
                    ->label('Annuler')
                    ->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(
                        fn($record) =>
                        in_array($record->statut, ['brouillon', 'valide'])
                            && auth()->user()?->can('annuler_bon_commande_regie')
                    )
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('motif')
                            ->label('Motif')->rows(2)->required(),
                    ])
                    ->action(function ($record, array $data) {
                        // Désengager si nécessaire avant annulation
                        if ($record->engage) {
                            $record->desengager();
                        }
                        $record->update([
                            'statut'       => 'annule',
                            'observations' => ($record->observations ?? '')
                                . "\n--- ANNULÉ " . now()->format('d/m/Y') . " ---\n"
                                . $data['motif'],
                        ]);
                        Notification::make()->title('BCR annulé')->warning()->send();
                    }),
            ])
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
            'controleur_financier'
        ])) {
            $query->whereHas(
                'regieAvance',
                fn($q) =>
                $q->where('responsable_id', $user->id)
            );
        }

        return $query;
    }
}
