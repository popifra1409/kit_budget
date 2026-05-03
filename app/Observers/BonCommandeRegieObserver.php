<?php


namespace App\Observers;

use App\Models\BonCommandeRegie;

class BonCommandeRegieObserver
{
    public function saved(BonCommandeRegie $bc): void
    {
        $this->recalculerTout($bc);
    }

    public function deleted(BonCommandeRegie $bc): void
    {
        $this->recalculerTout($bc);
    }

    protected function recalculerTout(BonCommandeRegie $bc): void
    {
        // Recalculer uniquement si engagé (sinon pas encore de débit)
        if (!$bc->engage) return;

        // ── 1. Provision ──────────────────────────────────────
        $bc->provisionLigneRegie?->recalculer();

        // ── 2. Ligne régie (mini-budget) ──────────────────────
        $bc->ligneRegieAvance?->recalculerMontants();

        // ── 3. Régie globale ──────────────────────────────────
        $bc->regieAvance?->recalculerMontants();
    }
}
