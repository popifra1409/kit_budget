<?php

namespace App\Filament\Marches\Resources\EvaluationResource\Pages;

use App\Models\ElementEvaluationDao;
use App\Models\Evaluation;
use App\Models\Marche;
use App\Models\Offre;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Les trois gardes d'une note avant écriture : la fenêtre du marché, l'étape du
 * référentiel, le barème — plus le doublon sur create.
 *
 * Le modèle les refuse déjà tous (hook saving de Evaluation, contrainte unique de la
 * table). Ce trait existe pour que le refus arrive sur le champ concerné, avec son
 * message à l'écran, au lieu d'une page d'erreur après la soumission — et pour que
 * le contrôle du doublon se lise comme « modifiez la note qui existe » plutôt que
 * comme une clef en conflit.
 *
 * Création et édition appliquent la même règle, d'où le trait : une divergence entre
 * les deux pages laisserait entrer une note hors barème par le chemin le plus commode.
 */
trait VerifieSaisieEvaluation
{
    protected function verifierSaisie(array $data, ?Model $record = null): array
    {
        $offreId   = (int) ($data['offre_id'] ?? $record?->offre_id ?? 0);
        $elementId = (int) ($data['element_evaluation_dao_id'] ?? $record?->element_evaluation_dao_id ?? 0);

        $offre = Offre::find($offreId);
        if (!$offre) {
            return $this->refuser('offre_id', "Choisissez l'offre qui est notée.");
        }

        $marche = $offre->marche;
        if (!$marche) {
            return $this->refuser('offre_id', "L'offre {$offre->numero} n'est rattachée à aucun marché.");
        }
        if (!$marche->peutNoterDesOffres()) {
            return $this->refuser('offre_id', sprintf(
                'Le marché %s est « %s » : les notes ne se portent qu\'après l\'ouverture des plis, et se '
                    . 'figent à l\'attribution — levez-la d\'abord.',
                $marche->numero,
                Marche::statutLibelle($marche->statut),
            ));
        }

        $element = ElementEvaluationDao::find($elementId);
        if (!$element) {
            return $this->refuser('element_evaluation_dao_id', "Choisissez l'étape d'évaluation du DAO qui est notée.");
        }
        if ($record === null && !$element->actif) {
            return $this->refuser('element_evaluation_dao_id', sprintf(
                '« %s » est inactive au paramétrage : elle ne se note plus. Désactivez-la de la grille '
                    . 'ou réactivez-la si elle doit de nouveau être jugée.',
                $element->libelle,
            ));
        }

        if (!array_key_exists('note', $data) || !is_numeric($data['note'])) {
            return $this->refuser('note', 'Une note d\'évaluation est un nombre.');
        }
        $note = (float) $data['note'];
        if ($note < 0) {
            return $this->refuser('note', 'Une note ne descend pas sous zéro : le barème commence là.');
        }
        if ($note > (float) $element->bareme_max) {
            return $this->refuser('note', sprintf(
                '« %s » se note au plus %s : %s est hors barème. Le barème se corrige au paramétrage '
                    . 'des éléments d\'évaluation, pas à la main sur une note.',
                $element->libelle,
                (float) $element->bareme_max,
                $note,
            ));
        }

        // Le couple (offre, étape) est fermé à l'édition : le doublon ne se contrôle
        // donc qu'à la création, et porte sur le champ qui le résout.
        if ($record === null) {
            $deja = Evaluation::query()
                ->where('offre_id', $offreId)
                ->where('element_evaluation_dao_id', $elementId)
                ->first();

            if ($deja) {
                return $this->refuser('element_evaluation_dao_id', sprintf(
                    '« %s » est déjà notée %s sur l\'offre %s (par %s le %s). Une offre ne reçoit qu\'une note '
                        . 'par étape : modifiez cette note-là.',
                    $element->libelle,
                    (float) $deja->note . '/' . (float) $element->bareme_max,
                    $offre->numero,
                    $deja->evaluateur?->name ?? '—',
                    $deja->date_evaluation?->format('d/m/Y') ?? '—',
                ));
            }
        }

        return $data;
    }

    /**
     * Erreur de champ + notification persistante : la notification est ce que l'on
     * voit même quand le formulaire ferme la fenêtre avant de faire défiler l'erreur.
     */
    protected function refuser(string $champ, string $message): array
    {
        Notification::make()
            ->title('❌ Note non enregistrée')
            ->body($message)
            ->danger()->persistent()->send();

        throw ValidationException::withMessages([$champ => $message]);
    }
}
