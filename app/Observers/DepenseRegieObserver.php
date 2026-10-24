<?php

namespace App\Observers;

use App\Models\DepenseRegie;

class DepenseRegieObserver
{
    public function saved(DepenseRegie $depense): void
    {
        $this->recalculerTout($depense);
    }

    public function deleted(DepenseRegie $depense): void
    {
        $this->recalculerTout($depense);
    }

    protected function recalculerTout(DepenseRegie $depense): void
    {
        // L'achat direct est chiffré par lignes : l'en-tête, que la provision
        // débite et que la tranche somme, doit d'abord porter leur synthèse.
        $depense->alignerTotauxSurLignes();
        $depense->rafraichirAgregeats();
    }
}
