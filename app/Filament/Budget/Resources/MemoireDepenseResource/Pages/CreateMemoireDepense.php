<?php

namespace App\Filament\Budget\Resources\MemoireDepenseResource\Pages;

use App\Filament\Budget\Resources\MemoireDepenseResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use App\Models\MemoireDepense;

class CreateMemoireDepense extends CreateRecord
{
    protected static string $resource = MemoireDepenseResource::class;

    // ← Rediriger vers Edit au lieu de Index
    // L'aperçu sera disponible immédiatement après création
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Mémoire créé')
            ->body("Le mémoire {$this->record->numero} a été créé. Vous pouvez maintenant utiliser le bouton Aperçu.");
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['numero'] = MemoireDepense::genererNumero((int)($data['exercice'] ?? now()->year));

        if (!isset($data['lignes']) || !is_array($data['lignes'])) {
            return $data;
        }

        foreach ($data['lignes'] as &$ligne) {
            $qte    = floatval($ligne['quantite']          ?? 0);
            $nap    = floatval($ligne['montant_nap_input'] ?? 0);
            $pu     = floatval($ligne['prix_unitaire']     ?? 0);
            $tauxIr = floatval($ligne['taux_ir']           ?? 5.5);

            \Log::info('CreateMemoire ligne', [
                'qte' => $qte,
                'nap' => $nap,
                'pu' => $pu,
                'tauxIr' => $tauxIr
            ]);

            if ($nap > 0 && $qte > 0) {
                $napTotal = $nap * $qte;
                $mht      = $napTotal / (1 - ($tauxIr / 100));
                $ligne['prix_unitaire'] = round($mht / $qte, 2);
            } elseif ($pu > 0) {
                $ligne['prix_unitaire'] = $pu;
            } else {
                $ligne['prix_unitaire'] = 0;
            }

            unset($ligne['montant_nap_input']);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->calculerTotaux();
        $this->record->saveQuietly();
    }
}
