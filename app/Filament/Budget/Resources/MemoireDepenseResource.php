<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\MemoireDepenseResource\Pages;
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
    protected static ?string $model          = MemoireDepense::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Mémoires de Dépenses';
    protected static ?string $modelLabel      = 'Mémoire de Dépense';
    protected static ?string $pluralModelLabel = 'Mémoires de Dépenses';
    protected static ?string $navigationGroup = 'Commandes & Engagement';
    protected static ?int    $navigationSort  = 30;

    public static function canViewAny(): bool
    {
        return auth()->check()
            && auth()->user()->can('view_any_memoire_depense');
    }

    public static function canView($record): bool
    {
        return auth()->check()
            && auth()->user()->can('view_memoire_depense');
    }

    public static function canCreate(): bool
    {
        return auth()->check()
            && auth()->user()->can('create_memoire_depense');
    }

    public static function canEdit($record): bool
    {
        if (!auth()->check()) return false;
        if (!auth()->user()->can('update_memoire_depense')) return false;
        return $record->statut === 'brouillon';
    }

    public static function canDelete($record): bool
    {
        if (!auth()->check()) return false;
        if (!auth()->user()->can('delete_memoire_depense')) return false;
        return $record->statut === 'brouillon';
    }

    public static function canValider($record): bool
    {
        return auth()->check()
            && auth()->user()->can('valider_memoire_depense');
    }

    public static function canTransformerEnDa($record): bool
    {
        return auth()->check()
            && auth()->user()->can('transformer_memoire_depense_en_da');
    }


    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('Informations Générales')
                ->schema([
                    Forms\Components\Grid::make(3)->schema([
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
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('numero_decision')->label('N° Décision'),
                        Forms\Components\DatePicker::make('date_decision')->label('Date Décision'),
                        Forms\Components\TextInput::make('numero_ce')->label("N° Certificat d'Engagement"),
                        Forms\Components\DatePicker::make('date_ce')->label('Date CE'),
                    ]),
                ])
                ->collapsed(),

            Forms\Components\Section::make('Lignes de Dépenses')
                ->description('Choisissez le mode de saisie pour toutes les lignes.')
                ->schema([

                    // ── Mode de saisie global ────────────────────────────
                    Forms\Components\ToggleButtons::make('mode_saisie_global')
                        ->label('Mode de saisie')
                        ->options([
                            'prix_unitaire' => '💰 Prix Unitaire',
                            'montant_nap'   => '📊 Montant NAP',
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
                            Forms\Components\Grid::make(12)->schema([

                                // Nature
                                Forms\Components\TextInput::make('nature_depense')
                                    ->label('Nature')->required()->columnSpan(3),

                                // Quantité
                                Forms\Components\TextInput::make('quantite')
                                    ->label('Qté')
                                    ->numeric()->default(1)->required()
                                    ->live(onBlur: true)
                                    ->columnSpan(1),

                                // ── Mode Prix Unitaire ─────────────────────────
                                Forms\Components\TextInput::make('prix_unitaire')
                                    ->label('Prix Unit.')
                                    ->numeric()->suffix('FCFA')->default(0)->required()
                                    // ✅ hidden() au lieu de visible() — toujours soumis au serveur
                                    ->hidden(fn(Get $get) => $get('../../mode_saisie_global') === 'montant_nap')
                                    ->live(onBlur: true)
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('montant_nap_input')
                                    ->label('NAP unitaire')
                                    ->numeric()->suffix('FCFA')
                                    ->required(fn(Get $get) => $get('../../mode_saisie_global') === 'montant_nap')
                                    ->hidden(fn(Get $get) => $get('../../mode_saisie_global') !== 'montant_nap')
                                    ->live(onBlur: true)
                                    // ✅ Calculer prix_unitaire immédiatement à la saisie
                                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                        $nap    = floatval($state ?? 0);
                                        $qte    = floatval($get('quantite') ?? 1);
                                        $tauxIr = floatval($get('taux_ir')  ?? 5.5);

                                        if ($nap > 0 && $qte > 0) {
                                            $napTotal = $nap * $qte;
                                            $mht      = $napTotal / (1 - ($tauxIr / 100));
                                            $pu       = round($mht / $qte, 2);
                                            // ✅ Stocker dans prix_unitaire pour la soumission
                                            $set('prix_unitaire', $pu);
                                        }
                                    })
                                    // ✅ dehydrated(true) — envoyer la valeur au serveur
                                    ->dehydrated(true)
                                    ->helperText('Net à payer par unité')
                                    ->columnSpan(2),
                                // Taux TVA
                                Forms\Components\TextInput::make('taux_tva')
                                    ->label('TVA%')
                                    ->numeric()->default(19.25)->required()
                                    ->live(onBlur: true)
                                    ->columnSpan(1),

                                // Taux IR
                                Forms\Components\TextInput::make('taux_ir')
                                    ->label('IR%')
                                    ->numeric()->default(5.5)->required()
                                    ->live(onBlur: true)
                                    ->columnSpan(1),

                                // ── Previews ───────────────────────────────────
                                Forms\Components\Placeholder::make('prev_mht')
                                    ->label('MHT')
                                    ->content(fn(Get $get) => number_format(
                                        static::calculerMontants($get)['mht'],
                                        0,
                                        ',',
                                        ' '
                                    ) . ' F')
                                    ->columnSpan(1),

                                Forms\Components\Placeholder::make('prev_tva')
                                    ->label('TVA')
                                    ->content(fn(Get $get) => number_format(
                                        static::calculerMontants($get)['tva'],
                                        0,
                                        ',',
                                        ' '
                                    ) . ' F')
                                    ->columnSpan(1),

                                Forms\Components\Placeholder::make('prev_ttc')
                                    ->label('TTC')
                                    ->content(fn(Get $get) => number_format(
                                        static::calculerMontants($get)['ttc'],
                                        0,
                                        ',',
                                        ' '
                                    ) . ' F')
                                    ->columnSpan(1),

                                Forms\Components\Placeholder::make('prev_ir')
                                    ->label('IR')
                                    ->content(fn(Get $get) => number_format(
                                        static::calculerMontants($get)['ir'],
                                        0,
                                        ',',
                                        ' '
                                    ) . ' F')
                                    ->columnSpan(1),

                                Forms\Components\Placeholder::make('prev_nap')
                                    ->label('NAP')
                                    ->content(fn(Get $get) => number_format(
                                        static::calculerMontants($get)['nap'],
                                        0,
                                        ',',
                                        ' '
                                    ) . ' F')
                                    ->columnSpan(1),
                            ]),
                        ])
                        ->mutateRelationshipDataBeforeCreateUsing(
                            fn(array $data, Get $get) => static::preparerDonneesLigne($data, $get)
                        )
                        ->mutateRelationshipDataBeforeSaveUsing(
                            fn(array $data, Get $get) => static::preparerDonneesLigne($data, $get)
                        )
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
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('signataire_nom')->label('Nom du Signataire'),
                        Forms\Components\TextInput::make('signataire_fonction')
                            ->label('Fonction')->default('LE DIRECTEUR GENERAL'),
                        Forms\Components\TextInput::make('lieu_signature')->label('Lieu')->default('Yaoundé'),
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
                            return view('filament.components.memoire-totaux', ['memoire' => $record]);
                        }),
                ])
                ->visible(fn($record) => $record && $record->exists)
                ->collapsed(),
        ]);
    }

    // =========================================================================
    // PRÉPARER DONNÉES LIGNE — FIX : calcul prix_unitaire depuis NAP
    // =========================================================================
    protected static function preparerDonneesLigne(array $data, Get $get): array
    {
        $nap    = floatval($data['montant_nap_input'] ?? 0);
        $pu     = floatval($data['prix_unitaire']     ?? 0);
        $qte    = floatval($data['quantite']          ?? 0);
        $tauxIr = floatval($data['taux_ir']           ?? 0);

        // Si prix_unitaire pas encore calculé mais NAP présent → recalculer
        if ($pu <= 0 && $nap > 0 && $qte > 0) {
            $mht                   = ($nap * $qte) / (1 - ($tauxIr / 100));
            $data['prix_unitaire'] = round($mht / $qte, 2);
        }

        if (empty($data['prix_unitaire'])) {
            $data['prix_unitaire'] = 0;
        }

        unset($data['montant_nap_input']);

        return $data;
    }

    // =========================================================================
    // CALCULER MONTANTS pour les previews
    // =========================================================================
    protected static function calculerMontants(Get $get): array
    {
        $zero = ['mht' => 0, 'tva' => 0, 'ttc' => 0, 'ir' => 0, 'nap' => 0];

        $mode   = $get('../../mode_saisie_global') ?? 'prix_unitaire';
        $qte    = floatval($get('quantite')   ?? 0);
        $tauxTv = floatval($get('taux_tva')   ?? 19.25);
        $tauxIr = floatval($get('taux_ir')    ?? 5.5);

        if ($qte <= 0) return $zero;

        if ($mode === 'montant_nap') {
            $napUnit = floatval($get('montant_nap_input') ?? 0);
            if ($napUnit <= 0) return $zero;

            $napTotal = $napUnit * $qte;
            $mht      = $napTotal / (1 - ($tauxIr / 100));
            $tva      = $mht * ($tauxTv / 100);
            $ttc      = $mht + $tva;
            $ir       = $mht * ($tauxIr / 100);

            return [
                'mht' => round($mht, 2),
                'tva' => round($tva, 2),
                'ttc' => round($ttc, 2),
                'ir'  => round($ir, 2),
                'nap' => round($napTotal, 2),
            ];
        }

        // Mode Prix Unitaire
        $pu = floatval($get('prix_unitaire') ?? 0);
        if ($pu <= 0) return $zero;

        $mht = $qte * $pu;
        $tva = $mht * ($tauxTv / 100);
        $ttc = $mht + $tva;
        $ir  = $mht * ($tauxIr / 100);
        $nap = $mht - $ir;

        return [
            'mht' => round($mht, 2),
            'tva' => round($tva, 2),
            'ttc' => round($ttc, 2),
            'ir'  => round($ir, 2),
            'nap' => round($nap, 2),
        ];
    }

    // =========================================================================
    // TABLE
    // =========================================================================
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('Numéro')->searchable()->sortable()->weight('bold')->copyable(),

                Tables\Columns\TextColumn::make('date_memoire')
                    ->label('Date')->date('d/m/Y')->sortable(),

                Tables\Columns\TextColumn::make('objet')
                    ->label('Objet')->limit(40)->searchable()
                    ->tooltip(fn($record) => $record->objet),

                Tables\Columns\TextColumn::make('montant_ttc')
                    ->label('Montant TTC')->money('XAF')->sortable()->alignEnd(),

                Tables\Columns\TextColumn::make('montant_net')
                    ->label('Montant NAP')->money('XAF')->sortable()->weight('bold')->alignEnd(),

                Tables\Columns\TextColumn::make('lignes_count')
                    ->label('Lignes')->counts('lignes')->badge()->color('info'),

                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
                    ->colors([
                        'secondary' => 'brouillon',
                        'success'   => 'valide',
                        'info'      => 'transmis',
                        'primary'   => 'approuve',
                        'danger'    => 'annule',
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')->dateTime('d/m/Y H:i')->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('statut')
                    ->options([
                        'brouillon' => 'Brouillon',
                        'valide'    => 'Validé',
                        'transmis'  => 'Transmis',
                        'approuve'  => 'Approuvé',
                        'annule'    => 'Annulé',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                // ── Aperçu modal ─────────────────────────────────────
                Tables\Actions\Action::make('apercu')
                    ->label('Aperçu')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading(fn($record) => 'Aperçu — ' . $record->numero)
                    ->modalWidth('7xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer')
                    ->modalContent(function ($record) {
                        $record->load('lignes');
                        return view('filament.modals.apercu-memoire-depense', [
                            'memoire' => $record,
                        ]);
                    }),

                // ── PDF — après validation uniquement ────────────────
                Tables\Actions\Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->visible(fn($record) => $record->statut !== 'brouillon')
                    ->url(fn($record) => route('memoire-depense.pdf', $record))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('valider')
                    ->label('Valider')
                    ->icon('heroicon-o-check-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(
                        fn($record) => $record->statut === 'brouillon'
                            && static::canValider($record)
                    )
                    ->action(function ($record) {
                        $record->update(['statut' => 'valide']);
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Mémoire validé')
                            ->body("Le mémoire {$record->numero} a été validé.")
                            ->send();
                    }),

                // ── Transformer en DA ─────────────────────────────────
                Tables\Actions\Action::make('transformer_en_da')
                    ->label('→ DA')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('primary')
                    ->visible(
                        fn($record) =>
                        in_array($record->statut, ['valide', 'approuve'])
                            && !$record->decision_administrative_id
                            && static::canTransformerEnDa($record)
                    )
                    ->url(
                        fn($record) =>
                        // Ouvre la page View où se trouve le modal complet
                        static::getUrl('view', ['record' => $record])
                    ),
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
            'index'  => Pages\ListMemoireDepenses::route('/'),
            'create' => Pages\CreateMemoireDepense::route('/create'),
            'view'   => Pages\ViewMemoireDepense::route('/{record}'),
            'edit'   => Pages\EditMemoireDepense::route('/{record}/edit'),
        ];
    }
}
