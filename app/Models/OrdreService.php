<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Ordre de service — étape E du module /marches.
 *
 * L'ordre de service est l'acte par lequel le maître d'ouvrage commande l'exécution
 * au titulaire : le démarrage, puis un arrêt ou une reprise. Ce n'est ni une
 * décision à trancher (l'offre, étape C) ni un choix à justifier (l'attribution,
 * étape D) : il est émis, daté, versé au dossier. D'où l'absence de machine à états
 * sur cette table — un ordre de service n'a pas de statut, il existe ou il a été
 * retiré.
 *
 * Le marché ne se modifie jamais ici. L'effet d'un ordre de service de démarrage —
 * passer d'« homologué » à « en cours d'exécution » — est porté par
 * `Marche::demarrerExecution()`, dont la condition est précisément l'existence de
 * cet ordre de service. Les deux écritures restent donc indépendantes et chacune
 * rattrapable : un ordre de service émis sans que le marché ait bougé se corrige par
 * l'action du marché, pas par une suppression ici.
 *
 * L'ordre de service qui a démarré le service ne se retire pas : il est le point de
 * départ de tout le délai d'exécution, et son absence ferait disparaître la date
 * d'effet du marché, de la fiche de suivi et de l'engagement (étape F).
 */
class OrdreService extends Model
{
    use SoftDeletes;

    protected $table = 'ordres_service';

    protected $fillable = [
        'numero',
        'marche_id',
        'type_ordre_service_id',
        'date_emission',
        'objet',
        'fichier_os',
        'emis_par_id',
        'observations',
    ];

    protected $casts = [
        'date_emission' => 'date',
    ];

    // ====================================
    // RELATIONS
    // ====================================

    /**
     * Le marché commandé, lu hors de la portée d'exercice : un ordre de service
     * notifié en janvier exécute le marché publié l'année précédente, et la portée
     * globale de HasExercice rendrait $this->marche null à la bascule — avec elle
     * la fenêtre d'émission et le retrait cesseraient de se vérifier.
     */
    public function marche(): BelongsTo
    {
        return $this->belongsTo(Marche::class)->withoutGlobalScope('exercice');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TypeOrdreService::class, 'type_ordre_service_id');
    }

    public function emetteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emis_par_id');
    }

    // ====================================
    // PRÉDICATS
    // ====================================

    /** Cet ordre de service est-il du type qui met le marché en exécution ? */
    public function demarreLexecution(): bool
    {
        return (bool) $this->type?->demarre_lexecution;
    }

    /**
     * Une date antérieure à l'homologation est une anomalie : l'ordre de service
     * exécute une attribution, il ne la précède pas.
     */
    public function dateAnterieureAHomologation(): bool
    {
        $homologation = $this->marche?->date_homologation;

        return $homologation !== null
            && $this->date_emission !== null
            && $this->date_emission->lt($homologation->toDateString());
    }

    // ====================================
    // SCOPES
    // ====================================

    public function scopeSurMarche(Builder $query, int $marcheId): Builder
    {
        return $query->where('marche_id', $marcheId);
    }

    /** Les ordres de service de démarrage, quel que soit le type sous ce nom. */
    public function scopeDemarrage(Builder $query): Builder
    {
        return $query->whereHas('type', fn (Builder $q) => $q->where('demarre_lexecution', true));
    }

    /** Émis depuis le début du jour — le mouvement du guichet, dans le badge du menu. */
    public function scopeEmisAujourdhui(Builder $query): Builder
    {
        return $query->whereDate('created_at', now()->toDateString());
    }

    // ====================================
    // BOOT
    // ====================================

    protected static function booted(): void
    {
        static::creating(function (OrdreService $os) {
            $os->numero      ??= static::genererNumero();
            $os->emis_par_id ??= auth()->id();

            // Les deux colonnes que l'acte exige : une date qui le situe et un objet
            // qui le commande. Sans elles, la ligne ne serait qu'une référence vide.
            if (!$os->date_emission) {
                throw new \Exception(
                    'Un ordre de service est daté : c\'est sa date qui ouvre le délai d\'exécution.'
                );
            }
            // blank() et non un test de vérité : «   » est vrai en PHP mais ne commande rien.
            if (blank($os->objet)) {
                throw new \Exception(
                    'Un ordre de service énonce le service commandé : sans objet, il ne notifie rien au titulaire.'
                );
            }

            $marche = $os->marche;
            if (!$marche) {
                throw new \Exception("L'ordre de service doit être rattaché à un marché.");
            }

            /*
             * La fenêtre : un marché n'obéit qu'après homologation, et jusqu'à ce
             * qu'il soit clos. Terminé, résilié ou annulé, ses papiers sont réglés —
             * un ordre de service écrit après coup ne commanderait rien.
             */
            if (!$marche->peutRecevoirDesOrdres()) {
                throw new \Exception(sprintf(
                    'Le marché %s est « %s » : les ordres de service s\'émettent sur un marché '
                        . 'homologué ou en cours d\'exécution.',
                    $marche->numero,
                    Marche::statutLibelle($marche->statut),
                ));
            }

            if (!$os->type) {
                throw new \Exception("L'ordre de service doit porter son type (démarrage, arrêt, reprise).");
            }
            if (!$os->type->actif) {
                throw new \Exception(sprintf(
                    '« %s » est inactive au paramétrage : elle ne s\'émet plus. Réactivez-la si elle '
                        . 'doit de nouveau servir, sinon choisissez un autre type.',
                    $os->type->libelle,
                ));
            }

            /*
             * Un marché ne démarre qu'une fois. La règle porte sur l'état du marché
             * plutôt que sur le seul comptage des lignes : un marché déjà en exécution
             * a nécessairement eu son ordre de service de démarrage, et le vérifier
             * ici tient même si la ligne de démarrage venait à être reprise ailleurs.
             */
            if ($os->demarreLexecution() && $marche->statut !== 'homologue') {
                throw new \Exception(sprintf(
                    'Le marché %s est déjà « %s » : un marché ne reçoit qu\'un seul ordre de service '
                        . 'de démarrage. Ce qui s\'émet ensuite, ce sont les arrêts et les reprises.',
                    $marche->numero,
                    Marche::statutLibelle($marche->statut),
                ));
            }

            // L'antériorité se juge ici et non à l'écran : la date est saisie, donc
            // elle peut être fausse, et un ordre de service antérieur à l'homologation
            // daterait le point de départ du délai d'exécution n'importe comment.
            if ($os->dateAnterieureAHomologation()) {
                throw new \Exception(sprintf(
                    'La date d\'émission (%s) est antérieure à l\'homologation du marché %s (%s) : '
                        . 'un ordre de service exécute une attribution, il ne la précède pas.',
                    $os->date_emission?->format('d/m/Y') ?? '—',
                    $marche->numero,
                    $marche->date_homologation->format('d/m/Y'),
                ));
            }
        });

        /*
         * L'ordre de service de démarrage est le point de départ du service : le
         * retirer ferait porter au marché un délai d'exécution sans acte qui l'ouvre.
         * La clé étrangère est `restrictOnDelete`, mais le retrait est en douceur —
         * c'est ici que le fait se défend. Ce qui se corrige, c'est son contenu
         * (date, objet, pièce jointe) tant que le marché n'est pas clos.
         */
        static::deleting(function (OrdreService $os) {
            if ($os->demarreLexecution()) {
                throw new \Exception(sprintf(
                    '« %s » est l\'ordre de service qui a ouvert l\'exécution du marché %s : il ne se '
                        . 'retire pas. Corrigez-en la date ou l\'objet, ou résiliez le marché.',
                    $os->numero,
                    $os->marche?->numero ?? 'un marché',
                ));
            }
        });
    }

    public static function genererNumero(): string
    {
        return DB::transaction(function () {
            $annee   = now()->year;
            $prefixe = "OS-{$annee}-";
            $dernier = static::withTrashed()
                ->where('numero', 'like', "{$prefixe}%")
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();
            $seq = $dernier ? intval(substr($dernier->numero, -4)) + 1 : 1;

            return $prefixe . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
        });
    }
}
