<?php

namespace App\Services;

use App\Models\FichePresence;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPDF;

/**
 * La fiche de présence imprimée — étape E du module /marches.
 *
 * Le papier se lit dans les deux sens : avant la séance, pour la présenter et faire
 * émarger, et après, pour la verser au dossier. Le PDF est donc l'image exacte des
 * lignes de la fiche, émargements et visas compris — c'est la case que le membre a
 * signée sur la feuille, pas une réécriture de l'écran.
 *
 * Le document n'a pas d'état : il n'est ni validé ni rejeté, il se relit. Rien ici ne
 * décide du marché.
 *
 * Convention retenue, comme `FicheControleEngagementsPdfService` : `Pdf::loadView` sur
 * un gabarit autonome de `resources/views/pdf/`, sans passer par la machinerie
 * `EtatConfig`. La fiche de présence est un papier de séance, pas un état budgétaire
 * paramétrable — il n'y a rien à choisir avant de l'imprimer.
 */
class FichePresencePdfService
{
    public function genererPdf(FichePresence $fiche): DomPDF
    {
        $fiche->load([
            'marche.exercice',
            'commission.service',
            'etabliePar',
            // La relation trie déjà par ordre puis par identifiant ; rien à ajouter ici.
            'membres',
        ]);

        $pdf = Pdf::loadView('pdf.fiche-presence', [
            'fiche'     => $fiche,
            'marche'    => $fiche->marche,
            'commission'=> $fiche->commission,
            'membres'   => $fiche->membres,
            // Ce qui manque à la fiche pour faire foi s'imprime avec elle : un papier
            // incomplet doit le dire sur le papier, pas seulement à l'écran.
            'attentes'  => $fiche->attentes(),
            'titres'    => config('marches.titres_membre'),
        ]);

        $pdf->setPaper('A4', 'portrait');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => false,
            'defaultFont'          => 'DejaVu Sans',
        ]);

        return $pdf;
    }
}
