<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Fiche de présence de la commission — étape E du module /marches.
 *
 * La fiche constate une séance : elle dit quelle commission s'est rassemblée, pour
 * quoi, ce jour-là, et qui était présent. Elle ne décide rien — le cycle du marché
 * reste dans `marches.statut` et se pilote dans `Marche`. C'est ce qui la distingue
 * de l'attribution (étape D) et de l'homologation (étape E 2/3) : personne n'a à
 * statuer sur une fiche de présence, elle est dressée, émargée, visée.
 *
 * Le visa est porté **par membre**, sur `fiche_presence_membres`, et non sur l'en-tête.
 * Une fiche de présence se signe case par case ; un visa unique dirait que quelqu'un
 * a répondu pour tous. Il n'y a donc pas de colonne `vise_par_id` ici, et le mot
 * « visa » ne se lit que sur les lignes.
 *
 * Les membres sont recopiés au dressage depuis le référentiel (nom, qualité,
 * téléphone). La fiche atteste une séance d'un jour donné : si elle lisait
 * `commission_membres` en direct, un nettoyage du paramétré le mois suivant
 * réécrirait qui siégeait — et une signature ne se corrige pas après coup.
 *
 * `present` est nullable volontairement : la case vide est un état, pas une absence.
 * C'est ce qui permet à l'écran de dire « il reste trois émargements à porter » au
 * lieu de rendre une fiche prétendument complète.
 */
class FichePresence extends Model
{
    use SoftDeletes;

    protected $table = 'fiches_presence';

    protected $fillable = [
        'numero',
        'marche_id',
        'commission_id',
        'date_seance',
        'lieu',
        'objet',
        'fichier',
        'etablie_par_id',
        'observations',
    ];

    protected $casts = [
        'date_seance' => 'date',
    ];

    /**
     * Les statuts sur lesquels une séance peut être dressée. Avant l'ouverture des
     * plis, la commission n'a rien à examiner : sa séance serait antérieure à la
     * matière qu'elle juge. Passé la clôture, ses papiers sont réglés et une fiche
     * ajoutée après coup ne constate plus rien.
     */
    public const STATUTS_SEANCE = ['en_evaluation', 'attribue', 'homologue', 'en_execution'];

    // ====================================
    // RELATIONS
    // ====================================

    /**
     * Le marché de la séance, lu hors de la portée d'exercice : une commission
     * réunie en janvier sur un DAO ouvert en décembre doit rester rattachée à son
     * marché, sinon les garde-fous du modèle ne se vérifient plus à la bascule.
     */
    public function marche(): BelongsTo
    {
        return $this->belongsTo(Marche::class)->withoutGlobalScope('exercice');
    }

    public function commission(): BelongsTo
    {
        return $this->belongsTo(Commission::class);
    }

    public function etabliePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'etablie_par_id');
    }

    public function membres(): HasMany
    {
        return $this->hasMany(FichePresenceMembre::class)->orderBy('ordre')->orderBy('id');
    }

    public function membresPresents(): HasMany
    {
        return $this->hasMany(FichePresenceMembre::class)->where('present', true);
    }

    public function membresVises(): HasMany
    {
        return $this->hasMany(FichePresenceMembre::class)->where('visa', true);
    }

    /** La ligne du président de séance, s'il a été recopié au dressage. */
    public function president(): HasOne
    {
        return $this->hasOne(FichePresenceMembre::class)->where('titre', 'president');
    }

    // ====================================
    // LECTURES DE LA FICHE
    // ====================================

    public function estEmargee(): bool
    {
        return $this->membres()->whereNull('present')->count() === 0;
    }

    public function casesVides(): int
    {
        return $this->membres()->whereNull('present')->count();
    }

    /**
     * Combien de présents n'ont pas encore visé : c'est le compte que l'agent veut
     * avant d'imprimer, pas le nombre de lignes.
     */
    public function visasManquants(): int
    {
        return $this->membres()->where('present', true)->where('visa', false)->count();
    }

    public function aUnVisa(): bool
    {
        return $this->membresVises()->exists();
    }

    /**
     * Ce qui manque à la fiche pour faire foi, dans l'ordre où l'agent le corrige.
     * Le texte est celui de l'écran : une fiche qui ne peut pas être imprimée doit
     * dire pourquoi, sinon la page d'à côté est un mystère.
     */
    public function attentes(): array
    {
        $attention = [];

        if ($this->membres()->count() === 0) {
            $attention[] = 'aucun membre sur la fiche : elle ne constate personne';
        }
        if ($this->casesVides() > 0) {
            $attention[] = $this->casesVides() . ' émargement(s) encore à porter (présent ou absent)';
        }
        if ($this->visasManquants() > 0) {
            $attention[] = $this->visasManquants() . ' présent(s) sans visa';
        }

        return $attention;
    }

    // ====================================
    // SCOPES
    // ====================================

    public function scopeSurMarche(Builder $query, int $marcheId): Builder
    {
        return $query->where('marche_id', $marcheId);
    }

    /**
     * Fiches qui attendent quelque chose : une case vide à émarger, ou un présent à
     * faire viser. C'est le badge du menu — le travail du guichet, pas l'historique.
     */
    public function scopeEnAttente(Builder $query): Builder
    {
        return $query->whereHas('membres', function (Builder $membres) {
            $membres->whereNull('present')
                ->orWhere(fn (Builder $ligne) => $ligne->where('present', true)->where('visa', false));
        });
    }

    public function scopeEtabliesAujourdhui(Builder $query): Builder
    {
        return $query->whereDate('created_at', now()->toDateString());
    }

    // ====================================
    // BOOT
    // ====================================

    protected static function booted(): void
    {
        static::creating(function (FichePresence $fiche) {
            $fiche->numero        ??= static::genererNumero();
            $fiche->etablie_par_id ??= auth()->id();

            $marche = $fiche->marche;
            if (!$marche) {
                throw new \Exception(
                    'La fiche de présence est dressée pour un marché : sans lui, elle ne constate aucune séance.'
                );
            }

            if (!in_array($marche->statut, self::STATUTS_SEANCE, true)) {
                throw new \Exception(sprintf(
                    'Le marché %s est « %s » : la fiche de présence se dresse à partir de l\'ouverture des '
                        . 'plis, et jusqu\'à ce que le marché soit clos.',
                    $marche->numero,
                    Marche::statutLibelle($marche->statut),
                ));
            }

            if (!$fiche->commission_id) {
                throw new \Exception(sprintf(
                    'Aucune commission épinglée au marché %s : la fiche ne peut pas dire qui siégeait. '
                        . 'Choisissez la commission sur la fiche du marché, puis dressez la fiche de présence.',
                    $marche->numero,
                ));
            }

            // Les trois conditions d'avant-fiche : la séance est passée, elle est datée,
            // et elle ne précède pas l'ouverture des plis qu'elle est censée constater.
            if (!$fiche->date_seance) {
                throw new \Exception('La fiche de présence est datée : c\'est le jour de la séance qu\'elle atteste.');
            }
            if ($fiche->date_seance->isFuture()) {
                throw new \Exception(sprintf(
                    'La séance du %s n\'a pas encore eu lieu : on ne dresse pas la fiche de présence d\'une '
                        . 'réunion future, on l\'émarge le jour dit.',
                    $fiche->date_seance->format('d/m/Y'),
                ));
            }
            if ($marche->date_ouverture_plis && $fiche->date_seance->lt($marche->date_ouverture_plis)) {
                throw new \Exception(sprintf(
                    'La séance serait antérieure à l\'ouverture des plis du marché %s (%s) : la commission '
                        . 'n\'a rien eu à examiner ce jour-là.',
                    $marche->numero,
                    $marche->date_ouverture_plis->format('d/m/Y'),
                ));
            }

            if (blank($fiche->objet)) {
                throw new \Exception(
                    'La fiche dit à quoi la séance était consacrée : sans objet, elle n\'atteste qu\'une salle comble.'
                );
            }

            static::refuserSeanceDejaDressee($marche->id, $fiche->date_seance->toDateString(), null);
        });

        static::updating(function (FichePresence $fiche) {
            // Le marché et la commission d'une fiche ne se reprennent plus : ils disent
            // de quoi la séance parle. Les déplacer serait attacher une signature à une
            // autre réunion. Ce qui se corrige, c'est la date, le lieu, l'objet et la pièce.
            if ($fiche->isDirty('marche_id')) {
                throw new \Exception(sprintf(
                    'La fiche %s constate une séance d\'un marché : on ne la déplace pas sur un autre. '
                        . 'Retirez-la et dressez-en une pour le marché concerné.',
                    $fiche->numero,
                ));
            }
            if ($fiche->isDirty('commission_id')) {
                throw new \Exception(sprintf(
                    'La commission qui s\'est rassemblée est celle de la séance : « %s » ne se remplace pas. '
                        . 'Si une autre commission a siégé, c\'est une autre séance, donc une autre fiche.',
                    $fiche->numero,
                ));
            }

            if ($fiche->isDirty('date_seance')) {
                $marche = $fiche->marche;
                if ($marche) {
                    static::refuserSeanceDejaDressee($marche->id, $fiche->date_seance->toDateString(), $fiche->getKey());
                }
            }
        });

        /*
         * Une fiche qui porte des visas est le papier sur lequel la commission a signé :
         * le retirer ferait disparaître l'attestation, pas l'erreur. Ce qui se corrige
         * alors, c'est la ligne — le visa se lève d'abord, et le retrait redevient
         * possible. Le marché clos ferme aussi le retrait : la fiche a servi.
         */
        static::deleting(function (FichePresence $fiche) {
            if ($fiche->aUnVisa()) {
                throw new \Exception(sprintf(
                    '« %s » porte %d visa(s) : une fiche visée ne se retire pas. Levez d\'abord les visas '
                        . 'ligne par ligne, puis retirez la fiche.',
                    $fiche->numero,
                    $fiche->membresVises()->count(),
                ));
            }

            $marche = $fiche->marche;
            if ($marche && $marche->estClo()) {
                throw new \Exception(sprintf(
                    'Le marché %s est « %s » : la fiche de présence a servi, elle reste au dossier.',
                    $marche->numero,
                    Marche::statutLibelle($marche->statut),
                ));
            }
        });
    }

    /**
     * Une séance d'un même jour pour un même marché ne se dresse pas deux fois. La
     * fiche retirée compte : son numéro reste et se restaure, plutôt que de laisser
     * deux feuilles datées le même jour dans le même dossier.
     */
    protected static function refuserSeanceDejaDressee(int $marcheId, string $dateSeance, ?int $excepteur): void
    {
        $deja = static::withTrashed()
            ->where('marche_id', $marcheId)
            ->whereDate('date_seance', $dateSeance)
            ->when($excepteur, fn ($query) => $query->whereKeyNot($excepteur))
            ->first();

        if (!$deja) {
            return;
        }

        throw new \Exception(sprintf(
            'La fiche %s existe déjà pour ce marché au %s%s : une séance d\'un jour ne se dresse pas deux '
                . 'fois. Corrigez-la par « Modifier », ou restaurez-la si elle avait été retirée.',
            $deja->numero,
            \Illuminate\Support\Carbon::parse($dateSeance)->format('d/m/Y'),
            $deja->trashed() ? ' (retirée)' : '',
        ));
    }

    public static function genererNumero(): string
    {
        return DB::transaction(function () {
            $annee   = now()->year;
            $prefixe = "FP-{$annee}-";
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
