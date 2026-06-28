<?php

namespace App\Filament\Budget\Widgets;

use App\Models\Transmission;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class ToutesLesTransmissionsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $pollingInterval = '30s';

    public function table(Table $table): Table
    {
        return $table
            ->heading('🌐 Toutes les transmissions en cours')
            ->description('Vue d\'ensemble des transmissions en attente dans le système')
            ->query(
                Transmission::query()
                    ->with(['expediteur', 'destinataire', 'document'])
                    ->enAttente()
                    ->latest('date_transmission')
            )
            ->columns([
                Tables\Columns\TextColumn::make('document_type')
                    ->label('Type')
                    ->formatStateUsing(fn($state) => $this->getTypeLabel($state))
                    ->badge()
                    ->color('info'),

                // ✅ N° Document cliquable
                Tables\Columns\TextColumn::make('document.numero')
                    ->label('N° Document')
                    ->searchable()
                    ->weight('bold')
                    ->color('primary')
                    ->url(fn(Transmission $record): string => $this->getDocumentUrl($record)),

                Tables\Columns\TextColumn::make('expediteur.name')
                    ->label('De')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('destinataire.name')
                    ->label('À')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\BadgeColumn::make('action_attendue')
                    ->label('Action')
                    ->formatStateUsing(fn($record) => $record->getActionLabel())
                    ->color('warning'),

                Tables\Columns\BadgeColumn::make('priorite')
                    ->label('Priorité')
                    ->colors([
                        'danger'  => 'urgente',
                        'warning' => 'haute',
                        'info'    => 'normale',
                        'gray'    => 'basse',
                    ]),

                Tables\Columns\IconColumn::make('date_lecture')
                    ->label('Lu')
                    ->boolean()
                    ->trueIcon('heroicon-o-eye')
                    ->falseIcon('heroicon-o-eye-slash')
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('date_transmission')
                    ->label('Date')
                    ->since()
                    ->sortable(),

                Tables\Columns\TextColumn::make('date_limite')
                    ->label('Limite')
                    ->date('d/m/Y')
                    ->color(fn($record) => $record->estEnRetard() ? 'danger' : 'gray')
                    ->weight(fn($record) => $record->estEnRetard() ? 'bold' : 'normal')
                    ->icon(fn($record) => $record->estEnRetard() ? 'heroicon-o-exclamation-triangle' : null)
                    ->placeholder('Pas de limite'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action_attendue')
                    ->label('Action')
                    ->options([
                        'validation'   => 'Validation',
                        'engagement'   => 'Engagement',
                        'verification' => 'Vérification',
                        'correction'   => 'Correction',
                        'signature'    => 'Signature',
                        'information'  => 'Information',
                        'liquidation'  => 'Liquidation',
                        'paiement'     => 'Paiement',
                    ]),

                Tables\Filters\SelectFilter::make('priorite')
                    ->options([
                        'urgente' => 'Urgente',
                        'haute'   => 'Haute',
                        'normale' => 'Normale',
                        'basse'   => 'Basse',
                    ]),

                Tables\Filters\Filter::make('en_retard')
                    ->label('En retard')
                    ->query(fn($query) => $query->whereNotNull('date_limite')
                        ->where('date_limite', '<', now()))
                    ->toggle(),

                Tables\Filters\Filter::make('non_lu')
                    ->label('Non lu')
                    ->query(fn($query) => $query->whereNull('date_lecture'))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\Action::make('voir')
                    ->label('Voir')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->url(fn(Transmission $record): string => $this->getDocumentUrl($record))
                    ->action(fn(Transmission $record) => $record->marquerCommeLu()),
            ])
            ->emptyStateHeading('Aucune transmission en cours')
            ->emptyStateDescription('Toutes les transmissions ont été traitées')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->defaultSort('date_transmission', 'desc')
            ->paginated([10, 25, 50]);
    }

    /**
     * ✅ Normalise le document_type en clé canonique.
     * Gère les deux formats : 'App\Models\BonCommande' ET 'bon_commande'
     */
    protected function normaliserType(string $documentType): string
    {
        // Format FQCN → extraire la classe
        if (str_contains($documentType, '\\')) {
            return class_basename($documentType);
        }

        // Format snake_case → PascalCase
        return str($documentType)->studly()->toString();
    }

    /**
     * ✅ Libellé lisible du type de document
     */
    protected function getTypeLabel(string $documentType): string
    {
        $type = $this->normaliserType($documentType);

        return match ($type) {
            'BonCommande'            => 'Bon de Commande',
            'BonCommandeRegie'       => 'BC Régie',
            'DecisionAdministrative' => 'Décision Admin.',
            'DecisionPrevisionnelle' => 'Décision Prév.',
            'Engagement'             => 'Engagement',
            'OrdonnancePaiement'     => 'Ordonnance',
            'MemoireDepense'         => 'Mémoire Dépense',
            'MenuDepense'            => 'Menu Dépense',
            'BordereauEngagement'    => 'Bordereau',
            'RegieAvance'            => 'Régie Avance',
            'AchatDirect'            => 'Achat Direct',
            default                  => $type,
        };
    }

    /**
     * ✅ URL vers le document correspondant.
     * Gère les deux formats de document_type en base :
     *   - FQCN  : 'App\Models\DecisionAdministrative'
     *   - snake : 'decision_administrative'
     */
    protected function getDocumentUrl(Transmission $transmission): string
    {
        $dashboard = route('filament.budget.pages.dashboard');
        $id        = $transmission->document_id;

        if (!$id || !$transmission->document_type) {
            return $dashboard;
        }

        $type = $this->normaliserType($transmission->document_type);

        $routeMap = [
            'BonCommande'            => 'filament.budget.resources.bon-commandes.view',
            'BonCommandeRegie'       => 'filament.budget.resources.bon-commande-regies.view',
            'DecisionAdministrative' => 'filament.budget.resources.decision-administratives.view',
            'DecisionPrevisionnelle' => 'filament.budget.resources.decision-previsionnelles.view',
            'Engagement'             => 'filament.budget.resources.engagements.view',
            'OrdonnancePaiement'     => 'filament.budget.resources.ordonnance-paiements.view',
            'MemoireDepense'         => 'filament.budget.resources.memoire-depenses.view',
            'MenuDepense'            => 'filament.budget.resources.menus-depenses.view',
            'BordereauEngagement'    => 'filament.budget.resources.bordereau-engagements.view',
            'RegieAvance'            => 'filament.budget.resources.regie-avances.view',
            'AchatDirect'            => 'filament.budget.resources.achats-directs.view',
            'Fournisseur'            => 'filament.budget.resources.fournisseurs.view',
            'DossierFournisseur'     => 'filament.budget.resources.dossier-fournisseurs.view',
            'Budget'                 => 'filament.budget.resources.budgets.view',
            'PrevisionRecette'       => 'filament.budget.resources.prevision-recettes.view',
            'VirementBudgetaire'     => 'filament.budget.resources.virement-budgetaires.view',
        ];

        $routeName = $routeMap[$type] ?? null;

        if (!$routeName) {
            \Log::warning("ToutesLesTransmissionsWidget: type [{$type}] non mappé", [
                'raw_type'    => $transmission->document_type,
                'document_id' => $id,
            ]);
            return $dashboard;
        }

        try {
            return route($routeName, ['record' => $id]);
        } catch (\Exception $e) {
            \Log::warning("ToutesLesTransmissionsWidget: route [{$routeName}] introuvable", [
                'error' => $e->getMessage(),
            ]);
            return $dashboard;
        }
    }

    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->can('view_all_transmissions');
    }
}
