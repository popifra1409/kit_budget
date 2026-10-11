<?php

namespace App\Console\Commands;

use App\Models\LigneRegieAvance;
use App\Models\RegieAvance;
use Illuminate\Console\Command;

/**
 * Recalcule le consommé et le disponible STOCKÉS des lignes de mini-budget des régies depuis
 * la consommation TRACÉE (provisions + achats directs sans provision), puis les totaux des régies.
 *
 *   php artisan regie:recalculer-lignes              → simulation
 *   php artisan regie:recalculer-lignes --appliquer  → correction
 */
class RecalculerLignesRegieCommand extends Command
{
    protected $signature = 'regie:recalculer-lignes {--appliquer : Enregistrer (sinon simulation)}';

    protected $description = 'Recalcule le consommé des lignes de mini-budget des régies depuis la consommation tracée';

    public function handle(): int
    {
        $appliquer = (bool) $this->option('appliquer');
        $f = fn($v) => number_format((float) $v, 0, ',', ' ');
        $lignes = [];

        foreach (LigneRegieAvance::with(['regieAvance', 'nomenclature', 'decaissementRegie'])->get() as $l) {
            $reel = $l->montantConsommeReel();
            if (abs((float) $l->montant_consomme - $reel) < 1) {
                continue;
            }

            $lignes[] = [
                $l->regieAvance?->numero,
                $l->nomenclature?->code,
                $l->decaissementRegie?->numero ?? 'hors tranche',
                $f($l->montant_alloue),
                $f($l->montant_consomme),
                $f($reel),
                $f((float) $l->montant_alloue - $reel),
            ];

            if ($appliquer) {
                $l->recalculerMontants();
            }
        }

        if (empty($lignes)) {
            $this->info('✅ Le consommé de toutes les lignes est conforme à la consommation tracée.');
            return self::SUCCESS;
        }

        $this->table(['Régie', 'Compte', 'Encaisse', 'Alloué', 'Consommé affiché', 'Consommé réel', 'Disponible réel'], $lignes);

        if ($appliquer) {
            RegieAvance::withoutGlobalScopes()->get()->each->recalculerMontants();
            $this->info('✅ Lignes et régies recalculées.');
        } else {
            $this->line('Simulation : relancez avec --appliquer pour corriger.');
        }

        return self::SUCCESS;
    }
}
