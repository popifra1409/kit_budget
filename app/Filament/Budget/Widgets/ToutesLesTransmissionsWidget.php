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

    // ✅ Polling 15s — se met à jour après clôture
    protected static ?string $pollingInterval = '15s';

    public function table(Table $table): Table
    {
        return $table
            ->heading('🌐 Toutes les transmissions')
            ->description('Vue d\'ensemble des transmissions — filtrables par statut')
            ->query(
                // ✅ TOUTES les transmissions (pas seulement en_attente)
                //    Le filtre statut permet de voir les clôturées
                Transmission::query()
                    ->with(['expediteur', 'destinataire'])
                    ->latest('date_transmission')
            )
            ->columns([

                Tables\Columns\TextColumn::make('document_type')
                    ->label('Type')
                    ->formatStateUsing(fn($state) => $this->getTypeLabel($state))
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('document_id')
                    ->label('N° Document')
                    ->formatStateUsing(fn($state, $record) => $this->getDocumentNumero($record))
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
                    ->label('Action attendue')
                    ->formatStateUsing(fn($record) => $record->getActionLabel())
                    ->color('warning'),

                // ✅ Colonne statut — clé pour voir les clôturées
                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
                    ->formatStateUsing(fn($state, $record) => $record->getStatutLabel())
                    ->color(fn($state) => match ($state) {
                        'en_attente' => 'warning',
                        'traite'     => 'success',
                        'retourne'   => 'info',
                        'annule'     => 'gray',
                        default      => 'secondary',
                    }),

                Tables\Columns\BadgeColumn::make('priorite')
                    ->label('Priorité')
                    ->colors([
                        'danger'  => 'urgente',
                        'warning' => 'haute',
                        'info'    => 'normale',
                        'gray'    => 'basse',
                    ]),

                Tables\Columns\TextColumn::make('date_transmission')
                    ->label('Transmis le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                // ✅ Date de clôture — visible si traitement effectué
                Tables\Columns\TextColumn::make('date_traitement')
                    ->label('Clôturé le')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('En attente')
                    ->color(fn($state) => $state ? 'success' : 'gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('date_limite')
                    ->label('Limite')
                    ->date('d/m/Y')
                    ->color(fn($record) => $record->estEnRetard() ? 'danger' : 'gray')
                    ->weight(fn($record) => $record->estEnRetard() ? 'bold' : 'normal')
                    ->icon(fn($record) => $record->estEnRetard() ? 'heroicon-o-exclamation-triangle' : null)
                    ->placeholder('Sans limite'),

                Tables\Columns\TextColumn::make('reponse')
                    ->label('Réponse/Motif')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([

                // ✅ Filtre statut — par défaut en_attente
                Tables\Filters\SelectFilter::make('statut')
                    ->label('Statut')
                    ->options([
                        'en_attente' => '⏳ En attente',
                        'traite'     => '✅ Clôturée',
                        'retourne'   => '↩️ Retournée',
                        'annule'     => '❌ Annulée',
                    ])
                    ->default('en_attente')
                    ->placeholder('Tous les statuts'),

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
                        'urgente' => '🔴 Urgente',
                        'haute'   => '🟠 Haute',
                        'normale' => '🔵 Normale',
                        'basse'   => '⚪ Basse',
                    ]),

                Tables\Filters\Filter::make('en_retard')
                    ->label('En retard')
                    ->query(
                        fn($query) => $query
                            ->whereNotNull('date_limite')
                            ->where('date_limite', '<', now())
                            ->where('statut', 'en_attente')
                    )
                    ->toggle(),

                Tables\Filters\Filter::make('non_lu')
                    ->label('Non lues')
                    ->query(fn($query) => $query->whereNull('date_lecture'))
                    ->toggle(),

                // ✅ Filtre clôturées aujourd'hui
                Tables\Filters\Filter::make('cloturees_aujourd_hui')
                    ->label('Clôturées aujourd\'hui')
                    ->query(
                        fn($query) => $query
                            ->where('statut', 'traite')
                            ->whereDate('date_traitement', today())
                    )
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
            ->emptyStateHeading('Aucune transmission')
            ->emptyStateDescription('Aucune transmission ne correspond aux filtres')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->defaultSort('date_transmission', 'desc')
            ->paginated([10, 25, 50]);
    }

    protected function getDocumentNumero(Transmission $record): string
    {
        try {
            $doc = $record->document;
            return $doc?->numero ?? "ID: {$record->document_id}";
        } catch (\Exception $e) {
            return "ID: {$record->document_id}";
        }
    }

    protected function normaliserType(string $documentType): string
    {
        if (str_contains($documentType, '\\')) {
            return class_basename($documentType);
        }
        return str($documentType)->studly()->toString();
    }

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

    protected function getDocumentUrl(Transmission $transmission): string
    {
        $dashboard = route('filament.budget.pages.dashboard');
        $id        = $transmission->document_id;
        if (!$id || !$transmission->document_type) return $dashboard;

        $type     = $this->normaliserType($transmission->document_type);
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
            'PrevisionRecette'       => 'filament.budget.resources.prevision-recettes.view',
            'VirementBudgetaire'     => 'filament.budget.resources.virement-budgetaires.view',
        ];

        $routeName = $routeMap[$type] ?? null;
        if (!$routeName) return $dashboard;

        try {
            return route($routeName, ['record' => $id]);
        } catch (\Exception $e) {
            return $dashboard;
        }
    }

    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->can('view_all_transmissions');
    }
}
