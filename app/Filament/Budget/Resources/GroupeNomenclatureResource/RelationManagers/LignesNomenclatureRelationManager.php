<?php

namespace App\Filament\Budget\Resources\GroupeNomenclatureResource\RelationManagers;

use App\Models\GroupeNomenclature;
use App\Models\NomenclatureBudgetaire;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LignesNomenclatureRelationManager extends RelationManager
{
    protected static string $relationship = 'lignesNomenclature';

    protected static ?string $title = 'Lignes de nomenclature rattachées';

    protected static ?string $recordTitleAttribute = 'libelle';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('libelle')
                    ->label('Libellé')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('niveau')
                    ->label('Niveau')
                    ->badge(),

                Tables\Columns\TextColumn::make('classe')
                    ->label('Classe')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\IconColumn::make('actif')
                    ->label('Actif')
                    ->boolean(),
            ])
            ->filters([])

            // ── Rattacher plusieurs lignes existantes en une fois ────
            ->headerActions([
                Tables\Actions\Action::make('rattacherLignes')
                    ->label('Rattacher des lignes existantes')
                    ->icon('heroicon-o-link')
                    ->color('primary')
                    ->form(function () {
                        /** @var GroupeNomenclature $groupe */
                        $groupe = $this->getOwnerRecord();

                        return [
                            Forms\Components\Select::make('nomenclature_ids')
                                ->label('Lignes de nomenclature à rattacher')
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->options(
                                    NomenclatureBudgetaire::query()
                                        ->where('type', $groupe->type)
                                        ->where(function ($q) use ($groupe) {
                                            $q->whereNull('groupe_id')
                                                ->orWhere('groupe_id', '!=', $groupe->id);
                                        })
                                        ->orderBy('code')
                                        ->get()
                                        ->mapWithKeys(fn($n) => [
                                            $n->id => "{$n->code} — {$n->libelle}" . ($n->groupe_id ? " (actuellement: {$n->groupe?->libelle})" : ''),
                                        ])
                                )
                                ->helperText("Seules les lignes de type « {$groupe->type} » sont proposées (cohérence de type). Une ligne déjà dans un autre groupe sera déplacée ici.")
                                ->required(),
                        ];
                    })
                    ->action(function (array $data) {
                        /** @var GroupeNomenclature $groupe */
                        $groupe = $this->getOwnerRecord();

                        $count = NomenclatureBudgetaire::whereIn('id', $data['nomenclature_ids'])
                            ->update(['groupe_id' => $groupe->id]);

                        Notification::make()
                            ->title("✅ {$count} ligne(s) rattachée(s)")
                            ->body("Les lignes sélectionnées sont maintenant rattachées au groupe « {$groupe->libelle} ».")
                            ->success()
                            ->send();
                    }),
            ])

            // ── Actions par ligne + en masse ──────────────────────────
            ->actions([
                Tables\Actions\Action::make('detacher')
                    ->label('Détacher')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Cette ligne ne sera plus rattachée à aucun groupe (catégorie "Non classées").')
                    ->action(function (NomenclatureBudgetaire $record) {
                        $record->update(['groupe_id' => null]);

                        Notification::make()
                            ->title('Ligne détachée')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Détacher en masse
                    Tables\Actions\BulkAction::make('detacherMasse')
                        ->label('Détacher la sélection')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription('Les lignes sélectionnées ne seront plus rattachées à aucun groupe.')
                        ->action(function ($records) {
                            $count = $records->count();
                            NomenclatureBudgetaire::whereIn('id', $records->pluck('id'))
                                ->update(['groupe_id' => null]);

                            Notification::make()
                                ->title("✅ {$count} ligne(s) détachée(s)")
                                ->success()
                                ->send();
                        }),

                    // Déplacer en masse vers un autre groupe (du même type)
                    Tables\Actions\BulkAction::make('deplacerMasse')
                        ->label('Déplacer vers un autre groupe')
                        ->icon('heroicon-o-arrows-right-left')
                        ->color('warning')
                        ->form(function () {
                            /** @var GroupeNomenclature $groupeActuel */
                            $groupeActuel = $this->getOwnerRecord();

                            return [
                                Forms\Components\Select::make('nouveau_groupe_id')
                                    ->label('Nouveau groupe de destination')
                                    ->options(
                                        GroupeNomenclature::query()
                                            ->where('type', $groupeActuel->type)
                                            ->where('id', '!=', $groupeActuel->id)
                                            ->where('actif', true)
                                            ->orderBy('ordre')
                                            ->pluck('libelle', 'id')
                                    )
                                    ->searchable()
                                    ->required(),
                            ];
                        })
                        ->action(function (array $data, $records) {
                            $count = $records->count();
                            $nouveauGroupe = GroupeNomenclature::find($data['nouveau_groupe_id']);

                            NomenclatureBudgetaire::whereIn('id', $records->pluck('id'))
                                ->update(['groupe_id' => $nouveauGroupe->id]);

                            Notification::make()
                                ->title("✅ {$count} ligne(s) déplacée(s) vers « {$nouveauGroupe->libelle} »")
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('code', 'asc');
    }
}
