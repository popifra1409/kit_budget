<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne de fiche de présence — un membre, sa qualité, son émargement et son visa.
 *
 * La ligne est une **copie** faite au dressage : nom, qualité et téléphone viennent
 * du référentiel de la commission et restent écrits ici. C'est ce qui permet à une
 * fiche signée en mars de se relire en septembre avec les mêmes mots, alors que la
 * commission a changé entre-temps. `commission_membre_id` et `user_id` gardent la
 * provenance ; ils ne servent pas à relire le paramétrage.
 *
 * Trois faits se distinguent sur cette table, et ils ne se confondent pas à l'écran :
 *
 * - `present` : la case est cochée, ou l'absence est motivée. Vide = pas encore porté.
 * - `visa` : le membre a signé la fiche. Il ne se pose que par `viser()`, avec la main
 *   et l'horodatage dans le journal — une case `visa` retournée par une saisie libre
 *   ferait signer quelqu'un sans qu'on sache qui a coché.
 * - la ligne elle-même, qui peut être ajoutée pour un membre que le référentiel ne
 *   connaît pas : une commission siège aussi des personnes convoquées pour la séance.
 *
 * `present` à `false` n'est pas une valeur par défaut : l'absence s'écrit, avec son
 * motif, et la contrainte de la migration 000013 ferme la ligne muette comme le visa
 * posé sur une absence.
 */
class FichePresenceMembre extends Model
{
    protected $table = 'fiche_presence_membres';

    protected $fillable = [
        'fiche_presence_id',
        'commission_membre_id',
        'user_id',
        'nom',
        'titre',
        'telephone',
        'ordre',
        'present',
        'motif_absence',
    ];

    protected $casts = [
        'present'   => 'boolean',
        'visa'      => 'boolean',
        'date_visa' => 'datetime',
    ];

    /**
     * Marker interne : `viser()` et `leverVisa()` l'allument avant d'enregistrer.
     * Il n'est ni fillable ni saissable — sans lui, rien ne distinguerait l'acte
     * d'une retouche de la colonne par un autre chemin.
     */
    public bool $enActeVisa = false;

    // ====================================
    // RELATIONS
    // ====================================

    public function fiche(): BelongsTo
    {
        return $this->belongsTo(FichePresence::class, 'fiche_presence_id');
    }

    public function commissionMembre(): BelongsTo
    {
        return $this->belongsTo(CommissionMembre::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ====================================
    // LECTURES
    // ====================================

    public function estVisee(): bool
    {
        return (bool) $this->visa;
    }

    /** La case est-elle portée à la fiche, dans un sens ou dans l'autre ? */
    public function estEmargee(): bool
    {
        return $this->present !== null;
    }

    /**
     * Ce qui empêche de poser le visa, énoncé en français d'écran. La liste est vide
     * quand la ligne est visable : l'appelant la affiche telle quelle, sans la
     * traduire en booléen, parce que le refus doit se lire.
     *
     * @return string[]
     */
    public function motifsEmpchantLeVisa(): array
    {
        $empechements = [];

        if ($this->estVisee()) {
            $empechements[] = 'a déjà visé cette fiche le ' . $this->date_visa?->format('d/m/Y H:i');
        }
        if ($this->present !== true) {
            $empechements[] = $this->estEmargee()
                ? 'est portée absente — on ne vise pas ce qu\'on n\'a pas signé'
                : 'n\'est pas encore portée à la fiche (présent ou absent)';
        }

        return $empechements;
    }

    // ====================================
    // ACTES
    // ====================================

    /**
     * Le visa du membre : la case que le papier porte, recopiée ici avec sa main et
     * son horodatage. La date du visa est celle de l'enregistrement — c'est le geste
     * que l'on date, pas celui que l'on recopie.
     */
    public function viser(User $user): void
    {
        if ($empechements = $this->motifsEmpchantLeVisa()) {
            throw new \Exception(sprintf(
                '« %s » (%s) ne peut pas viser la fiche %s : %s.',
                $this->nom,
                config('marches.titres_membre')[$this->titre] ?? $this->titre,
                $this->fiche?->numero ?? '—',
                implode(' ; ', $empechements),
            ));
        }

        $this->enActeVisa = true;
        $this->visa       = true;
        $this->date_visa  = now();
        $this->save();
        $this->enActeVisa = false;

        ActivityLog::logAction($this, 'viser_membre', [
            'nom'  => $this->nom,
            'titre' => $this->titre,
            'par'  => $user->name,
        ]);
    }

    /**
     * Le visa se lève par le même chemin qui l'a posé — et lui seul. La date part avec
     * le visa : une `date_visa` sans visa ne raconterait plus rien, et le couple se
     * lit ensemble ou pas du tout.
     */
    public function leverVisa(User $user): void
    {
        if (!$this->estVisee()) {
            throw new \Exception(sprintf(
                '« %s » n\'a pas visé la fiche %s : il n\'y a rien à lever.',
                $this->nom,
                $this->fiche?->numero ?? '—',
            ));
        }

        $this->enActeVisa = true;
        $this->visa       = false;
        $this->date_visa  = null;
        $this->save();
        $this->enActeVisa = false;

        ActivityLog::logAction($this, 'lever_visa_membre', [
            'nom'  => $this->nom,
            'par'  => $user->name,
        ]);
    }

    // ====================================
    // BOOT
    // ====================================

    protected static function booted(): void
    {
        static::creating(function (FichePresenceMembre $ligne) {
            // Une ligne ajoutée après le dressage (un membre convoqué) se range au bout
            // de la feuille : l'ordre du référentiel, lui, est passé tel quel.
            if ($ligne->ordre === null) {
                $ligne->ordre = (int) static::where('fiche_presence_id', $ligne->fiche_presence_id)->max('ordre') + 1;
            }

            if (blank($ligne->nom)) {
                throw new \Exception(
                    'Un membre de la fiche est nommément écrit : sans nom, la case de visa n\'attache à personne.'
                );
            }
            if (!$ligne->titre || !array_key_exists($ligne->titre, config('marches.titres_membre'))) {
                throw new \Exception(sprintf(
                    '« %s » porte une qualité que le paramétrage ne connaît pas. La qualité fait autorité '
                        . 'pour l\'ordre des signatures — choisissez-la dans la liste.',
                    $ligne->nom ?: 'un membre',
                ));
            }

            // À la création, le visa n'est pas encore posé : il viendra par son acte.
            if ($ligne->visa || $ligne->date_visa) {
                throw new \Exception(sprintf(
                    'Le visa de « %s » ne se saisit pas à la création de la ligne : il se pose par l\'action '
                        . '« Viser », qui en garde la main et la date au journal.',
                    $ligne->nom,
                ));
            }

            static::refuserAbsenceMuette($ligne);
        });

        static::updating(function (FichePresenceMembre $ligne) {
            $toucheAuVisa = $ligne->isDirty('visa') || $ligne->isDirty('date_visa');

            if ($toucheAuVisa && !$ligne->enActeVisa) {
                throw new \Exception(sprintf(
                    'Le visa de « %s » ne se retourne pas par la fiche du membre : il se pose par « Viser » '
                        . 'et se lève par « Lever le visa », qui gardent la main et la date au journal.',
                    $ligne->nom,
                ));
            }

            if ($ligne->enActeVisa) {
                // L'acte ne meut que ses deux colonnes ; tout le reste a été validé avant.
                return;
            }

            // Une ligne visée a produit son attestation : son émargement et l'identité
            // du membre ne se réécrivent plus. Ce qui se corrige encore, c'est l'avis
            // du membre lui-même — par la levée du visa, qui est un acte nommé.
            if ($ligne->visa && $ligne->isDirty(['present', 'motif_absence', 'nom', 'titre', 'telephone'])) {
                throw new \Exception(sprintf(
                    '« %s » a visé cette fiche : son émargement ne se retourne plus. Levez le visa d\'abord — '
                        . 'la levée est une écriture à son tour, et elle reste au journal.',
                    $ligne->nom,
                ));
            }

            static::refuserAbsenceMuette($ligne);
        });

        /*
         * Un visa retiré par la suppression de la ligne serait un retrait sans main :
         * la ligne part, l'attestation aussi, et le journal ne montre qu'une
         * suppression. Le visa se lève d'abord — c'est l'acte qui dit qui défait quoi.
         */
        static::deleting(function (FichePresenceMembre $ligne) {
            if ($ligne->visa) {
                throw new \Exception(sprintf(
                    '« %s » a visé la fiche : sa ligne ne se retire pas. Levez le visa, puis retirez la ligne '
                        . 'si le membre n\'a pas siégé.',
                    $ligne->nom,
                ));
            }
        });
    }

    /**
     * L'absence s'écrit avec son motif. La contrainte de la migration le tient en
     * base, ce message le tient à l'écran.
     */
    protected static function refuserAbsenceMuette(FichePresenceMembre $ligne): void
    {
        if ($ligne->present === false && blank($ligne->motif_absence)) {
            throw new \Exception(sprintf(
                '« %s » est portée absente sans motif : une fiche qui dit que quelqu\'un n\'est pas venu '
                    . 'sans dire pourquoi ne se relit pas devant un recours.',
                    $ligne->nom,
            ));
        }
    }
}
