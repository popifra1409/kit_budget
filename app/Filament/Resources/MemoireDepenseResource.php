<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MemoireDepenseResource\Pages;
use App\Models\MemoireDepense;
use Filament\Forms;
use Filament\Forms\Form;
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

    protected static ?string $navigationGroup = 'Documents';

    protected static ?int $navigationSort = 30;

    /**
     * Permissions – Mémoires de dépenses
     */
    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->can('view_any_memoire_depense');
    }

    public static function canView($record): bool
    {
        return auth()->check() && auth()->user()->can('view_memoire_depense');
    }

    public static function canCreate(): bool
    {
        return auth()->check() && auth()->user()->can('create_memoire_depense');
    }

    public static function canEdit($record): bool
    {
        if (!auth()->check()) {
            return false;
        }

        if (!auth()->user()->can('update_memoire_depense')) {
            return false;
        }

        return true;
    }

    public static function canDelete($record): bool
    {
        return auth()->check() && auth()->user()->can('delete_memoire_depense');
    }

    /**
     * Action spéciale : Valider un mémoire (Contrôleur Financier)
     */
    public static function canValider($record): bool
    {
        return auth()->check() && auth()->user()->can('valider_memoire_depense');
    }

    /**
     * Action spéciale : Publier un mémoire (Agence Comptable)
     */
    public static function canPublier($record): bool
    {
        return auth()->check() && auth()->user()->can('publier_memoire_depense');
    }

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
                            ->rows(2)
                            ->placeholder('Ex: Achat de fournitures de bureau pour les services'),
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
                    ->schema([
                        Forms\Components\Repeater::make('lignes')
                            ->relationship('lignes')
                            ->schema([
                                Forms\Components\Grid::make(9)
                                    ->schema([
                                        Forms\Components\TextInput::make('nature_depense')
                                            ->label('Nature de la Dépense')
                                            ->required()
                                            ->columnSpan(2)
                                            ->placeholder('Ex: Rames de papier A4'),

                                        Forms\Components\TextInput::make('quantite')
                                            ->label('Qté')
                                            ->numeric()
                                            ->default(1)
                                            ->required()
                                            ->live(onBlur: true)
                                            ->columnSpan(1),

                                        Forms\Components\TextInput::make('prix_unitaire')
                                            ->label('PU FCFA')
                                            ->numeric()
                                            ->required()
                                            ->live(onBlur: true)
                                            //->suffix('FCFA')
                                            ->columnSpan(1),

                                        Forms\Components\TextInput::make('taux_tva')
                                            ->label('TVA %')
                                            ->numeric()
                                            ->default(19.25)
                                            //->suffix('%')
                                            ->columnSpan(1),

                                        Forms\Components\TextInput::make('taux_ir')
                                            ->label('IR %')
                                            ->numeric()
                                            ->default(5.5)
                                            //->suffix('%')
                                            ->columnSpan(1),

                                        Forms\Components\Placeholder::make('montant_ht_preview')
                                            ->label('MHT')
                                            ->content(function (callable $get) {
                                                $qte = floatval($get('quantite') ?? 0);
                                                $pu = floatval($get('prix_unitaire') ?? 0);
                                                $mht = $qte * $pu;
                                                return number_format($mht, 0, ',', ' ') . ' F';
                                            })
                                            ->columnSpan(1),

                                        Forms\Components\Placeholder::make('ttc_preview')
                                            ->label('TTC')
                                            ->content(function (callable $get) {
                                                $qte = floatval($get('quantite') ?? 0);
                                                $pu = floatval($get('prix_unitaire') ?? 0);
                                                $tauxTva = floatval($get('taux_tva') ?? 19.25);

                                                $mht = $qte * $pu;
                                                $tva = $mht * ($tauxTva / 100);
                                                $ttc = $mht + $tva;

                                                return number_format($ttc, 0, ',', ' ') . ' F';
                                            })
                                            ->columnSpan(1),

                                        Forms\Components\Placeholder::make('nap_preview')
                                            ->label('NAP')
                                            ->content(function (callable $get) {
                                                $qte = floatval($get('quantite') ?? 0);
                                                $pu = floatval($get('prix_unitaire') ?? 0);
                                                $tauxTva = floatval($get('taux_tva') ?? 19.25);
                                                $tauxIr = floatval($get('taux_ir') ?? 5.5);

                                                $mht = $qte * $pu;
                                                $tva = $mht * ($tauxTva / 100);
                                                $ttc = $mht + $tva;
                                                $ir = $mht * ($tauxIr / 100);
                                                $nap = $ttc - $ir;

                                                return number_format($nap, 0, ',', ' ') . ' F';
                                            })
                                            ->columnSpan(1),
                                    ]),
                            ])
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
                                    return 'Les totaux seront calculés automatiquement';
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
                    ->limit(50)
                    ->searchable()
                    ->tooltip(fn($record) => $record->objet),

                Tables\Columns\TextColumn::make('montant_ttc')
                    ->label('Montant TTC')
                    ->money('XAF')
                    ->sortable()
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
                Tables\Actions\Action::make('generer_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->url(fn($record) => route('memoire-depense.pdf', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('valider')
                    ->label('Valider')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn($record) => $record->statut === 'brouillon')
                    ->action(function ($record) {
                        $record->valider();

                        Notification::make()
                            ->success()
                            ->title('Mémoire validé')
                            ->body("Le mémoire {$record->numero} a été validé.")
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
        return [
            //
        ];
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
