<?php

namespace App\Filament\Widgets;

use App\Models\Transmission;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class ToutesLesTransmissionsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    // ✅ Rafraîchir automatiquement toutes les 30 secondes
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
                    ->formatStateUsing(fn($state) => class_basename($state))
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('document.numero')
                    ->label('N° Document')
                    ->searchable()
                    ->weight('bold'),

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
                        'danger' => 'urgente',
                        'warning' => 'haute',
                        'info' => 'normale',
                        'gray' => 'basse',
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
                        'validation' => 'Validation',
                        'engagement' => 'Engagement',
                        'verification' => 'Vérification',
                        'correction' => 'Correction',
                        'signature' => 'Signature',
                        'information' => 'Information',
                        'liquidation' => 'Liquidation',
                        'paiement' => 'Paiement',
                    ]),

                Tables\Filters\SelectFilter::make('priorite')
                    ->options([
                        'urgente' => 'Urgente',
                        'haute' => 'Haute',
                        'normale' => 'Normale',
                        'basse' => 'Basse',
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
                    ->action(function (Transmission $record) {
                        // Marquer comme lu
                        $record->marquerCommeLu();

                        // Rediriger vers le document
                        $this->redirect($this->getDocumentUrl($record));
                    }),
            ])
            ->emptyStateHeading('Aucune transmission en cours')
            ->emptyStateDescription('Toutes les transmissions ont été traitées')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->defaultSort('date_transmission', 'desc')
            ->paginated([10, 25, 50]);
    }

    protected function getDocumentUrl(Transmission $transmission): string
    {
        $documentType = class_basename($transmission->document_type);

        return match ($documentType) {
            'BonCommande' => route('filament.admin.resources.bon-commandes.edit', ['record' => $transmission->document_id]),
            'Engagement' => route('filament.admin.resources.engagements.edit', ['record' => $transmission->document_id]),
            'Decision' => route('filament.admin.resources.decisions.edit', ['record' => $transmission->document_id]),
            'Recours' => route('filament.admin.resources.recours.edit', ['record' => $transmission->document_id]),
            default => route('filament.admin.pages.dashboard'),
        };
    }

    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->can('view_all_transmissions');
    }
}
