<?php

namespace App\Console\Commands;

use App\Services\Administration\GenerateurCharteDroits;
use Illuminate\Console\Command;

/**
 * Régénère la charte des droits d'utilisation du SIGB (Word .docx).
 *
 * Le document est un état : il relit les droits réellement configurés dans la base
 * et les permissions réellement testées par le code. À relancer après chaque
 * campagne de paramétrage des rôles.
 */
class GenererCharteDroits extends Command
{
    protected $signature = 'charte:generer
                            {--sans-nominatifs : Omet l\'annexe nominative : version diffusable hors Direction.}
                            {--sortie= : Chemin du .docx (storage/app par défaut).}';

    protected $description = 'Génère la charte des droits d\'utilisation du SIGB à partir des rôles configurés';

    public function handle(GenerateurCharteDroits $generateur): int
    {
        $sortie = $generateur->generer(
            (bool) $this->option('sans-nominatifs'),
            $this->option('sortie') ?: null,
        );

        foreach ($generateur->notes() as $note) {
            $this->line(rtrim($note));
        }

        $this->info('Charte générée : '.$sortie);

        if (! $this->option('sans-nominatifs')) {
            $this->comment('Version nominative : à ne diffuser qu\'à la Direction Générale.');
        }

        return self::SUCCESS;
    }
}
