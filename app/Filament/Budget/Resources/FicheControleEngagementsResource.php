<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\FicheControleEngagementsResource\Pages;
use App\Models\LigneBudgetaire;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Notifications\Notification;

class FicheControleEngagementsResource extends Resource
{
    protected static ?string $model = LigneBudgetaire::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Fiches de Contrôle';
    protected static ?string $modelLabel = 'Fiche de Contrôle';
    protected static ?string $pluralModelLabel = 'Fiches de Contrôle';
    protected static ?string $navigationGroup = 'Contrôle & Suivi';
    protected static ?int $navigationSort = 5;

    // ========================================
    // PERMISSIONS
    // ========================================
    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->can('view_any_fiche_controle_engagements');
    }
    public static function canView($record): bool
    {
        return auth()->check() && auth()->user()->can('view_fiche_controle_engagements');
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
        return false;
    }
    public static function canGenererPdf($record): bool
    {
        return auth()->check() && auth()->user()->can('generer_pdf_fiche_controle_engagements');
    }

    // ========================================
    // HELPER — calculs dynamiques centralisés
    // ========================================

    /**
     * ✅ Calcule le total engagé depuis les engagements actifs (non annulés, non supprimés)
     *    Utilise une requête directe pour éviter le N+1 et avoir des données fraîches
     */
    private static function getTotalEngage(LigneBudgetaire $record): float
    {
        return (float) \App\Models\Engagement::query()
            ->where('budget_id', $record->budget_id)
            ->where('nomenclature_principale_id', $record->nomenclature_id)
            ->whereNotIn('statut', ['annule'])
            ->sum('montant_engage');
    }

    /**
     * ✅ Calcule le disponible réel = budget_initial - totalEngagé
     *    (ne dépend PAS de la colonne stockée disponible_engagement qui peut être stale)
     */
    private static function getDisponibleReel(LigneBudgetaire $record): float
    {
        // ✅ Utiliser budget_rectifie (inclut les collectifs budgétaires)
        return (float) $record->budget_rectifie - self::getTotalEngage($record);
    }

    /**
     * ✅ Total des montants OP Standard liés aux engagements de cette ligne
     */
    private static function getMontantOP(LigneBudgetaire $record): float
    {
        return (float) \App\Models\OrdonnancePaiement::query()
            ->whereHas(
                'engagement',
                fn($q) => $q
                    ->where('budget_id', $record->budget_id)
                    ->where('nomenclature_principale_id', $record->nomenclature_id)
                    ->whereNotIn('statut', ['annule'])
            )
            ->where('type_ordonnance', 'standard')
            ->whereNotIn('statut', ['annulee'])
            ->sum('montant_net');
    }

    /**
     * ✅ Total des montants OPT (Impôt) liés aux engagements de cette ligne
     */
    private static function getMontantOPT(LigneBudgetaire $record): float
    {
        return (float) \App\Models\OrdonnancePaiement::query()
            ->whereHas(
                'engagement',
                fn($q) => $q
                    ->where('budget_id', $record->budget_id)
                    ->where('nomenclature_principale_id', $record->nomenclature_id)
                    ->whereNotIn('statut', ['annule'])
            )
            ->where('type_ordonnance', 'impot')
            ->whereNotIn('statut', ['annulee'])
            ->sum('montant_net');
    }

    /**
     * ✅ Recalcule et persiste les colonnes stockées sur LigneBudgetaire
     *    Appelé depuis l'action "Recalculer" ou après une suppression/avenant
     */
    public static function recalculerLigne(LigneBudgetaire $record): void
    {
        $totalEngage = self::getTotalEngage($record);

        // ✅ Utiliser budget_rectifie (après collectifs budgétaires)
        // budget_rectifie = budget_initial + Σ mouvements collectifs adoptés
        $budgetRectifie = self::getBudgetRectifieReel($record);
        $disponible     = $budgetRectifie - $totalEngage;

        $record->updateQuietly([
            'engage'                => $totalEngage,
            'budget_rectifie'       => $budgetRectifie,
            'disponible_engagement' => $disponible,
        ]);
    }

    /**
     * ✅ Calcule le budget rectifié réel = budget_initial + Σ mouvements collectifs adoptés
     */
    public static function getBudgetRectifieReel(LigneBudgetaire $record): float
    {
        $base = (float) $record->budget_initial;

        // Ajouter les mouvements de collectifs adoptés
        $mouvements = \App\Models\MouvementCollectif::where(function ($q) use ($record) {
            $q->where('ligne_depense_id', $record->id)
                ->orWhere('nouvelle_ligne_depense_id', $record->id);
        })
            ->whereHas('collectif', fn($q) => $q->where('statut', 'adopte'))
            ->sum('montant_modification');

        return $base + (float) $mouvements;
    }

    // ========================================
    // TABLE
    // ========================================
    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')->sortable(),

                Tables\Columns\TextColumn::make('budget.exercice.annee')
                    ->label('Exercice')->badge()->color('success')->default('-'),

                Tables\Columns\TextColumn::make('budget.libelle')
                    ->label('Budget')->searchable()->limit(30),

                Tables\Columns\TextColumn::make('nomenclature.code')
                    ->label('Code')->searchable()->weight('bold'),

                Tables\Columns\TextColumn::make('nomenclature.libelle')
                    ->label('Libellé')->searchable()->limit(40),

                Tables\Columns\TextColumn::make('budget_initial')
                    ->label('Dotation Initiale')->money('XAF')->sortable()->color('info'),

                // ✅ Collectifs budgétaires adoptés
                Tables\Columns\TextColumn::make('collectifs_montant')
                    ->label('Dont Collectifs')
                    ->getStateUsing(function ($record) {
                        $total = \App\Models\MouvementCollectif::where(function ($q) use ($record) {
                            $q->where('ligne_depense_id', $record->id)
                                ->orWhere('nouvelle_ligne_depense_id', $record->id);
                        })
                            ->whereHas('collectif', fn($q) => $q->where('statut', 'adopte'))
                            ->sum('montant_modification');
                        return $total != 0
                            ? ($total > 0 ? '+' : '') . number_format($total, 0, ',', ' ') . ' FCFA'
                            : '—';
                    })
                    ->color(fn($state) => str_starts_with($state ?? '', '+') ? 'success'
                        : (str_starts_with($state ?? '', '-') ? 'danger' : 'gray'))
                    ->toggleable(isToggledHiddenByDefault: true),

                // ✅ CORRIGÉ — calcul dynamique depuis engagements actifs
                Tables\Columns\TextColumn::make('total_engage_dynamique')
                    ->label('Total Engagé')
                    ->getStateUsing(fn($record) => self::getTotalEngage($record))
                    ->money('XAF')->color('warning')->weight('bold'),

                // ✅ CORRIGÉ — disponible calculé dynamiquement (pas la colonne stockée)
                Tables\Columns\TextColumn::make('disponible_reel')
                    ->label('Disponible')
                    ->getStateUsing(fn($record) => self::getDisponibleReel($record))
                    ->money('XAF')
                    ->color(fn($state) => $state > 0 ? 'success' : 'danger')
                    ->weight('bold'),

                // ✅ NOUVEAU — Montant OP Standard émises/payées
                Tables\Columns\TextColumn::make('montant_op')
                    ->label('Montant OP')
                    ->getStateUsing(fn($record) => self::getMontantOP($record))
                    ->money('XAF')->color('primary')
                    ->toggleable(isToggledHiddenByDefault: false),

                // ✅ NOUVEAU — Montant OPT Impôt émises/payées
                Tables\Columns\TextColumn::make('montant_opt')
                    ->label('Montant OPT')
                    ->getStateUsing(fn($record) => self::getMontantOPT($record))
                    ->money('XAF')->color('warning')
                    ->toggleable(isToggledHiddenByDefault: false),

                // ✅ CORRIGÉ — taux calculé dynamiquement
                Tables\Columns\TextColumn::make('taux_consommation_dynamique')
                    ->label('Taux Conso.')
                    ->getStateUsing(function ($record) {
                        if ($record->budget_initial == 0) return 0;
                        return (self::getTotalEngage($record) / $record->budget_initial) * 100;
                    })
                    ->formatStateUsing(fn($state) => number_format($state, 1) . '%')
                    ->badge()
                    ->color(fn($state) => match (true) {
                        $state < 50 => 'success',
                        $state < 80 => 'warning',
                        default     => 'danger',
                    }),

                Tables\Columns\TextColumn::make('nb_engagements')
                    ->label('Nb Engagements')
                    ->getStateUsing(
                        fn($record) =>
                        \App\Models\Engagement::where('budget_id', $record->budget_id)
                            ->where('nomenclature_principale_id', $record->nomenclature_id)
                            ->whereNotIn('statut', ['annule'])
                            ->count()
                    )
                    ->badge()->color('gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')->date('d/m/Y')->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('budget_id')
                    ->label('Budget')
                    ->options(fn() => \App\Models\Budget::orderBy('libelle')->pluck('libelle', 'id'))
                    ->searchable()->preload(),

                Tables\Filters\Filter::make('depassement')
                    ->label('Dépassements de crédits')
                    ->query(fn($query) => $query->whereRaw('disponible_engagement < 0'))
                    ->toggle(),

                Tables\Filters\Filter::make('dotation_positive')
                    ->label('Avec dotation > 0')
                    ->query(fn($query) => $query->where('budget_initial', '>', 0))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([

                    // ── PDF ───────────────────────────────────────
                    Tables\Actions\Action::make('generer_pdf')
                        ->label('Générer PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('danger')
                        ->visible(fn($record) => static::canGenererPdf($record))
                        ->action(function ($record) {
                            // ✅ Recalcule avant de générer le PDF
                            static::recalculerLigne($record);
                        })
                        ->url(fn($record) => route('fiche-controle-engagements.pdf', $record->id))
                        ->openUrlInNewTab(),

                    // ── Aperçu ────────────────────────────────────
                    Tables\Actions\Action::make('preview')
                        ->label('Aperçu')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->visible(fn($record) => static::canView($record))
                        ->action(function ($record) {
                            // ✅ Recalcule avant l'aperçu
                            static::recalculerLigne($record);
                        })
                        ->url(fn($record) => route('fiche-controle-engagements.preview', $record->id))
                        ->openUrlInNewTab(),

                    // ✅ NOUVEAU — Recalculer les montants stockés
                    Tables\Actions\Action::make('recalculer')
                        ->label('Recalculer')
                        ->icon('heroicon-o-arrow-path')
                        ->color('gray')
                        ->tooltip('Recalcule les montants engagés et le disponible depuis les données réelles')
                        ->action(function ($record) {
                            static::recalculerLigne($record);
                            Notification::make()
                                ->title('✅ Recalcul effectué')
                                ->body(
                                    'Engagé : ' . number_format(self::getTotalEngage($record), 0, ',', ' ') . ' FCFA'
                                        . ' | Disponible : ' . number_format(self::getDisponibleReel($record), 0, ',', ' ') . ' FCFA'
                                )
                                ->success()->send();
                        }),

                    // ── Détails ───────────────────────────────────
                    Tables\Actions\Action::make('details')
                        ->label('Détails')
                        ->icon('heroicon-o-information-circle')
                        ->color('gray')
                        ->visible(fn($record) => static::canView($record))
                        ->modalHeading('Détails de la Ligne Budgétaire')
                        ->modalContent(function ($record) {
                            $engagements = \App\Models\Engagement::with(['ordonnancesPaiement'])
                                ->where('budget_id', $record->budget_id)
                                ->where('nomenclature_principale_id', $record->nomenclature_id)
                                ->whereNotIn('statut', ['annule'])
                                ->get();

                            $totalEngage = self::getTotalEngage($record);
                            $tauxConso   = $record->budget_initial > 0
                                ? ($totalEngage / $record->budget_initial) * 100
                                : 0;
                            $montantOP  = self::getMontantOP($record);
                            $montantOPT = self::getMontantOPT($record);

                            return view('filament.pages.fiche-controle-details', [
                                'record'      => $record,
                                'engagements' => $engagements,
                                'totalEngage' => $totalEngage,
                                'tauxConso'   => $tauxConso,
                                'montantOP'   => $montantOP,
                                'montantOPT'  => $montantOPT,
                                'disponible'  => self::getDisponibleReel($record),
                            ]);
                        })
                        ->modalWidth('7xl')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Fermer'),

                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')
                    ->button()
                    ->size('sm'),

            ], position: ActionsPosition::BeforeColumns)
            ->headerActions([
                // ✅ Recalcul global de toutes les lignes budgétaires
                Tables\Actions\Action::make('recalculer_tout')
                    ->label('🔄 Recalculer tout')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Recalculer toutes les lignes budgétaires ?')
                    ->modalDescription('Recalcule les montants engagés et disponibles pour toutes les lignes. Peut prendre quelques secondes.')
                    ->action(function () {
                        $lignes = LigneBudgetaire::all();
                        $count = 0;
                        foreach ($lignes as $ligne) {
                            static::recalculerLigne($ligne);
                            $count++;
                        }
                        Notification::make()
                            ->title('✅ Recalcul terminé')
                            ->body("{$count} ligne(s) recalculée(s)")
                            ->success()->send();
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFicheControleEngagements::route('/'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->with(['budget.exercice', 'nomenclature']);
    }
}
