<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

class ActivityLog extends SpatieActivity
{
    // =========================================================
    // CAPTURE AUTOMATIQUE DU CONTEXTE
    // =========================================================
    protected static function booted(): void
    {
        static::creating(function (self $activity) {
            $activity->ip_address = $activity->ip_address ?? request()?->ip();
            $activity->user_agent = $activity->user_agent ?? request()?->userAgent();

            // ✅ Contexte HTTP enrichi dans les properties
            $extra = [];

            if (request()) {
                $extra['url']        = request()->fullUrl();
                $extra['method']     = request()->method();
                $extra['route']      = request()->route()?->getName();
                $extra['session_id'] = session()?->getId();
            }

            // Fusionner avec les properties existantes
            if (!empty($extra)) {
                $props = $activity->properties?->toArray() ?? [];
                $props['_context'] = $extra;
                $activity->properties = collect($props);
            }
        });
    }

    // =========================================================
    // MÉTHODES STATIQUES — Logging des actions workflow
    //
    // Appelez ces méthodes dans vos modèles pour tracer
    // toutes les actions métier au-delà du CRUD de base.
    //
    // Exemples :
    //   ActivityLog::logAction($bonCommande, 'valider', ['ancien_statut' => 'brouillon']);
    //   ActivityLog::logAction($da, 'engager', ['montant' => 1500000, 'nomenclature' => 'XX']);
    // =========================================================

    public static function logAction(
        $subject,
        string $action,
        array  $details    = [],
        string $logName    = 'workflow'
    ): void {
        try {
            $props = [
                'action'  => $action,
                'details' => $details,
            ];

            // Capturer le statut avant/après si disponible
            if (isset($details['ancien_statut']) || isset($details['nouveau_statut'])) {
                $props['transition'] = ($details['ancien_statut'] ?? '?')
                    . ' → ' . ($details['nouveau_statut'] ?? '?');
            }

            activity($logName)
                ->performedOn($subject)
                ->causedBy(auth()->user())
                ->withProperties($props)
                ->event($action)
                ->log(static::_getActionLabel($action, $subject));
        } catch (\Exception $e) {
            // Silencieux — le log ne doit jamais bloquer l'action
            \Log::warning("ActivityLog::logAction échoué : " . $e->getMessage());
        }
    }

    // =========================================================
    // Logging spécialisé — Authentification
    // =========================================================
    public static function logAuth(string $event, \App\Models\User $user): void
    {
        try {
            activity('auth')
                ->causedBy($user)
                ->withProperties([
                    'email'      => $user->email,
                    'roles'      => $user->getRoleNames()->toArray(),
                    'ip'         => request()?->ip(),
                    'user_agent' => request()?->userAgent(),
                ])
                ->event($event)
                ->log($event === 'login' ? 'Connexion de ' . $user->name : 'Déconnexion de ' . $user->name);
        } catch (\Exception $e) {
            \Log::warning("ActivityLog::logAuth échoué : " . $e->getMessage());
        }
    }

    // =========================================================
    // Logging spécialisé — Accès refusé (sécurité)
    // =========================================================
    public static function logAccesRefuse(
        string  $action,
        ?string $resource = null,
        ?int    $resourceId = null,
        array   $extra = []
    ): void {
        try {
            activity('security')
                ->causedBy(auth()->user())
                ->withProperties(array_merge([
                    'action'      => $action,
                    'resource'    => $resource,
                    'resource_id' => $resourceId,
                    'url'         => request()?->fullUrl(),
                    'ip'          => request()?->ip(),
                ], $extra))
                ->event('access_denied')
                ->log("Accès refusé : {$action}" . ($resource ? " sur {$resource}" : ''));
        } catch (\Exception $e) {
            \Log::warning("ActivityLog::logAccesRefuse échoué : " . $e->getMessage());
        }
    }

    /**
     * Journaliser le refus que le logiciel vient d'opposer.
     *
     * Les 600-garde-fous `->visible()` de l'interface ne sont pas des refus : un bouton
     * caché n'atteint jamais le serveur, et le tracer noierait le journal sous des
     * intentions que personne n'a exprimées. Ici on ne trace que ce qui est réellement
     * dit « non » : le 403 d'une ressource, d'une page ou d'une action Filament refusée,
     * et le `abort(403)` des contrôleurs — exports, impressions, fiches de contrôle.
     *
     * Le point d'entrée est `withExceptions()->renderable()`, pas `reportable()` :
     * `AuthorizationException` et `HttpException` sont dans `$internalDontReport` du
     * framework, donc `reportable()` ne les voit jamais passer.
     */
    public static function journaliserRefusOppose(\Throwable $e): void
    {
        $refus = $e instanceof AuthorizationException
            || ($e instanceof HttpException && $e->getStatusCode() === 403);

        if (!$refus) {
            return;
        }

        // Le journal ne doit jamais transformer un 403 en 500 : cette méthode est appelée
        // depuis le gestionnaire d'exceptions lui-même, elle avale donc \Throwable et pas
        // seulement \Exception — un Error y serait sinon devenu une erreur applicative.
        try {
            // prepareException() remplace AuthorizationException par une HttpException
            // 403 : la classe d'origine ne survit que dans le previous.
            $source   = $e->getPrevious() ?: $e;
            $route    = request()?->route();
            $document = null;

            // Un 403 sur un document se relit par ce document : Filament a déjà résolu le
            // paramètre de route, c'est la seule trace de la cible qui vaille.
            foreach ($route?->parameters() ?? [] as $parametre) {
                if ($parametre instanceof Model) {
                    $document = $parametre;
                    break;
                }
            }

            $action = $route?->getName()
                ?: trim((string) preg_replace('#/\d+$#', '', (string) request()?->path()), '/');

            static::logAccesRefuse(
                $action !== '' ? $action : 'route inconnue',
                $document ? class_basename($document) : null,
                $document ? (int) $document->getKey() : null,
                [
                    'exception' => class_basename($source),
                    'motif'     => mb_substr($e->getMessage(), 0, 190),
                ]
            );
        } catch (\Throwable $erreur) {
            \Log::warning('ActivityLog::journaliserRefusOppose échoué : ' . $erreur->getMessage());
        }
    }

    // =========================================================
    // Logging spécialisé — droits d'un rôle (écran Rôles)
    // =========================================================

    /**
     * Journalise l'enregistrement d'un rôle avec le détail des cases cochées et décochées.
     *
     * L'écran écrivait déjà « Modification du rôle X » avec un compteur de permissions :
     * il répondait à « combien de droits ce rôle en a-t-il maintenant », pas à « qui a reçu
     * quel droit, et qui l'a perdu » — c'est pourtant la seconde question qu'on pose dans un
     * contrôle, et elle est restée hors du journal (charte §5.3).
     *
     * L'appelant fournit l'état d'avant, lu avant que le formulaire ne synchrone le pivot :
     * `CheckboxList::relationship()` déhydrate son état, la liste des permissions ne passe
     * donc jamais par `$data`, et après coup il n'y a plus rien à comparer. L'état d'après se
     * relit en base, jamais dans l'instance : c'est la synchronisation du pivot qui fait foi.
     *
     * Aucun diff, aucune écriture : cocher puis décocher avant d'enregistrer laisse le journal
     * propre, et une simple correction du niveau hiérarchique ne se déguise pas en campagne
     * de droits.
     *
     * @param Model $role             le rôle concerné
     * @param array $permissionsAvant noms de permissions, avant la synchronisation du pivot
     * @param array $extra            propriétés propres à l'écran (niveau, porteurs…)
     * @param bool  $nouveau          rôle qui naît ici : toute la liste est un accord
     */
    public static function journaliserDroitsDeRole(
        Model $role,
        array $permissionsAvant,
        array $extra = [],
        bool  $nouveau = false,
    ): void {
        try {
            $apres = $role->permissions()->pluck('permissions.name')->all();
            $noms  = $permissionsAvant;

            // Listes triées : l'ordre du pivot ne doit pas imposer sa logique à la lecture
            // du journal, et une comparaison d'une écriture à l'autre demande du stable.
            $tri      = function (array $liste): array {
                sort($liste);

                return array_values($liste);
            };
            $ajoutees = $tri(array_diff($apres, $noms));
            $retirees = $tri(array_diff($noms, $apres));
            $noms     = $tri($noms);

            $activite = activity()
                ->performedOn($role)
                ->causedBy(auth()->user())
                ->withProperties($extra + [
                    'permissions_count'    => count($apres),
                    'permissions_avant'    => $noms,
                    'permissions_ajoutees' => $ajoutees,
                    'permissions_retirees' => $retirees,
                ]);

            if ($nouveau) {
                $activite->event('permissions_attribuees')
                    ->log("Création du rôle {$role->name} avec " . count($apres) . ' droit(s)');

                return;
            }

            if ($ajoutees !== [] || $retirees !== []) {
                $activite->event('permissions_modifiees')
                    ->log("Droits modifiés sur le rôle {$role->name} "
                        . '(+' . count($ajoutees) . ' / −' . count($retirees) . ')');

                return;
            }

            $activite->log("Modification du rôle {$role->name}");
        } catch (\Exception $e) {
            \Log::warning('ActivityLog::journaliserDroitsDeRole échoué : ' . $e->getMessage());
        }
    }

    // =========================================================
    // HELPERS — Accesseurs utiles
    // =========================================================

    public function getDocumentNumero(): ?string
    {
        try {
            return $this->subject?->numero ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getContextUrl(): ?string
    {
        return $this->properties?->get('_context.url');
    }

    public function getOldValues(): array
    {
        return $this->properties?->get('old') ?? [];
    }

    public function getNewValues(): array
    {
        return $this->properties?->get('attributes') ?? [];
    }

    public function getChangedFields(): array
    {
        $old = $this->getOldValues();
        $new = $this->getNewValues();
        if (empty($old) && empty($new)) return [];

        $changed = [];
        foreach ($new as $key => $newVal) {
            $oldVal = $old[$key] ?? null;
            if ($oldVal != $newVal) {
                $changed[$key] = ['ancien' => $oldVal, 'nouveau' => $newVal];
            }
        }
        return $changed;
    }

    public function getEventLabel(): string
    {
        return match ($this->event) {
            'created'       => '✅ Créé',
            'updated'       => '✏️ Modifié',
            'deleted'       => '🗑️ Supprimé',
            'login'         => '🔓 Connexion',
            'logout'        => '🔒 Déconnexion',
            'valider'       => '✅ Validé',
            'engager'       => '💰 Engagé',
            'annuler'       => '❌ Annulé',
            'recuperer'     => '🔄 Récupéré',
            'desengager'    => '↩️ Désengagé',
            'devalider'     => '⬇️ Dévalidé',
            // Marchés publics — le cycle propre au module, dans le même vocabulaire
            // que le journal existant (aucun événement repris, uniquement des ajouts).
            'publier_dao'   => '📢 DAO publié',
            'ouvrir_plis'   => '📂 Plis ouverts',
            'classer_manuellement' => '✋ Classé contre le seuil',
            'revenir_au_seuil'     => '📐 Reclasé par le seuil',
            // Réception des offres (étape C). « rejeter » se produisait déjà sur les
            // liquidations et les virements, qui tombaient dans le libellé par défaut.
            'rejeter'       => '🚫 Rejeté',
            'lever_decision' => '↩️ Décision levée',
            // Évaluation et attribution (étape D). « evaluer » se distingue de la
            // décision : la note est une saisie répétée, l'attribution est un choix.
            'evaluer'            => '📝 Note portée',
            'attribuer'          => '🏆 Attribué',
            'lever_attribution'  => '↩️ Attribution levée',
            // Homologation et ordres de service (étape E). Le démarrage de l'exécution
            // porte un libellé propre parce que ce n'est pas une écriture du marché :
            // c'est la conséquence d'un ordre de service, et le journal doit montrer les
            // deux événements séparément — l'acte, puis le mouvement qu'il commande.
            'homologuer'         => '🛡️ Homologué',
            'lever_homologation' => '↩️ Homologation levée',
            'demarrer_execution' => '▶️ Exécution démarrée',
            'emettre_ordre_service' => '📄 Ordre de service émis',
            // Fiche de présence (étape E). Le visa d'un membre se nomme `viser_membre`
            // et non `viser` : ce mot désire déjà le visa du contrôleur financier sur la
            // liquidation, et les deux journaux se relisent ensemble. La fiche, elle,
            // n'est pas une décision — elle est dressée, puis émargée.
            'dresser_fiche_presence' => '📋 Fiche de présence dressée',
            'viser_membre'       => '👁️ Membre visa la fiche',
            'lever_visa_membre'  => '↩️ Visa de membre levé',
            'transformer'   => '🔀 Transformé',
            'marquer_payee' => '💳 Marqué payé',
            'viser'         => '👁️ Visé',
            'transmettre'   => '📤 Transmis',
            'cloturer'      => '🏁 Clôturé',
            'retourner'     => '↩️ Retourné',
            'access_denied' => '🚫 Accès refusé',
            'permissions_modifiees'  => '🔑 Droits modifiés',
            'permissions_attribuees' => '🔑 Droits accordés',
            'restored'      => '♻️ Restauré',
            'login_failed'  => '⚠️ Échec de connexion',
            'archivage'     => '🗄️ Archivage du journal',
            'correction_donnees'  => '🛠️ Correction de données',
            'correctifs_donnees'  => '🛠️ Correctifs de données',
            'corriger_beneficiaire' => '🛠️ Correction du bénéficiaire',
            default         => ucfirst($this->event ?? '—'),
        };
    }

    /**
     * Classe du sujet, que subject_type contienne un alias de morph map
     * ('engagement', 'bon_commande'...) ou le nom complet de la classe.
     */
    public function getSubjectClass(): ?string
    {
        if (!$this->subject_type) {
            return null;
        }

        return \Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel($this->subject_type)
            ?? $this->subject_type;
    }

    public function getSubjectLabel(): string
    {
        if (!$this->subject_type) return '—';

        // Libelle defini par module dans config/audit.php (accepte alias et nom de classe)
        return \App\Support\Audit::libelleModele($this->subject_type)
            ?? class_basename($this->getSubjectClass());
    }

    /** Module de l'entree (budget, planification...), deduit du type de document. */
    public function getModule(): ?string
    {
        return \App\Support\Audit::moduleDe($this->subject_type);
    }

    /** Libelle du module ; "Sécurité" pour les connexions et alertes sans document. */
    public function getModuleLabel(): string
    {
        if ($module = $this->getModule()) {
            return \App\Support\Audit::libelleModule($module);
        }

        return in_array($this->log_name, ['auth', 'security'], true) ? 'Sécurité' : '—';
    }

    // ── Helper privé ─────────────────────────────────────────
    /**
     * L'étiquette du sujet est le nom de classe, et « Validation de Offre N° … » se
     * lit mal : l'élision se décide sur la première lettre. Les initiales
     * consonantiques gardent le « du » que le journal écrit déjà.
     */
    private static function article(string $type): string
    {
        return preg_match('/^[aeiouAEIOU]/', $type) ? "de l'" : 'du ';
    }

    private static function _getActionLabel(string $action, $subject): string
    {
        $type = $subject ? class_basename(get_class($subject)) : 'Document';
        $num  = method_exists($subject, '__get') ? ($subject->numero ?? $subject->id ?? '') : '';
        $du   = self::article($type);

        return match ($action) {
            'valider'       => "Validation {$du}{$type}" . ($num ? " N° {$num}" : ''),
            'engager'       => "Engagement budgétaire {$du}{$type}" . ($num ? " N° {$num}" : ''),
            'annuler'       => "Annulation {$du}{$type}" . ($num ? " N° {$num}" : ''),
            'recuperer'     => "Récupération {$du}{$type}" . ($num ? " N° {$num}" : ''),
            'desengager'    => "Désengagement {$du}{$type}" . ($num ? " N° {$num}" : ''),
            'devalider'     => "Dévalidation {$du}{$type}" . ($num ? " N° {$num}" : ''),
            'transformer'   => "Transformation {$du}{$type}" . ($num ? " N° {$num}" : '') . ' en DA',
            'marquer_payee' => "Paiement {$du}{$type}" . ($num ? " N° {$num}" : ''),
            'transmettre'   => "Transmission {$du}{$type}" . ($num ? " N° {$num}" : ''),
            'cloturer'      => "Clôture de transmission — {$type}" . ($num ? " N° {$num}" : ''),
            'publier_dao'   => "Publication du DAO {$du}{$type}" . ($num ? " N° {$num}" : ''),
            'ouvrir_plis'   => "Ouverture des plis {$du}{$type}" . ($num ? " N° {$num}" : ''),
            'classer_manuellement' => "Classement manuel (contre le seuil) {$du}{$type}" . ($num ? " N° {$num}" : ''),
            'revenir_au_seuil'     => "Retour au classement par le seuil pour le {$type}" . ($num ? " N° {$num}" : ''),
            'rejeter'       => "Rejet {$du}{$type}" . ($num ? " N° {$num}" : ''),
            // Levée d'une décision sur une offre : tournure sans article, l'événement
            // n'existe que pour l'offre et le « sur » se passe d'élision.
            'lever_decision' => "Décision levée sur {$type}" . ($num ? " N° {$num}" : ''),
            // Une note n'a pas de numéro : c'est l'offre notée et l'étape du DAO qui
            // font la phrase. L'attribution se nomme par son attributaire, pas par le
            // seul marché — c'est lui que le lecteur cherche dans le journal.
            'evaluer' => 'Note portée sur ' . ($subject->offre?->numero ?? 'une offre')
                . ' — étape ' . ($subject->element?->libelle ?? '—'),
            'attribuer' => "Attribution {$du}{$type}" . ($num ? " N° {$num}" : '')
                . ($subject->offreAttribuee?->fournisseur?->raison_sociale
                    ? ' — ' . $subject->offreAttribuee->fournisseur->raison_sociale : ''),
            'lever_attribution' => "Levée de l'attribution {$du}{$type}" . ($num ? " N° {$num}" : ''),
            // Homologation (étape E) : la décision se nomme comme celle de l'étape D,
            // avec le marché dans la phrase — c'est lui que le lecteur cherche.
            'homologuer'         => "Homologation {$du}{$type}" . ($num ? " N° {$num}" : ''),
            'lever_homologation' => "Levée de l'homologation {$du}{$type}" . ($num ? " N° {$num}" : ''),
            'demarrer_execution' => "Démarrage de l'exécution {$du}{$type}" . ($num ? " N° {$num}" : ''),
            // L'ordre de service n'existe pas sans le marché qu'il commande : la phrase
            // porte les deux références, le journal ne retrouve pas le dossier autrement.
            'emettre_ordre_service' => 'Émission de l\'ordre de service'
                . ($num ? " N° {$num}" : '')
                . ($subject->marche?->numero ? ' — ' . $subject->marche->numero : ''),
            // Fiche de présence (étape E) : la séance se relit par son marché, et
            // l'émargement se relit par son membre. Une ligne du journal qui dirait
            // « Membre visa la fiche N° 4 » sans nom ni marché ne répond à rien.
            'dresser_fiche_presence' => 'Dressage de la fiche de présence'
                . ($num ? " N° {$num}" : '')
                . ($subject->marche?->numero ? ' — ' . $subject->marche->numero : ''),
            'viser_membre' => 'Visa posé par ' . ($subject->nom ?? 'un membre')
                . ($subject->fiche?->numero ? ' — fiche ' . $subject->fiche->numero : '')
                . ($subject->fiche?->marche?->numero ? ' (' . $subject->fiche->marche->numero . ')' : ''),
            'lever_visa_membre' => 'Visa levé sur ' . ($subject->nom ?? 'un membre')
                . ($subject->fiche?->numero ? ' — fiche ' . $subject->fiche->numero : '')
                . ($subject->fiche?->marche?->numero ? ' (' . $subject->fiche->marche->numero . ')' : ''),
            default         => "{$action} sur {$type}" . ($num ? " N° {$num}" : ''),
        };
    }
}
