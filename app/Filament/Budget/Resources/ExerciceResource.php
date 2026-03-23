<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\ExerciceResource\Pages;
use App\Models\Exercice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use App\Services\ReconductionExerciceService;
use App\Filament\Clusters\GestionBudgetaire;


class ExerciceResource extends Resource
{
    protected static ?string $model = Exercice::class;

    // protected static ?string $cluster = GestionBudgetaire::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationLabel = 'Exercices Budgétaires';

    protected static ?string $modelLabel = 'Exercice';

    protected static ?string $pluralModelLabel = 'Exercices Budgétaires';

    protected static ?string $navigationGroup = 'Paramétrage';

    protected static ?int $navigationSort = 1;

    /**
     * Permissions – Exercice budgétaire
     */
    public static function canViewAny(): bool
    {
        return auth()->check()
            && auth()->user()->can('view_any_exercice');
    }

    public static function canView($record): bool
    {
        return auth()->check()
            && auth()->user()->can('view_exercice');
    }

    public static function canCreate(): bool
    {
        return auth()->check()
            && auth()->user()->can('create_exercice');
    }

    public static function canEdit($record): bool
    {
        if (!auth()->check()) {
            return false;
        }

        if (!auth()->user()->can('update_exercice')) {
            return false;
        }

        // Règle métier : exercice modifiable
        if (!$record->estModifiable()) {
            \Filament\Notifications\Notification::make()
                ->title('Modification impossible')
                ->warning()
                ->body("L'exercice {$record->annee} est {$record->statut}.")
                ->send();

            return false;
        }

        return true;
    }

    public static function canDelete($record): bool
    {
        if (!auth()->check()) {
            return false;
        }

        if (!auth()->user()->can('delete_exercice')) {
            return false;
        }

        return $record->estModifiable();
    }

    /**
     * Action spéciale : Ouvrir un exercice
     */
    public static function canOuvrir($record): bool
    {
        return auth()->check()
            && auth()->user()->can('ouvrir_exercice')
            && $record->statut === 'brouillon';
    }

    /**
     * Action spéciale : Clôturer un exercice
     */
    public static function canCloturer($record): bool
    {
        return auth()->check()
            && auth()->user()->can('cloturer_exercice')
            && $record->statut === 'ouvert';
    }

    /**
     * Action spéciale : Archiver un exercice
     */
    public static function canArchiver($record): bool
    {
        return auth()->check()
            && auth()->user()->can('archiver_exercice')
            && $record->statut === 'cloture';
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informations de l\'exercice')
                    ->schema([
                        Forms\Components\TextInput::make('annee')
                            ->label('Année budgétaire')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->numeric()
                            ->minValue(2020)
                            ->maxValue(2050)
                            ->placeholder('Ex: 2025')
                            ->helperText('Année de l\'exercice budgétaire'),

                        Forms\Components\TextInput::make('libelle')
                            ->label('Libellé')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ex: Exercice budgétaire 2025')
                            ->default(
                                fn(callable $get) =>
                                $get('annee') ? 'Exercice budgétaire ' . $get('annee') : null
                            ),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull()
                            ->placeholder('Description détaillée de l\'exercice'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Période')
                    ->schema([
                        Forms\Components\DatePicker::make('date_debut')
                            ->label('Date de début')
                            ->required()
                            ->default(
                                fn(callable $get) =>
                                $get('annee') ? now()->year($get('annee'))->startOfYear() : null
                            ),

                        Forms\Components\DatePicker::make('date_fin')
                            ->label('Date de fin')
                            ->required()
                            ->default(
                                fn(callable $get) =>
                                $get('annee') ? now()->year($get('annee'))->endOfYear() : null
                            ),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Statut et activation')
                    ->schema([
                        Forms\Components\Select::make('statut')
                            ->label('Statut')
                            ->options([
                                'brouillon' => '📝 Brouillon',
                                'actif' => '✅ Actif',
                                'cloture' => '🔒 Clôturé',
                                'archive' => '📦 Archivé',
                            ])
                            ->required()
                            ->default('brouillon')
                            ->disabled(fn($record) => $record && $record->estCloture())
                            ->helperText('Le statut définit le cycle de vie de l\'exercice'),

                        Forms\Components\Toggle::make('actif')
                            ->label('Exercice actif')
                            ->helperText('Un seul exercice peut être actif à la fois')
                            ->disabled(fn(callable $get) => $get('statut') !== 'actif'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Reconduction')
                    ->description('Lier cet exercice à un exercice source pour reconduction')
                    ->schema([
                        Forms\Components\Select::make('exercice_source_id')
                            ->label('Exercice source')
                            ->relationship('exerciceSource', 'libelle')
                            ->searchable()
                            ->preload()
                            ->placeholder('Aucun (nouvel exercice)')
                            ->helperText('Exercice depuis lequel les données seront reconduites'),

                        Forms\Components\Toggle::make('reconduction_effectuee')
                            ->label('Reconduction effectuée')
                            ->disabled()
                            ->helperText('Sera activé automatiquement après reconduction'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),

                Forms\Components\Section::make('Observations')
                    ->schema([
                        Forms\Components\Textarea::make('observations')
                            ->label('Observations')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('annee')
                    ->label('Année')
                    ->sortable()
                    ->searchable()
                    ->weight('bold')
                    ->size('lg'),

                Tables\Columns\TextColumn::make('libelle')
                    ->label('Libellé')
                    ->searchable()
                    ->wrap()
                    ->limit(50),

                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
                    ->colors([
                        'gray' => 'brouillon',
                        'success' => 'actif',
                        'warning' => 'cloture',
                        'danger' => 'archive',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'brouillon' => '📝 Brouillon',
                        'actif' => '✅ Actif',
                        'cloture' => '🔒 Clôturé',
                        'archive' => '📦 Archivé',
                        default => $state,
                    })
                    ->sortable(),

                Tables\Columns\IconColumn::make('actif')
                    ->label('Actif')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('date_debut')
                    ->label('Début')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('date_fin')
                    ->label('Fin')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('date_cloture')
                    ->label('Clôture')
                    ->date('d/m/Y H:i')
                    ->sortable()
                    ->toggleable()
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('reconduction_effectuee')
                    ->label('Reconduit')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('exerciceSource.annee')
                    ->label('Source')
                    ->sortable()
                    ->toggleable()
                    ->badge()
                    ->color('info')
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('statut')
                    ->label('Statut')
                    ->options([
                        'brouillon' => 'Brouillon',
                        'actif' => 'Actif',
                        'cloture' => 'Clôturé',
                        'archive' => 'Archivé',
                    ]),

                Tables\Filters\TernaryFilter::make('actif')
                    ->label('Actif'),

                Tables\Filters\TernaryFilter::make('reconduction_effectuee')
                    ->label('Reconduit'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),

                    // Action : Activer
                    Tables\Actions\Action::make('activer')
                        ->label('Activer')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn($record) => $record->estBrouillon())
                        ->requiresConfirmation()
                        ->modalHeading('Activer cet exercice ?')
                        ->modalDescription('L\'exercice actuel sera désactivé automatiquement.')
                        ->action(function ($record) {
                            try {
                                $record->activer(auth()->user());

                                Notification::make()
                                    ->title('Exercice activé')
                                    ->success()
                                    ->body("L'exercice {$record->annee} est maintenant actif")
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Erreur')
                                    ->danger()
                                    ->body($e->getMessage())
                                    ->send();
                            }
                        }),

                    // Action : Clôturer
                    Tables\Actions\Action::make('cloturer')
                        ->label('Clôturer')
                        ->icon('heroicon-o-lock-closed')
                        ->color('warning')
                        ->visible(fn($record) => $record->estActif())
                        ->requiresConfirmation()
                        ->modalHeading('Clôturer cet exercice')
                        ->modalDescription(function ($record) {
                            $service = app(\App\Services\ValidationExerciceService::class);
                            $validation = $service->validerCloture($record);

                            $description = "**Exercice {$record->annee}**\n\n";

                            // Erreurs
                            if (!empty($validation['erreurs'])) {
                                $description .= "### ❌ Erreurs bloquantes\n\n";
                                foreach ($validation['erreurs'] as $erreur) {
                                    $description .= "- {$erreur}\n";
                                }
                                $description .= "\n";
                            }

                            // Avertissements
                            if (!empty($validation['avertissements'])) {
                                $description .= "### ⚠️ Avertissements\n\n";
                                foreach ($validation['avertissements'] as $avert) {
                                    $description .= "- {$avert}\n";
                                }
                                $description .= "\n";
                            }

                            // Statistiques
                            if (isset($validation['stats'])) {
                                $stats = $validation['stats'];
                                $description .= "### 📊 Statistiques budgétaires\n\n";
                                $description .= "- **Budget total** : " . number_format($stats['budget_total'], 0, ',', ' ') . " FCFA\n";
                                $description .= "- **Engagé** : " . number_format($stats['engage_total'], 0, ',', ' ') . " FCFA\n";
                                $description .= "- **Disponible** : " . number_format($stats['disponible'], 0, ',', ' ') . " FCFA\n";
                                $description .= "- **Taux d'exécution** : " . number_format($stats['taux_execution'], 2) . "%\n\n";
                            }

                            if ($validation['valid']) {
                                $description .= "✅ **L'exercice peut être clôturé.**\n\n";
                                $description .= "⚠️ Une fois clôturé, l'exercice ne pourra plus être modifié (sauf par un super admin).";
                            } else {
                                $description .= "❌ **Corrigez les erreurs avant de clôturer.**";
                            }

                            return new \Illuminate\Support\HtmlString(
                                \Illuminate\Support\Str::markdown($description)
                            );
                        })
                        ->modalSubmitActionLabel('Clôturer définitivement')
                        ->modalCancelActionLabel('Annuler')
                        ->disabled(function ($record) {
                            $service = app(\App\Services\ValidationExerciceService::class);
                            $validation = $service->validerCloture($record);
                            return !$validation['valid'];
                        })
                        ->action(function ($record) {
                            try {
                                // Mettre à jour les statistiques
                                $record->mettreAJourStatistiques();

                                // Clôturer
                                $record->cloturer(auth()->user());

                                Notification::make()
                                    ->title('Exercice clôturé')
                                    ->success()
                                    ->body("L'exercice {$record->annee} a été clôturé avec succès.")
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Erreur')
                                    ->danger()
                                    ->body($e->getMessage())
                                    ->send();
                            }
                        }),
                    // Action : Reconduire vers un nouvel exercice
                    Tables\Actions\Action::make('reconduire')
                        ->label('Reconduire')
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
                        ->visible(fn($record) => $record->estCloture() || $record->estActif())
                        ->form([
                            Forms\Components\Section::make('Exercice cible')
                                ->description('Vers quel exercice reconduire ?')
                                ->schema([
                                    Forms\Components\Select::make('exercice_cible_id')
                                        ->label('Exercice de destination')
                                        ->options(function ($record) {
                                            return \App\Models\Exercice::where('statut', 'brouillon')
                                                ->where('id', '!=', $record->id)
                                                ->where('annee', '>', $record->annee)
                                                ->pluck('libelle', 'id');
                                        })
                                        ->required()
                                        ->searchable()
                                        ->helperText('Sélectionnez un exercice en brouillon'),
                                ]),

                            Forms\Components\Section::make('Options de reconduction')
                                ->schema([
                                    Forms\Components\Toggle::make('reconduire_nomenclature')
                                        ->label('Reconduire la nomenclature budgétaire')
                                        ->default(false)
                                        ->inline(false)
                                        ->helperText('Dupliquer également la nomenclature vers le nouvel exercice'),

                                    Forms\Components\Toggle::make('ajuster_montants')
                                        ->label('Ajuster les montants budgétaires')
                                        ->default(false)
                                        ->inline(false)
                                        ->live()
                                        ->helperText('Appliquer un pourcentage d\'ajustement (inflation, etc.)'),

                                    Forms\Components\TextInput::make('pourcentage_ajustement')
                                        ->label('Pourcentage d\'ajustement (%)')
                                        ->numeric()
                                        ->default(0)
                                        ->suffix('%')
                                        ->visible(fn(callable $get) => $get('ajuster_montants'))
                                        ->helperText('Exemple : 5 pour +5%, -3 pour -3%')
                                        ->minValue(-100)
                                        ->maxValue(100),
                                ])
                                ->columns(2),

                            Forms\Components\Section::make('Aperçu')
                                ->description('Ce qui sera reconduit')
                                ->schema([
                                    Forms\Components\Placeholder::make('apercu_programmes')
                                        ->label('Programmes')
                                        ->content(function ($record) {
                                            $count = \App\Models\Programme::where('exercice_id', $record->id)->count();
                                            return "{$count} programme(s) et sous-programmes";
                                        }),

                                    Forms\Components\Placeholder::make('apercu_actions')
                                        ->label('Actions')
                                        ->content(function ($record) {
                                            $count = \App\Models\Action::where('exercice_id', $record->id)->count();
                                            return "{$count} action(s)";
                                        }),

                                    Forms\Components\Placeholder::make('apercu_activites')
                                        ->label('Activités')
                                        ->content(function ($record) {
                                            $count = \App\Models\Activite::where('exercice_id', $record->id)->count();
                                            return "{$count} activité(s)";
                                        }),

                                    Forms\Components\Placeholder::make('apercu_taches')
                                        ->label('Tâches')
                                        ->content(function ($record) {
                                            $count = \App\Models\Tache::where('exercice_id', $record->id)->count();
                                            return "{$count} tâche(s) et sous-tâches";
                                        }),
                                ])
                                ->columns(2),
                        ])
                        ->modalHeading('Reconduire cet exercice')
                        ->modalSubmitActionLabel('Reconduire maintenant')
                        ->modalWidth('3xl')
                        ->action(function ($record, array $data) {
                            try {
                                $exerciceCible = \App\Models\Exercice::find($data['exercice_cible_id']);

                                if (!$exerciceCible) {
                                    throw new \Exception('Exercice cible introuvable');
                                }

                                // Appeler le service de reconduction
                                $service = app(\App\Services\ReconductionExerciceService::class);

                                $resultat = $service->reconduire($record, $exerciceCible, [
                                    'reconduire_programmes' => true,
                                    'reconduire_nomenclature' => $data['reconduire_nomenclature'] ?? false,
                                    'ajuster_montants' => $data['ajuster_montants'] ?? false,
                                    'pourcentage_ajustement' => $data['pourcentage_ajustement'] ?? 0,
                                ]);

                                if ($resultat['success']) {
                                    $stats = $resultat['stats'];

                                    Notification::make()
                                        ->title('Reconduction réussie !')
                                        ->success()
                                        ->body(sprintf(
                                            "Exercice %s → %s\n\n" .
                                                "📊 Éléments reconduits :\n" .
                                                "• %d programme(s)\n" .
                                                "• %d action(s)\n" .
                                                "• %d activité(s)\n" .
                                                "• %d tâche(s)\n" .
                                                "%s",
                                            $record->annee,
                                            $exerciceCible->annee,
                                            $stats['programmes'],
                                            $stats['actions'],
                                            $stats['activites'],
                                            $stats['taches'],
                                            $stats['nomenclatures'] > 0 ? "• {$stats['nomenclatures']} nomenclature(s)\n" : ""
                                        ))
                                        ->duration(10000)
                                        ->send();
                                } else {
                                    throw new \Exception($resultat['error']);
                                }
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Erreur lors de la reconduction')
                                    ->danger()
                                    ->body($e->getMessage())
                                    ->persistent()
                                    ->send();
                            }
                        }),

                    // Action : Archiver
                    Tables\Actions\Action::make('archiver')
                        ->label('Archiver')
                        ->icon('heroicon-o-archive-box')
                        ->color('danger')
                        ->visible(fn($record) => $record->estCloture())
                        ->requiresConfirmation()
                        ->modalHeading('Archiver cet exercice ?')
                        ->modalDescription('L\'exercice sera accessible uniquement aux administrateurs.')
                        ->action(function ($record) {
                            try {
                                $record->archiver(auth()->user());

                                Notification::make()
                                    ->title('Exercice archivé')
                                    ->danger()
                                    ->body("L'exercice {$record->annee} a été archivé")
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Erreur')
                                    ->danger()
                                    ->body($e->getMessage())
                                    ->send();
                            }
                        }),

                    // Action : Rouvrir (Admin uniquement)
                    Tables\Actions\Action::make('rouvrir')
                        ->label('Rouvrir')
                        ->icon('heroicon-o-lock-open')
                        ->color('info')
                        ->visible(
                            fn($record) =>
                            auth()->user()->hasRole('super_admin') &&
                                ($record->estCloture() || $record->estArchive())
                        )
                        ->requiresConfirmation()
                        ->modalHeading('Rouvrir cet exercice ?')
                        ->modalDescription('⚠️ Action réservée aux administrateurs. L\'exercice redeviendra actif.')
                        ->action(function ($record) {
                            try {
                                $record->rouvrir(auth()->user());

                                Notification::make()
                                    ->title('Exercice rouvert')
                                    ->info()
                                    ->body("L'exercice {$record->annee} a été rouvert")
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Erreur')
                                    ->danger()
                                    ->body($e->getMessage())
                                    ->send();
                            }
                        }),

                    // Action : Statistiques
                    Tables\Actions\Action::make('statistiques')
                        ->label('Statistiques')
                        ->icon('heroicon-o-chart-bar')
                        ->color('primary')
                        ->action(function ($record) {
                            $record->mettreAJourStatistiques();

                            $stats = $record->statistiques;

                            Notification::make()
                                ->title('Statistiques de l\'exercice ' . $record->annee)
                                ->info()
                                ->body(sprintf(
                                    "Programmes: %d | Budgets: %d | Bordereaux: %d",
                                    $stats['nb_programmes'] ?? 0,
                                    $stats['nb_budgets'] ?? 0,
                                    $stats['nb_bordereaux'] ?? 0
                                ))
                                ->send();
                        }),
                ])
                    ->label('Actions')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->button(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn() => auth()->user()->hasRole('super_admin')),
                ]),
            ])
            ->defaultSort('annee', 'desc')
            ->contentGrid(null) // Désactive la grille, pleine largeur
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExercices::route('/'),
            'create' => Pages\CreateExercice::route('/create'),
            'edit' => Pages\EditExercice::route('/{record}/edit'),
            //'view' => Pages\ViewExercice::route('/{record}'),
        ];
    }
}
