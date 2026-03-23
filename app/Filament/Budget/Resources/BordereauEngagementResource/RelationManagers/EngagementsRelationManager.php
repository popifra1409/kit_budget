<?php

namespace App\Filament\Budget\Resources\BordereauEngagementResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Engagement;
use App\Models\NomenclatureBudgetaire;
use Filament\Notifications\Notification;

class EngagementsRelationManager extends RelationManager
{
    protected static string $relationship = 'engagements';

    protected static ?string $title = 'Engagements';

    protected static ?string $label = 'Engagement';

    protected static ?string $pluralLabel = 'Engagements';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('engagement_id')
                    ->label('Engagement')
                    ->options(function () {
                        $budgetId = $this->getOwnerRecord()->budget_id;

                        // Engagements du même budget, pas encore dans un bordereau
                        return Engagement::where('budget_id', $budgetId)
                            ->whereDoesntHave('bordereaux')
                            ->orWhereHas('bordereaux', function ($query) {
                                $query->where('bordereau_id', $this->getOwnerRecord()->id);
                            })
                            ->with(['nomenclaturePrincipale', 'beneficiaire'])
                            ->get()
                            ->mapWithKeys(fn($e) => [
                                $e->id => "{$e->numero} - {$e->type_engagement} - " .
                                    number_format($e->montant_engage, 0, ',', ' ') . " FCFA - " .
                                    ($e->beneficiaire ? $e->getNomBeneficiaire() : 'N/A')
                            ]);
                    })
                    ->required()
                    ->searchable()
                    ->preload()
                    ->disabled(fn() => !$this->getOwnerRecord()->estModifiable()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('numero')
            ->columns([
                Tables\Columns\TextColumn::make('pivot.numero_ligne')
                    ->label('#')
                    ->sortable(false),

                Tables\Columns\TextColumn::make('numero')
                    ->label('N° Engagement')
                    ->searchable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('numero_document')
                    ->label('N° BC/DA')
                    ->getStateUsing(function ($record) {
                        return $record->engageable?->numero ?? '-';
                    })
                    ->badge()
                    ->color(fn($record) => match ($record->type_engagement) {
                        'BC' => 'primary',
                        'DA' => 'success',
                        default => 'gray',
                    })
                    ->description(fn($record) => match ($record->type_engagement) {
                        'BC' => 'Bon de Commande',
                        'DA' => 'Décision Administrative',
                        default => null,
                    })
                    ->placeholder('-'),

                Tables\Columns\BadgeColumn::make('type_engagement')
                    ->label('Type')
                    ->colors([
                        'primary' => 'BC',
                        'success' => 'DA',
                        'warning' => 'Mission',
                        'info' => 'Avance',
                        'secondary' => fn($state) => !in_array($state, ['BC', 'DA', 'Mission', 'Avance']),
                    ]),

                Tables\Columns\TextColumn::make('nomenclaturePrincipale.code')
                    ->label('Nomenclature')
                    ->searchable()
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('beneficiaire')
                    ->label('Bénéficiaire')
                    ->getStateUsing(fn($record) => $record->getNomBeneficiaire() ?? 'Non défini')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('objet')
                    ->label('Objet')
                    ->limit(40)
                    ->wrap()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('montant_engage')
                    ->label('Montant')
                    ->money('XAF')
                    ->sortable()
                    ->weight('bold')
                    ->color('success')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('XAF')
                            ->label('Total'),
                    ]),

                Tables\Columns\BadgeColumn::make('pivot.statut_ligne')
                    ->label('Statut')
                    ->colors([
                        'secondary' => 'en_attente',
                        'success' => 'valide',
                        'danger' => 'rejete',
                        'gray' => 'annule',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'en_attente' => 'En attente',
                        'valide' => 'Validé',
                        'rejete' => 'Rejeté',
                        'annule' => 'Annulé',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('pivot.motif_rejet')
                    ->label('Motif rejet')
                    ->limit(30)
                    ->wrap()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type_engagement')
                    ->label('Type')
                    ->options([
                        'BC' => 'Bon de Commande',
                        'DA' => 'Décision',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->label('Ajouter un engagement')
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(function ($query) {
                        $budgetId = $this->getOwnerRecord()->budget_id;

                        return $query->where('budget_id', $budgetId)
                            ->whereDoesntHave('bordereaux')
                            ->where('statut', 'provisoire');
                    })
                    ->recordTitle(
                        fn($record) =>
                        "{$record->numero} - {$record->type_engagement} - " .
                            number_format($record->montant_engage, 0, ',', ' ') . " FCFA"
                    )
                    ->after(function () {
                        $this->getOwnerRecord()->recalculerMontants();
                        Notification::make()
                            ->title('Engagement ajouté')
                            ->success()
                            ->send();
                    })
                    ->visible(fn() => $this->getOwnerRecord()->estModifiable()),
            ])
            ->actions([
                Tables\Actions\Action::make('valider')
                    ->label('Valider')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(
                        fn($record) =>
                        $record->pivot->statut_ligne === 'en_attente' &&
                            in_array($this->getOwnerRecord()->statut, ['en_cours', 'transmis'])
                    )
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $ligne = $this->getOwnerRecord()
                            ->lignes()
                            ->where('engagement_id', $record->id)
                            ->first();

                        $ligne->valider();

                        Notification::make()
                            ->title('Engagement validé')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('rejeter')
                    ->label('Rejeter')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(
                        fn($record) =>
                        $record->pivot->statut_ligne === 'en_attente' &&
                            in_array($this->getOwnerRecord()->statut, ['en_cours', 'transmis'])
                    )
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('motif')
                            ->label('Motif du rejet')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        $ligne = $this->getOwnerRecord()
                            ->lignes()
                            ->where('engagement_id', $record->id)
                            ->first();

                        $ligne->rejeter($data['motif']);

                        Notification::make()
                            ->title('Engagement rejeté')
                            ->warning()
                            ->send();
                    }),

                Tables\Actions\DetachAction::make()
                    ->label('Retirer')
                    ->visible(fn() => $this->getOwnerRecord()->estModifiable())
                    ->after(function () {
                        $this->getOwnerRecord()->recalculerMontants();
                        Notification::make()
                            ->title('Engagement retiré')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make()
                        ->visible(fn() => $this->getOwnerRecord()->estModifiable()),
                ]),
            ])
            ->defaultSort('numero', 'desc');
    }
}
