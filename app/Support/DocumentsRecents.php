<?php

namespace App\Support;

use App\Models\ActivityLog;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Les documents qu'un agent a dernièrement traités, tels que le bandeau peut les
 * rouvrir : remis à la ressource du panneau courant, donc jamais un lien mort ni
 * un document que l'agent n'a plus le droit de voir.
 */
class DocumentsRecents
{
    /** Lignes du menu. */
    public const LIMITE = 8;

    /** Documents distincts candidats balayés dans le journal avant re-validation. */
    public const BALAYAGE = 40;

    /** Journaux sans document : une connexion n'est pas quelque chose qu'on rouvre. */
    public const JOURNAUX_EXCLUS = ['auth', 'security'];

    /** Ce qui fait l'étiquette d'un document, dans l'ordre. */
    public const ETIQUETTES = ['numero', 'code', 'nom', 'name', 'libelle', 'designation'];

    /** Carte modèle => ressource, une fois par panneau et par requête. */
    protected static array $cartes = [];

    /**
     * @return list<array{url: string, document: string, type: string, evenement: string, date: string}>
     */
    public static function lister(?int $agent = null): array
    {
        $agent = $agent ?? auth()->id();

        if ($agent === null) {
            return [];
        }

        $traces = static::tracesDuJournal($agent);

        if ($traces === []) {
            return [];
        }

        $carte = static::carteRessources();
        $evenements = static::evenementsDesTraces($traces);

        // Un document n'a d'existence dans le menu que s'il a un écran dans le panneau
        // courant : les lignes enfants (ligne_bon_commande…) sont éliminées ici, sans
        // liste noire, parce qu'aucune ressource ne les déclare comme leur modèle.
        // L'ordre d'insertion suit celui du journal, donc chaque classe est rencontrée
        // à la date de son document le plus récent.
        $parClasse = [];

        foreach ($traces as $trace) {
            $classe = Audit::classeDepuisType($trace->subject_type);

            if (!$classe || Audit::estExclu($classe) || !isset($carte[$classe])) {
                continue;
            }

            $trace->classe = $classe;
            $parClasse[$classe][(int) $trace->subject_id] = $trace;
        }

        $rangées = [];
        $ramifiees = [];

        foreach ($parClasse as $classe => $tracesParId) {
            $rangées = array_merge($rangées, static::rangéesDeLaClasse(
                $classe,
                $tracesParId,
                $carte[$classe],
                $evenements
            ));

            $ramifiees[] = $classe;

            // Ramifier une classe coûte des requêtes en plus du reste : s'arrêter dès que
            // le huitième rang ne peut plus être discuté évite de payer pour des documents
            // que le menu n'affichera pas.
            $plafond = static::plafondDesNonRamifiees($traces, $ramifiees);

            if ($plafond === null) {
                break;
            }

            $cutoff = static::dateDeCoupure($rangées);

            if ($cutoff !== null && $plafond <= $cutoff) {
                break;
            }
        }

        return array_map(static function (array $rangée): array {
            unset($rangée['tri']);

            return $rangée;
        }, static::top($rangées));
    }

    /**
     * Les documents d'un modèle qui passent la ressource : la requête de celle-ci
     * d'abord (exercice, structure, corbeille), la politique d'accès ensuite.
     *
     * @param  array<int, object>  $tracesParId
     * @param  class-string  $ressource
     * @return list<array{url: string, document: string, type: string, evenement: string, date: string, tri: string}>
     */
    protected static function rangéesDeLaClasse(string $classe, array $tracesParId, string $ressource, array $evenements): array
    {
        try {
            // La requête de la ressource est prise telle quelle, eager loads compris :
            // les relations que chargent les ressources sont précisément celles que leurs
            // politiques d'accès relisent. Les retirer faisait repartir chaque contrôle en
            // chargement individuel (17 requêtes de plus sur un compte régie mesuré).
            $modeles = $ressource::getEloquentQuery()
                ->whereKey(array_keys($tracesParId))
                ->get();
        } catch (\Throwable $e) {
            // Le bandeau ne doit jamais faire tomber la page : le modèle est consigné et sauté.
            static::signale('requête de ressource impossible', $classe, $e);

            return [];
        }

        $rangées = [];

        foreach ($modeles as $modele) {
            $trace = $tracesParId[$modele->getKey()] ?? null;

            if (!$trace || !$ressource::canView($modele)) {
                continue;
            }

            $url = static::urlDuDocument($ressource, $modele);

            if ($url === null) {
                continue;
            }

            $event = $evenements[$trace->derniere_ligne] ?? null;

            $rangées[] = [
                'url'       => $url,
                'document'  => static::numeroDuDocument($modele),
                'type'      => Audit::libelleModele($trace->subject_type) ?: class_basename($classe),
                'evenement' => $event ? (new ActivityLog(['event' => $event]))->getEventLabel() : '—',
                'date'      => static::date($trace->dernier),
                'tri'       => (string) $trace->dernier,
            ];
        }

        return $rangées;
    }

    /**
     * Une ligne par document : sa dernière activité dans le journal de l'agent.
     * Sans ce GROUP BY, une saisie en série (une DA vaut dix lignes) remplirait le
     * menu de répétitions au lieu de montrer dix documents.
     */
    protected static function tracesDuJournal(int $agent): array
    {
        try {
            return DB::table('activity_log')
                ->selectRaw('subject_type, subject_id, max(created_at) as dernier, max(id) as derniere_ligne')
                ->whereIn('causer_type', Audit::formesDuType(\App\Models\User::class))
                ->where('causer_id', $agent)
                ->whereNotNull('subject_type')
                ->whereNotNull('subject_id')
                ->whereNotIn('log_name', self::JOURNAUX_EXCLUS)
                ->groupBy('subject_type', 'subject_id')
                ->orderByDesc('dernier')
                ->limit(self::BALAYAGE)
                ->get()
                ->all();
        } catch (\Throwable $e) {
            static::signale('lecture du journal impossible', 'activity_log', $e);

            return [];
        }
    }

    /** Libellés d'événement des dernières lignes, en une seule requête. */
    protected static function evenementsDesTraces(array $traces): array
    {
        $ids = array_values(array_unique(array_filter(array_column($traces, 'derniere_ligne'))));

        if ($ids === []) {
            return [];
        }

        try {
            return ActivityLog::query()
                ->select(['id', 'event'])
                ->whereIn('id', $ids)
                ->pluck('event', 'id')
                ->all();
        } catch (\Throwable $e) {
            static::signale('libellés des événements indisponibles', 'activity_log', $e);

            return [];
        }
    }

    /**
     * modèle => ressource pour le panneau courant. Deux ressources peuvent porter le
     * même modèle (RegieAvance est servie par RegieAvanceResource et MenuDepenseResource) :
     * la plus proche du nom du modèle gagne, sinon l'ordre de découverte fait foi.
     *
     * @return array<class-string, class-string>
     */
    public static function carteRessources(): array
    {
        $panneau = Filament::getCurrentPanel()?->getId() ?? '—';

        if (isset(self::$cartes[$panneau])) {
            return self::$cartes[$panneau];
        }

        $parModele = [];

        try {
            foreach (Filament::getResources() as $ressource) {
                if (!is_string($ressource) || !class_exists($ressource)) {
                    continue;
                }

                try {
                    $modele = $ressource::getModel();
                } catch (\Throwable) {
                    continue;
                }

                if (!is_a($modele, Model::class, true)) {
                    continue;
                }

                $parModele[$modele][] = $ressource;
            }
        } catch (\Throwable $e) {
            static::signale('ressources du panneau inaccessibles', $panneau, $e);

            return self::$cartes[$panneau] = [];
        }

        $carte = [];

        foreach ($parModele as $modele => $ressources) {
            $choisie = $ressources[0];

            foreach ($ressources as $ressource) {
                if (str_replace('Resource', '', class_basename($ressource)) === class_basename($modele)) {
                    $choisie = $ressource;
                    break;
                }
            }

            $carte[$modele] = $choisie;
        }

        return self::$cartes[$panneau] = $carte;
    }

    /** Les LIMITE rangées les plus récentes, triées. */
    protected static function top(array $rangées): array
    {
        usort($rangées, fn(array $a, array $b): int => strcmp($b['tri'], $a['tri']));

        return array_slice($rangées, 0, self::LIMITE);
    }

    /** La date du huitième rang, ou null si le menu n'est pas encore plein. */
    protected static function dateDeCoupure(array $rangées): ?string
    {
        if (count($rangées) < self::LIMITE) {
            return null;
        }

        return static::top($rangées)[self::LIMITE - 1]['tri'];
    }

    /**
     * Ce que les modèles pas encore ramifiés pourraient proposer de mieux : la dernière
     * activité de leur trace la plus récente. Null quand tout a été ramifié.
     *
     * Les traces sans `classe` sont celles déjà écartées (aucun écran, modèle exclus) :
     * les compter comme plafond empêcherait tout arrêt anticipé.
     */
    protected static function plafondDesNonRamifiees(array $traces, array $ramifiees): ?string
    {
        foreach ($traces as $trace) {
            $classe = $trace->classe ?? null;

            if ($classe !== null && !in_array($classe, $ramifiees, true)) {
                return (string) $trace->dernier;
            }
        }

        return null;
    }

    /** Le numéro qui identifie le document ; à défaut, sa classe et sa clé. */
    public static function numeroDuDocument(Model $modele): string
    {
        foreach (self::ETIQUETTES as $colonne) {
            $valeur = $modele->getAttribute($colonne);

            if (filled($valeur) && is_scalar($valeur)) {
                return trim((string) $valeur);
            }
        }

        return class_basename($modele) . ' n°' . $modele->getKey();
    }

    /**
     * Écran auquel renvoyer le document : la fiche de consultation d'abord, l'édition
     * ensuite. Une ressource sans page ouvrable (liste seule) n'a rien à proposer ici.
     */
    protected static function urlDuDocument(string $ressource, Model $modele): ?string
    {
        foreach (['view', 'edit'] as $page) {
            try {
                $url = $ressource::getUrl($page, ['record' => $modele->getRouteKey()]);

                if (filled($url)) {
                    return $url;
                }
            } catch (\Throwable) {
                // Cette page n'existe pas pour cette ressource : c'est la réponse.
            }
        }

        return null;
    }

    /** Date absolue : « il y a 3 heures » ne se recoupe pas avec le journal. */
    protected static function date(?string $date): string
    {
        return $date === null ? '—' : date('d/m/Y H:i', strtotime((string) $date));
    }

    protected static function signale(string $quoi, string $ou, \Throwable $e): void
    {
        Log::warning("Documents récents : {$quoi}", [
            'endroit' => $ou,
            'erreur'  => $e->getMessage(),
        ]);
    }
}
