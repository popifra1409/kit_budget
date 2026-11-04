<?php

namespace App\Filament\Marches\Resources\OrdreServiceResource\Pages;

use App\Filament\Marches\Resources\OrdreServiceResource;
use App\Models\Marche;
use App\Models\OrdreService;
use Filament\Resources\Pages\ListRecords;

/**
 * La chronologie des actes : tous les ordres de service émis, marché par marché.
 *
 * Pas de bouton « Créer » ici, et ce n'est pas une cache : émettre un ordre de
 * service commande l'exécution d'un marché, donc cela se décide sur le marché
 * (action « Émettre un ordre de service »), où la fenêtre du cycle est lisible.
 */
class ListOrdresService extends ListRecords
{
    protected static string $resource = OrdreServiceResource::class;

    /**
     * Le chiffre du badge du menu se relit ici : ce que le guichet a notifié
     * aujourd'hui, et surtout ce qui l'attend — un marché homologué sans ordre de
     * service de démarrage est un marché qui ne tourne pas encore.
     */
    public function getSubheading(): ?string
    {
        $aujourdhui = OrdreService::emisAujourdhui()->count();
        $sansDemarrage = Marche::sansExercice()
            ->where('statut', 'homologue')
            ->whereDoesntHave('ordresService', fn ($q) => $q->whereHas(
                'type', fn ($t) => $t->where('demarre_lexecution', true)
            ))
            ->count();

        return sprintf(
            '%s ordre(s) notifié(s) aujourd\'hui · %s marché(s) homologué(s) attendant leur ordre de '
                . 'service de démarrage.',
            $aujourdhui,
            $sansDemarrage,
        );
    }
}
