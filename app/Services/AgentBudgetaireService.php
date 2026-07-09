<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use App\Models\User;

class AgentBudgetaireService
{
    // =========================================================
    // CONFIGURATION — Groq API (gratuit)
    // Modèles disponibles sur Groq :
    //   - llama-3.1-70b-versatile  (meilleur, recommandé)
    //   - llama-3.1-8b-instant     (plus rapide, moins précis)
    //   - mixtral-8x7b-32768       (bon pour les longs contextes)
    //   - gemma2-9b-it             (alternatif)
    // =========================================================
    protected string $apiUrl    = 'https://api.groq.com/openai/v1/chat/completions';
    protected string $model     = 'llama-3.3-70b-versatile'; // Remplace llama-3.1-70b-versatile décommissionné
    protected int    $maxTokens = 1024;

    // =========================================================
    // POINT D'ENTRÉE PRINCIPAL
    // =========================================================
    public function chat(
        array  $messages,
        string $pageCourante     = '',
        ?int   $recordId         = null,
        string $recordType       = '',
        array  $dernieresErreurs = []
    ): string {
        $user    = auth()->user();
        $context = $this->construireContexte($user, $pageCourante, $recordId, $recordType, $dernieresErreurs);

        $systemPrompt = $this->construireSystemPrompt($context);

        // ✅ Groq utilise le format OpenAI : system message en tête de messages
        $messagesAvecSystem = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $messages
        );

        // Groq ne supporte pas les tools natifs comme Anthropic
        // → On gère les tools via le system prompt (tool-calling simulé)
        return $this->appelerGroq($messagesAvecSystem);
    }

    // =========================================================
    // CONTEXTE UTILISATEUR
    // =========================================================
    protected function construireContexte(
        ?User  $user,
        string $page,
        ?int   $recordId,
        string $recordType,
        array  $erreurs
    ): array {
        if (!$user) return [];

        $roles       = $user->getRoleNames()->toArray();
        $permissions = $user->getAllPermissions()->pluck('name')->toArray();

        $contexte = [
            'utilisateur' => [
                'nom'         => $user->name,
                'email'       => $user->email,
                'roles'       => $roles,
                'permissions' => $permissions,
            ],
            'page_courante'    => $page,
            'record_id'        => $recordId,
            'record_type'      => $recordType,
            'erreurs_recentes' => $erreurs,
            'timestamp'        => now()->format('d/m/Y H:i'),
        ];

        // Enrichir avec les données du record courant si disponible
        if ($recordId && $recordType) {
            $contexte['record_courant'] = $this->getRecordInfo($recordType, $recordId);
        }

        // Enrichir avec les disponibilités budgétaires si pertinent
        if (in_array($recordType, ['bon_commande', 'engagement', 'decision_administrative'])) {
            $contexte['info_budgetaire'] = $this->getInfoBudgetaire($recordType, $recordId);
        }

        return $contexte;
    }

    protected function getRecordInfo(string $type, int $id): array
    {
        $modelMap = [
            'bon_commande'            => \App\Models\BonCommande::class,
            'engagement'              => \App\Models\Engagement::class,
            'decision_administrative' => \App\Models\DecisionAdministrative::class,
            'ordonnance_paiement'     => \App\Models\OrdonnancePaiement::class,
            'memoire_depense'         => \App\Models\MemoireDepense::class,
            'bordereau_engagement'    => \App\Models\BordereauEngagement::class,
        ];

        $modelClass = $modelMap[$type] ?? null;
        if (!$modelClass) return [];

        try {
            $record = $modelClass::find($id);
            if (!$record) return ['erreur' => 'Document introuvable'];

            return [
                'id'         => $record->id,
                'numero'     => $record->numero     ?? null,
                'statut'     => $record->statut     ?? null,
                'montant'    => $record->montant_ttc ?? $record->montant_total ?? null,
                'engage'     => $record->engage     ?? null,
                'created_at' => $record->created_at?->format('d/m/Y'),
            ];
        } catch (\Exception $e) {
            return ['erreur' => $e->getMessage()];
        }
    }

    protected function getInfoBudgetaire(string $type, ?int $id): array
    {
        if (!$id) return [];
        try {
            if ($type === 'bon_commande') {
                $bc = \App\Models\BonCommande::with('lignes.nomenclature')->find($id);
                if (!$bc) return [];
                $v = $bc->verifierDisponibiliteBudgetaire();
                return [
                    'peut_engager' => $v['peut_engager'] ?? null,
                    'lignes'       => collect($v['lignes_budgetaires'] ?? [])
                        ->map(fn($l) => [
                            'code'       => $l['nomenclature']->code ?? '?',
                            'disponible' => $l['disponible'] ?? 0,
                            'suffisant'  => $l['suffisant']  ?? true,
                        ])->toArray(),
                ];
            }
        } catch (\Exception $e) {
            return [];
        }
        return [];
    }

    // =========================================================
    // SYSTEM PROMPT — connaissance complète du système CHUY
    // =========================================================
    protected function construireSystemPrompt(array $ctx): string
    {
        $utilisateur = $ctx['utilisateur'] ?? [];
        $roles       = implode(', ', $utilisateur['roles'] ?? ['inconnu']);
        $permissions = implode(', ', array_slice($utilisateur['permissions'] ?? [], 0, 20));
        $page        = $ctx['page_courante']    ?? 'inconnue';
        $erreurs     = $ctx['erreurs_recentes'] ?? [];
        $erreursStr  = $erreurs ? '- ' . implode("\n- ", $erreurs) : 'aucune';
        $record      = $ctx['record_courant']   ?? [];
        $recordStr   = $record ? json_encode($record, JSON_UNESCAPED_UNICODE) : 'aucun';
        $budget      = $ctx['info_budgetaire']  ?? [];
        $budgetStr   = $budget ? json_encode($budget, JSON_UNESCAPED_UNICODE) : 'non disponible';
        $timestamp   = $ctx['timestamp']        ?? now()->format('d/m/Y H:i');

        return <<<PROMPT
Tu es Budget Suite Assistant, l'agent intelligent de la plateforme de gestion budgétaire du Centre Hospitalier et Universitaire de Yaoundé (CHUY), Cameroun.

## CONTEXTE UTILISATEUR EN TEMPS RÉEL
- **Utilisateur** : {$utilisateur['nom']} ({$utilisateur['email']})
- **Rôles** : {$roles}
- **Permissions principales** : {$permissions}
- **Page courante** : {$page}
- **Document ouvert** : {$recordStr}
- **Info budgétaire** : {$budgetStr}
- **Erreurs récentes** : {$erreursStr}
- **Heure** : {$timestamp}

## CONNAISSANCE COMPLÈTE DU SYSTÈME CHUY/BUDGET

### MODULES ET WORKFLOWS

**1. Bons de Commande (BC/BCA)**
- Statuts : brouillon → validé → engagé → livré → annulé
- Règles : BC validé obligatoire avant engagement | BC engagé non modifiable
- Calculs : TVA=HT×19.25% | IR=HT×5.5% | TTC=HT+TVA | NAP=HT-IR
- Mode arrondi : true=entiers FCFA | false=décimales conservées
- Annulation/Récupération : annulé → récupérable en brouillon

**2. Décisions Administratives (DA)**
- Statuts : brouillon → validée → engagée → annulee (féminin avec 'e')
- Récupérable depuis annulee vers brouillon

**3. Engagements**
- Statuts : provisoire → définitif → annulé
- Créés automatiquement lors de l'engagement d'un BC/DA
- Définitif avec OP : non supprimable

**4. Ordonnances de Paiement (OP)**
- Statuts : émise → visée → validée → payée → annulée
- Payée : IRRÉVERSIBLE
- Créées depuis un engagement définitif

**5. Mémoires de Dépense (MD)**
- Formule : MHT = NAP/(1-IR%) ex: NAP/0.945 si IR=5.5%
- NAP = MHT - IR | TTC = MHT + TVA

**6. Bordereaux d'Engagement (BE)**
- Regroupement d'engagements pour visa du contrôleur financier

**7. Régies d'Avance**
- Décaissements → dépenses → apurement avec justificatifs

### CALCULS BUDGÉTAIRES
- Budget rectifié = Dotation initiale + virements entrants - virements sortants
- Disponible = Budget rectifié - Total engagé
- Taux consommation = (Total engagé / Budget rectifié) × 100

### RÔLES ET PERMISSIONS
| Rôle | Capacités principales |
|---|---|
| super_admin | Accès total, modification tous statuts |
| admin | Accès large sauf gestion rôles |
| daaf | Validation, ordonnancement complet |
| chef_service_budget | Validation BC/DA/engagements |
| operateur_budget | Création BC/DA/mémoires |
| controleur_financier | Visa ordonnances et bordereaux |
| directeur_general | Validation et signature finale |
| agence_comptable | Paiement des ordonnances |
| responsable_regie | Gestion régies d'avance |

### ERREURS COURANTES ET SOLUTIONS
| Erreur | Cause | Solution |
|---|---|---|
| "Modification interdite" | Document non brouillon | Annuler → Récupérer en brouillon |
| "Crédit insuffisant" | Disponible < montant BC | Virement budgétaire ou augmentation dotation |
| "Engagement impossible" | BC non validé ou déjà engagé | Vérifier statut du BC |
| "Suppression interdite" | OP/engagements liés | Annuler les OP d'abord |
| "403 Permission refusée" | Rôle insuffisant | Contacter admin pour obtenir le bon rôle |
| "401 Auth error" | Clé API invalide | Vérifier ANTHROPIC_API_KEY dans .env |

## INSTRUCTIONS DE COMPORTEMENT
1. Réponds TOUJOURS en français, de façon concise et bienveillante
2. Utilise le contexte utilisateur pour personnaliser tes réponses
3. Si l'utilisateur ne peut pas faire une action, explique POURQUOI précisément
4. Pour les erreurs, donne toujours la CAUSE et la SOLUTION
5. Utilise des emojis pour la lisibilité (✅ ❌ ⚠️ 💰 🔐 📋)
6. Format Markdown : **gras**, listes à tirets, blocs de code si nécessaire
7. Si tu n'es pas sûr, dis-le franchement et suggère de contacter un admin
8. Maximum 300 mots par réponse sauf si l'utilisateur demande plus de détails
PROMPT;
    }

    // =========================================================
    // APPEL API GROQ (format OpenAI-compatible)
    // =========================================================
    protected function appelerGroq(array $messages): string
    {
        $apiKey = config('services.groq.api_key');

        if (empty($apiKey)) {
            return "❌ Clé API Groq non configurée.\n\n"
                . "Ajoutez dans votre `.env` :\n"
                . "```\nGROQ_API_KEY=gsk_votre_clé\n```\n\n"
                . "Obtenez une clé gratuite sur **https://console.groq.com**";
        }

        $payload = json_encode([
            'model'       => $this->model,
            'messages'    => $messages,
            'max_tokens'  => $this->maxTokens,
            'temperature' => 0.3,  // Plus déterministe pour les réponses métier
            'stream'      => false,
        ]);

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT    => 30,
        ]);

        $body   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Log::error("CHUY-Agent cURL error: {$error}");
            return "❌ Erreur de connexion. Vérifiez votre connexion internet.";
        }

        if ($status !== 200) {
            Log::error("CHUY-Agent HTTP {$status}", ['body' => $body]);
            $errData = json_decode($body, true);
            $msg     = $errData['error']['message'] ?? $body;

            return match ($status) {
                401 => "❌ Clé API Groq invalide. Vérifiez `GROQ_API_KEY` dans votre `.env`.",
                429 => "⚠️ Limite de débit atteinte. Réessayez dans quelques secondes.",
                503 => "⚠️ Service Groq temporairement indisponible. Réessayez dans un moment.",
                default => "❌ Erreur API ({$status}) : {$msg}",
            };
        }

        $data = json_decode($body, true);

        return $data['choices'][0]['message']['content']
            ?? "Je n'ai pas pu générer une réponse. Réessayez.";
    }

    // =========================================================
    // UTILITAIRES
    // =========================================================

    /**
     * Réinitialise le contexte (utile pour les tests)
     */
    public function getModelInfo(): array
    {
        return [
            'provider' => 'Groq',
            'model'    => $this->model,
            'api_url'  => $this->apiUrl,
            'gratuit'  => true,
        ];
    }
}
