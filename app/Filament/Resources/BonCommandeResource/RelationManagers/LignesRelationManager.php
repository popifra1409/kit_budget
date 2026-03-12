<?php

namespace App\Filament\Resources\BonCommandeResource\RelationManagers;

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
        return $form
            ->schema([
                Forms\Components\Grid::make(3)
                    ->schema([
                        // ===== RÉFÉRENCE MERCURIALE =====
                        Forms\Components\Select::make('reference_mercuriale_id')
                            ->label('Référence Mercuriale')
                            ->options(function () {
                                $bc = $this->getOwnerRecord();
                                $exerciceId = $bc->exercice_id ?? null;

                                if (!$exerciceId) {
                                    return ['Exercice non défini sur le BC'];
                                }

                                $references = \App\Models\ReferenceMercuriale::where('exercice_id', $exerciceId)
                                    ->where('actif', true)
                                    ->get()
                                    ->mapWithKeys(fn($ref) => [
                                        $ref->id => "{$ref->code_reference} - {$ref->designation} ({$ref->unite}) - " .
                                            number_format($ref->prix_reference, 0, ',', ' ') . " FCFA"
                                    ]);

                                return ['manual' => '➕ Saisie manuelle'] + $references->toArray();
                            })
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state && $state !== 'manual') {
                                    $reference = \App\Models\ReferenceMercuriale::find($state);
                                    if ($reference) {
                                        $set('designation', $reference->designation);
                                        $set('unite', $reference->unite);
                                        $set('prix_unitaire_ht', $reference->prix_reference);
                                        $set('reference_personnalisee', null);
                                    }
                                } else {
                                    $set('designation', '');
                                    $set('unite', 'pièce');
                                    $set('prix_unitaire_ht', 0);
                                }
                            })
                            ->dehydrateStateUsing(fn($state) => $state === 'manual' ? null : $state)
                            ->columnSpan(2),

                        // ===== RÉFÉRENCE PERSONNALISÉE =====
                        Forms\Components\TextInput::make('reference_personnalisee')
                            ->label('Réf. perso')
                            ->maxLength(100)
                            ->visible(fn(callable $get) => $get('reference_mercuriale_id') === 'manual' || !$get('reference_mercuriale_id'))
                            ->columnSpan(1),
                    ]),

                // ===== DÉSIGNATION =====
                Forms\Components\TextInput::make('designation')
                    ->label('Désignation')
                    ->required()
                    ->maxLength(500)
                    ->columnSpan(2),

                // ===== OBSERVATIONS =====
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
                            ->afterStateUpdated(fn($state, callable $set, callable $get) => self::recalculerLigne($set, $get))
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
                            ->afterStateUpdated(fn($state, callable $set, callable $get) => self::recalculerLigne($set, $get))
                            ->columnSpan(2),

                        // ✅ TVA (avec répercussion du taux commun)
                        Forms\Components\TextInput::make('taux_tva')
                            ->label('TVA (%)')
                            ->numeric()
                            ->suffix('%')
                            ->default(function () {
                                $bc = $this->getOwnerRecord();

                                // 1. Si exonéré → 0
                                if ($bc->exonere_tva) {
                                    return 0;
                                }

                                // 2. Si taux commun défini → utiliser
                                if (isset($bc->tva_commune) && $bc->tva_commune > 0) {
                                    return (float) $bc->tva_commune;
                                }

                                // 3. Sinon → calcul selon type engagement
                                if ($bc->type_engagement_id) {
                                    $typeEngagement = \App\Models\TypeEngagement::find($bc->type_engagement_id);
                                    if ($typeEngagement) {
                                        return $typeEngagement->calcul_tva ? 19.25 : 0;
                                    }
                                }

                                return 19.25;
                            })
                            ->disabled(function () {
                                $bc = $this->getOwnerRecord();
                                return (bool) $bc->exonere_tva;
                            })
                            ->dehydrated(true)
                            ->minValue(0)
                            ->maxValue(100)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn($state, callable $set, callable $get) => self::recalculerLigne($set, $get))
                            ->helperText(function () {
                                $bc = $this->getOwnerRecord();
                                if ($bc->exonere_tva) {
                                    return '⚠️ BC exonéré de TVA';
                                }
                                if (isset($bc->tva_commune) && $bc->tva_commune > 0) {
                                    return "💡 Taux commun du BC : {$bc->tva_commune}%";
                                }
                                return null;
                            })
                            ->columnSpan(1),

                        // ✅ IR (avec répercussion du taux commun)
                        Forms\Components\TextInput::make('taux_ir')
                            ->label('IR (%)')
                            ->numeric()
                            ->suffix('%')
                            ->default(function () {
                                $bc = $this->getOwnerRecord();

                                // 1. Si exonéré → 0
                                if ($bc->exonere_ir) {
                                    return 0;
                                }

                                // 2. Si taux commun défini → utiliser
                                if (isset($bc->ir_commun) && $bc->ir_commun > 0) {
                                    return (float) $bc->ir_commun;
                                }

                                // 3. Sinon → calcul selon régime fiscal
                                if ($bc->type_engagement_id && $bc->fournisseur_id) {
                                    $typeEngagement = \App\Models\TypeEngagement::find($bc->type_engagement_id);
                                    $fournisseur = \App\Models\Fournisseur::with('regimeFiscal')->find($bc->fournisseur_id);

                                    if ($typeEngagement && $fournisseur && $fournisseur->regimeFiscal) {
                                        return $typeEngagement->calculerTauxIR($fournisseur->regimeFiscal);
                                    }
                                }

                                return 0;
                            })
                            ->disabled(function () {
                                $bc = $this->getOwnerRecord();
                                return (bool) $bc->exonere_ir;
                            })
                            ->dehydrated(true)
                            ->minValue(0)
                            ->maxValue(100)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn($state, callable $set, callable $get) => self::recalculerLigne($set, $get))
                            ->helperText(function () {
                                $bc = $this->getOwnerRecord();
                                if ($bc->exonere_ir) {
                                    return '⚠️ BC exonéré d\'IR';
                                }
                                if (isset($bc->ir_commun) && $bc->ir_commun > 0) {
                                    return "💡 Taux commun du BC : {$bc->ir_commun}%";
                                }
                                return null;
                            })
                            ->columnSpan(1),

                        // Net à payer
                        Forms\Components\Placeholder::make('net_a_payer_display')
                            ->label('Net à payer')
                            ->content(function (callable $get) {
                                $netAPayer = (float) ($get('net_a_payer') ?? 0);
                                return number_format($netAPayer, 0, ',', ' ') . ' FCFA';
                            })
                            ->columnSpan(1),
                    ]),

                // ===== CHAMPS CACHÉS =====
                Forms\Components\Hidden::make('montant_ht')->default(0),
                Forms\Components\Hidden::make('montant_tva')->default(0),
                Forms\Components\Hidden::make('montant_ir')->default(0),
                Forms\Components\Hidden::make('montant_tsr')->default(0),
                Forms\Components\Hidden::make('montant_ttc')->default(0),
                Forms\Components\Hidden::make('net_a_payer')->default(0),

                // ✅ NOMENCLATURE : Automatiquement celle du BC
                Forms\Components\Hidden::make('nomenclature_id')
                    ->default(function () {
                        $bc = $this->getOwnerRecord();
                        return $bc->nomenclature_commune_id ?? null;
                    }),

                Forms\Components\Hidden::make('quantite_livree')->default(0),
                Forms\Components\Hidden::make('quantite_restante')
                    ->default(fn(callable $get) => $get('quantite') ?? 0),

                // ===== RÉCAPITULATIF =====
                Forms\Components\Placeholder::make('recap_montants')
                    ->label('Récapitulatif')
                    ->content(function (callable $get) {
                        $montantHT = (float) ($get('montant_ht') ?? 0);
                        $montantTVA = (float) ($get('montant_tva') ?? 0);
                        $montantIR = (float) ($get('montant_ir') ?? 0);
                        $montantTSR = (float) ($get('montant_tsr') ?? 0);
                        $montantTTC = (float) ($get('montant_ttc') ?? 0);
                        $netAPayer = (float) ($get('net_a_payer') ?? 0);

                        return sprintf(
                            "HT: %s | TVA: %s | TTC: %s | IR: %s | TSR: %s | Net: %s",
                            number_format($montantHT, 0, ',', ' '),
                            number_format($montantTVA, 0, ',', ' '),
                            number_format($montantTTC, 0, ',', ' '),
                            number_format($montantIR, 0, ',', ' '),
                            number_format($montantTSR, 0, ',', ' '),
                            number_format($netAPayer, 0, ',', ' ')
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
            ->modifyQueryUsing(fn($query) => $query->with(['nomenclature', 'referenceMercuriale']))

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
                        $dernierNumero = $bc->lignes()->max('numero_ligne') ?? 0;
                        $data['numero_ligne'] = $dernierNumero + 1;

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
                        }),
                ]),
            ])
            ->defaultSort('numero_ligne', 'asc');
    }

    // ✅ MÉTHODE : Recalculer une ligne
    protected static function recalculerLigne(callable $set, callable $get): void
    {
        $quantite = (float) ($get('quantite') ?? 0);
        $prixUnitaireHT = (float) ($get('prix_unitaire_ht') ?? 0);
        $tauxTVA = (float) ($get('taux_tva') ?? 0);
        $tauxIR = (float) ($get('taux_ir') ?? 0);

        // Montant HT
        $montantHT = $quantite * $prixUnitaireHT;

        // Montant TVA
        $montantTVA = ($montantHT * $tauxTVA) / 100;

        // Montant TTC
        $montantTTC = $montantHT + $montantTVA;

        // Montant IR
        $montantIR = ($montantHT * $tauxIR) / 100;

        // TSR (si applicable)
        $montantTSR = 0; // À adapter selon votre logique

        // Net à payer
        $netAPayer = $montantTTC - $montantIR - $montantTSR;

        // Mettre à jour les champs
        $set('montant_ht', round($montantHT, 2));
        $set('montant_tva', round($montantTVA, 2));
        $set('montant_ttc', round($montantTTC, 2));
        $set('montant_ir', round($montantIR, 2));
        $set('montant_tsr', round($montantTSR, 2));
        $set('net_a_payer', round($netAPayer, 2));
    }

    // ✅ MÉTHODE : Recalculer les totaux du BC
    protected function recalculerTotauxBC(): void
    {
        $bc = $this->getOwnerRecord();
        $bc->refresh();
        $bc->load('lignes');

        $totaux = [
            'montant_ht' => 0,
            'montant_tva' => 0,
            'montant_ttc' => 0,
            'montant_ir' => 0,
            'montant_tsr' => 0,
        ];

        foreach ($bc->lignes as $ligne) {
            $totaux['montant_ht'] += $ligne->montant_ht;
            $totaux['montant_tva'] += $ligne->montant_tva;
            $totaux['montant_ttc'] += $ligne->montant_ttc;
            $totaux['montant_ir'] += $ligne->montant_ir;
            $totaux['montant_tsr'] += $ligne->montant_tsr;
        }

        $bc->update($totaux);

        \Log::info("Totaux BC recalculés", [
            'bc' => $bc->numero,
            'montant_ttc' => $totaux['montant_ttc'],
        ]);
    }
}
