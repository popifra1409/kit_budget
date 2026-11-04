<?php

namespace App\Filament\Marches\Resources\MarcheResource\Pages;

use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Le contingentement de la fiche : la règle de seuil garde la main tant que
 * « Classer contre le seuil » n'est pas coché, et la contradiction ne s'enregistre
 * qu'avec un justificatif qui dit quelque chose.
 *
 * Le reste du rangement est dans le modèle (hook saving) : vidage des colonnes de
 * la contradiction, auteur et date. C'est ce qui fait que semence, reprise en
 * direct ou fiche donnent la même trace.
 *
 * Les deux pages — création et édition — appliquent la même règle, d'où le trait :
 * une divergence entre les deux serait une porte de sortie pour un classement
 * manuel non justifié.
 */
trait GereLeClassementDuMarche
{
    protected function normaliserClassement(array $data): array
    {
        if (empty($data['classement_manuel'])) {
            return $data;
        }

        $justificatif = trim((string) ($data['classement_justificatif'] ?? ''));

        if ($justificatif === '') {
            // Le formulaire exige déjà le justificatif : ce garde-fu n'attrape que
            // les espaces seuls, que la contrainte de la base refuserait en SQL brut.
            // Notification en plus de l'erreur de champ — une exception de validation
            // dont la clé n'est pas dans le formulaire ferme la page sans rien dire.
            Notification::make()
                ->title('❌ Classement manuel refusé')
                ->body('Un classement contre le seuil s\'enregistre avec son justificatif : le marché reste classé par le seuil.')
                ->danger()->persistent()->send();

            throw ValidationException::withMessages([
                'classement_justificatif' =>
                    'Le justificatif est obligatoire pour un classement manuel — il sera lu par l\'audit.',
            ]);
        }

        $data['classement_justificatif'] = $justificatif;

        return $data;
    }
}
