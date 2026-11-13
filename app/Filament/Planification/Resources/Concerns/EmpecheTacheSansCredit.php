<?php

namespace App\Filament\Planification\Resources\Concerns;

use Filament\Forms\Components\Radio;
use Illuminate\Database\Eloquent\Model;

/**
 * La règle « cette tâche ne financera rien », écrite une seule fois.
 *
 * Les deux écrans de saisie des tâches avaient chacun leur condition, et celle du
 * sous-onglet « Tâches » d'une activité rendait la nomenclature invisible pour une tâche
 * principale : cet écran ne pouvait produire AUCUNE tâche porteuse de crédit. Les 25 tâches
 * hors crédit de SP-410 viennent de là — pas d'une relation cassée, d'un formulaire muet.
 *
 * Depuis, une tâche principale sans compte et sans sous-tâche n'est plus enregistrable
 * sans que l'agent l'ait dit : le champ de confirmation nomme la situation, et l'état
 * imprimé lui rend sa ligne — vide de code de nomenclature — et la signale
 * (CadreLogiqueEtendu::anomalies()['sans_budget']).
 */
trait EmpecheTacheSansCredit
{
    /** Nom du champ de confirmation — virtuel, il n'est pas enregistré. */
    public const CHAMP_CONFIRMATION = 'confirme_tache_sans_credit';

    /**
     * La tâche, telle que le formulaire la décrit, ne portera aucun crédit : ni compte lié,
     * ni sous-tâche pour le porter. À la création, l'absence de sous-tâche est certaine
     * (aucun record) — c'est justement le cas à faire nommer.
     */
    protected static function tacheSansCredit(callable $get, ?Model $record): bool
    {
        return $get('niveau') === 'tache'
            && !filled($get('nomenclature_id'))
            && static::sansSousTaches($record);
    }

    /** Rien ne lui est rattaché pour porter le crédit à sa place (nouvelle tâche comprise). */
    protected static function sansSousTaches(?Model $record): bool
    {
        return $record === null || !$record->sousTaches()->exists();
    }

    /**
     * La tâche finance-t-elle elle-même, donc AE et CP se saisissent-ils ici ?
     * C'est aussi la condition sous laquelle le compte est obligatoire.
     */
    protected static function tacheDosee(callable $get, ?Model $record): bool
    {
        return $get('niveau') === 'sous_tache'
            || (filled($get('nomenclature_id')) && static::sansSousTaches($record));
    }

    /** Le champ qui refuse le silence. Requis seulement quand la tâche ne financera rien. */
    protected static function confirmationTacheSansCredit(): Radio
    {
        return Radio::make(self::CHAMP_CONFIRMATION)
            ->label('Cette tâche ne financera rien : quel est le motif ?')
            ->options([
                'sous_taches' => 'Ce sont ses sous-tâches qui porteront le crédit — je les ajoute maintenant.',
                'non_dosee'   => 'Aucun compte ne lui est encore affecté — la matrice rendra sa ligne, sans code de nomenclature.',
            ])
            ->required()
            ->live()
            // virtuel : il sert à la validation du formulaire, pas à la table des tâches
            ->dehydrated(false)
            ->helperText('Sans compte (nomenclature) et sans sous-tâche, la tâche a bien une ligne dans les '
                . 'états, mais aucun compte en face : son montant reste compté dans les totaux.')
            ->visible(fn(callable $get, ?Model $record = null) => static::tacheSansCredit($get, $record))
            ->columns(1)
            ->columnSpanFull();
    }
}
