<?php

namespace App\Exports\EtatsActions;

use App\Models\Exercice;
use App\Models\SousProgrammeEp;
use App\Services\Planification\EtatsActionsSousProgramme;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Un onglet par action : c'est l'état isolé, donc le fichier aussi. Le responsable qui
 * reçoit le classeur ne doit pas avoir à filtrer les autres actions pour lire la sienne.
 */
class EtatsActionsSousProgrammeExport implements WithMultipleSheets
{
    public function __construct(protected EtatsActionsSousProgramme $rapport) {}

    /** @return array<int, EtatActionSheet> */
    public function sheets(): array
    {
        $chapeau = $this->rapport->titre();
        $exercice = $this->rapport->exercice;
        $sousProgramme = $this->rapport->sousProgramme;

        return collect($this->rapport->etats())
            ->map(fn(array $etat) => new EtatActionSheet(
                $this->titreOnglet($etat),
                $etat,
                $chapeau,
                $exercice,
                $sousProgramme,
            ))
            ->values()
            ->all();
    }

    /** Les noms d'onglets Excel sont limités à 31 caractères et à certains signes. */
    protected function titreOnglet(array $etat): string
    {
        // « Action 412-A0 : … » répéterait le code déjà devant le libellé de l'onglet.
        $libelle = trim("{$etat['code']} {$etat['action']->libelle}");
        $titre = preg_replace('/[\[\]:*?\/\\\\]/', '-', $libelle);

        return trim(mb_substr($titre, 0, 31));
    }
}
