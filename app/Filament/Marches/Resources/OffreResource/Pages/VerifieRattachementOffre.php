<?php

namespace App\Filament\Marches\Resources\OffreResource\Pages;

use App\Models\Fournisseur;
use App\Models\Marche;
use App\Models\Offre;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Les deux gardes d'enregistrement d'une offre : le marché doit encore accueillir
 * les offres, et un même soumissionnaire ne dépose qu'une offre par marché.
 *
 * Le modèle refuse déjà les deux (hook creating pour la fenêtre du marché, contrainte
 * unique partielle de la table pour le doublon). Le trait sert à ce que le refus
 * arrive sur le champ concerné, avec son message à l'écran, au lieu d'une page
 * d'erreur après la soumission — et à ce que le contrôle du doublon, côté édition,
 * exclue l'offre en main : sans cette exclusion, la page se refuserait elle-même.
 *
 * La règle « une offre par soumissionnaire et par marché » est une règle de la
 * commande publique : deux offres du même candidat dans le classement fausseraient
 * l'évaluation, et la redondance se corrige en modifiant l'offre existante, pas en
 * ajoutant une ligne.
 *
 * Création et édition appliquent la même règle, d'où le trait : une divergence
 * entre les deux pages laisserait passer le doublon par le chemin le plus commode.
 */
trait VerifieRattachementOffre
{
    /**
     * $record nul = dépôt nouveau-né, la fenêtre du marché se contrôle. Sinon la
     * fiche corrige un dépôt déjà constant : le marché a pu avancer depuis, ou
     * relever d'un exercice que la session ne filtre plus, ce n'est pas une raison
     * pour ne plus pouvoir réparer la quittance.
     */
    protected function verifierRattachement(array $data, ?Model $record = null): array
    {
        $marcheId      = (int) ($data['marche_id'] ?? $record?->marche_id ?? 0);
        $fournisseurId = (int) ($data['fournisseur_id'] ?? $record?->fournisseur_id ?? 0);

        // sansExercice() : la fenêtre du marché se juge sur le marché, pas sur
        // l'exercice que la session a sélectionné (voir Offre::marche()).
        $marche = Marche::sansExercice()->find($marcheId);

        if ($record === null) {
            if (!$marche) {
                return $this->refuser('marche_id', "Choisissez le marché sur lequel l'offre est déposée.");
            }
            if (!$marche->peutRecevoirDesOffres()) {
                return $this->refuser('marche_id', sprintf(
                    "Le marché %s est « %s » : il n'accueille plus les offres.",
                    $marche->numero,
                    Marche::statutLibelle($marche->statut),
                ));
            }
        }

        $doublon = Offre::query()
            ->where('marche_id', $marcheId)
            ->where('fournisseur_id', $fournisseurId)
            ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
            ->first();

        if ($doublon) {
            return $this->refuser('fournisseur_id', sprintf(
                '%s a déjà déposé une offre sur le marché %s (%s). Un même soumissionnaire ne présente '
                    . "qu'une offre par appel d'offres : modifiez cette offre-là.",
                Fournisseur::find($fournisseurId)?->raison_sociale ?? "#{$fournisseurId}",
                $marche?->numero ?? "#{$marcheId}",
                $doublon->numero,
            ));
        }

        return $data;
    }

    /**
     * Erreur de champ + notification persistante : la notification est ce que l'on
     * voit même si le formulaire ferme la fenêtre avant de faire défiler l'erreur.
     */
    protected function refuser(string $champ, string $message): array
    {
        Notification::make()
            ->title("❌ Offre non enregistrée")
            ->body($message)
            ->danger()->persistent()->send();

        throw ValidationException::withMessages([$champ => $message]);
    }
}
