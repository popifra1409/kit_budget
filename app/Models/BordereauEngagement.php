<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Role;
use App\Traits\HasExercice;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class BordereauEngagement extends Model
{
    use HasFactory, SoftDeletes, HasExercice, LogsActivity;

    protected $table = 'bordereaux_engagement';

    protected $fillable = [
        'exercice_id',
        'numero',
        'budget_id',
        'date_emission',
        'date_transmission',
        'exercice',
        'emis_par',
        'instance_destinataire',
        'detenu_par_id',
        'date_derniere_action',
        'jours_attente',
        'priorite',
        'objet',
        'montant_total',
        'nombre_engagements',
        'statut',
        'receptionne_par',
        'date_reception',
        'valide_par',
        'date_validation',
        'motif_rejet',
        'rejete_par',
        'date_rejet',
        'observations',
    ];

    protected $casts = [
        'date_emission' => 'date',
        'date_transmission' => 'date',
        'date_reception' => 'datetime',
        'date_validation' => 'datetime',
        'date_rejet' => 'datetime',
        'montant_total' => 'decimal:2',
        'nombre_engagements' => 'integer',
        'exercice' => 'integer',
    ];

    /**
     * Boot - Générer le numéro et calculer automatiquement
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($bordereau) {
            if (empty($bordereau->numero)) {
                $bordereau->numero = $bordereau->genererNumero();
            }

            if (empty($bordereau->exercice)) {
                $bordereau->exercice = now()->year;
            }

            if (empty($bordereau->date_emission)) {
                $bordereau->date_emission = now();
            }

            // Ajouter l'utilisateur connecté automatiquement
            if (empty($bordereau->emis_par)) {
                $bordereau->emis_par = auth()->id();
            }
        });
    }

    public function peutEtreTransmisPar(User $user): bool
    {
        return $this->statut === 'brouillon'
            && $user->can('bordereau.transmettre');
    }

    /**
     * Relation : Budget
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function exercice()
    {
        return $this->belongsTo(Exercice::class, "exercice_id");
    }

    /**
     * Relation : Émis par
     */
    public function emetteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emis_par');
    }

    /**
     * Relation : Réceptionné par
     */
    public function receptionniste(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receptionne_par');
    }

    /**
     * Relation : Validé par
     */
    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    /**
     * Relation : Rejeté par
     */
    public function rejeteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejete_par');
    }

    /**
     * Relation : Détenu par (utilisateur actuel qui traite le bordereau)
     */
    public function detenuPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'detenu_par_id');
    }

    /**
     * Relation : Lignes du bordereau (pivot)
     */
    public function lignes(): HasMany
    {
        return $this->hasMany(BordereauEngagementLigne::class, 'bordereau_id');
    }

    /**
     * Relation : Engagements (via pivot)
     */
    public function engagements(): BelongsToMany
    {
        return $this->belongsToMany(
            Engagement::class,
            'bordereau_engagement_lignes',
            'bordereau_id',
            'engagement_id'
        )->withPivot('numero_ligne', 'statut_ligne', 'motif_rejet', 'observations')
            ->withTimestamps();
    }

    /**
     * Relation : Mouvements (historique)
     */
    public function mouvements(): HasMany
    {
        return $this->hasMany(BordereauMouvement::class, 'bordereau_id')
            ->orderBy('date_action', 'desc');
    }

    /**
     * Scope : Par exercice
     */
    public function scopeExercice($query, $exercice)
    {
        return $query->where('exercice', $exercice);
    }

    /**
     * Scope : Par statut
     */
    public function scopeStatut($query, $statut)
    {
        return $query->where('statut', $statut);
    }

    /**
     * Scope : En attente (transmis ou en cours)
     */
    public function scopeEnAttente($query)
    {
        return $query->whereIn('statut', ['transmis', 'en_cours']);
    }

    /**
     * Générer le numéro de bordereau
     * Format: BDE-YYYY-XXXXX
     */
    public function genererNumero(): string
    {
        $annee = now()->year;
        $dernier = self::where('numero', 'like', "BDE-{$annee}-%")
            ->orderBy('numero', 'desc')
            ->first();

        if ($dernier) {
            $dernierNumero = intval(substr($dernier->numero, -5));
            $nouveauNumero = $dernierNumero + 1;
        } else {
            $nouveauNumero = 1;
        }

        return sprintf('BDE-%d-%05d', $annee, $nouveauNumero);
    }

    /**
     * Ajouter un engagement au bordereau
     */
    public function ajouterEngagement(Engagement $engagement, int $numeroLigne = null): void
    {
        if ($this->statut !== 'brouillon') {
            throw new \Exception("Impossible d'ajouter un engagement à un bordereau déjà transmis");
        }

        // ✅ Vérifier si l'engagement n'est pas déjà dans le bordereau
        if ($this->lignes()->where('engagement_id', $engagement->id)->exists()) {
            throw new \Exception("Cet engagement est déjà dans le bordereau");
        }

        // Calculer le numéro de ligne
        if ($numeroLigne === null) {
            $maxNumero = $this->lignes()->max('numero_ligne') ?? 0;
            $numeroLigne = $maxNumero + 1;
        }

        // ✅ Créer la ligne
        \App\Models\BordereauEngagementLigne::create([
            'bordereau_id' => $this->id,
            'engagement_id' => $engagement->id,
            'numero_ligne' => $numeroLigne,
            'statut_ligne' => 'en_attente',
        ]);

        // ✅ Recalculer les montants
        $this->recalculerMontants();
    }

    /**
     * Retirer un engagement du bordereau
     */
    public function retirerEngagement(Engagement $engagement): void
    {
        if ($this->statut !== 'brouillon') {
            throw new \Exception("Impossible de retirer un engagement d'un bordereau déjà transmis");
        }

        $this->lignes()->where('engagement_id', $engagement->id)->delete();
        $this->recalculerMontants();
    }

    /**
     * Recalculer les montants du bordereau
     */
    public function recalculerMontants(): void
    {
        // ✅ Compter le nombre de lignes
        $this->nombre_engagements = $this->lignes()->count();

        // ✅ Calculer le total en chargeant les engagements
        $total = 0;
        $this->load('lignes.engagement');

        foreach ($this->lignes as $ligne) {
            if ($ligne->engagement) {
                $total += $ligne->engagement->montant_engage;
            }
        }

        $this->montant_total = $total;
        $this->saveQuietly();
    }

    /**
     * Transmettre le bordereau à un utilisateur spécifique
     */
    public function transmettre(User $user, User $destinataire, ?string $observations = null): void
    {
        if ($this->statut !== 'brouillon') {
            throw new \LogicException('Seul un bordereau en brouillon peut être transmis.');
        }

        if (! $destinataire->hasAnyRole([
            'controleur_financier',
            'daaf',
            'directeur_general',
        ])) {
            throw new \LogicException('Destinataire non autorisé.');
        }

        $this->update([
            'statut'              => 'transmis',
            'detenu_par_id'       => $destinataire->id,
            'instance_destinataire' => $destinataire->roles->first()?->name,
        ]);

        $this->mouvements()->create([
            'action'        => 'transmission',
            'effectue_par'  => $user->id,
            'destinataire_id' => $destinataire->id,
            'commentaire'   => $observations,
        ]);
    }

    public function receptionner(User $user): void
    {
        if ($this->statut !== 'transmis') {
            throw new \Exception("Seul un bordereau transmis peut être réceptionné");
        }

        // Vérifier que c'est bien le destinataire
        if ($this->detenu_par_id !== $user->id) {
            throw new \Exception("Vous n'êtes pas le destinataire de ce bordereau");
        }

        \DB::beginTransaction();
        try {
            $this->statut = 'en_cours';
            $this->receptionne_par = $user->id;
            $this->date_reception = now();
            $this->date_derniere_action = now();
            $this->save();

            // Enregistrer le mouvement
            $this->enregistrerMouvement(
                'receptionne',
                $user,
                $this->instance_destinataire,
                null,
                "Bordereau réceptionné par {$user->name}"
            );

            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollBack();
            throw $e;
        }
    }

    /**
     * Valider le bordereau (toutes les lignes)
     */
    public function valider(User $user): void
    {
        if (!in_array($this->statut, ['en_cours', 'transmis'])) {
            throw new \Exception("Seul un bordereau en cours peut être validé");
        }

        \DB::beginTransaction();
        try {
            // Valider toutes les lignes
            $this->lignes()->update(['statut_ligne' => 'valide']);

            $this->statut = 'valide';
            $this->valide_par = $user->id;
            $this->date_validation = now();
            $this->date_derniere_action = now();
            $this->save();

            // Passer tous les engagements en définitif
            foreach ($this->engagements as $engagement) {
                $engagement->passerDefinitif($user);
            }

            // Enregistrer le mouvement
            $this->enregistrerMouvement(
                'valide',
                $user,
                null,
                null,
                "Bordereau validé - {$this->nombre_engagements} engagements approuvés"
            );

            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollBack();
            throw $e;
        }
    }

    /**
     * Rejeter le bordereau (total ou partiel)
     */
    public function rejeter(User $user, string $motif, array $engagementsRejetes = []): void
    {
        if (!in_array($this->statut, ['en_cours', 'transmis'])) {
            throw new \Exception("Seul un bordereau en cours peut être rejeté");
        }

        \DB::beginTransaction();
        try {
            // Si aucun engagement spécifié, rejeter tout
            if (empty($engagementsRejetes)) {
                $this->lignes()->update([
                    'statut_ligne' => 'rejete',
                    'motif_rejet' => $motif,
                ]);
                $this->statut = 'rejete_total';
            } else {
                // Rejet partiel
                foreach ($engagementsRejetes as $engagementId => $motifRejet) {
                    $this->lignes()
                        ->where('engagement_id', $engagementId)
                        ->update([
                            'statut_ligne' => 'rejete',
                            'motif_rejet' => $motifRejet,
                        ]);
                }
                $this->statut = 'rejete_partiel';
            }

            $this->motif_rejet = $motif;
            $this->rejete_par = $user->id;
            $this->date_rejet = now();
            $this->date_derniere_action = now();
            $this->save();

            // Enregistrer le mouvement
            $nbRejetes = count($engagementsRejetes) ?: $this->nombre_engagements;
            $this->enregistrerMouvement(
                'rejete',
                $user,
                null,
                null,
                "{$nbRejetes} engagement(s) rejeté(s) - Motif: {$motif}"
            );

            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollBack();
            throw $e;
        }
    }

    /**
     * Retourner le bordereau (pour corrections)
     */
    public function retourner(User $user, string $commentaire = ''): void
    {
        if (!in_array($this->statut, ['en_cours', 'rejete_partiel'])) {
            throw new \Exception("Impossible de retourner ce bordereau");
        }

        \DB::beginTransaction();
        try {
            $this->statut = 'retourne';
            $this->save();

            // Enregistrer le mouvement
            $this->enregistrerMouvement(
                'retourne',
                $user,
                $this->instance_destinataire,
                "Agent DAF",
                $commentaire ?: "Bordereau retourné pour corrections"
            );

            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollBack();
            throw $e;
        }
    }

    /**
     * Enregistrer un mouvement dans l'historique
     */
    protected function enregistrerMouvement(
        string $action,
        User $user,
        ?string $de = null,
        ?string $vers = null,
        ?string $commentaire = null
    ): void {
        BordereauMouvement::create([
            'bordereau_id' => $this->id,
            'action' => $action,
            'effectue_par' => $user->id,
            'date_action' => now(),
            'de' => $de,
            'vers' => $vers,
            'commentaire' => $commentaire,
        ]);
    }

    /**
     * Obtenir les statistiques du bordereau
     */
    public function getStatistiques(): array
    {
        $stats = $this->lignes()
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN statut_ligne = "en_attente" THEN 1 ELSE 0 END) as en_attente,
                SUM(CASE WHEN statut_ligne = "valide" THEN 1 ELSE 0 END) as valides,
                SUM(CASE WHEN statut_ligne = "rejete" THEN 1 ELSE 0 END) as rejetes,
                SUM(CASE WHEN statut_ligne = "annule" THEN 1 ELSE 0 END) as annules
            ')
            ->first();

        return [
            'total' => $stats->total ?? 0,
            'en_attente' => $stats->en_attente ?? 0,
            'valides' => $stats->valides ?? 0,
            'rejetes' => $stats->rejetes ?? 0,
            'annules' => $stats->annules ?? 0,
        ];
    }

    /**
     * Vérifier si le bordereau est modifiable
     */
    public function estModifiable(): bool
    {
        return $this->statut === 'brouillon';
    }

    /**
     * Calculer et mettre à jour les jours d'attente
     */
    public function calculerJoursAttente(): void
    {
        if ($this->date_reception) {
            $this->jours_attente = now()->diffInDays($this->date_reception);
            $this->save();
        }
    }

    /**
     * Scope : Bordereaux détenus par un utilisateur
     */
    public function scopeDetenuPar($query, $userId)
    {
        return $query->where('detenu_par_id', $userId);
    }

    /**
     * Scope : Bordereaux en retard (> 5 jours)
     */
    public function scopeEnRetard($query)
    {
        return $query->where('jours_attente', '>', 5)
            ->whereIn('statut', ['transmis', 'en_cours']);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['numero', 'budget_id', 'exercice_id', 'statut', 'date_bordereau', 'montant_total'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Bordereau {$eventName}");
    }
}
