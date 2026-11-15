<?php

namespace App\Filament\Planification\Resources\Concerns;

use App\Models\Action;
use App\Models\Programme;
use App\Models\SousProgrammeEp;
use Filament\Forms;

/**
 * Créer une action sous un sous-programme — la même saisie depuis deux écrans.
 *
 * L'onglet « Actions » de la fiche du sous-programme, et la ligne de ce sous-programme dans sa
 * liste (celle qu'ouvre le menu « Programmes » par « Sous-programmes »). Le partage n'est pas
 * une question de goût : une action se range sous le programme budgétaire du MINSANTE auquel le
 * sous-programme de l'EP est rattaché (`actions.programme_id`) et sur l'exercice de ce programme,
 * deux valeurs que le saisissant ne voit pas et ne choisit pas. Écrites une fois dans chaque
 * écran, elles divergent sans message — et l'action disparaît alors des états du sous-programme,
 * qui la retrouve par `sous_programmes_ep.code_programme_ep` : un code mal rangé ne se signale
 * pas, il s'absente.
 *
 * Le lien est « à travers » le programme de la tutelle, pas (que) une clé étrangère du
 * sous-programme : c'est pourquoi `programmeEp()` peut être `null` (sous-programme non rattaché) et
 * pourquoi la création refuse plutôt que d'écrire une action orpheline de toute hiérarchie.
 *
 * Depuis B2b, `actions` a sa propre clé de sous-programme, et B2c l'écrit ici même : `creer()` pose
 * `sous_programme_ep_id`, et `ranger()` est la même dérivée pour la FICHE de l'action, où le menu
 * déroulant ne propose plus un programme du MINSANTE mais un sous-programme de l'hôpital. Les deux
 * écrans restent une seule règle — `programmeDe()` est le seul endroit qui répond à « sous quel
 * programme du MINSANTE ce sous-programme de l'EP range-t-il ses actions ? ».
 */
class CreationActionSousProgramme
{
    /** Les trois champs que les deux écrans posent — ni plus : le reste se saisit à la fiche. */
    public static function champs(): array
    {
        return [
            Forms\Components\TextInput::make('code')->required()->maxLength(50),
            Forms\Components\TextInput::make('libelle')->required()->maxLength(255),
            Forms\Components\Textarea::make('description')->columnSpanFull(),
        ];
    }

    /**
     * Le sous-programme désigne le programme de l'EP et son exercice ; le saisissant, non.
     *
     * Le rattachement est posé à gauche du `+` : dans ce sens, une clé `programme_id` ou
     * `exercice_id` que l'appelant aurait quand même dans sa donnée est écrasée par la valeur du
     * sous-programme. L'écran ne peut pas produire ces clés (le formulaire ne déhydrate que ses
     * trois champs, contrôlé en C10 du harnais), mais la promesse est celle de cette méthode,
     * pas de l'écran.
     *
     * La dérivée est `programmeDe()`, la même que celle de la fiche : deux réponses à une seule
     * question, c'est deux écrans qui écrivent deux `programme_id` différents pour le même
     * sous-programme — la panne que cette classe existe d'empêcher.
     *
     * @throws \RuntimeException quand le sous-programme n'a aucun programme de la tutelle rattaché :
     *         il n'y a rien où ranger l'action, et cela se règle dans « Modifier » de cette ligne.
     */
    public static function creer(SousProgrammeEp $sousProgramme, array $data): Action
    {
        $programme = static::programmeDe($sousProgramme);

        return Action::create([
            'programme_id' => $programme->id,
            // B2c — la clé du pont. Omise, l'action créée d'ici ne serait visible d'AUCUNE des
            //       lectures qui descendent par `sous_programme_ep_id`, et `Action::saving()` ne
            //       peut rien signaler : un lien absent n'est pas un lien faux.
            'sous_programme_ep_id' => $sousProgramme->id,
            'exercice_id'  => $programme->exercice_id,
        ] + $data);
    }

    /**
     * Le programme du MINSANTE sous les actions duquel ce sous-programme range les siennes, et son
     * exercice : les deux valeurs que le saisissant ne choisit pas.
     *
     * La réponse est `programmeEp()`, pas `programmeBudgetaire`, et ce n'est pas un goût :
     * aujourd'hui encore, les lecteurs d'actions descendent par le code (`SousProgrammeEp::actions()`,
     * hasManyThrough sur `programmes.code = code_programme_ep`). Écrire le rattachement pendant que
     * les lecteurs suivent le code, c'est faire apparaître l'action nulle part — précisément la panne
     * que le témoin de l'Étape 5 contrôle en D1/D2, et qu'il a vue rouge à la première version de
     * cette méthode. Le lien neuf (`sous_programme_ep_id`) est écrit à côté, et B2c basculera les
     * lecteurs dessus : à ce moment-là, le rattachement deviendra la seule réponse et `programmeEp()`
     * s'éteindra avec `code_programme_ep` (B2e).
     *
     * Les deux voies de saisie des actions passent ici : le modal des écrans de sous-programme
     * (`creer()`) et la fiche de l'action (`ranger()`, avec le même message sous le champ).
     *
     * @throws \RuntimeException le sous-programme n'a rien où ranger une action — se règle dans
     *         « Modifier » de sa ligne, champ « Programme budgétaire du MINSANTE ».
     */
    public static function programmeDe(SousProgrammeEp $sousProgramme): Programme
    {
        $programme = $sousProgramme->programmeEp();

        if ($programme === null) {
            throw new \RuntimeException(
                "« {$sousProgramme->code} » n'est rattaché à aucun programme budgétaire du MINSANTE "
                . "de l'exercice actif. Modifiez ce sous-programme et choisissez d'abord son "
                . "« Programme budgétaire du MINSANTE »."
            );
        }

        return $programme;
    }

    /**
     * Ce que la fiche de l'action écrit à l'enregistrement : le sous-programme choisi à l'écran
     * range l'action, et `programme_id` n'est plus une colonne que le saisissant choisit.
     *
     * Dériver ici plutôt que de laisser le modèle corriger : `Action::saving()` refuse, il ne
     * réécrit pas — un garde qui corrige fait croire à l'écran que la saisie a été enregistrée
     * alors qu'elle a été changée. Ici le changement EST la règle, et la valeur annoncée sous le
     * menu est celle qui sera écrite.
     *
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function ranger(array $data): array
    {
        $id = $data['sous_programme_ep_id'] ?? null;

        if ($id === null || $id === '') {
            // Rien à dériver. Le champ est `required()` à l'écran ; une action sans sous-programme
            // garde le `programme_id` qu'elle a en base, et le garde du modèle ne peut pas être
            // contredit puisqu'aucun lien n'est posé.
            return $data;
        }

        $sousProgramme = SousProgrammeEp::withTrashed()->findOrFail((int) $id);

        $data['sous_programme_ep_id'] = (int) $id;
        $data['programme_id'] = static::programmeDe($sousProgramme)->id;

        return $data;
    }
}
