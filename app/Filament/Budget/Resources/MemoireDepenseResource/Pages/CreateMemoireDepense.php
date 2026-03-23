<?php

namespace App\Filament\Budget\Resources\MemoireDepenseResource\Pages;

use App\Filament\Budget\Resources\MemoireDepenseResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateMemoireDepense extends CreateRecord
{
    protected static string $resource = MemoireDepenseResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Mémoire créé')
            ->body('Le mémoire de dépense a été créé avec succès.');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (!isset($data['lignes']) || !is_array($data['lignes'])) {
            return $data;
        }

        foreach ($data['lignes'] as &$ligne) {

            $qte = floatval($ligne['quantite'] ?? 0);
            $nap = floatval($ligne['montant_nap_input'] ?? 0);
            $tauxIr = floatval($ligne['taux_ir'] ?? 5.5);

            // Si mode NAP
            if ($nap > 0 && $qte > 0) {

                $napTotal = $nap * $qte;

                $mht = $napTotal / (1 - ($tauxIr / 100));

                $pu = $mht / $qte;

                $ligne['prix_unitaire'] = round($pu, 2);
                $ligne['net_a_payer'] = $napTotal;
            }

            // Sécurité absolue
            if (!isset($ligne['prix_unitaire'])) {
                $ligne['prix_unitaire'] = 0;
            }
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // Recalculer les totaux après création
        $this->record->calculerTotaux();
        $this->record->save();
    }
}
