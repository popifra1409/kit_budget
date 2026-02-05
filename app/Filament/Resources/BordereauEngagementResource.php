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


    /**
     * Permissions - Bordereau d'engagement avec workflow complet
     */

    // ========================================
    // CRUD Standard
    // ========================================

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_bordereau') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->can('view_bordereau') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_bordereau') ?? false;
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();

        if (!$user?->can('update_bordereau')) {
            return false;
        }

        // règle métier
        if (!$record->estModifiable()) {
            Notification::make()
                ->title('Bordereau verrouillé')
                ->warning()
                ->body("L'exercice {$record->exercice->annee} est {$record->exercice->statut}.")
                ->send();

            return false;
        }

        // si pas super admin → seulement ses brouillons
        if (!$user->can('override_bordereau')) {
            return $record->statut === 'brouillon'
                && $record->emis_par === $user->id;
        }

        return true;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('delete_bordereau') &&
            $record->estModifiable();
    }

    public static function canEditRecord($record): bool
    {
        $canEdit = static::canEdit($record);

        if (!$canEdit && $record->estLectureSeule()) {
            Notification::make()
                ->title('Édition impossible')
                ->warning()
                ->body("Exercice {$record->exercice->annee} en lecture seule.")
                ->send();
        }

        return $canEdit;
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
        return parent::getEloquentQuery()->with('exercice');
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
                    ->visible(fn($record) => $record->statut === 'brouillon')
                    ->requiresConfirmation()
                    ->form([
                        // ✅ NOUVEAU : Sélection du destinataire (utilisateur)
                        Forms\Components\Select::make('destinataire_id')
                            ->label('Transmettre à')
                            ->options(function () {
                                return \App\Models\User::whereHas('roles', function ($query) {
                                    $query->whereIn('name', [
                                        'chef_service_budget',
                                        'sous_directeur_budget',
                                        'directeur_general',
                                        'controleur_financier',
                                        'agence_comptable'
                                    ]);
                                })->get()->mapWithKeys(fn($user) => [
                                    $user->id => $user->name . ' - ' .
                                        ($user->roles->first()?->name ? match ($user->roles->first()->name) {
                                            'chef_service_budget' => 'Chef Service Budget',
                                            'sous_directeur_budget' => 'Sous-Directeur Budget',
                                            'directeur_general' => 'Directeur Général',
                                            'controleur_financier' => 'Contrôleur Financier',
                                            'agence_comptable' => 'Agence Comptable',
                                            default => $user->roles->first()->name
                                        } : 'Utilisateur')
                                ]);
                            })
                            ->required()
                            ->searchable()
                            ->helperText('Sélectionnez l\'utilisateur destinataire'),

                        Forms\Components\Textarea::make('observations')
                            ->label('Observations')
                            ->rows(3)
                            ->placeholder('Commentaire de transmission (optionnel)'),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            $destinataire = \App\Models\User::findOrFail($data['destinataire_id']);

                            // Appeler la méthode modifiée avec User au lieu de string
                            $record->transmettre(
                                user: auth()->user(),
                                destinataire: $destinataire,
                                observations: $data['observations'] ?? null
                            );

                            Notification::make()
                                ->title('Bordereau transmis')
                                ->success()
                                ->body("Transmis à {$destinataire->name}")
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Erreur')
                                ->danger()
                                ->body($e->getMessage())
                                ->send();
                        }
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
                ActionGroup::make([
                    // Vos actions existantes...

                    Action::make('telecharger_certificat')
                        ->label('Certificat d\'engagement')
                        ->icon('heroicon-o-document-text')
                        ->color('success')
                        ->url(fn($record) => route('pdf.telecharger', [
                            'etat' => 'certificat_engagement',
                            'id' => $record->id
                        ])),

                    Action::make('afficher_certificat')
                        ->label('Aperçu Certificat')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->url(fn($record) => route('pdf.afficher', [
                            'etat' => 'certificat_engagement',
                            'id' => $record->id
                        ]))
                        ->openUrlInNewTab(),

                    Action::make('telecharger_autorisation')
                        ->label('Autorisation d\'engagement')
                        ->icon('heroicon-o-document-check')
                        ->color('warning')
                        ->url(fn($record) => route('pdf.telecharger', [
                            'etat' => 'autorisation_engagement',
                            'id' => $record->id
                        ])),

                    Action::make('afficher_autorisation')
                        ->label('Aperçu Autorisation')
                        ->icon('heroicon-o-eye')
                        ->color('gray')
                        ->url(fn($record) => route('pdf.afficher', [
                            'etat' => 'autorisation_engagement',
                            'id' => $record->id
                        ]))
                        ->openUrlInNewTab(),
                ])
                    ->label('États PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('primary')
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
