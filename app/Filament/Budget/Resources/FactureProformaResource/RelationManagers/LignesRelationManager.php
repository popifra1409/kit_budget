<?php

namespace App\Filament\Budget\Resources\FactureProformaResource\RelationManagers;

use App\Models\ReferenceMercuriale;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LignesRelationManager extends RelationManager
{
    protected static string $relationship = 'lignes';
    protected static ?string $title = 'Lignes de la facture proforma';
    protected static ?string $recordTitleAttribute = 'designation';

    public function form(Form $form): Form
    {
        return $form->schema([
            // ── Mode de saisie (pas une vraie colonne) ──────────
            Forms\Components\Radio::make('mode_saisie')
                ->label('Mode de saisie')
                ->options([
                    'mercuriale' => '📋 Depuis une Référence Mercuriale',
                    'libre'      => '✏️ Saisie libre',
                ])
                ->default(fn($record) => $record?->reference_mercuriale_id ? 'mercuriale' : 'libre')
                ->live()
                ->columnSpanFull()
                ->dehydrated(false),

            // ── Mode Référence Mercuriale ────────────────────────
            Forms\Components\Select::make('reference_mercuriale_id')
                ->label('Référence Mercuriale')
                ->searchable()
                ->getSearchResultsUsing(function (string $search) {
                    return ReferenceMercuriale::actif()
                        ->where(function ($q) use ($search) {
                            $q->where('code_reference', 'ilike', "%{$search}%")
                                ->orWhere('designation', 'ilike', "%{$search}%");
                        })
                        ->limit(50)
                        ->get()
                        ->mapWithKeys(fn($r) => [$r->id => $r->libelle_complet])
                        ->toArray();
                })
                ->getOptionLabelUsing(fn($value) => ReferenceMercuriale::find($value)?->libelle_complet)
                ->visible(fn(callable $get) => $get('mode_saisie') === 'mercuriale')
                ->required(fn(callable $get) => $get('mode_saisie') === 'mercuriale')
                ->live()
                ->afterStateUpdated(function ($state, callable $set) {
                    if (!$state) return;
                    $ref = ReferenceMercuriale::find($state);
                    if ($ref) {
                        $set('designation', $ref->designation);
                        $set('unite', $ref->unite);
                        $set('prix_unitaire_ht', (float) $ref->prix_reference);
                    }
                })
                ->columnSpanFull(),

            // ── Champs communs (désignation en lecture seule si mercuriale) ──
            Forms\Components\TextInput::make('designation')
                ->label('Désignation')
                ->required()
                ->disabled(fn(callable $get) => $get('mode_saisie') === 'mercuriale' && filled($get('reference_mercuriale_id')))
                ->dehydrated()
                ->columnSpanFull(),

            Forms\Components\Grid::make(5)->schema([
                Forms\Components\TextInput::make('unite')
                    ->label('Unité')
                    ->maxLength(30),

                Forms\Components\TextInput::make('quantite')
                    ->label('Quantité')
                    ->numeric()
                    ->required()
                    ->default(1)
                    ->minValue(0.001)
                    ->step(0.001),

                Forms\Components\TextInput::make('prix_unitaire_ht')
                    ->label('Prix Unitaire HT')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->suffix('FCFA')
                    ->disabled(fn(callable $get) => $get('mode_saisie') === 'mercuriale' && filled($get('reference_mercuriale_id')))
                    ->dehydrated(),

                Forms\Components\TextInput::make('taux_tva')
                    ->label('Taux TVA (%)')
                    ->numeric()
                    ->default(19.25)
                    ->suffix('%'),

                Forms\Components\TextInput::make('taux_ir')
                    ->label('Taux IR (%)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->suffix('%')
                    ->helperText('Laisser vide pour un calcul automatique (barème par défaut)'),
            ]),

            Forms\Components\Textarea::make('observations')
                ->label('Observations')
                ->rows(2)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero_ligne')
                    ->label('#')
                    ->alignCenter()
                    ->width('40px'),

                Tables\Columns\IconColumn::make('reference_mercuriale_id')
                    ->label('')
                    ->icon(fn($state) => $state ? 'heroicon-o-clipboard-document-list' : 'heroicon-o-pencil')
                    ->color(fn($state) => $state ? 'info' : 'gray')
                    ->tooltip(fn($state) => $state ? 'Depuis référence mercuriale' : 'Saisie libre'),

                Tables\Columns\TextColumn::make('designation')
                    ->label('Désignation')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('unite')
                    ->label('Unité'),

                Tables\Columns\TextColumn::make('quantite')
                    ->label('Qté')
                    ->numeric(decimalPlaces: 2),

                Tables\Columns\TextColumn::make('prix_unitaire_ht')
                    ->label('P.U HT')
                    ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ')),

                Tables\Columns\TextColumn::make('montant_ttc')
                    ->label('Montant TTC')
                    ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ') . ' FCFA')
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\IconColumn::make('ligne_bon_commande_id')
                    ->label('Utilisée')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')
                    ->tooltip(fn($record) => $record->ligne_bon_commande_id ? 'Déjà reprise dans un Bon de Commande' : null),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Ajouter une ligne')
                    ->mutateFormDataUsing(function (array $data): array {
                        if (($data['mode_saisie'] ?? 'libre') === 'libre') {
                            $data['reference_mercuriale_id'] = null;
                        }
                        unset($data['mode_saisie']);
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => is_null($record->ligne_bon_commande_id))
                    ->mutateFormDataUsing(function (array $data): array {
                        if (($data['mode_saisie'] ?? 'libre') === 'libre') {
                            $data['reference_mercuriale_id'] = null;
                        }
                        unset($data['mode_saisie']);
                        return $data;
                    }),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn($record) => is_null($record->ligne_bon_commande_id))
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('numero_ligne');
    }
}
