<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\BonCommandeRegieResource\Pages;
use App\Filament\Budget\Resources\BonCommandeRegieResource\RelationManagers;
use App\Models\BonCommandeRegie;
use App\Models\RegieAvance;
use App\Models\Fournisseur;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use App\Models\Exercice;

class BonCommandeRegieResource extends Resource
{
    protected static ?string $model = BonCommandeRegie::class;

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
            && $record->statut === 'brouillon';
    }

    // =========================================================
    // FORMULAIRE
    // =========================================================
    public static function form(Form $form): Form
    {
        return $form->schema([

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

                        Forms\Components\Placeholder::make('statut')
                            ->label('Statut')
                            ->content(function ($record) {
                                if (!$record) return '—';
                                $labels = [
                                    'brouillon' => 'Brouillon',
                                    'valide'    => 'Validé',
                                    'livre'     => 'Livré',
                                    'paye'      => 'Payé',
                                    'annule'    => 'Annulé',
                                ];
                                return $labels[$record->statut] ?? $record->statut;
                            }),
                    ]),
                ]),

            Forms\Components\Section::make('Régie source')
                ->schema([
                    Forms\Components\Select::make('regie_avance_id')
                        ->label('Régie / Menu Dépense')
                        ->options(function () {
                            return RegieAvance::where('statut', 'actif')
                                ->where(function ($q) {
                                    $user = auth()->user();
                                    if (!$user->hasAnyRole([
                                        'super_admin',
                                        'admin',
                                        'daaf',
                                        'agence_comptable'
                                    ])) {
                                        $q->where('responsable_id', $user->id);
                                    }
                                })
                                ->with('exercice')
                                ->get()
                                ->mapWithKeys(fn($r) => [
                                    $r->id => "{$r->numero} — {$r->libelle} "
                                        . "({$r->label_type}) "
                                        . "| Dispo: "
                                        . number_format($r->montant_disponible, 0, ',', ' ')
                                        . " FCFA"
                                ]);
                        })
                        ->required()
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set) {
                            // Réinitialiser la dépense liée si on change de régie
                            $set('depense_regie_id', null);
                        })
                        ->columnSpanFull(),

                    // ── Dépense liée (optionnel) ──────────────
                    Forms\Components\Select::make('depense_regie_id')
                        ->label('Dépense régie associée')
                        ->options(function (Get $get) {
                            $regieId = $get('regie_avance_id');
                            if (!$regieId) return [];
                            return \App\Models\DepenseRegie::where('regie_avance_id', $regieId)
                                ->where('type_depense', 'bon_commande')
                                ->whereIn('statut', ['brouillon', 'valide'])
                                ->get()
                                ->mapWithKeys(fn($d) => [
                                    $d->id => "{$d->numero} — {$d->objet}"
                                ]);
                        })
                        ->searchable()
                        ->nullable()
                        ->helperText('Optionnel — rattacher à une dépense existante'),
                ]),

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
                        ->createOptionUsing(function (array $data) {
                            return Fournisseur::create($data)->id;
                        }),

                    Forms\Components\Textarea::make('objet')
                        ->label('Objet du bon de commande')
                        ->required()->rows(2)->columnSpanFull(),

                    Forms\Components\Textarea::make('observations')
                        ->label('Observations')->rows(2)->columnSpanFull(),
                ])
                ->columns(2),
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
                    ->searchable()->sortable()
                    ->weight('bold')->copyable(),

                Tables\Columns\TextColumn::make('regieAvance.numero')
                    ->label('Régie source')
                    ->badge()->color('info')->searchable(),

                Tables\Columns\TextColumn::make('regieAvance.type')
                    ->label('Type')
                    ->formatStateUsing(fn($state) => match ($state) {
                        'rav'          => 'RAV',
                        'menu_depense' => 'MD',
                        default        => $state,
                    })
                    ->badge()
                    ->color(fn($state) => $state === 'rav' ? 'primary' : 'warning'),

                Tables\Columns\TextColumn::make('fournisseur.raison_sociale')
                    ->label('Fournisseur')
                    ->searchable()->limit(25),

                Tables\Columns\TextColumn::make('date_emission')
                    ->label('Date')->date('d/m/Y')->sortable(),

                Tables\Columns\TextColumn::make('objet')
                    ->label('Objet')->limit(30)
                    ->tooltip(fn($record) => $record->objet),

                Tables\Columns\TextColumn::make('montant_ttc')
                    ->label('Montant TTC')
                    ->money('XAF')->sortable()->weight('bold'),

                Tables\Columns\TextColumn::make('montant_ir')
                    ->label('IR')
                    ->money('XAF')->color('warning'),

                Tables\Columns\TextColumn::make('net_a_payer')
                    ->label('Net à payer')
                    ->money('XAF')->color('success')->weight('bold'),

                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
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
                            ->when($data['du'], fn($q, $v) => $q->whereDate('date_emission', '>=', $v))
                            ->when($data['au'], fn($q, $v) => $q->whereDate('date_emission', '<=', $v))
                    ),
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
                            && auth()->user()?->can('valider_bon_commande_regie')
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Valider le bon de commande')
                    ->modalDescription(
                        fn($record) =>
                        "Valider le {$record->numero} d'un montant TTC de "
                            . number_format($record->montant_ttc, 0, ',', ' ') . " FCFA ?"
                    )
                    ->action(function ($record) {
                        $record->update(['statut' => 'valide']);
                        Notification::make()
                            ->title('✅ BCR/BCM validé')->success()->send();
                    }),

                // ── Marquer livré ─────────────────────────────
                Tables\Actions\Action::make('livrer')
                    ->label('Marquer livré')
                    ->icon('heroicon-o-truck')->color('info')
                    ->visible(
                        fn($record) =>
                        in_array($record->statut, ['valide', 'livre_partiellement'])
                            && auth()->user()?->can('valider_bon_commande_regie')
                    )
                    ->requiresConfirmation()
                    ->action(fn($record) => $record->update(['statut' => 'livre'])),

                // ── Marquer payé ──────────────────────────────
                Tables\Actions\Action::make('payer')
                    ->label('Marquer payé')
                    ->icon('heroicon-o-banknotes')->color('primary')
                    ->visible(
                        fn($record) =>
                        $record->statut === 'livre'
                            && auth()->user()?->can('valider_bon_commande_regie')
                    )
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
                            ->label('Motif d\'annulation')->rows(2)->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'statut'       => 'annule',
                            'observations' => ($record->observations ?? '')
                                . "\n\n--- ANNULÉ LE " . now()->format('d/m/Y') . " ---\n"
                                . "Motif : " . $data['motif'],
                        ]);
                        Notification::make()->title('BCR/BCM annulé')->warning()->send();
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
            ->with(['regieAvance', 'fournisseur', 'lignes']);

        $user = auth()->user();
        if ($user && !$user->hasAnyRole([
            'super_admin',
            'admin',
            'daaf',
            'agence_comptable',
            'controleur_financier'
        ])) {
            // Responsable régie : ne voit que les BCR de ses régies
            $query->whereHas(
                'regieAvance',
                fn($q) =>
                $q->where('responsable_id', $user->id)
            );
        }

        return $query;
    }
}
