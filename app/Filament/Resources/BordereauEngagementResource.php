<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BordereauEngagementResource\Pages;
use App\Filament\Resources\BordereauEngagementResource\RelationManagers;
use App\Models\BordereauEngagement;
use App\Models\Budget;
use App\Models\Engagement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use App\Services\PdfGenerator\PdfGenerator;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use App\Filament\Forms\Components\ExerciceSelect;
use App\Models\Exercice;

class BordereauEngagementResource extends Resource
{
    protected static ?string $model = BordereauEngagement::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Bordereaux de transmission';

    protected static ?string $modelLabel = 'Bordereau de transmission';

    protected static ?string $pluralModelLabel = 'Bordereaux de transmission';

    protected static ?string $navigationGroup = 'Commandes & Engagement';

    protected static ?int $navigationSort = 2;

    
    // ========================================
    // PERMISSIONS
    // ========================================

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_bordereau_engagement') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->can('view_bordereau_engagement') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_bordereau_engagement') ?? false;
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();

        if (!$user?->can('update_bordereau_engagement')) {
            return false;
        }

        // Règle métier : Seuls les brouillons sont modifiables
        if (!$record->estModifiable()) {
            return false;
        }

        // Override pour super admin / DAAF
        if ($user->can('override_bordereau_engagement')) {
            return true;
        }

        // Sinon, seulement ses propres bordereaux
        return $record->statut === 'brouillon' && $record->emis_par === $user->id;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('delete_bordereau_engagement') && $record->estModifiable();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Exercice')
                    ->description('Exercice budgétaire de rattachement')
                    ->schema([
                        ExerciceSelect::make(),
                    ])
                    ->collapsible()
                    ->collapsed(fn($record) => $record !== null),

                Forms\Components\Section::make('Informations principales')
                    ->schema([
                        Forms\Components\Select::make('budget_id')
                            ->label('Budget')
                            ->options(Budget::where('actif', true)->pluck('libelle', 'id'))
                            ->required()
                            ->searchable()
                            ->preload(),

                        Forms\Components\DatePicker::make('date_emission')
                            ->label('Date d\'émission')
                            ->default(now())
                            ->required(),

                        Forms\Components\TextInput::make('instance_destinataire')
                            ->label('Instance destinataire')
                            ->placeholder('Ex: Contrôle Financier, Tutelle, Direction Générale')
                            ->maxLength(255)
                            ->helperText('Optionnel : sera renseigné lors de la transmission'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Objet')
                    ->schema([
                        Forms\Components\Textarea::make('objet')
                            ->label('Objet du bordereau')
                            ->required()
                            ->rows(3)
                            ->placeholder('Ex: Transmission engagements décembre 2026')
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('observations')
                            ->label('Observations')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                // ✅ NOUVELLE SECTION : Sélection des engagements
                Forms\Components\Section::make('Engagements à inclure')
                    ->description('Sélectionnez les engagements à attacher à ce bordereau')
                    ->schema([
                        Forms\Components\Select::make('engagements')
                            ->label('Engagements')
                            ->relationship(
                                name: 'engagements',
                                titleAttribute: 'numero',
                                modifyQueryUsing: fn($query) => $query
                                    ->where('statut', 'definitif')
                                    ->whereDoesntHave('bordereaux', function ($q) {
                                        $q->where('statut', 'valide');
                                    })
                                    ->orderBy('date_engagement', 'desc')
                            )
                            ->multiple()
                            ->preload()  // ← CRUCIAL : Charge toutes les options
                            ->searchable(['numero', 'objet', 'reference_document'])
                            ->getOptionLabelFromRecordUsing(
                                fn($record) =>
                                sprintf(
                                    '%s - %s (%s FCFA) - %s',
                                    $record->numero,
                                    \Str::limit($record->objet, 50),
                                    number_format($record->montant_engage, 0, ',', ' '),
                                    $record->date_engagement->format('d/m/Y')
                                )
                            )
                            ->helperText('Seuls les engagements définitifs non encore validés dans un bordereau sont affichés')
                            ->columnSpanFull()
                            ->hiddenOn('edit'),  // Masquer en édition (utiliser le RelationManager)
                    ])
                    ->hiddenOn('edit'),  // Masquer toute la section en édition

                Forms\Components\Section::make('Informations automatiques')
                    ->schema([
                        Forms\Components\Placeholder::make('numero')
                            ->label('Numéro')
                            ->content(fn($record) => $record?->numero ?? 'Généré automatiquement'),

                        Forms\Components\Placeholder::make('nombre_engagements')
                            ->label('Nombre d\'engagements')
                            ->content(fn($record) => $record?->nombre_engagements ?? 0),

                        Forms\Components\Placeholder::make('montant_total')
                            ->label('Montant total')
                            ->content(
                                fn($record) =>
                                $record ? number_format($record->montant_total, 0, ',', ' ') . ' FCFA' : '0 FCFA'
                            ),
                    ])
                    ->columns(3)
                    ->visible(fn($record) => $record !== null),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->with(['exercice']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\BadgeColumn::make('exercice.annee')
                    ->label('Exercice')
                    ->sortable()
                    ->colors([
                        'success' => fn($record) =>
                        $record->exercice instanceof \App\Models\Exercice && $record->exercice->estActif(),
                        'warning' => fn($record) =>
                        $record->exercice instanceof \App\Models\Exercice && $record->exercice->estCloture(),
                        'danger' => fn($record) =>
                        $record->exercice instanceof \App\Models\Exercice && $record->exercice->estArchive(),
                        'gray' => fn($record) =>
                        $record->exercice instanceof \App\Models\Exercice && $record->exercice->estBrouillon(),
                    ])
                    ->tooltip(
                        fn($record) =>
                        $record->exercice instanceof \App\Models\Exercice
                            ? $record->exercice->libelle
                            : null
                    )
                    ->toggleable(),

                Tables\Columns\TextColumn::make('numero')
                    ->label('N° Bordereau')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('budget.code')
                    ->label('Budget')
                    ->searchable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('date_emission')
                    ->label('Date émission')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('emetteur.name')
                    ->label('Émis par')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('instance_destinataire')
                    ->label('Destinataire')
                    ->searchable()
                    ->limit(30)
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('nombre_engagements')
                    ->label('Nb Eng.')
                    ->alignCenter()
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('montant_total')
                    ->label('Montant Total')
                    ->money('XAF')
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\TextColumn::make('detenuPar.name')
                    ->label('Détenu par')
                    ->searchable()
                    ->badge()
                    ->color('warning')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('jours_attente')
                    ->label('Délai')
                    ->suffix(' j')
                    ->badge()
                    ->color(fn($state) => match (true) {
                        $state > 10 => 'danger',
                        $state > 5 => 'warning',
                        default => 'success',
                    })
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('priorite')
                    ->label('Priorité')
                    ->colors([
                        'success' => 'normale',
                        'warning' => 'urgente',
                        'danger' => 'tres_urgente',
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'normale' => 'Normale',
                        'urgente' => 'Urgente',
                        'tres_urgente' => 'Très urgente',
                        default => $state
                    }),

                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
                    ->colors([
                        'secondary' => 'brouillon',
                        'info' => 'transmis',
                        'warning' => 'en_cours',
                        'success' => 'valide',
                        'danger' => fn($state) => in_array($state, ['rejete_total', 'rejete_partiel']),
                        'gray' => 'retourne',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'brouillon' => 'Brouillon',
                        'transmis' => 'Transmis',
                        'en_cours' => 'En cours',
                        'valide' => 'Validé',
                        'rejete_partiel' => 'Rejeté partiel',
                        'rejete_total' => 'Rejeté total',
                        'retourne' => 'Retourné',
                        default => $state,
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('exercice_id')
                    ->label('Exercice')
                    ->relationship('exercice', 'annee')
                    ->searchable()
                    ->preload()
                    ->placeholder('Tous les exercices')
                    ->default(fn() => Exercice::getActif()?->id),

                Tables\Filters\SelectFilter::make('budget_id')
                    ->label('Budget')
                    ->relationship('budget', 'libelle')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('statut')
                    ->label('Statut')
                    ->options([
                        'brouillon' => 'Brouillon',
                        'transmis' => 'Transmis',
                        'en_cours' => 'En cours',
                        'valide' => 'Validé',
                        'rejete_partiel' => 'Rejeté partiel',
                        'rejete_total' => 'Rejeté total',
                        'retourne' => 'Retourné',
                    ]),
                Tables\Filters\SelectFilter::make('detenu_par_id')
                    ->label('Détenu par')
                    ->relationship('detenuPar', 'name')
                    ->multiple(),

                Tables\Filters\Filter::make('mes_bordereaux')
                    ->label('Mes bordereaux')
                    ->query(fn($query) => $query->where('detenu_par_id', auth()->id())),

                Tables\Filters\Filter::make('en_retard')
                    ->label('En retard (> 5 jours)')
                    ->query(fn($query) => $query->where('jours_attente', '>', 5)),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => $record->estModifiable()),

                Tables\Actions\Action::make('transmettre')
                    ->label('Transmettre')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->visible(
                        fn($record) =>
                        $record->statut === 'brouillon'
                            && auth()->user()?->can('transmettre_bordereau')
                    )
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Select::make('destinataire_id')
                            ->label('Transmettre à')
                            ->options(
                                fn() =>
                                \App\Models\User::actif()
                                    ->where('id', '!=', auth()->id())
                                    ->whereHas(
                                        'roles',
                                        fn($q) =>
                                        $q->whereIn('name', [
                                            'controleur_financier',
                                            'daaf',
                                            'directeur_general',
                                        ])
                                    )
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn($user) => [
                                        $user->id => sprintf(
                                            '%s (%s)',
                                            $user->name,
                                            $user->roles->pluck('name')
                                                ->map(fn($r) => ucfirst(str_replace('_', ' ', $r)))
                                                ->join(', ')
                                        )
                                    ])
                            )
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText('Destinataire autorisé : Contrôle Financier, DAAF ou Direction Générale'),

                        Forms\Components\Textarea::make('observations')
                            ->label('Observations')
                            ->rows(3)
                            ->placeholder('Commentaire de transmission (optionnel)'),
                    ])
                    ->action(function ($record, array $data) {

                        $expediteur   = auth()->user();
                        $destinataire = \App\Models\User::actif()->findOrFail($data['destinataire_id']);

                        // 🔐 Sécurité serveur ABSOLUE
                        if (! $destinataire->hasAnyRole([
                            'controleur_financier',
                            'daaf',
                            'directeur_general',
                        ])) {
                            throw new \Exception(
                                'Le destinataire sélectionné n’est pas autorisé à recevoir un bordereau d’engagement.'
                            );
                        }

                        $record->transmettre(
                            user: $expediteur,
                            destinataire: $destinataire,
                            observations: $data['observations'] ?? null
                        );

                        Notification::make()
                            ->title('Bordereau transmis')
                            ->success()
                            ->body("Transmis à {$destinataire->name}")
                            ->send();
                    }),

                Tables\Actions\Action::make('receptionner')
                    ->label('Réceptionner')
                    ->icon('heroicon-o-inbox-arrow-down')
                    ->color('warning')
                    ->visible(fn($record) => $record->statut === 'transmis')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        try {
                            $record->receptionner(auth()->user());
                            Notification::make()
                                ->title('Bordereau réceptionné')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Erreur')
                                ->danger()
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
            ])

            ->actions([
                Tables\Actions\ActionGroup::make([
                    // ✅ Voir le bordereau
                    Tables\Actions\ViewAction::make()
                        ->label('Voir le détail')
                        ->icon('heroicon-o-eye')
                        ->color('info'),

                    // ✅ Éditer (seulement si brouillon)
                    Tables\Actions\EditAction::make()
                        ->label('Modifier')
                        ->icon('heroicon-o-pencil')
                        ->color('warning')
                        ->visible(fn($record) => $record->statut === 'brouillon'),

                    // ✅ Télécharger le PDF du bordereau
                    Tables\Actions\Action::make('telecharger_pdf')
                        ->label('Télécharger PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('primary')
                        ->url(fn($record) => route('pdf.telecharger', [
                            'etat' => 'bordereau_engagement',
                            'id' => $record->id,
                        ]))
                        ->openUrlInNewTab(),

                    // ✅ Afficher le PDF dans le navigateur
                    Tables\Actions\Action::make('afficher_pdf')
                        ->label('Aperçu PDF')
                        ->icon('heroicon-o-document-magnifying-glass')
                        ->color('gray')
                        ->url(fn($record) => route('pdf.afficher', [
                            'etat' => 'bordereau_engagement',
                            'id' => $record->id,
                        ]))
                        ->openUrlInNewTab(),

                    // ✅ Supprimer (seulement si brouillon)
                    Tables\Actions\DeleteAction::make()
                        ->label('Supprimer')
                        ->icon('heroicon-o-trash')
                        ->visible(fn($record) => $record->statut === 'brouillon')
                        ->requiresConfirmation()
                        ->modalHeading('Supprimer le bordereau')
                        ->modalDescription('Êtes-vous sûr de vouloir supprimer ce bordereau ? Cette action est irréversible.'),
                ])
                    ->label('Actions')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->size('sm')
                    ->color('gray')
                    ->button(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\EngagementsRelationManager::class,
            RelationManagers\MouvementsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBordereauEngagements::route('/'),
            'create' => Pages\CreateBordereauEngagement::route('/create'),
            'edit' => Pages\EditBordereauEngagement::route('/{record}/edit'),
            'view' => Pages\ViewBordereauEngagement::route('/{record}'),
        ];
    }
}
