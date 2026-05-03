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
        // ── 1. Provision ──────────────────────────────────────
        if ($depense->provision_ligne_regie_id) {
            $depense->provisionLigneRegie?->recalculer();
        }

        // ── 2. Ligne régie (mini-budget) ──────────────────────
        if ($depense->ligne_regie_avance_id) {
            $depense->ligneRegieAvance?->recalculerMontants();
        }

        // ── 3. Décaissement (tranche) ─────────────────────────
        if ($depense->decaissement_regie_id) {
            $depense->decaissementRegie?->recalculerDepenses();
        }

        // ── 4. Régie globale ──────────────────────────────────
        $depense->regieAvance?->recalculerMontants();
    }
}
