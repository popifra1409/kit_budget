<?php

namespace App\Filament\Budget\Widgets;

use App\Models\Transmission;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class MesTachesEnAttenteWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    /**
     * Normalise document_type vers le FQCN
     * (la base contient des noms courts ET des FQCN)
     */
    protected function normaliserType(string $type): string
    {
        return match ($type) {
            'bon_commande',
            'BonCommande',
            \App\Models\BonCommande::class            => \App\Models\BonCommande::class,

            'decision_administrative',
            'DecisionAdministrative',
            \App\Models\DecisionAdministrative::class => \App\Models\DecisionAdministrative::class,

            'engagement',
            'Engagement',
            \App\Models\Engagement::class             => \App\Models\Engagement::class,

            'bordereau_engagement',
            'BordereauEngagement',
            \App\Models\BordereauEngagement::class    => \App\Models\BordereauEngagement::class,

            'memoire_depense',
            'MemoireDepense',
            \App\Models\MemoireDepense::class         => \App\Models\MemoireDepense::class,

            default => $type,
        };
    }

    /**
     * Mappage FQCN → [route Filament, permission requise]
     */
    protected function getRouteConfig(): array
    {
        return [
            \App\Models\BonCommande::class => [
                'route'      => 'filament.budget.resources.bon-commandes.view',
                'permission' => 'view_bon_commande',
            ],
            \App\Models\Engagement::class => [
                'route'      => 'filament.budget.resources.engagements.view',
                'permission' => 'view_engagement',
            ],
            \App\Models\DecisionAdministrative::class => [
                'route'      => 'filament.budget.resources.decision-administratives.view',
                'permission' => 'view_decision_administrative',
            ],
            \App\Models\BordereauEngagement::class => [
                'route'      => 'filament.budget.resources.bordereau-engagements.view',
                'permission' => 'view_bordereau_engagement',
            ],
            \App\Models\MemoireDepense::class => [
                'route'      => 'filament.budget.resources.memoire-depenses.view',
                'permission' => 'view_memoire_depense',
            ],
        ];
    }

    /**
     * Vérifie si l'utilisateur peut voir ce type de document
     */
    protected function peutVoirDocument(Transmission $transmission): bool
    {
        $type   = $this->normaliserType($transmission->document_type ?? '');
        $config = $this->getRouteConfig();

        if (!isset($config[$type])) return false;

        return auth()->user()?->can($config[$type]['permission']) ?? false;
    }

    /**
     * Construit l'URL du document — retourne null si pas de permission
     */
    protected function getDocumentUrl(Transmission $transmission): ?string
    {
        $type   = $this->normaliserType($transmission->document_type ?? '');
        $config = $this->getRouteConfig();

        if (!isset($config[$type])) return null;

        $permission = $config[$type]['permission'];
        $routeName  = $config[$type]['route'];

        // ✅ Vérifier la permission avant de construire l'URL
        if (!auth()->user()?->can($permission)) {
            return null;
        }

        return route($routeName, ['record' => $transmission->document_id]);
    }

    protected function getLabelType(string $type): string
    {
        return match ($this->normaliserType($type)) {
            \App\Models\BonCommande::class            => 'Bon de Commande',
            \App\Models\Engagement::class             => 'Engagement',
            \App\Models\DecisionAdministrative::class => 'Décision Admin.',
            \App\Models\BordereauEngagement::class    => 'Bordereau',
            \App\Models\MemoireDepense::class         => 'Mémoire Dépense',
            default                                   => class_basename($type),
        };
    }

    protected function getCouleurType(string $type): string
    {
        return match ($this->normaliserType($type)) {
            \App\Models\BonCommande::class            => 'info',
            \App\Models\Engagement::class             => 'success',
            \App\Models\DecisionAdministrative::class => 'warning',
            \App\Models\BordereauEngagement::class    => 'gray',
            \App\Models\MemoireDepense::class         => 'danger',
            default                                   => 'gray',
        };
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('📬 Mes tâches en attente')
            ->description('Documents en attente de votre action')
            ->query(
                Transmission::query()
                    ->with(['expediteur', 'document'])
                    ->pourDestinataire(auth()->id())
                    ->enAttente()
                    ->latest('date_transmission')
            )
            ->columns([
                Tables\Columns\TextColumn::make('document_type')
                    ->label('Type')
                    ->formatStateUsing(fn($state) => $this->getLabelType($state ?? ''))
                    ->badge()
                    ->color(fn($state) => $this->getCouleurType($state ?? '')),

                Tables\Columns\TextColumn::make('document.numero')
                    ->label('N° Document')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('expediteur.name')
                    ->label('De')
                    ->searchable(),

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

                Tables\Columns\TextColumn::make('date_transmission')
                    ->label('Reçu le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('date_limite')
                    ->label('Limite')
                    ->date('d/m/Y')
                    ->color(fn($record) => $record->estEnRetard() ? 'danger' : 'gray')
                    ->weight(fn($record) => $record->estEnRetard() ? 'bold' : 'normal')
                    ->icon(fn($record) => $record->estEnRetard()
                        ? 'heroicon-o-exclamation-triangle' : null),

                Tables\Columns\TextColumn::make('commentaire')
                    ->label('Commentaire')
                    ->limit(50)
                    ->toggleable(),
            ])
            ->actions([
                // ✅ Bouton "Voir" — visible uniquement si l'utilisateur a la permission
                Tables\Actions\Action::make('voir')
                    ->label('Voir')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->visible(fn($record) => $this->peutVoirDocument($record))
                    ->url(fn($record) => $this->getDocumentUrl($record))
                    ->openUrlInNewTab(),

                // ✅ Bouton "Accès restreint" — visible si pas de permission (informatif)
                Tables\Actions\Action::make('acces_restreint')
                    ->label('Accès restreint')
                    ->icon('heroicon-o-lock-closed')
                    ->color('gray')
                    ->visible(fn($record) => !$this->peutVoirDocument($record))
                    ->tooltip('Vous n\'avez pas accès à ce type de document')
                    ->disabled(),

                Tables\Actions\Action::make('marquer_lu')
                    ->label('Marquer lu')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn($record) => !$record->date_lecture)
                    ->action(fn($record) => $record->marquerCommeLu()),
            ])
            ->emptyStateHeading('🎉 Aucune tâche en attente')
            ->emptyStateDescription('Vous êtes à jour !')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([5, 10, 25]);
    }

    public static function canView(): bool
    {
        return auth()->check();
    }
}
