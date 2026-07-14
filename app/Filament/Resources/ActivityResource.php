<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityResource\Pages;
use App\Models\ActivityLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;

class ActivityResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static ?string $navigationIcon  = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Journal d\'activité';
    protected static ?string $modelLabel      = 'Activité';
    protected static ?string $pluralModelLabel = 'Journal d\'activité';
    protected static ?string $navigationGroup = 'Audit';
    protected static ?int    $navigationSort  = 1;

    // =========================================================
    // PERMISSIONS
    // =========================================================
    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_activity') ?? false;
    }
    public static function canCreate(): bool
    {
        return false;
    }
    public static function canEdit($record): bool
    {
        return false;
    }
    public static function canDelete($record): bool
    {
        return auth()->user()?->can('delete_activity') ?? false;
    }

    // =========================================================
    // TABLE
    // =========================================================
    public static function table(Table $table): Table
    {
        return $table
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->groups([
                Tables\Grouping\Group::make('event')
                    ->label('Par evenement')
                    ->collapsible()
            ])
            ->defaultGroup("event")
            ->deferLoading()
            ->columns([

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date & Heure')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable()
                    ->searchable()
                    ->size('sm'),

                // ✅ Événement avec couleur sémantique
                Tables\Columns\BadgeColumn::make('event')
                    ->label('Événement')
                    ->formatStateUsing(fn($state, $record) => $record->getEventLabel())
                    ->color(fn($state) => match ($state) {
                        'created'       => 'success',
                        'updated'       => 'info',
                        'deleted'       => 'danger',
                        'login'         => 'success',
                        'logout'        => 'gray',
                        'valider'       => 'success',
                        'engager'       => 'primary',
                        'annuler'       => 'danger',
                        'recuperer'     => 'warning',
                        'desengager'    => 'warning',
                        'devalider'     => 'warning',
                        'transformer'   => 'info',
                        'marquer_payee' => 'success',
                        'transmettre'   => 'info',
                        'cloturer'      => 'success',
                        'retourner'     => 'warning',
                        'access_denied' => 'danger',
                        default         => 'gray',
                    })
                    ->sortable(),

                // ✅ Type de document lisible
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Type')
                    ->formatStateUsing(fn($state, $record) => $record->getSubjectLabel())
                    ->badge()
                    ->color(fn($state) => match (class_basename($state ?? '')) {
                        'BonCommande'            => 'info',
                        'DecisionAdministrative' => 'warning',
                        'Engagement'             => 'primary',
                        'OrdonnancePaiement'     => 'success',
                        'MemoireDepense'         => 'danger',
                        'Budget'                 => 'gray',
                        'User'                   => 'purple',
                        'Transmission'           => 'cyan',
                        default                  => 'gray',
                    })
                    ->searchable(),

                // ✅ N° Document — plus utile que l'ID seul
                Tables\Columns\TextColumn::make('document_numero')
                    ->label('N° Document')
                    ->getStateUsing(fn($record) => $record->getDocumentNumero() ?? "ID:{$record->subject_id}")
                    ->weight('bold')
                    ->color('primary')
                    ->searchable(query: function (Builder $query, string $search) {
                        // Recherche via jointure sur le sujet
                        $query->whereHas('subject', fn($q) => $q->where('numero', 'like', "%{$search}%"));
                    })
                    ->copyable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(60)
                    ->tooltip(fn($record) => $record->description)
                    ->wrap(),

                Tables\Columns\TextColumn::make('causer.name')
                    ->label('Utilisateur')
                    ->searchable()
                    ->sortable()
                    ->default('Système')
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->copyable()
                    ->default('—')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // ✅ Indication s'il y a des champs modifiés
                Tables\Columns\IconColumn::make('has_changes')
                    ->label('Diff')
                    ->getStateUsing(fn($record) => !empty($record->getChangedFields()))
                    ->boolean()
                    ->trueIcon('heroicon-o-arrow-path')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->tooltip(
                        fn($record) => !empty($record->getChangedFields())
                            ? count($record->getChangedFields()) . ' champ(s) modifié(s)'
                            : 'Aucune modification'
                    )
                    ->toggleable(),
            ])
            ->filters([

                Tables\Filters\SelectFilter::make('event')
                    ->label('Événement')
                    ->options([
                        // ── CRUD ──────────────────────────
                        'created' => '✅ Créé',
                        'updated' => '✏️ Modifié',
                        'deleted' => '🗑️ Supprimé',
                        // ── Workflow budget ────────────────
                        'valider'       => '✅ Validé',
                        'engager'       => '💰 Engagé',
                        'annuler'       => '❌ Annulé',
                        'recuperer'     => '🔄 Récupéré',
                        'desengager'    => '↩️ Désengagé',
                        'devalider'     => '⬇️ Dévalidé',
                        'transformer'   => '🔀 Transformé',
                        'marquer_payee' => '💳 Payé',
                        // ── Transmissions ──────────────────
                        'transmettre'  => '📤 Transmis',
                        'cloturer'     => '🏁 Clôturé',
                        'retourner'    => '↩️ Retourné',
                        // ── Auth & Sécurité ─────────────────
                        'login'         => '🔓 Connexion',
                        'logout'        => '🔒 Déconnexion',
                        'access_denied' => '🚫 Accès refusé',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('subject_type')
                    ->label('Type de document')
                    ->options([
                        'App\Models\BonCommande'            => 'Bon de Commande',
                        'App\Models\DecisionAdministrative' => 'Décision Administrative',
                        'App\Models\Engagement'             => 'Engagement',
                        'App\Models\OrdonnancePaiement'     => 'Ordonnance de Paiement',
                        'App\Models\MemoireDepense'         => 'Mémoire de Dépense',
                        'App\Models\BordereauEngagement'    => 'Bordereau d\'Engagement',
                        'App\Models\Budget'                 => 'Budget',
                        'App\Models\LigneBudgetaire'        => 'Ligne Budgétaire',
                        'App\Models\RegieAvance'            => 'Régie d\'Avance',
                        'App\Models\Exercice'               => 'Exercice',
                        'App\Models\Transmission'           => 'Transmission',
                        'App\Models\VirementBudgetaire'     => 'Virement Budgétaire',
                        'App\Models\User'                   => 'Utilisateur',
                    ]),

                Tables\Filters\SelectFilter::make('causer_id')
                    ->label('Utilisateur')
                    ->options(fn() => \App\Models\User::orderBy('name')->pluck('name', 'id'))
                    ->searchable(),

                Tables\Filters\SelectFilter::make('log_name')
                    ->label('Catégorie')
                    ->options([
                        'default'  => 'Général',
                        'workflow' => 'Workflow',
                        'auth'     => 'Authentification',
                        'security' => 'Sécurité',
                    ]),

                Tables\Filters\Filter::make('periode')
                    ->label('Période')
                    ->form([
                        Forms\Components\DatePicker::make('du')->label('Du'),
                        Forms\Components\DatePicker::make('au')->label('Au'),
                    ])
                    ->query(
                        fn(Builder $query, array $data) => $query
                            ->when($data['du'], fn($q, $d) => $q->whereDate('created_at', '>=', $d))
                            ->when($data['au'], fn($q, $d) => $q->whereDate('created_at', '<=', $d))
                    )
                    ->indicateUsing(fn(array $data): array => array_filter([
                        $data['du'] ? "Du : {$data['du']}" : null,
                        $data['au'] ? "Au : {$data['au']}" : null,
                    ])),

                Tables\Filters\Filter::make('aujourd_hui')
                    ->label('Aujourd\'hui')
                    ->query(fn($query) => $query->whereDate('created_at', today()))
                    ->toggle(),

                Tables\Filters\Filter::make('access_denied')
                    ->label('Accès refusés uniquement')
                    ->query(fn($query) => $query->where('event', 'access_denied'))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn() => auth()->user()->hasRole('super_admin')),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
    }

    // =========================================================
    // INFOLIST — Vue détaillée d'une activité
    // =========================================================
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([

            Infolists\Components\Section::make('Événement')
                ->schema([
                    Infolists\Components\Grid::make(4)->schema([

                        Infolists\Components\TextEntry::make('event')
                            ->label('Type d\'événement')
                            ->formatStateUsing(fn($state, $record) => $record->getEventLabel())
                            ->badge()
                            ->color(fn($state) => match ($state) {
                                'created', 'valider', 'login', 'marquer_payee', 'cloturer' => 'success',
                                'updated', 'transformer', 'transmettre'                    => 'info',
                                'deleted', 'annuler', 'access_denied'                     => 'danger',
                                'recuperer', 'desengager', 'devalider', 'retourner'       => 'warning',
                                'engager'                                                  => 'primary',
                                default                                                    => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('log_name')
                            ->label('Catégorie')
                            ->badge()->color('gray'),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Date & Heure')
                            ->dateTime('d/m/Y à H:i:s'),

                        Infolists\Components\TextEntry::make('description')
                            ->label('Description')
                            ->columnSpanFull()
                            ->weight('bold'),
                    ]),
                ]),

            Infolists\Components\Section::make('Auteur & Accès')
                ->schema([
                    Infolists\Components\Grid::make(4)->schema([

                        Infolists\Components\TextEntry::make('causer.name')
                            ->label('Utilisateur')
                            ->default('Système')
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('causer.email')
                            ->label('Email')
                            ->default('—')
                            ->copyable(),

                        Infolists\Components\TextEntry::make('ip_address')
                            ->label('Adresse IP')
                            ->default('—')
                            ->copyable(),

                        Infolists\Components\TextEntry::make('user_agent')
                            ->label('Navigateur')
                            ->default('—')
                            ->limit(60)
                            ->tooltip(fn($record) => $record->user_agent),
                    ]),
                ]),

            Infolists\Components\Section::make('Document concerné')
                ->schema([
                    Infolists\Components\Grid::make(3)->schema([

                        Infolists\Components\TextEntry::make('subject_type_label')
                            ->label('Type')
                            ->getStateUsing(fn($record) => $record->getSubjectLabel())
                            ->badge()->color('info'),

                        Infolists\Components\TextEntry::make('document_numero')
                            ->label('Numéro')
                            ->getStateUsing(
                                fn($record) =>
                                $record->getDocumentNumero() ?? "ID: {$record->subject_id}"
                            )
                            ->weight('bold')
                            ->copyable(),

                        Infolists\Components\TextEntry::make('document_statut')
                            ->label('Statut actuel')
                            ->getStateUsing(fn($record) => $record->subject?->statut ?? '—')
                            ->badge(),
                    ]),
                ])
                ->visible(fn($record) => $record->subject_type !== null),

            // ✅ Diff avant/après pour les événements 'updated'
            Infolists\Components\Section::make('Modifications')
                ->schema([
                    Infolists\Components\TextEntry::make('changes_display')
                        ->label('')
                        ->getStateUsing(fn($record) => $record->getChangedFields())
                        ->formatStateUsing(fn($state) => $state)
                        ->view('filament.infolists.activity-diff')
                        ->columnSpanFull(),
                ])
                ->visible(fn($record) => !empty($record->getChangedFields())),

            // ✅ Détails métier pour les actions workflow
            Infolists\Components\Section::make('Détails de l\'action')
                ->schema([
                    Infolists\Components\KeyValueEntry::make('properties.details')
                        ->label('')
                        ->columnSpanFull()
                        ->keyLabel('Paramètre')
                        ->valueLabel('Valeur'),
                ])
                ->visible(
                    fn($record) =>
                    !empty($record->properties?->get('details'))
                        && $record->event !== 'updated'
                        && $record->event !== 'created'
                ),

            Infolists\Components\Section::make('Contexte HTTP')
                ->schema([
                    Infolists\Components\Grid::make(2)->schema([
                        Infolists\Components\TextEntry::make('context_url')
                            ->label('URL')
                            ->getStateUsing(fn($record) => $record->getContextUrl() ?? '—')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('context_route')
                            ->label('Route')
                            ->getStateUsing(
                                fn($record) =>
                                $record->properties?->get('_context.route') ?? '—'
                            ),
                    ]),
                ])
                ->collapsible()->collapsed(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivities::route('/'),
            'view'  => Pages\ViewActivity::route('/{record}'),
        ];
    }
}
