<?php

namespace App\Filament\Budget\Resources\MemoireDepenseResource\Pages;

use App\Filament\Budget\Resources\MemoireDepenseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditMemoireDepense extends EditRecord
{
    protected static string $resource = MemoireDepenseResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Charger les lignes depuis la relation si non présentes dans $data
        $lignesRelation = $this->record->lignes->sortBy('numero_ligne');

        if ($lignesRelation->isNotEmpty()) {
            $lignesData = [];

            foreach ($lignesRelation as $ligne) {
                $qte      = max(1, (float) ($ligne->quantite  ?? 1));
                $napTotal = (float) ($ligne->montant_net       ?? $ligne->net_a_payer ?? 0);

                // Fallback : si montant_net absent, calculer depuis MHT - IR
                if ($napTotal <= 0 && ($ligne->montant_ht ?? 0) > 0) {
                    $napTotal = (float) $ligne->montant_ht - (float) ($ligne->montant_ir ?? 0);
                }

                // ✅ Injecter montant_nap_input = NAP total ÷ quantité
                $napUnitaire = $qte > 0 ? round($napTotal / $qte, 4) : 0;

                $lignesData[] = array_merge($ligne->toArray(), [
                    'montant_nap_input' => $napUnitaire,
                ]);
            }

            $data['lignes'] = $lignesData;
        }

        // Propager le mode de saisie
        $data['mode_saisie_global'] = $this->record->mode_saisie ?? 'montant_nap';

        // Propager les taux globaux depuis la première ligne
        if ($lignesRelation->isNotEmpty()) {
            $premiere = $lignesRelation->first();
            $data['taux_tva_global'] = $premiere->taux_tva ?? 19.25;
            $data['taux_ir_global']  = $premiere->taux_ir  ?? 5.5;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            // ── Aperçu ───────────────────────────────────────────
            Actions\Action::make('apercu_memoire')
                ->label('Aperçu')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->outlined()
                ->modalHeading(fn() => 'Aperçu — ' . $this->record->numero)
                ->modalWidth('7xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fermer')
                ->modalContent(function () {
                    $this->record->load('lignes');
                    return view('filament.modals.apercu-memoire-depense', [
                        'memoire' => $this->record,
                    ]);
                }),

            Actions\ViewAction::make(),

            Actions\DeleteAction::make()
                ->visible(fn() => $this->record->statut === 'brouillon'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Mémoire mis à jour')
            ->body("Le mémoire {$this->record->numero} a été sauvegardé.");
    }

    protected function afterSave(): void
    {
        $this->record->refresh();
        $this->record->load('lignes');

        if (method_exists($this->record, 'calculerTotaux')) {
            $this->record->calculerTotaux();
            $this->record->saveQuietly();
        }
    }
}