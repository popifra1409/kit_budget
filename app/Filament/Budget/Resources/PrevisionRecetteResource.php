<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\PrevisionRecetteResource\Pages;
use App\Filament\Budget\Resources\PrevisionRecetteResource\RelationManagers;
use App\Models\PrevisionRecette;
use App\Exports\PrevisionRecetteExport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Enums\ActionsPosition;
use App\Filament\Forms\Components\ExerciceSelect;
use App\Models\Exercice;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Filament\Budget\Resources\RelationManagers\CollectifsAppliquesRelationManager;

class PrevisionRecetteResource extends Resource
{
    protected static ?string $model            = PrevisionRecette::class;
    protected static ?string $navigationIcon   = 'heroicon-o-currency-dollar';
    protected static ?string $navigationLabel  = 'Prévisions de Recettes';
    protected static ?string $modelLabel       = 'Prévision de Recettes';
    protected static ?string $pluralModelLabel = 'Prévisions de Recettes';
    protected static ?string $navigationGroup  = 'Recettes';
    protected static ?int    $navigationSort   = 1;

    // ========================================
    // PERMISSIONS
    // ========================================

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_prevision_recette') ?? false;
    }
    public static function canView($record): bool
    {
        return auth()->user()?->can('view_prevision_recette') ?? false;
    }
    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_prevision_recette') ?? false;
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if ($user->can('update_prevision_recette')) return $record->estModifiable();
        return false;
    }

    public static function canDelete($record): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        return $user->can('delete_prevision_recette') && $record->estModifiable();
    }

    // ========================================
    // FORM
    // ========================================

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Exercice')
                ->description('Exercice budgétaire de rattachement')
                ->schema([ExerciceSelect::make()])
                ->collapsible()
                ->collapsed(fn($record) => $record !== null),

            Forms\Components\Section::make('Informations de la Prévision')
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->label('Code')->required()->unique(ignoreRecord: true)
                        ->maxLength(50)->placeholder('Ex: PREV-REC-2026')
                        ->helperText('Code unique de la prévision de recettes'),

                    Forms\Components\TextInput::make('libelle')
                        ->label('Libellé')->required()->maxLength(255)->columnSpanFull()
                        ->placeholder('Ex: Prévisions de Recettes 2026'),

                    Forms\Components\DatePicker::make('date_adoption')
                        ->label('Date d\'adoption')
                        ->helperText('Date de vote/adoption de la prévision'),

                    Forms\Components\DatePicker::make('date_revision')
                        ->label('Date de révision')
                        ->helperText('Date de dernière révision'),

                    Forms\Components\Select::make('statut')
                        ->label('Statut')
                        ->options([
                            'elaboration' => 'En élaboration',
                            'adopte'      => 'Adopté',
                            'execution'   => 'En exécution',
                            'cloture'     => 'Clôturé',
                        ])
                        ->required()->default('elaboration'),

                    Forms\Components\Toggle::make('actif')->label('Actif')->default(true),
                ])
                ->columns(2),

            Forms\Components\Section::make('Observations')
                ->schema([
                    Forms\Components\Textarea::make('observations')
                        ->label('Observations')->rows(3)->columnSpanFull(),
                ])
                ->collapsible()->collapsed(),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('exercice')
            ->withCount('lignesPrevisions')
            ->withSum('lignesPrevisions as total_prevu', 'montant_rectifie')
            ->withSum('lignesPrevisions as total_recouvre', 'montant_recouvre');
    }

    // ========================================
    // TABLE
    // ========================================

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\BadgeColumn::make('exercice.annee')
                    ->label('Exercice')->sortable()
                    ->colors([
                        'success' => fn($record) => $record->exercice instanceof Exercice && $record->exercice->estActif(),
                        'warning' => fn($record) => $record->exercice instanceof Exercice && $record->exercice->estCloture(),
                        'danger'  => fn($record) => $record->exercice instanceof Exercice && $record->exercice->estArchive(),
                        'gray'    => fn($record) => $record->exercice instanceof Exercice && $record->exercice->estBrouillon(),
                    ])
                    ->tooltip(fn($record) => $record->exercice instanceof Exercice ? $record->exercice->libelle : null)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('code')
                    ->label('Code')->searchable()->sortable()->weight('bold'),

                Tables\Columns\TextColumn::make('libelle')
                    ->label('Libellé')->searchable()->sortable()->limit(50)->wrap(),

                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
                    ->colors([
                        'secondary' => 'elaboration',
                        'success'   => 'adopte',
                        'warning'   => 'execution',
                        'danger'    => 'cloture',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'elaboration' => 'Élaboration',
                        'adopte'      => 'Adopté',
                        'execution'   => 'Exécution',
                        'cloture'     => 'Clôturé',
                        default       => $state,
                    }),

                Tables\Columns\TextColumn::make('lignes_previsions_count')
                    ->label('Lignes')->alignCenter()->badge()->color('primary'),

                Tables\Columns\TextColumn::make('total_prevu')
                    ->label('Total Prévu')
                    ->state(fn(PrevisionRecette $record) => $record->getTotalPrevuRectifie())
                    ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ') . ' FCFA')
                    ->color('info'),

                Tables\Columns\TextColumn::make('total_recouvre')
                    ->label('Total Recouvré')
                    ->state(fn(PrevisionRecette $record) => $record->getTotalRecouvre())
                    ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ') . ' FCFA')
                    ->color('success'),

                Tables\Columns\TextColumn::make('taux_recouvrement')
                    ->label('Taux')
                    ->state(fn(PrevisionRecette $record) => round($record->getTauxRecouvrement(), 1))
                    ->formatStateUsing(fn($state) => $state . ' %')
                    ->badge()
                    ->color(fn($state) => $state >= 90 ? 'success' : ($state >= 70 ? 'warning' : 'danger')),

                Tables\Columns\TextColumn::make('date_adoption')
                    ->label('Date adoption')->date('d/m/Y')->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('actif')->label('Actif')->boolean(),

                Tables\Columns\TextColumn::make('collectifs_count')
                    ->label('Collectifs')
                    ->counts('collectifs')
                    ->badge()
                    ->color('primary')
                    ->url(fn($record) => CollectifBudgetaireResource::getUrl('index', ['filters' => ['exercice_id' => $record->exercice_id]]))
                    ->openUrlInNewTab(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('exercice_id')
                    ->label('Exercice')
                    ->relationship('exercice', 'annee')
                    ->searchable()->preload()
                    ->placeholder('Tous les exercices')
                    ->default(fn() => Exercice::getActif()?->id),

                Tables\Filters\SelectFilter::make('statut')
                    ->label('Statut')
                    ->options([
                        'elaboration' => 'Élaboration',
                        'adopte'      => 'Adopté',
                        'execution'   => 'Exécution',
                        'cloture'     => 'Clôturé',
                    ]),

                Tables\Filters\TernaryFilter::make('actif')
                    ->label('Actif')->placeholder('Tous')
                    ->trueLabel('Actifs')->falseLabel('Inactifs'),
            ])

            ->actions([
                Tables\Actions\ActionGroup::make([

                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),

                    Tables\Actions\Action::make('exportExcel')
                        ->label('Export Excel')
                        ->icon('heroicon-o-table-cells')
                        ->color('success')
                        ->url(
                            fn(PrevisionRecette $record) =>
                            route('prevision-recette.export.excel', $record->id)
                        )
                        ->openUrlInNewTab(),

                    // ── Export PDF ────────────────────────────────
                    // ✅ FIX — même pattern que Excel
                    Tables\Actions\Action::make('exportPdf')
                        ->label('Export PDF')
                        ->icon('heroicon-o-document-text')
                        ->color('danger')
                        ->url(
                            fn(PrevisionRecette $record) =>
                            route('prevision-recette.export.pdf', $record->id)
                        )
                        ->openUrlInNewTab(),

                    // ── ✅ Suppression EXCEPTIONNELLE d'une ligne (même prévision adoptée) ──
                    Tables\Actions\Action::make('supprimerLigneExceptionnelle')
                        ->label('Supprimer une ligne (exceptionnel)')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->color('danger')
                        ->visible(fn() => \App\Services\Budget\SuppressionLignePrevisionService::autorise())
                        ->modalHeading('Suppression exceptionnelle d\'une ligne de prévision')
                        ->modalDescription(fn(PrevisionRecette $record) => "Prévision {$record->code} (" . $record->statut . '). '
                            . 'La ligne et ses 12 prévisions mensuelles seront supprimées de façon récupérable ; '
                            . 'le motif, la référence et votre nom seront conservés et inscrits au journal d\'audit.')
                        ->form(fn(PrevisionRecette $record) => [
                            Forms\Components\Select::make('ligne_id')
                                ->label('Ligne à supprimer')
                                ->options(fn() => \App\Models\LignePrevisionRecette::where('prevision_recette_id', $record->id)
                                    ->orderBy('code_nomenclature')->get()
                                    ->mapWithKeys(fn($l) => [$l->id => "{$l->code_nomenclature} — {$l->libelle_nomenclature} ("
                                        . number_format((float) $l->montant_rectifie, 0, ',', ' ') . ' FCFA)']))
                                ->searchable()->required()->live(),
                            Forms\Components\Placeholder::make('obstacles')
                                ->label('Contrôle')
                                ->visible(fn(Forms\Get $get) => filled($get('ligne_id')))
                                ->content(function (Forms\Get $get) {
                                    $ligne = \App\Models\LignePrevisionRecette::find($get('ligne_id'));
                                    $obstacles = $ligne ? app(\App\Services\Budget\SuppressionLignePrevisionService::class)->obstacles($ligne) : [];
                                    return new \Illuminate\Support\HtmlString($obstacles
                                        ? '<span style="color:#b91c1c;">⛔ ' . implode('<br>⛔ ', array_map('e', $obstacles)) . '</span>'
                                        : '<span style="color:#166534;">✅ Aucune recette ni aucun collectif sur cette ligne : suppression possible.</span>');
                                }),
                            Forms\Components\Textarea::make('motif')
                                ->label('Motif de la suppression')->required()->minLength(15)->rows(3)
                                ->placeholder('Ex. : ligne créée par erreur lors de la saisie de la prévision, compte non applicable au CHUY'),
                            Forms\Components\TextInput::make('reference')
                                ->label('Référence de la décision (si disponible)')
                                ->placeholder('Ex. : Note n° 125/DG/DAAF du 10/10/2026')->maxLength(255),
                        ])
                        ->action(function (array $data) {
                            try {
                                $ligne = \App\Models\LignePrevisionRecette::findOrFail($data['ligne_id']);
                                app(\App\Services\Budget\SuppressionLignePrevisionService::class)->supprimer($ligne, $data['motif'], $data['reference'] ?? null);
                                \Filament\Notifications\Notification::make()->success()->title('Ligne supprimée')
                                    ->body("{$ligne->code_nomenclature} — {$ligne->libelle_nomenclature} : suppression tracée, restaurable.")->send();
                            } catch (\DomainException $e) {
                                \Filament\Notifications\Notification::make()->warning()->title('Suppression refusée')->body($e->getMessage())->persistent()->send();
                            }
                        }),

                    // ── ✅ Restauration d'une ligne supprimée ──
                    Tables\Actions\Action::make('restaurerLigne')
                        ->label('Restaurer une ligne supprimée')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('gray')
                        ->visible(fn(PrevisionRecette $record) => \App\Services\Budget\SuppressionLignePrevisionService::autorise()
                            && \App\Models\LignePrevisionRecette::onlyTrashed()->where('prevision_recette_id', $record->id)->exists())
                        ->form(fn(PrevisionRecette $record) => [
                            Forms\Components\Select::make('ligne_id')
                                ->label('Ligne supprimée')
                                ->options(fn() => \App\Models\LignePrevisionRecette::onlyTrashed()->where('prevision_recette_id', $record->id)
                                    ->with('nomenclature')->get()
                                    ->mapWithKeys(fn($l) => [$l->id => "{$l->code_nomenclature} — {$l->libelle_nomenclature} — supprimée le "
                                        . $l->deleted_at?->format('d/m/Y') . ($l->motif_suppression ? ' : « ' . \Str::limit($l->motif_suppression, 60) . ' »' : '')]))
                                ->required(),
                            Forms\Components\Textarea::make('motif')->label('Motif de la restauration')->required()->rows(2),
                        ])
                        ->action(function (array $data) {
                            try {
                                $ligne = \App\Models\LignePrevisionRecette::onlyTrashed()->findOrFail($data['ligne_id']);
                                app(\App\Services\Budget\SuppressionLignePrevisionService::class)->restaurer($ligne, $data['motif']);
                                \Filament\Notifications\Notification::make()->success()->title('Ligne restaurée')
                                    ->body("{$ligne->code_nomenclature} — {$ligne->libelle_nomenclature}")->send();
                            } catch (\DomainException $e) {
                                \Filament\Notifications\Notification::make()->warning()->title('Restauration refusée')->body($e->getMessage())->persistent()->send();
                            }
                        }),

                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')
                    ->button()
                    ->size('sm'),

            ], position: ActionsPosition::BeforeColumns)

            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('exportExcelBulk')
                        ->label('Exporter en Excel')
                        ->icon('heroicon-o-table-cells')
                        ->color('success')
                        ->action(function ($records) {
                            // Export multiple prévisions — à développer
                        }),
                ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('exportAllExcel')
                    ->label('Exporter tout en Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function () {
                        // Export de toutes les prévisions filtrées — à développer
                    }),
            ])
            ->defaultSort('exercice', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LignesPrevisionsRelationManager::class,
            CollectifsAppliquesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPrevisionRecettes::route('/'),
            'create' => Pages\CreatePrevisionRecette::route('/create'),
            'edit'   => Pages\EditPrevisionRecette::route('/{record}/edit'),
            'view'   => Pages\ViewPrevisionRecette::route('/{record}'),
        ];
    }
}
