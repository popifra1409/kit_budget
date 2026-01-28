<?php

namespace App\Filament\Widgets;

use App\Models\Transmission;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class MesTachesEnAttenteWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

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
                    ->formatStateUsing(fn($state) => class_basename($state))
                    ->badge()
                    ->color('info'),

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
                        'danger' => 'urgente',
                        'warning' => 'haute',
                        'info' => 'normale',
                        'gray' => 'basse',
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
                    ->icon(fn($record) => $record->estEnRetard() ? 'heroicon-o-exclamation-triangle' : null),

                Tables\Columns\TextColumn::make('commentaire')
                    ->label('Commentaire')
                    ->limit(50)
                    ->toggleable(),
            ])
            ->actions([
                Tables\Actions\Action::make('voir')
                    ->label('Voir')
                    ->icon('heroicon-o-eye')
                    ->url(fn($record) => $this->getDocumentUrl($record))
                    ->openUrlInNewTab(),

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

    protected function getDocumentUrl(Transmission $transmission): string
    {
        return match ($transmission->document_type) {
            'App\Models\BonCommande' => route('filament.admin.resources.bon-commandes.view', $transmission->document_id),
            'App\Models\Engagement' => route('filament.admin.resources.engagements.view', $transmission->document_id),
            default => route('filament.admin.pages.dashboard'),
        };
    }

    public static function canView(): bool
    {
        return auth()->check();
    }
}
