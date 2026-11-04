<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Type d'ordre de service (paramétrage) — la cinquième ligne du scan 1/4, restée
 * à faire après l'étape A.
 *
 * Une ligne plate : un code, un libellé, un effet. `demarre_lexecution` est cet
 * effet — la seule règle que le référentiel porte, et la raison pour laquelle il
 * existe comme table plutôt que comme énumération dans le code : l'EP décide quels
 * types commandent le démarrage du service, et un semis ne doit pas deviner ses
 * libellés à lui.
 */
class TypeOrdreService extends Model
{
    protected $table = 'types_ordre_service';

    protected $fillable = [
        'code', 'libelle', 'description', 'demarre_lexecution', 'ordre', 'actif',
    ];

    protected $casts = [
        'demarre_lexecution' => 'boolean',
        'actif'              => 'boolean',
    ];

    public function scopeActives($query)
    {
        return $query->where('actif', true)->orderBy('ordre');
    }

    /** Les types proposés à l'émission : la liste vivante du référentiel. */
    public static function grille(): Collection
    {
        return static::actives()->get();
    }

    /**
     * Les ordres de service émis sous ce type — ce qui interdit de le supprimer.
     *
     * Retirés du dossier compris : une ligne d'arrêt de service que le service a
     * effacée a quand même été émise sous ce type, et la clé étrangère la retient
     * encore (`restrictOnDelete`). La compter comme absente laisserait supprimer le
     * type sur une page d'exception SQL.
     */
    public function ordresService(): HasMany
    {
        return $this->hasMany(OrdreService::class, 'type_ordre_service_id')->withTrashed();
    }

    protected static function booted(): void
    {
        /*
         * Un type qui a servi n'est plus une ligne du référentiel, c'est une pièce
         * de la trace des marchés. La clé étrangère `restrictOnDelete` le refuse en
         * base ; ce garde-fu le dit en français, et renvoie à la désactivation, qui
         * sort le type du jeu sans effacer les ordres de service émis sous lui.
         */
        static::deleting(function (TypeOrdreService $type) {
            if ($type->ordresService()->exists()) {
                throw new \Exception(sprintf(
                    '« %s » a déjà servi à émettre des ordres de service : désactivez-le plutôt '
                        . 'que de le supprimer, la fiche d\'exécution du marché s\'appuie sur cette ligne.',
                    $type->libelle,
                ));
            }
        });
    }
}
