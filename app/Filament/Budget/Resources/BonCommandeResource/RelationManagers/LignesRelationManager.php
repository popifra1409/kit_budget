<?php

namespace App\Filament\Budget\Resources\BonCommandeResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Database\Eloquent\Builder;
use App\Models\LigneBonCommande;

class LignesRelationManager extends RelationManager
{
    protected static string $relationship = 'lignes';

    protected static ?string $title = 'Lignes du Bon de Commande';

    protected static ?string $recordTitleAttribute = 'designation';

    public function form(Forms\Form $form): Forms\Form
    {
        // ✅ Charger le BC UNE SEULE FOIS ici — accessible dans toutes les closures via use()
        $bc = $this->getOwnerRecord();

        return $form
            ->schema([
                Forms\Components\Grid::make(3)
                    ->schema([
                        // ===== RÉFÉRENCE MERCURIALE =====
                        // ✅ APRÈS — même approche lazy que le repeater de BonCommandeResource
                        Forms\Components\Select::make('reference_mercuriale_id')
                            ->label('Référence Mercuriale')
                            ->searchable()
                            // ✅ PAS de ->options(), PAS de ->preload()
                            ->getSearchResultsUsing(function (string $search) use ($bc) {
                                if (strlen($search) < 3) {
                                    return ['manual' => '➕ Saisie manuelle (tapez au moins 3 caractères)'];
                                }

                                $exerciceId = $bc->exercice_id ?? \App\Models\Exercice::getActif()?->id;

                                if (!$exerciceId) {
                                    return ['manual' => '➕ Saisie manuelle'];
                                }

                                $cacheKey = "mercuriale_search_{$exerciceId}_" . md5($search);

                                $results = \Cache::remember($cacheKey, now()->addMinutes(5), function () use ($exerciceId, $search) {
                                    return \App\Models\ReferenceMercuriale::where('exercice_id', $exerciceId)
                                        ->where('actif', true)
                                        ->where(function ($query) use ($search) {
                                            $query->where('code_reference', 'LIKE', "%{$search}%")
                                                ->orWhere('designation',  'LIKE', "%{$search}%")
                                                ->orWhere('rubrique',      'LIKE', "%{$search}%");
                                        })
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(fn($ref) => [
                                            $ref->id => "{$ref->code_reference} - {$ref->designation} ({$ref->unite}) — "
                                                . number_format($ref->prix_reference, 0, ',', ' ') . " FCFA"
                                        ]);
                                });

                                return ['manual' => '➕ Saisie manuelle'] + $results->toArray();
                            })
                            ->getOptionLabelUsing(function ($value) use ($bc) {
                                if (!$value || $value === 'manual') {
                                    return '➕ Saisie manuelle';
                                }
                                return \Cache::remember(
                                    "mercuriale_label_{$value}",
                                    now()->addMinutes(10),
                                    function () use ($value) {
                                        $ref = \App\Models\ReferenceMercuriale::find($value);
                                        if (!$ref) return "Référence #{$value}";
                                        return "{$ref->code_reference} - {$ref->designation} ({$ref->unite}) — "
                                            . number_format($ref->prix_reference, 0, ',', ' ') . " FCFA";
                                    }
                                );
                            })
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                if (!$state || $state === 'manual') {
                                    $set('designation', '');
                                    $set('unite', 'pièce');
                                    $set('prix_unitaire_ht', 0);
                                    $set('reference_personnalisee', null);
                                    return;
                                }

                                $ref = \Cache::remember(
                                    "mercuriale_full_{$state}",
                                    now()->addMinutes(10),
                                    fn() => \App\Models\ReferenceMercuriale::find($state)
                                );

                                if ($ref) {
                                    $set('designation',             $ref->designation);
                                    $set('unite',                   $ref->unite);
                                    $set('prix_unitaire_ht',        $ref->prix_reference);
                                    $set('reference_personnalisee', null);
                                    self::recalculerLigne($set, $get);
                                }
                            })
                            ->helperText('Tapez au moins 3 caractères pour rechercher')
                            ->dehydrateStateUsing(fn($state) => $state === 'manual' ? null : $state)
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('reference_personnalisee')
                            ->label('Réf. perso')
                            ->maxLength(100)
                            ->visible(
                                fn(callable $get) =>
                                $get('reference_mercuriale_id') === 'manual'
                                    || !$get('reference_mercuriale_id')
                            )
                            ->columnSpan(1),
                    ]),

                Forms\Components\TextInput::make('designation')
                    ->label('Désignation')
                    ->required()
                    ->maxLength(500)
                    ->columnSpan(2),

                Forms\Components\Textarea::make('observations')
                    ->label('Observations')
                    ->rows(2)
                    ->columnSpan(3),

                // ===== QUANTITÉS, PRIX, TAXES =====
                Forms\Components\Grid::make(6)
                    ->schema([
                        Forms\Components\TextInput::make('quantite')
                            ->label('Quantité')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->minValue(0)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                // Mettre à jour quantite_restante
                                $livree   = (float) ($get('quantite_livree') ?? 0);
                                $set('quantite_restante', max(0, (float) $state - $livree));
                                self::recalculerLigne($set, $get);
                            })
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
                            ->required()
                            ->default('pièce')
                            ->searchable()
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('prix_unitaire_ht')
                            ->label('Prix Unitaire HT')
                            ->numeric()
                            ->required()
                            ->prefix('FCFA')
                            ->minValue(0)
                            ->live(onBlur: true)
                            ->afterStateUpdated(
                                fn($state, callable $set, callable $get) =>
                                self::recalculerLigne($set, $get)
                            )
                            ->columnSpan(2),

                        // ✅ Fix 3 : remplacer $this par use($bc) dans tous les callbacks
                        Forms\Components\TextInput::make('taux_tva')
                            ->label('TVA (%)')
                            ->numeric()
                            ->suffix('%')
                            ->default(function () use ($bc) {
                                if ($bc->exonere_tva) return 0;
                                if ($bc->tva_commune !== null && $bc->tva_commune !== '') {
                                    return (float) $bc->tva_commune;
                                }
                                if ($bc->type_engagement_id) {
                                    $type = \App\Models\TypeEngagement::find($bc->type_engagement_id);
                                    return $type?->calcul_tva ? 19.25 : 0;
                                }
                                return 19.25;
                            })
                            ->disabled(fn() => (bool) $bc->exonere_tva)
                            ->dehydrated(true)
                            ->minValue(0)->maxValue(100)
                            ->live(onBlur: true)
                            ->afterStateUpdated(
                                fn($state, callable $set, callable $get) =>
                                self::recalculerLigne($set, $get)
                            )
                            ->helperText(function () use ($bc) {
                                if ($bc->exonere_tva) return '⚠️ BC exonéré de TVA';
                                if (!empty($bc->tva_commune) && $bc->tva_commune > 0) {
                                    return "💡 Taux commun BC : {$bc->tva_commune}%";
                                }
                                return null;
                            })
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('taux_ir')
                            ->label('IR (%)')
                            ->numeric()
                            ->suffix('%')
                            ->default(function () use ($bc) {
                                if ($bc->exonere_ir) return 0;
                                if (!empty($bc->ir_commun) && $bc->ir_commun > 0) {
                                    return (float) $bc->ir_commun;
                                }
                                if ($bc->type_engagement_id && $bc->fournisseur_id) {
                                    $type        = \App\Models\TypeEngagement::find($bc->type_engagement_id);
                                    $fournisseur = \App\Models\Fournisseur::with('regimeFiscal')
                                        ->find($bc->fournisseur_id);
                                    if ($type && $fournisseur?->regimeFiscal) {
                                        return $type->calculerTauxIR($fournisseur->regimeFiscal);
                                    }
                                }
                                return 0;
                            })
                            ->disabled(fn() => (bool) $bc->exonere_ir)
                            ->dehydrated(true)
                            ->minValue(0)->maxValue(100)
                            ->live(onBlur: true)
                            ->afterStateUpdated(
                                fn($state, callable $set, callable $get) =>
                                self::recalculerLigne($set, $get)
                            )
                            ->helperText(function () use ($bc) {
                                if ($bc->exonere_ir) return '⚠️ BC exonéré d\'IR';
                                if (!empty($bc->ir_commun) && $bc->ir_commun > 0) {
                                    return "💡 Taux commun BC : {$bc->ir_commun}%";
                                }
                                return null;
                            })
                            ->columnSpan(1),

                        Forms\Components\Placeholder::make('net_a_payer_display')
                            ->label('Net à payer')
                            ->content(
                                fn(callable $get) =>
                                number_format((float) ($get('net_a_payer') ?? 0), 0, ',', ' ') . ' FCFA'
                            )
                            ->columnSpan(1),
                    ]),

                // ===== SUIVI DES QUANTITÉS =====
                // ✅ Fix 4 : quantite_livree visible avec validation
                Forms\Components\Section::make('Suivi de livraison')
                    ->schema([
                        Forms\Components\Grid::make(3)->schema([

                            Forms\Components\Placeholder::make('quantite_commandee_affichee')
                                ->label('Qté commandée')
                                ->content(fn(callable $get) => (int) ($get('quantite') ?? 0)),

                            Forms\Components\TextInput::make('quantite_livree')
                                ->label('Qté livrée')
                                ->numeric()
                                ->default(0)
                                ->minValue(0)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                    $commandee = (float) ($get('quantite') ?? 0);
                                    $livree    = (float) ($state ?? 0);

                                    if ($livree > $commandee) {
                                        $set('quantite_livree', $commandee);
                                        $livree = $commandee;
                                        \Filament\Notifications\Notification::make()
                                            ->title('Quantité livrée limitée')
                                            ->warning()
                                            ->body("Maximum autorisé : {$commandee}")
                                            ->send();
                                    }
                                    $set('quantite_restante', max(0, $commandee - $livree));
                                })
                                ->suffix(fn(callable $get) => '/ ' . (int) ($get('quantite') ?? 0)),

                            Forms\Components\Placeholder::make('quantite_restante_affichee')
                                ->label('Qté restante')
                                ->content(function (callable $get) {
                                    $restante = max(
                                        0,
                                        (float) ($get('quantite')        ?? 0)
                                            - (float) ($get('quantite_livree') ?? 0)
                                    );
                                    $icone = $restante === 0.0 ? '✅' : '⏳';
                                    return "{$icone} {$restante}";
                                }),
                        ]),
                    ])
                    ->collapsible()
                    ->columnSpanFull(),

                // ===== CHAMPS CACHÉS =====
                Forms\Components\Hidden::make('montant_ht')->default(0),
                Forms\Components\Hidden::make('montant_tva')->default(0),
                Forms\Components\Hidden::make('montant_ir')->default(0),
                Forms\Components\Hidden::make('montant_tsr')->default(0),
                Forms\Components\Hidden::make('montant_ttc')->default(0),
                Forms\Components\Hidden::make('net_a_payer')->default(0),
                Forms\Components\Hidden::make('quantite_restante')->default(0),
                Forms\Components\Hidden::make('nomenclature_id')
                    ->default(fn() => $bc->nomenclature_commune_id ?? null),

                // ===== RÉCAPITULATIF =====
                Forms\Components\Placeholder::make('recap_montants')
                    ->label('Récapitulatif')
                    ->content(function (callable $get) {
                        return sprintf(
                            "HT: %s | TVA: %s | TTC: %s | IR: %s | Net: %s FCFA",
                            number_format((float) ($get('montant_ht')  ?? 0), 0, ',', ' '),
                            number_format((float) ($get('montant_tva') ?? 0), 0, ',', ' '),
                            number_format((float) ($get('montant_ttc') ?? 0), 0, ',', ' '),
                            number_format((float) ($get('montant_ir')  ?? 0), 0, ',', ' '),
                            number_format((float) ($get('net_a_payer') ?? 0), 0, ',', ' ')
                        );
                    })
                    ->columnSpan(3),
            ])
            ->columns(3);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            // ✅ Charger la relation referenceMercuriale
            // ->modifyQueryUsing(fn($query) => $query->with(['nomenclature', 'referenceMercuriale']))

            ->modifyQueryUsing(
                fn($query) => $query
                    ->with(['nomenclature', 'referenceMercuriale'])
                    ->orderBy('numero_ligne', 'asc')
                    ->orderBy('created_at', 'asc')
            )

            ->columns([
                // ✅ NUMÉRO DE LIGNE
                Tables\Columns\TextColumn::make('numero_ligne')
                    ->label('#')
                    ->alignCenter()
                    ->sortable()
                    ->width('50px'),

                // ✅ RÉFÉRENCE MERCURIALE
                Tables\Columns\TextColumn::make('referenceMercuriale.code_reference')
                    ->label('Réf. Mercuriale')
                    ->searchable()
                    ->placeholder('N/A')
                    ->badge()
                    ->color('success')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->limit(20)
                    ->tooltip(fn($record) => $record->referenceMercuriale ?
                        "{$record->referenceMercuriale->code_reference} - {$record->referenceMercuriale->designation}" :
                        'Saisie manuelle'),

                // ✅ RÉFÉRENCE PERSONNALISÉE
                Tables\Columns\TextColumn::make('reference_personnalisee')
                    ->label('Réf. Perso')
                    ->searchable()
                    ->placeholder('N/A')
                    ->badge()
                    ->color('info')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->limit(20),

                // ✅ CODE NOMENCLATURE (caché par défaut)
                Tables\Columns\TextColumn::make('nomenclature.code')
                    ->label('Code Nomenclature')
                    ->searchable()
                    ->badge()
                    ->color('warning')
                    ->placeholder('N/A')
                    ->toggleable(isToggledHiddenByDefault: true),

                // ✅ DÉSIGNATION
                Tables\Columns\TextColumn::make('designation')
                    ->label('Désignation')
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->wrap()
                    ->tooltip(fn($record) => $record->designation),

                // ✅ QUANTITÉ
                Tables\Columns\TextColumn::make('quantite')
                    ->label('Qté')
                    ->alignCenter()
                    ->sortable()
                    ->numeric(decimalPlaces: 2),

                // ✅ UNITÉ
                Tables\Columns\TextColumn::make('unite')
                    ->label('Unité')
                    ->alignCenter()
                    ->sortable(),

                // ✅ PRIX UNITAIRE HT
                Tables\Columns\TextColumn::make('prix_unitaire_ht')
                    ->label('P.U HT')
                    ->money('XAF')
                    ->alignRight()
                    ->sortable(),

                // ✅ MONTANT HT
                Tables\Columns\TextColumn::make('montant_ht')
                    ->label('Montant HT')
                    ->money('XAF')
                    ->alignRight()
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('XAF')
                            ->label('Total HT'),
                    ]),

                // ✅ TAUX TVA
                Tables\Columns\TextColumn::make('taux_tva')
                    ->label('TVA %')
                    ->suffix('%')
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(),

                // ✅ MONTANT TVA
                Tables\Columns\TextColumn::make('montant_tva')
                    ->label('Montant TVA')
                    ->money('XAF')
                    ->alignRight()
                    ->sortable()
                    ->toggleable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('XAF')
                            ->label('Total TVA'),
                    ]),

                // ✅ MONTANT TTC
                Tables\Columns\TextColumn::make('montant_ttc')
                    ->label('Montant TTC')
                    ->money('XAF')
                    ->alignRight()
                    ->sortable()
                    ->weight('bold')
                    ->color('success')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('XAF')
                            ->label('Total TTC'),
                    ]),

                // ✅ TAUX IR
                Tables\Columns\TextColumn::make('taux_ir')
                    ->label('IR %')
                    ->suffix('%')
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(),

                // ✅ MONTANT IR
                Tables\Columns\TextColumn::make('montant_ir')
                    ->label('Montant IR')
                    ->money('XAF')
                    ->alignRight()
                    ->sortable()
                    ->toggleable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('XAF')
                            ->label('Total IR'),
                    ]),

                // ✅ TSR (si applicable)
                Tables\Columns\TextColumn::make('montant_tsr')
                    ->label('TSR')
                    ->money('XAF')
                    ->alignRight()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('XAF')
                            ->label('Total TSR'),
                    ]),

                // ✅ NET À PAYER (IMPORTANT)
                Tables\Columns\TextColumn::make('net_a_payer')
                    ->label('Net à payer')
                    ->money('XAF')
                    ->alignRight()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('XAF')
                            ->label('Total Net à Payer'),
                    ]),

                // ✅ QUANTITÉS LIVRÉES (toggleable)
                Tables\Columns\TextColumn::make('quantite_livree')
                    ->label('Qté livrée')
                    ->numeric(decimalPlaces: 2)
                    ->alignCenter()
                    ->color('info')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('quantite_restante')
                    ->label('Qté restante')
                    ->numeric(decimalPlaces: 2)
                    ->alignCenter()
                    ->color(fn($record) => $record->quantite_restante > 0 ? 'warning' : 'success')
                    ->toggleable(isToggledHiddenByDefault: true),

                // ✅ OBSERVATIONS (toggleable)
                Tables\Columns\TextColumn::make('observations')
                    ->label('Observations')
                    ->limit(30)
                    ->tooltip(fn($record) => $record->observations)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Filtres optionnels
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('➕ Ajouter une ligne')
                    ->modalHeading('Ajouter une ligne au BC')
                    ->modalWidth('5xl')
                    ->visible(function () {
                        $bc = $this->getOwnerRecord();
                        return $bc->statut === 'brouillon';
                    })
                    ->mutateFormDataUsing(function (array $data): array {
                        $bc = $this->getOwnerRecord();

                        // Nomenclature automatique
                        if (empty($data['nomenclature_id']) && $bc->nomenclature_commune_id) {
                            $data['nomenclature_id'] = $bc->nomenclature_commune_id;
                        }

                        // Forcer exonérations
                        if ($bc->exonere_tva) {
                            $data['taux_tva'] = 0;
                            $data['montant_tva'] = 0;
                        }

                        if ($bc->exonere_ir) {
                            $data['taux_ir'] = 0;
                            $data['montant_ir'] = 0;
                        }

                        // Numéro de ligne automatique
                        // $dernierNumero = $bc->lignes()->max('numero_ligne') ?? 0;
                        // $data['numero_ligne'] = $dernierNumero + 1;

                        $data['numero_ligne'] = $bc->lignes()->count() + 1;

                        return $data;
                    })
                    ->after(function () {
                        $this->recalculerTotauxBC();

                        \Filament\Notifications\Notification::make()
                            ->title('Ligne ajoutée')
                            ->success()
                            ->body('Les totaux du BC ont été recalculés.')
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->modalHeading('Modifier la ligne')
                    ->modalWidth('5xl')
                    ->visible(function () {
                        $bc = $this->getOwnerRecord();
                        return $bc->statut === 'brouillon' || auth()->user()->hasRole('super_admin');
                    })
                    ->after(function () {
                        $this->recalculerTotauxBC();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->visible(function () {
                        $bc = $this->getOwnerRecord();
                        return $bc->statut === 'brouillon' || auth()->user()->hasRole('super_admin');
                    })
                    ->after(function () {
                        $this->recalculerTotauxBC();
                        $this->renumereroterLignes();

                        \Filament\Notifications\Notification::make()
                            ->title('Ligne supprimée')
                            ->success()
                            ->body('Les totaux du BC ont été recalculés.')
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(function () {
                            $bc = $this->getOwnerRecord();
                            return $bc->statut === 'brouillon' || auth()->user()->hasRole('super_admin');
                        })
                        ->after(function () {
                            $this->recalculerTotauxBC();
                            $this->renumereroterLignes();
                        }),
                ]),
            ])
            ->defaultSort('numero_ligne', 'asc');
    }

    // ✅ MÉTHODE : Recalculer une ligne
    protected static function recalculerLigne(callable $set, callable $get): void
    {
        $quantite       = (float) ($get('quantite')         ?? 0);
        $prixUnitaireHT = (float) ($get('prix_unitaire_ht') ?? 0);
        $tauxTVA        = (float) ($get('taux_tva')         ?? 0);
        $tauxIR         = (float) ($get('taux_ir')          ?? 0);

        // Calculs bruts
        $montantHT  = $quantite * $prixUnitaireHT;
        $montantTVA = ($montantHT * $tauxTVA) / 100;
        $montantTTC = $montantHT + $montantTVA;
        $montantIR  = ($montantHT * $tauxIR)  / 100;
        $montantTSR = 0;

        // ✅ Stocker des entiers arrondis — jamais de .5 en DB
        $htInt  = (int) number_format($montantHT,  0, '.', '');
        $tvaInt = (int) number_format($montantTVA, 0, '.', '');
        $ttcInt = (int) number_format($montantTTC, 0, '.', '');
        $irInt  = (int) number_format($montantIR,  0, '.', '');
        $tsrInt = (int) number_format($montantTSR, 0, '.', '');

        // ✅ NET A PAYER = HT arrondi - IR arrondi
        $netAPayer = $htInt - $irInt - $tsrInt;

        $set('montant_ht',  $htInt);
        $set('montant_tva', $tvaInt);
        $set('montant_ttc', $ttcInt);
        $set('montant_ir',  $irInt);
        $set('montant_tsr', $tsrInt);
        $set('net_a_payer', $netAPayer);
    }

    // ✅ MÉTHODE : Recalculer les totaux du BC
    protected function recalculerTotauxBC(): void
    {
        $bc = $this->getOwnerRecord();
        $bc->refresh();
        $bc->load('lignes');

        $totalHT  = 0;
        $totalTVA = 0;
        $totalTTC = 0;
        $totalIR  = 0;
        $totalTSR = 0;

        // ✅ Sommer les valeurs déjà arrondies ligne par ligne
        foreach ($bc->lignes as $ligne) {
            $totalHT  += (int) number_format((float)($ligne->montant_ht  ?? 0), 0, '.', '');
            $totalTVA += (int) number_format((float)($ligne->montant_tva ?? 0), 0, '.', '');
            $totalTTC += (int) number_format((float)($ligne->montant_ttc ?? 0), 0, '.', '');
            $totalIR  += (int) number_format((float)($ligne->montant_ir  ?? 0), 0, '.', '');
            $totalTSR += (int) number_format((float)($ligne->montant_tsr ?? 0), 0, '.', '');
        }

        // ✅ NET A PAYER du BC = Total HT - Total IR - Total TSR
        $netAPayer = $totalHT - $totalIR - $totalTSR;

        $bc->update([
            'montant_ht'  => $totalHT,
            'montant_tva' => $totalTVA,
            'montant_ttc' => $totalTTC,
            'montant_ir'  => $totalIR,
            'montant_tsr' => $totalTSR,
            'net_a_payer' => $netAPayer,
        ]);

        \Log::info("Totaux BC recalculés", [
            'bc'          => $bc->numero,
            'montant_ttc' => $totalTTC,
            'net_a_payer' => $netAPayer,
        ]);
    }

    protected function renumereroterLignes(): void
    {
        $bc = $this->getOwnerRecord();
        $bc->refresh();

        $lignes = $bc->lignes()
            ->orderBy('numero_ligne', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        $numero = 1;
        foreach ($lignes as $ligne) {
            if ($ligne->numero_ligne !== $numero) {
                $ligne->updateQuietly(['numero_ligne' => $numero]);
            }
            $numero++;
        }
    }
}
