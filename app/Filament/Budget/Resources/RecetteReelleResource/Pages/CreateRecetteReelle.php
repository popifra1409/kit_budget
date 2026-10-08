<?php

namespace App\Filament\Budget\Resources\RecetteReelleResource\Pages;

use App\Filament\Budget\Resources\RecetteReelleResource;
use App\Models\PrevisionRecetteMensuelle;
use App\Models\RecetteReelle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * ✅ Saisie groupée des recettes : exercice, mois et date communs ; une recette créée par ligne
 * du répétiteur, dans une seule transaction (tout ou rien).
 */
class CreateRecetteReelle extends CreateRecord
{
    protected static string $resource = RecetteReelleResource::class;

    protected static ?string $title = 'Saisie des recettes';

    protected int $nombreCrees = 0;

    protected function handleRecordCreation(array $data): Model
    {
        $derniere = null;

        DB::transaction(function () use ($data, &$derniere) {
            foreach ($data['recettes'] ?? [] as $ligne) {
                $mensuelle = PrevisionRecetteMensuelle::with('lignePrevisionRecette')->find($ligne['prevision_recette_mensuelle_id']);

                $derniere = RecetteReelle::create([
                    'prevision_recette_mensuelle_id' => $ligne['prevision_recette_mensuelle_id'],
                    'libelle'            => $mensuelle?->lignePrevisionRecette?->libelle_nomenclature,
                    'montant_constate'   => (float) $ligne['montant_constate'],
                    'montant'            => (float) ($ligne['montant'] ?? 0),
                    'date_recette'       => $data['date_recette'],
                    'tiers_recette_id'   => $ligne['tiers_recette_id'] ?? null,
                    'mode_paiement'      => (float) ($ligne['montant'] ?? 0) > 0 ? ($ligne['mode_paiement'] ?? null) : null,
                    'reference_paiement' => $ligne['reference_paiement'] ?? null,
                    'observations'       => $ligne['observations'] ?? null,
                    'statut'             => 'encaissee',   // ajusté automatiquement (constatée s'il reste un RAR)
                ]);

                $this->nombreCrees++;
            }
        });

        return $derniere;
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()->success()
            ->title("{$this->nombreCrees} recette(s) enregistrée(s)")
            ->body('Les prévisions mensuelles et le tableau de suivi sont à jour.');
    }

    /** Retour à la liste après la saisie (et non sur la dernière recette créée). */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
