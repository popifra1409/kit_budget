<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * C2b — le geste de la corbeille : estamiller, entraîner, ressusciter.
 *
 * Le trait ne décide rien de ce qui meurt : il écrit le jeton du geste sur la ligne
 * qui vient de partir, puis balaie les enfants *déclarés*. Il suppose donc deux choses,
 * toutes deux vérifiées en C2a : la table porte `deleted_at` (sinon le `delete()` du
 * modèle resterait une suppression physique silencieuse), et elle porte `deleted_by` +
 * `corbeille_group` (sinon l'estampillage écrirait dans le vide).
 *
 * Trois choix de mécanique, chacun avec sa raison :
 *
 * 1. Tout se passe dans `deleted` et `restored`, jamais dans `deleting`/`restoring`. Un
 *    `deleting` garde une erreur (`BonCommande`, `Offre`, `FichePresence`…) et les hooks
 *    des traits se registrent AVANT le corps de `boot()` du modèle : balayer depuis
 *    `deleting` tuerait donc des lignes avant que le refus du parent ne soit connu, et
 *    le parent resterait vivant et vidé. En `deleted`, le refus a déjà eu lieu ; ce qui
 *    part est parti pour de bon.
 *
 * 2. `runSoftDelete()` n'écrit que `deleted_at` (et `updated_at`) : tout attribute sali
 *    dans un hook est jeté avec lui. L'estampillage est donc une `update()` explicite sur
 *    le query builder — une écriture en masse, pas un `save()`, pour ne pas rejouer les
 *    hooks `saving` du modèle (dont plusieurs refusent ou bornent la saisie).
 *
 * 3. Le geste entier passe dans une transaction. Sans elle, un enfant refusé en plein
 *    balayage (une pièce dont le fichier… un garde qui lance) laisserait le parent en
 *    corbeille, la moitié des lignes avec lui, et le refus affiché à l'écran.
 *
 * Restauration : les descendants reviennent par le jeton (`corbeille_group`), pas par
 * l'arborescence des relations. C'est exactement la décision Q2 : une ligne jetée à la
 * main avant son parent porte un autre jeton et doit rester en corbeille quand le parent
 * revient. Le jeton est effacé derrière soi — le geste est fini, une ligne vivante qui
 * porte encore `deleted_by` mentirait sur son compte.
 */
trait HasCorbeille
{
    protected static function bootHasCorbeille(): void
    {
        static::deleted(function ($record) {
            // forceDelete() : performDeleteOnModel() a remis $exists à false, la ligne
            // n'a plus rien à estampiller ni à entraîner.
            if (!$record->exists) {
                return;
            }

            $jeton = $record->corbeille_group ?: (string) Str::uuid();

            static::withTrashed()
                ->withoutGlobalScopes()
                ->whereKey($record->getKey())
                ->update([
                    'deleted_by' => auth()->id(),
                    'corbeille_group' => $jeton,
                ]);

            // Le jeton se relit sur l'INSTANCE au geste d'après, et `runSoftDelete()` n'écrit
            // que `deleted_at` : sans ce miroir, la ligne porte son jeton en base et rien en
            // mémoire, et `restored()` — qui remonte par le jeton — part d'un balayage vide.
            $record->corbeille_group = $jeton;
            $record->syncOriginalAttribute('corbeille_group');

            static::balayerEnfants($record, $jeton);
        });

        static::restored(function ($record) {
            if (!$record->corbeille_group) {
                // Ligne jetée avant la corbeille (deleted_by et jeton nulls) : rien à
                // ressusciter autour d'elle, elle revient telle quelle.
                return;
            }

            static::ressusciterDescendants($record->corbeille_group);

            static::withTrashed()
                ->withoutGlobalScopes()
                ->whereKey($record->getKey())
                ->update([
                    'deleted_by' => null,
                    'corbeille_group' => null,
                ]);
        });
    }

    /**
     * Relations `hasMany`/`morphMany` dont les lignes meurent avec le parent. À redéfinir
     * dans chaque modèle câblé. Un pivot `belongsToMany` n'a rien à y faire : la relation
     * renvoie l'autre bout, pas la ligne de jointure, et balayer « les dossiers d'un
     * fournisseur » effacerait les fournisseurs.
     */
    public static function corbeilleEnfants(): array
    {
        return [];
    }

    /**
     * Vrai pendant le `delete()` d'une ligne vivante qu'un ascendant emporte : le jeton a été
     * posé sur l'instance par `balayerEnfants()`, et la ligne n'est pas encore morte. Un garde
     * `deleting` qui protège une preuve (« une fiche visée ne se retire pas », « une note ne se
     * lève qu'après l'attribution ») doit s'effacer devant ce geste : ce n'est pas la preuve
     * qu'on retire, c'est tout le dossier, et il revient entier par le même jeton.
     *
     * Le second test n'est pas décoratif. Sur une ligne DÉJÀ en corbeille, `corbeille_group` est
     * rempli et la ligne est morte : la purge définitive, elle, doit rencontrer les gardes —
     * c'est le seul geste qui efface pour de bon.
     */
    public function entraineParUnAscendant(): bool
    {
        return $this->corbeille_group !== null
            && $this->{$this->getDeletedAtColumn()} === null;
    }

    /**
     * Emporter les enfants vivants, et par eux leurs petits-enfants : `$enfant->delete()`
     * plutôt qu'une écriture en masse, parce que c'est le hook `deleted` de l'enfant qui
     * estampille son jeton et balaie sa propre descendance. Le `whereNull` est obligatoire
     * et non décoratif : `withoutGlobalScopes()` décroche aussi la portée `SoftDeletes`,
     * donc une ligne déjà en corbeille reviendrait dans la course — et se verrait signer
     * par le geste d'un autre, sous un autre jeton.
     */
    protected static function balayerEnfants($parent, string $jeton): void
    {
        foreach (static::corbeilleEnfants() as $relation) {
            $query = $parent->{$relation}()->withoutGlobalScopes();

            $query->whereNull($query->getRelated()->getDeletedAtColumn())
                ->get()
                ->each(function ($enfant) use ($jeton) {
                    // Le jeton se propage, il ne se rempile pas : frappé vide, chaque enfant
                    // sortirait le SIEN et la fratrie n'aurait plus rien de commun à ressusciter
                    // — `restaurer()` remonte par le jeton du parent, pas par l'arborescence.
                    $enfant->corbeille_group = $jeton;
                    $enfant->delete();
                });
        }
    }

    /**
     * Ramener, pour un geste donné, tout ce qui porte son jeton — ligne par ligne, puis
     * récursivement dans la classe enfant (le jeton est le même pour les petits-enfants,
     * mais seul l'enfant connaît ses propres enfants déclarés). Écriture en masse : pas
     * d'événements, donc pas de `saving` garde qui refuserait de ressusciter une ligne,
     * et pas de boucle entre le parent et son enfant.
     */
    public static function ressusciterDescendants(string $jeton, array $dejà = []): void
    {
        if (in_array(static::class, $dejà, true)) {
            return;
        }

        $dejà[] = static::class;

        foreach (static::corbeilleEnfants() as $relation) {
            $enfant = (new static)->{$relation}()->getRelated();
            $childClass = get_class($enfant);

            $cles = $enfant->newQueryWithoutScopes()
                ->where('corbeille_group', $jeton)
                ->whereNotNull('deleted_at')
                ->pluck($enfant->getKeyName());

            if ($cles->isEmpty()) {
                continue;
            }

            $enfant->newQueryWithoutScopes()
                ->whereIn($enfant->getKeyName(), $cles)
                ->update([
                    'deleted_at' => null,
                    'deleted_by' => null,
                    'corbeille_group' => null,
                ]);

            $childClass::ressusciterDescendants($jeton, $dejà);
        }
    }

    /**
     * Le geste complet : estamper le parent, balayer ses enfants, le tout atomiquement.
     * `DeleteAction` de Filament et les `delete()` des services appellent cette méthode ;
     * `forceDelete()` y passe aussi (SoftDeletes l'appelle) et n'y écrit alors que du vide.
     */
    public function delete()
    {
        return DB::transaction(fn () => parent::delete());
    }
}
