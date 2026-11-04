<?php

namespace App\Filament\Marches\Resources\OffreResource\Pages;

use App\Filament\Marches\Resources\OffreResource;
use App\Models\Offre;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

/**
 * Le guichet de réception des offres.
 */
class ListOffres extends ListRecords
{
    protected static string $resource = OffreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Enregistrer une offre')
                ->icon('heroicon-o-inbox-arrow-down'),
        ];
    }

    /**
     * Le badge du menu se retrouve en sous-titre : la liste doit dire d'elle-même
     * combien d'offres attendent encore une décision, sinon le chiffre du menu est
     * un mystère à décrypter quand on est déjà devant la liste.
     */
    public function getSubheading(): ?string
    {
        $enAttente = Offre::query()->enAttenteDeDecision()->count();

        return match (true) {
            $enAttente === 0  => 'Toutes les offres déposées ont été décidées.',
            $enAttente === 1  => '1 offre attend encore une décision.',
            default           => "{$enAttente} offres attendent encore une décision.",
        };
    }
}
