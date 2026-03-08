<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MemoireDepenseResource\Pages;
use App\Models\MemoireDepense;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class MemoireDepenseResource extends Resource
{
    protected static ?string $model = MemoireDepense::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Mémoires de Dépenses';
    protected static ?string $modelLabel = 'Mémoire de Dépense';
    protected static ?string $pluralModelLabel = 'Mémoires de Dépenses';
    protected static ?string $navigationGroup = 'Commandes & Engagement';
    protected static ?int $navigationSort = 30;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informations Générales')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('numero')
                                    ->label('Numéro')
                                    ->default(fn() => MemoireDepense::genererNumero(now()->year))
                                    ->disabled()
                                    ->dehydrated(),

                                Forms\Components\DatePicker::make('date_memoire')
                                    ->label('Date du Mémoire')
                                    ->default(now())
                                    ->required(),

                                Forms\Components\TextInput::make('exercice')
                                    ->label('Exercice')
                                    ->numeric()
                                    ->default(now()->year)
                                    ->required(),
                            ]),

                        Forms\Components\Textarea::make('objet')
                            ->label('Objet de la Dépense')
                            ->required()
                            ->rows(2),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Références')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('numero_decision')
                                    ->label('N° Décision'),
                                Forms\Components\DatePicker::make('date_decision')
                                    ->label('Date Décision'),
                                Forms\Components\TextInput::make('numero_ce')
                                    ->label('N° Certificat d\'Engagement'),
                                Forms\Components\DatePicker::make('date_ce')
                                    ->label('Date CE'),
                            ]),
                    ])
                    ->collapsed(),

                Forms\Components\Section::make('Lignes de Dépenses')
                    ->description('Choisissez le mode de saisie pour toutes les lignes.')
                    ->schema([
                        // ✅ Mode de saisie global
                        Forms\Components\ToggleButtons::make('mode_saisie_global')
                            ->label('Mode de saisie')
                            ->options([
                                'prix_unitaire' => '💰 Prix Unitaire',
                                'montant_nap' => '📊 Montant NAP',
                            ])
                            ->default('prix_unitaire')
                            ->inline()
                            ->live()
                            ->dehydrated(false)
                            ->helperText('Ce mode s\'applique à toutes les lignes du mémoire')
                            ->columnSpanFull(),

                        Forms\Components\Repeater::make('lignes')
                            ->relationship('lignes')
                            ->schema([
                                Forms\Components\Grid::make(12)
                                    ->schema([
                                        Forms\Components\TextInput::make('nature_depense')
                                            ->label('Nature')
                                            ->required()
                                            ->columnSpan(3),

                                        Forms\Components\TextInput::make('quantite')
                                            ->label('Qté')
                                            ->numeric()
                                            ->default(1)
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(function (Get $get, Set $set) {
                                                static::updatePrixUnitaire($get, $set);
                                            })
                                            ->columnSpan(1),

                                        // ✅ Prix Unitaire (toujours présent, parfois caché)
                                        Forms\Components\TextInput::make('prix_unitaire')
                                            ->label('Prix Unit.')
                                            ->numeric()
                                            ->suffix('FCFA')
                                            ->default(0)
                                            ->required()
                                            ->visible(fn(Get $get) => $get('../../mode_saisie_global') === 'prix_unitaire')
                                            ->live()
                                            ->columnSpan(2),

                                        // ✅ Montant NAP (champ temporaire pour saisie)
                                        Forms\Components\TextInput::make('montant_nap_input')
                                            ->label('NAP')
                                            ->numeric()
                                            ->suffix('FCFA')
                                            ->required(fn(Get $get) => $get('../../mode_saisie_global') === 'montant_nap')
                                            ->visible(fn(Get $get) => $get('../../mode_saisie_global') === 'montant_nap')
                                            ->live()
                                            ->afterStateUpdated(function (Get $get, Set $set) {
                                                static::updatePrixUnitaire($get, $set);
                                            })
                                            ->dehydrated(false)
                                            ->columnSpan(2),

                                        Forms\Components\TextInput::make('taux_tva')
                                            ->label('TVA%')
                                            ->numeric()
                                            ->default(19.25)
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(function (Get $get, Set $set) {
                                                static::updatePrixUnitaire($get, $set);
                                            })
                                            ->columnSpan(1),

                                        Forms\Components\TextInput::make('taux_ir')
                                            ->label('IR%')
                                            ->numeric()
                                            ->default(5.5)
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(function (Get $get, Set $set) {
                                                static::updatePrixUnitaire($get, $set);
                                            })
                                            ->columnSpan(1),

                                        // ✅ Previews
                                        Forms\Components\Placeholder::make('mht')
                                            ->label('MHT')
                                            ->content(function (Get $get) {
                                                $montants = static::calculerMontants($get);
                                                return number_format($montants['mht'], 0, ',', ' ') . ' F';
                                            })
                                            ->columnSpan(1),

                                        Forms\Components\Placeholder::make('tva')
                                            ->label('TVA')
                                            ->content(function (Get $get) {
                                                $montants = static::calculerMontants($get);
                                                return number_format($montants['tva'], 0, ',', ' ') . ' F';
                                            })
                                            ->columnSpan(1),

                                        Forms\Components\Placeholder::make('ttc')
                                            ->label('TTC')
                                            ->content(function (Get $get) {
                                                $montants = static::calculerMontants($get);
                                                return number_format($montants['ttc'], 0, ',', ' ') . ' F';
                                            })
                                            ->columnSpan(1),

                                        Forms\Components\Placeholder::make('ir')
                                            ->label('IR')
                                            ->content(function (Get $get) {
                                                $montants = static::calculerMontants($get);
                                                return number_format($montants['ir'], 0, ',', ' ') . ' F';
                                            })
                                            ->columnSpan(1),

                                        Forms\Components\Placeholder::make('nap')
                                            ->label('NAP')
                                            ->content(function (Get $get) {
                                                $montants = static::calculerMontants($get);
                                                return number_format($montants['nap'], 0, ',', ' ') . ' F';
                                            })
                                            ->columnSpan(1),
                                    ]),
                            ])
                            // ✅ CRITIQUE : Calculer le prix_unitaire AVANT la sauvegarde
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data, Get $get): array {
                                return static::preparerDonneesLigne($data, $get);
                            })
                            ->mutateRelationshipDataBeforeSaveUsing(function (array $data, Get $get): array {
                                return static::preparerDonneesLigne($data, $get);
                            })
                            ->orderColumn('numero_ligne')
                            ->defaultItems(1)
                            ->addActionLabel('Ajouter une ligne')
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn(array $state): ?string => $state['nature_depense'] ?? null),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Signature')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('signataire_nom')
                                    ->label('Nom du Signataire'),
                                Forms\Components\TextInput::make('signataire_fonction')
                                    ->label('Fonction')
                                    ->default('LE DIRECTEUR GENERAL'),
                                Forms\Components\TextInput::make('lieu_signature')
                                    ->label('Lieu')
                                    ->default('Yaoundé'),
                            ]),
                    ])
                    ->collapsed(),

                Forms\Components\Section::make('Totaux')
                    ->schema([
                        Forms\Components\Placeholder::make('totaux')
                            ->label('')
                            ->content(function ($record) {
                                if (!$record || !$record->exists) {
                                    return 'Les totaux seront calculés automatiquement après sauvegarde';
                                }
                                return view('filament.components.memoire-totaux', [
                                    'memoire' => $record
                                ]);
                            }),
                    ])
                    ->visible(fn($record) => $record && $record->exists)
                    ->collapsed(),
            ]);
    }

    /**
     * ✅ Préparer les données de la ligne AVANT sauvegarde
     */
    protected static function preparerDonneesLigne(array $data, Get $get): array
    {
        $mode = $get('mode_saisie_global') ?? 'prix_unitaire';

        if ($mode === 'montant_nap' && isset($data['montant_nap_input'])) {
            // Calculer le prix unitaire depuis le NAP
            $nap = floatval($data['montant_nap_input'] ?? 0);
            $qte = floatval($data['quantite'] ?? 1);
            $tauxIr = floatval($data['taux_ir'] ?? 5.5);

            if ($nap > 0 && $qte > 0) {
                // Formule inversée : NAP = MHT - IR
                // NAP = MHT × (1 - IR/100)
                // MHT = NAP / (1 - IR/100)
                $mht = $nap / (1 - ($tauxIr / 100));
                $pu = $mht / $qte;
                $data['prix_unitaire'] = round($pu, 2);
            } else {
                $data['prix_unitaire'] = 0;
            }
        }

        // S'assurer que prix_unitaire existe toujours
        if (!isset($data['prix_unitaire']) || $data['prix_unitaire'] === null) {
            $data['prix_unitaire'] = 0;
        }

        // Supprimer le champ temporaire
        unset($data['montant_nap_input']);

        return $data;
    }

    /**
     * ✅ Mettre à jour le prix unitaire (pour les previews)
     */
    protected static function updatePrixUnitaire(Get $get, Set $set): void
    {
        $mode = $get('../../mode_saisie_global') ?? 'prix_unitaire';

        if ($mode === 'montant_nap') {
            $nap = floatval($get('montant_nap_input') ?? 0);
            $qte = floatval($get('quantite') ?? 1);
            $tauxIr = floatval($get('taux_ir') ?? 5.5);

            if ($nap > 0 && $qte > 0) {
                $mht = $nap / (1 - ($tauxIr / 100));
                $pu = $mht / $qte;
                $set('prix_unitaire', round($pu, 2));
            } else {
                $set('prix_unitaire', 0);
            }
        }
    }

    /**
     * ✅ Calculer les montants pour les previews
     */
    protected static function calculerMontants(Get $get): array
    {
        $mode = $get('../../mode_saisie_global') ?? 'prix_unitaire';
        $qte = floatval($get('quantite') ?? 0);
        $tauxTva = floatval($get('taux_tva') ?? 19.25);
        $tauxIr = floatval($get('taux_ir') ?? 5.5);
        if ($qte <= 0) {
            return ['mht' => 0, 'tva' => 0, 'ttc' => 0, 'ir' => 0, 'nap' => 0];
        }
        if ($mode === 'montant_nap') {

            $napUnitaire = floatval($get('montant_nap_input') ?? 0);

            if ($napUnitaire <= 0) {
                return ['mht' => 0, 'tva' => 0, 'ttc' => 0, 'ir' => 0, 'nap' => 0];
            }

            // ✅ NAP total
            $napTotal = $qte * $napUnitaire;

            // Formule inversée
            $mht = $napTotal / (1 - ($tauxIr / 100));
            $tva = $mht * ($tauxTva / 100);
            $ttc = $mht + $tva;
            $ir = $mht * ($tauxIr / 100);

            return [
                'mht' => round($mht, 2),
                'tva' => round($tva, 2),
                'ttc' => round($ttc, 2),
                'ir' => round($ir, 2),
                'nap' => round($napTotal, 2),
            ];
        } else {
            // ✅ MODE PU : Calculer depuis le Prix Unitaire 
            $pu = floatval($get('prix_unitaire') ?? 0);
            if ($pu <= 0) {
                return [
                    'mht' => 0,
                    'tva' => 0,
                    'ttc' => 0,
                    'ir' => 0,
                    'nap' => 0
                ];
            }
            $mht = $qte * $pu;
            $tva = $mht * ($tauxTva / 100);
            $ttc = $mht + $tva;
            $ir = $mht * ($tauxIr / 100);
            $nap = $mht - $ir;
            // ✅ Formule correcte 
            return [
                'mht' => round($mht, 2),
                'tva' => round($tva, 2),
                'ttc' => round($ttc, 2),
                'ir' => round($ir, 2),
                'nap' => round($nap, 2),
            ];
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('Numéro')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('date_memoire')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('objet')
                    ->label('Objet')
                    ->limit(40)
                    ->searchable()
                    ->tooltip(fn($record) => $record->objet),

                Tables\Columns\TextColumn::make('montant_ttc')
                    ->label('Montant TTC')
                    ->money('XAF')
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('montant_net')
                    ->label('Montant NAP')
                    ->money('XAF')
                    ->sortable()
                    ->weight('bold')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('lignes_count')
                    ->label('Lignes')
                    ->counts('lignes')
                    ->badge()
                    ->color('info'),

                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
                    ->colors([
                        'secondary' => 'brouillon',
                        'success' => 'valide',
                        'info' => 'transmis',
                        'primary' => 'approuve',
                        'danger' => 'annule',
                    ])
                    ->icons([
                        'heroicon-o-pencil' => 'brouillon',
                        'heroicon-o-check-circle' => 'valide',
                        'heroicon-o-paper-airplane' => 'transmis',
                        'heroicon-o-check-badge' => 'approuve',
                        'heroicon-o-x-circle' => 'annule',
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('statut')
                    ->options([
                        'brouillon' => 'Brouillon',
                        'valide' => 'Validé',
                        'transmis' => 'Transmis',
                        'approuve' => 'Approuvé',
                        'annule' => 'Annulé',
                    ]),

                Tables\Filters\Filter::make('date_memoire')
                    ->form([
                        Forms\Components\DatePicker::make('date_debut')
                            ->label('Du'),
                        Forms\Components\DatePicker::make('date_fin')
                            ->label('Au'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['date_debut'], fn($q, $date) => $q->whereDate('date_memoire', '>=', $date))
                            ->when($data['date_fin'], fn($q, $date) => $q->whereDate('date_memoire', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('valider')
                    ->label('Valider')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn($record) => $record->statut === 'brouillon')
                    ->action(function ($record) {
                        $record->update(['statut' => 'valide']);

                        Notification::make()
                            ->success()
                            ->title('Mémoire validé')
                            ->body("Le mémoire {$record->numero} a été validé avec succès.")
                            ->send();
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMemoireDepenses::route('/'),
            'create' => Pages\CreateMemoireDepense::route('/create'),
            'view' => Pages\ViewMemoireDepense::route('/{record}'),
            'edit' => Pages\EditMemoireDepense::route('/{record}/edit'),
        ];
    }
}
