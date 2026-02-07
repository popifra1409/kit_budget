<?php

namespace App\Filament\Resources\BonCommandeResource\Pages;

use App\Filament\Resources\BonCommandeResource;
use App\Models\BonCommande;
use Filament\Resources\Pages\CreateRecord;

class CreateBonCommande extends CreateRecord
{
    protected static string $resource = BonCommandeResource::class;

    protected ?BonCommande $draft = null;

    /**
     * ===============================
     * INITIALISATION DU BROUILLON
     * ===============================
     */
    public function mount(): void
    {
        parent::mount();

        $this->draft = BonCommande::query()
            ->where('statut', 'brouillon')
            ->where('created_by', auth()->id())
            ->latest()
            ->first();

        if (! $this->draft) {
            $this->draft = BonCommande::create([
                'statut'     => 'brouillon',
                'created_by' => auth()->id(),
            ]);
        }

        // 🔗 Lier le formulaire au brouillon
        $this->form->model($this->draft)->fill();
    }

    /**
     * =====================================
     * AUTO-SAUVEGARDE À CHAQUE MODIFICATION
     * =====================================
     */
    protected function getFormSchema(): array
    {
        return array_map(function ($component) {
            return $component->afterStateUpdated(function () {
                $data = self::forcerExonerationTVA(
                    $this->form->getState()
                );

                $this->draft->update($data);
            });
        }, parent::getFormSchema());
    }

    /**
     * ===============================
     * VALIDATION FINALE
     * ===============================
     */
    protected function handleRecordCreation(array $data): BonCommande
    {
        $data['statut'] = 'valide';

        $data = self::forcerExonerationTVA($data);

        $this->draft->update($data);

        return $this->draft;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', [
            'record' => $this->draft,
        ]);
    }

    /**
     * ===============================
     * RÈGLE MÉTIER TVA
     * ===============================
     */
    protected static function forcerExonerationTVA(array $data): array
    {
        if (!empty($data['exonere_tva'])) {
            $data['montant_tva'] = 0;

            foreach ($data['lignes'] ?? [] as &$ligne) {
                $ligne['taux_tva'] = 0;
                $ligne['montant_tva'] = 0;
                $ligne['montant_ttc'] = $ligne['montant_ht'] ?? 0;
            }
        }

        return $data;
    }
}
