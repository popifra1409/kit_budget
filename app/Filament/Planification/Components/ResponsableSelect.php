<?php

namespace App\Filament\Planification\Components;

use App\Models\Personnel;
use Filament\Forms\Components\Select;

/**
 * Le sélecteur d'agent responsable, le même sur les quatre niveaux de la hiérarchie.
 *
 * Programme, sous-programme, action et activité portent tous une colonne
 * `responsable_personnel_id` désignant un agent de `personnels`. Le composant est écrit une
 * fois ici parce que la règle délicate — celle qui distingue ce champ d'un simple
 * `->relationship('responsable', 'nom')` — est la même partout, et qu'elle ne se voit pas
 * quand elle manque :
 *
 *  - la liste est construite à la main sur `Personnel` et non par `relationship()`, parce que
 *    `Personnel` est en SoftDeletes et que la relation est déclarée `withTrashed()` : sans
 *    cela, un agent retiré déjà désigné disparaît des options et le champ affiche un
 *    identifiant nu ;
 *  - l'agent déjà posé est donc réinjecté dans les options même s'il est retiré, autrement un
 *    enregistrement sans clic sur ce champ l'effacerait silencieusement ;
 *  - le matricule accompagne le nom parce que `personnels` porte des homonymes (deux
 *    « WADJIRI VINCENT », dont un inactif) et qu'une désignation au nom seul est une
 *    désignation incertaine ;
 *  - le libellé dit « personnels, et non des comptes » : c'est précisément l'erreur que ces
 *    colonnes corrigeaient, quatre sélecteurs pointaient `users` et un agent sans accès
 *    n'était pas un choix possible.
 *
 * L'appelant garde la main sur `->label()` et `->required()` : le sous-programme exige un
 * responsable (tâche #25), les trois niveaux enfants non.
 */
class ResponsableSelect
{
    /** Le champ, prêt à être chaîné. La colonne s'appelle pareil sur les quatre tables. */
    public static function make(string $column = 'responsable_personnel_id'): Select
    {
        return Select::make($column)
            ->label('Responsable de mise en œuvre')
            ->placeholder('Aucun')
            ->searchable()
            ->preload()
            ->options(fn ($record) => static::options($record?->{$column}))
            ->helperText('Table des personnels, et non des comptes : un agent sans accès peut être responsable. C\'est lui qu\'impriment le cadre logique étendu et les états des actions.');
    }

    /**
     * Les agents en poste, plus celui déjà désigné s'il a été retiré depuis.
     *
     * @return array<int, string> id => « NOM Prénoms (matricule) »
     */
    public static function options(?int $dejaDésigné): array
    {
        return Personnel::withTrashed()
            ->where(fn ($q) => $q->whereNull('deleted_at')
                ->when($dejaDésigné, fn ($x) => $x->orWhere('id', $dejaDésigné)))
            ->orderBy('nom')
            ->orderBy('prenoms')
            ->get()
            ->mapWithKeys(fn (Personnel $agent) => [
                $agent->id => $agent->nom_complet.' ('.($agent->matricule ?: '—').')'
                    .($agent->trashed() ? ' — agent retiré' : ''),
            ])
            ->all();
    }
}
