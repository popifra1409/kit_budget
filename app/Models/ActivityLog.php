<?php

namespace App\Models;

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
        ?int    $resourceId = null
    ): void {
        try {
            activity('security')
                ->causedBy(auth()->user())
                ->withProperties([
                    'action'      => $action,
                    'resource'    => $resource,
                    'resource_id' => $resourceId,
                    'url'         => request()?->fullUrl(),
                    'ip'          => request()?->ip(),
                ])
                ->event('access_denied')
                ->log("Accès refusé : {$action}" . ($resource ? " sur {$resource}" : ''));
        } catch (\Exception $e) {
            \Log::warning("ActivityLog::logAccesRefuse échoué : " . $e->getMessage());
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
            'transformer'   => '🔀 Transformé',
            'marquer_payee' => '💳 Marqué payé',
            'viser'         => '👁️ Visé',
            'transmettre'   => '📤 Transmis',
            'cloturer'      => '🏁 Clôturé',
            'retourner'     => '↩️ Retourné',
            'access_denied' => '🚫 Accès refusé',
            default         => ucfirst($this->event ?? '—'),
        };
    }

    public function getSubjectLabel(): string
    {
        if (!$this->subject_type) return '—';
        return match (class_basename($this->subject_type)) {
            'BonCommande'            => 'Bon de Commande',
            'DecisionAdministrative' => 'Décision Administrative',
            'Engagement'             => 'Engagement',
            'OrdonnancePaiement'     => 'Ordonnance de Paiement',
            'MemoireDepense'         => 'Mémoire de Dépense',
            'BordereauEngagement'    => 'Bordereau d\'Engagement',
            'Budget'                 => 'Budget',
            'LigneBudgetaire'        => 'Ligne Budgétaire',
            'RegieAvance'            => 'Régie d\'Avance',
            'Exercice'               => 'Exercice',
            'User'                   => 'Utilisateur',
            'Transmission'           => 'Transmission',
            'VirementBudgetaire'     => 'Virement Budgétaire',
            default => class_basename($this->subject_type),
        };
    }

    // ── Helper privé ─────────────────────────────────────────
    private static function _getActionLabel(string $action, $subject): string
    {
        $type = $subject ? class_basename(get_class($subject)) : 'Document';
        $num  = method_exists($subject, '__get') ? ($subject->numero ?? $subject->id ?? '') : '';

        return match ($action) {
            'valider'       => "Validation du {$type}" . ($num ? " N° {$num}" : ''),
            'engager'       => "Engagement budgétaire du {$type}" . ($num ? " N° {$num}" : ''),
            'annuler'       => "Annulation du {$type}" . ($num ? " N° {$num}" : ''),
            'recuperer'     => "Récupération du {$type}" . ($num ? " N° {$num}" : ''),
            'desengager'    => "Désengagement du {$type}" . ($num ? " N° {$num}" : ''),
            'devalider'     => "Dévalidation du {$type}" . ($num ? " N° {$num}" : ''),
            'transformer'   => "Transformation du {$type}" . ($num ? " N° {$num}" : '') . ' en DA',
            'marquer_payee' => "Paiement du {$type}" . ($num ? " N° {$num}" : ''),
            'transmettre'   => "Transmission du {$type}" . ($num ? " N° {$num}" : ''),
            'cloturer'      => "Clôture de transmission — {$type}" . ($num ? " N° {$num}" : ''),
            default         => "{$action} sur {$type}" . ($num ? " N° {$num}" : ''),
        };
    }
}
