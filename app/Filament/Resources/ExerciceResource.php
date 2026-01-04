<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExerciceResource\Pages;
use App\Models\Exercice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class ExerciceResource extends Resource
{
    protected static ?string $model = Exercice::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationLabel = 'Exercices Budgétaires';

    protected static ?string $modelLabel = 'Exercice';

    protected static ?string $pluralModelLabel = 'Exercices Budgétaires';

    protected static ?string $navigationGroup = 'Configuration';

    protected static ?int $navigationSort = 1;

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
            ]);
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
            ->defaultSort('annee', 'desc');
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
