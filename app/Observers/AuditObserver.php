<?php

namespace App\Observers;

use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Journalise les modeles listes dans config/audit.php qui n'utilisent pas LogsActivity.
 *
 * Format identique a celui de spatie/laravel-activitylog :
 *   properties.attributes = nouvelles valeurs, properties.old = anciennes valeurs.
 * L'ecran du journal (detail des champs modifies) fonctionne donc sans changement.
 *
 * Regle absolue : l'audit ne doit JAMAIS bloquer une operation metier.
 */
class AuditObserver
{
    public function created(Model $modele): void
    {
        $this->journaliser($modele, 'created', nouveau: $this->filtrer($modele, $modele->getAttributes()));
    }

    public function updated(Model $modele): void
    {
        // Dans l'evenement "updated", getChanges() contient les champs modifies
        // et getRawOriginal() encore les anciennes valeurs.
        $champs = array_keys($this->filtrer($modele, $modele->getChanges()));

        if (empty($champs)) {
            return; // seuls des champs exclus (ex. updated_at) ont change : rien a tracer
        }

        $cles = array_flip($champs);

        $this->journaliser(
            $modele,
            'updated',
            nouveau: array_intersect_key($this->filtrer($modele, $modele->getAttributes()), $cles),
            ancien: array_intersect_key($this->filtrer($modele, $modele->getRawOriginal()), $cles),
        );
    }

    public function deleted(Model $modele): void
    {
        $this->journaliser($modele, 'deleted', ancien: $this->filtrer($modele, $modele->getAttributes()));
    }

    public function restored(Model $modele): void
    {
        $this->journaliser($modele, 'restored', nouveau: $this->filtrer($modele, $modele->getAttributes()));
    }

    // ────────────────────────────────────────────────────────────────

    protected function journaliser(Model $modele, string $evenement, array $nouveau = [], array $ancien = []): void
    {
        if (!Audit::doitJournaliser()) {
            return;
        }

        try {
            activity(config('audit.log_name', 'audit'))
                ->performedOn($modele)
                ->causedBy(auth()->user())
                ->event($evenement)
                ->withProperties(array_filter(['attributes' => $nouveau, 'old' => $ancien], fn($v) => !empty($v)))
                ->log($this->description($evenement, $modele));
        } catch (\Throwable $e) {
            Log::warning('AuditObserver : journalisation impossible', [
                'modele'    => $modele::class,
                'id'        => $modele->getKey(),
                'evenement' => $evenement,
                'erreur'    => $e->getMessage(),
            ]);
        }
    }

    /** Retire les champs exclus et caches (mots de passe...), tronque les textes longs. */
    protected function filtrer(Model $modele, array $valeurs): array
    {
        $exclus = array_merge(config('audit.champs_exclus', []), $modele->getHidden());
        $longueurMax = (int) config('audit.longueur_max_valeur', 500);

        $resultat = array_diff_key($valeurs, array_flip($exclus));

        foreach ($resultat as $champ => $valeur) {
            if (is_string($valeur) && mb_strlen($valeur) > $longueurMax) {
                $resultat[$champ] = mb_substr($valeur, 0, $longueurMax) . '… [tronqué]';
            }
        }

        return $resultat;
    }

    protected function description(string $evenement, Model $modele): string
    {
        $type = Audit::libelleModele($modele::class) ?? class_basename($modele);

        $reference = $modele->getAttribute('numero')
            ?? $modele->getAttribute('code')
            ?? $modele->getAttribute('libelle')
            ?? $modele->getAttribute('name')
            ?? ('#' . $modele->getKey());

        $reference = mb_strimwidth((string) $reference, 0, 80, '…');

        return match ($evenement) {
            'created'  => "Création — {$type} {$reference}",
            'updated'  => "Modification — {$type} {$reference}",
            'deleted'  => "Suppression — {$type} {$reference}",
            'restored' => "Restauration — {$type} {$reference}",
            default    => "{$evenement} — {$type} {$reference}",
        };
    }
}
