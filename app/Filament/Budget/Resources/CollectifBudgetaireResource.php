<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\CollectifBudgetaireResource\Pages;
use App\Filament\Budget\Resources\CollectifBudgetaireResource\RelationManagers\MouvementsRelationManager;
use App\Models\CollectifBudgetaire;
use App\Models\Exercice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CollectifBudgetaireResource extends Resource
{
    protected static ?string $model = CollectifBudgetaire::class;

    // Navigation
    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationLabel = 'Collectifs budgétaires';
    protected static ?string $modelLabel = 'Collectif budgétaire';
    protected static ?string $pluralModelLabel = 'Collectifs budgétaires';
    protected static ?string $navigationGroup = 'Gestion Budgétaire';
    protected static ?int $navigationSort = 3;

    // ========================================
    // PERMISSIONS
    // ========================================

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_collectif_budgetaire') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->can('view_collectif_budgetaire') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_collectif_budgetaire') ?? false;
    }

    public static function canEdit($record): bool
    {
        if (!auth()->user()?->can('update_collectif_budgetaire')) return false;
        // ✅ Pas de notification ici — appelé trop souvent (chaque ligne du tableau)
        return $record->estModifiable();
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('delete_collectif_budgetaire') && $record->estModifiable();
    }

    public static function canEditRecord($record): bool
    {
        return static::canEdit($record);
    }

    // Actions personnalisées
    public static function canAdopter($record): bool
    {
        return auth()->user()?->can('adopter_collectif_budgetaire')
            && $record->statut === 'projet';
    }

    public static function canAnnuler($record): bool
    {
        return auth()->user()?->can('annuler_collectif_budgetaire')
            && $record->statut === 'adopte';
    }

    // ========================================
    // FORM
    // ========================================

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informations générales')
                    ->schema([
                        Forms\Components\Select::make('exercice_id')
                            ->label('Exercice')
                            ->relationship('exercice', 'annee')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->default(fn() => Exercice::getActif()?->id),

                        Forms\Components\TextInput::make('numero')
                            ->label('Numéro')
                            ->disabled()
                            ->helperText('Généré automatiquement'),

                        Forms\Components\TextInput::make('reference')
                            ->label('Référence document')
                            ->maxLength(100)
                            ->helperText('Numéro de référence du document signé par le CA'),

                        Forms\Components\TextInput::make('libelle')
                            ->label('Libellé')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\DatePicker::make('date_collectif')
                            ->label('Date du collectif')
                            ->required()
                            ->default(now()),

                        Forms\Components\DatePicker::make('date_adoption')
                            ->label('Date d\'adoption')
                            ->nullable(),

                        Forms\Components\FileUpload::make('document_path')
                            ->label('Document signé (optionnel)')
                            ->disk('public') // ou votre disque
                            ->directory('collectifs/documents')
                            ->visibility('public')
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            ->maxSize(5120) // 5MB
                            ->nullable(),

                        Forms\Components\Select::make('statut')
                            ->label('Statut')
                            ->options([
                                'projet' => 'Projet',
                                'adopte' => 'Adopté',
                                'annule' => 'Annulé',
                            ])
                            ->required()
                            ->default('projet')
                            ->disabled(fn($record) => $record && !$record->estModifiable()),

                        Forms\Components\Textarea::make('observations')
                            ->label('Observations')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    // ========================================
    // TABLE
    // ========================================

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('Numéro')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('libelle')
                    ->label('Libellé')
                    ->searchable()
                    ->limit(50)
                    ->wrap(),

                Tables\Columns\TextColumn::make('exercice.annee')
                    ->label('Exercice')
                    ->sortable()
                    ->badge(),

                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
                    ->formatStateUsing(fn($state) => CollectifBudgetaire::STATUTS[$state] ?? $state)
                    ->colors([
                        'secondary' => 'projet',
                        'success'   => 'adopte',
                        'danger'    => 'annule',
                        'gray'      => 'fusionne',
                    ])
                    ->description(fn($record) => match (true) {
                        $record->statut === 'fusionne' => 'dans ' . ($record->fusionneDans?->numero ?? '?'),
                        $record->estIssuDeFusion()     => 'fusion de ' . collect($record->fusion_meta['origines'] ?? [])->pluck('numero')->implode(', '),
                        default                        => null,
                    }),

                Tables\Columns\TextColumn::make('date_collectif')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('mouvements_count')
                    ->label('Mouvements')
                    ->counts('mouvements')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('reference')
                    ->label('Réf. document')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('document_path')
                    ->label('Document')
                    ->icon(fn($record) => $record->document_path ? 'heroicon-o-document' : 'heroicon-o-minus')
                    ->color(fn($record) => $record->document_path ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('createur.name')
                    ->label('Créé par')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('exercice_id')
                    ->label('Exercice')
                    ->relationship('exercice', 'annee')
                    ->searchable()
                    ->preload()
                    ->default(fn() => Exercice::getActif()?->id),

                Tables\Filters\SelectFilter::make('statut')
                    ->label('Statut')
                    ->options(CollectifBudgetaire::STATUTS),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),

                    // Action pour adopter le collectif
                    Tables\Actions\Action::make('adopter')
                        ->label('Adopter')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Adopter le collectif')
                        ->modalDescription('Cette action appliquera les modifications sur les budgets et prévisions.')
                        ->visible(fn($record) => static::canAdopter($record))
                        ->action(function ($record) {
                            try {
                                DB::transaction(function () use ($record) {
                                    $record->statut = 'adopte';
                                    $record->date_adoption = now();
                                    $record->save();
                                    $record->appliquer(auth()->user());
                                });

                                Notification::make()
                                    ->title('Collectif adopté et appliqué avec succès')
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Adoption impossible')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    // ✅ Impression de l'état du collectif (A-RECETTES / B-DÉPENSES), même en projet
                    Tables\Actions\Action::make('imprimerEtat')
                        ->label('Imprimer l\'état (PDF)')
                        ->icon('heroicon-o-printer')
                        ->color('gray')
                        ->action(fn($record) => static::telechargerEtat([$record->id], $record->exercice_id)),

                    // ✅ Défaire une fusion (collectif fusionné encore en projet, sans regroupement)
                    Tables\Actions\Action::make('defaireFusion')
                        ->label('Défaire la fusion')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('warning')
                        ->visible(fn($record) => auth()->user()?->can('update_collectif_budgetaire')
                            && app(\App\Services\Budget\FusionCollectifsService::class)->peutDefaire($record))
                        ->requiresConfirmation()
                        ->modalDescription(fn($record) => 'Les mouvements retourneront dans '
                            . collect($record->fusion_meta['origines'] ?? [])->pluck('numero')->implode(', ')
                            . ', qui retrouveront le statut « ' . (CollectifBudgetaire::STATUTS[$record->fusion_meta['statut_origine'] ?? 'projet'] ?? '') . ' »'
                            . " et leurs rattachements. Le collectif {$record->numero} sera supprimé. Les dotations ne changent pas.")
                        ->action(function ($record) {
                            try {
                                app(\App\Services\Budget\FusionCollectifsService::class)->defaire($record);
                                Notification::make()->title('Fusion défaite')->success()->send();
                            } catch (\DomainException $e) {
                                Notification::make()->title('Impossible de défaire la fusion')->body($e->getMessage())->warning()->persistent()->send();
                            }
                        }),

                    // Action pour annuler le collectif (si déjà adopté)
                    Tables\Actions\Action::make('annuler')
                        ->label('Annuler')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Annuler le collectif')
                        ->modalDescription('Cette action annulera toutes les modifications apportées par ce collectif.')
                        ->visible(fn($record) => static::canAnnuler($record))
                        ->action(function ($record) {
                            $record->annuler();
                            Notification::make()
                                ->title('Collectif annulé avec succès')
                                ->success()
                                ->send();
                        }),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->button()
                    ->size('sm'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // ✅ Fusion de plusieurs collectifs en projet en un nouveau collectif
                    Tables\Actions\BulkAction::make('fusionner')
                        ->label('Fusionner en un nouveau collectif')
                        ->icon('heroicon-o-square-3-stack-3d')
                        ->color('primary')
                        ->visible(fn() => auth()->user()?->can('create_collectif_budgetaire'))
                        ->modalHeading('Fusion de collectifs budgétaires')
                        ->modalSubmitActionLabel('Fusionner')
                        ->form(fn($records) => [
                            Forms\Components\Placeholder::make('apercu')
                                ->label('Aperçu')
                                ->content(function (Forms\Get $get) use ($records) {
                                    $service = app(\App\Services\Budget\FusionCollectifsService::class);
                                    try {
                                        $service->verifier($records);
                                    } catch (\DomainException $e) {
                                        return new \Illuminate\Support\HtmlString('<span style="color:#b91c1c;">⛔ ' . e($e->getMessage()) . '</span>');
                                    }
                                    $a = $service->apercu($records, (bool) $get('regrouper'));
                                    $f = fn($v) => number_format((float) $v, 0, ',', ' ');
                                    return new \Illuminate\Support\HtmlString(
                                        "Collectifs : <strong>{$a['collectifs']}</strong> — "
                                            . ($a['statut'] === 'adopte'
                                                ? '<strong>adoptés</strong> : le nouveau collectif sera adopté, sans réappliquer les mouvements (dotations inchangées).<br>'
                                                : '<strong>en projet</strong> : le nouveau collectif sera en projet.<br>')
                                            . "Mouvements : <strong>{$a['mouvements']}</strong>" . ($get('regrouper') ? " → <strong>{$a['mouvements_apres']}</strong> après regroupement" : '')
                                            . " · lignes regroupables : {$a['lignes_regroupables']}<br>"
                                            . "Recettes : <strong>{$f($a['recettes'])}</strong> · dépenses : <strong>{$f($a['depenses'])}</strong> · virements : {$f($a['virements'])}<br>"
                                            . 'Équilibre : ' . (abs($a['equilibre']) < 1 ? '<span style="color:#166534;">✅ équilibré</span>' : '<span style="color:#b91c1c;">⚠️ écart de ' . $f($a['equilibre']) . ' FCFA</span>')
                                    );
                                }),
                            Forms\Components\TextInput::make('libelle')->label('Libellé du collectif issu de la fusion')->required()->maxLength(255)
                                ->default('Collectif budgétaire n° … — session du Conseil d\'Administration'),
                            Forms\Components\DatePicker::make('date_collectif')->label('Date du collectif')->default(now())->required(),
                            Forms\Components\Toggle::make('regrouper')->label('Regrouper les mouvements portant sur une même ligne')
                                ->helperText('Les virements et les créations de lignes ne sont jamais regroupés. Les dotations ne changent pas (somme identique). Une fusion avec regroupement ne peut plus être défaite automatiquement.')
                                ->live(),
                            Forms\Components\Textarea::make('observations')->rows(2),
                        ])
                        ->action(function ($records, array $data) {
                            try {
                                $fusion = app(\App\Services\Budget\FusionCollectifsService::class)->fusionner(
                                    $records->pluck('id')->all(),
                                    $data['libelle'],
                                    $data['date_collectif'],
                                    $data['observations'] ?? null,
                                    (bool) ($data['regrouper'] ?? false)
                                );
                                Notification::make()->title("Collectif {$fusion->numero} créé par fusion")
                                    ->body($records->count() . ' collectifs fusionnés (' . (CollectifBudgetaire::STATUTS[$fusion->statut] ?? $fusion->statut)
                                        . ') ; ils restent consultables au statut « Fusionné ».')->success()->send();
                            } catch (\DomainException $e) {
                                Notification::make()->title('Fusion impossible')->body($e->getMessage())->warning()->persistent()->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),

                    // ✅ Impression groupée : plusieurs collectifs sur le même état
                    Tables\Actions\BulkAction::make('imprimerEtats')
                        ->label('Imprimer l\'état des collectifs sélectionnés (PDF)')
                        ->icon('heroicon-o-printer')
                        ->action(function ($records) {
                            if ($records->pluck('exercice_id')->unique()->count() > 1) {
                                Notification::make()->title('Sélection impossible')->body('Les collectifs doivent appartenir au même exercice.')->warning()->send();
                                return null;
                            }
                            return static::telechargerEtat($records->pluck('id')->all(), $records->first()->exercice_id);
                        }),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /** ✅ PDF des collectifs choisis (format A-RECETTES / B-DÉPENSES), via l'état des mouvements de crédits. */
    public static function telechargerEtat(array $ids, int $exerciceId)
    {
        $budget = \App\Models\Budget::withoutGlobalScope('exercice')->where('exercice_id', $exerciceId)
            ->orderByDesc('actif')->orderByDesc('id')->first();

        if (!$budget) {
            Notification::make()->title('Impression impossible')->body("Aucun budget pour l'exercice de ce collectif.")->warning()->send();
            return null;
        }

        $etat = app(\App\Services\Budget\EtatMouvementsCreditsService::class)->construire($budget, null, null, true, $ids, false);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.etat-mouvements-credits', $etat + [
            'structure' => \App\Models\ParametresStructure::where('actif', true)->first() ?? \App\Models\ParametresStructure::first(),
        ])->setPaper('a4', 'landscape');

        $nom = count($ids) === 1
            ? 'collectif_' . (CollectifBudgetaire::find($ids[0])?->numero ?? 'etat')
            : 'collectifs_' . count($ids);

        return response()->streamDownload(fn() => print($pdf->output()), $nom . '_' . now()->format('Ymd_His') . '.pdf');
    }

    // ========================================
    // RELATIONS
    // ========================================

    public static function getRelations(): array
    {
        return [
            MouvementsRelationManager::class,
        ];
    }

    // ========================================
    // PAGES
    // ========================================

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCollectifBudgetaires::route('/'),
            'create' => Pages\CreateCollectifBudgetaire::route('/create'),
            'edit' => Pages\EditCollectifBudgetaire::route('/{record}/edit'),
            'view' => Pages\ViewCollectifBudgetaire::route('/{record}'),
        ];
    }
}
