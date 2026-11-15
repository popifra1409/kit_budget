<?php

namespace App\Console\Commands;

use App\Models\Action;
use App\Models\Programme;
use App\Models\SousProgrammeEp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B2b — peuple `actions.sous_programme_ep_id` à partir du rattachement déjà saisi.
 *
 * Le lien à recopier existe déjà, mais sous une forme fragile : `sous_programmes_ep.programme_
 * budgetaire_id` désigne le programme du MINSANTE, et `actions.programme_id` désigne le même
 * programme. L'action appartient au sous-programme qui décompose ce programme. Ni le code porteur
 * (`code_programme_ep`) ni le préfixe du code de l'action n'entrent dans la règle : c'est la
 * colonne à clé étrangère qui décide, et B2e pourra supprimer le code porteur sans rien casser.
 *
 * Cette règle est celle que le modèle impose depuis B1a — un seul sous-programme de l'EP par
 * programme du MINSANTE. Pour chaque action, trois issues seulement :
 *  - un sous-programme vivant : la liaison est écrite ;
 *  - aucun : le programme n'est décomposé par aucun sous-programme vivant de l'hôpital ;
 *  - deux ou plus : la base contredit la règle (doublon antérieur à B1a) — signalé, jamais
 *    tranché par un `first()` implicite, contrairement à ce que fait
 *    `RelierTachesCreditsCommand::sousProgrammeDe()`.
 *
 * Rien n'est effacé : le lien d'une action dont le sous-programme est supprimé est CONSERVÉ (il
 * reste la trace de ce qui finançait l'action) et dit en sortie ; une action refusée garde la
 * valeur qu'elle avait. La commande est donc relisible à volonté — après chaque création d'action
 * ou de sous-programme, une nouvelle exécution ne relie que ce qui ne l'était pas.
 *
 * Les actions supprimées (SoftDeletes) sont reliées comme les autres : `restrictOnDelete` protège
 * contre leur purge, et une restauration ne doit pas rendre une ligne subitement orpheline.
 *
 * `--dry-run` annonce sans écrire. Sinon, tout est écrit dans une transaction et vérifié AVANT
 * commit : chaque couple annoncé retrouvé en base, et aucun couple vivant dont les programmes
 * diffèrent. Si la promesse manque, rien n'est enregistré.
 */
class RelierActionsSousProgrammesCommand extends Command
{
    protected $signature = 'actions:relier-sous-programmes
                            {--dry-run : Annoncer les liaisons sans rien écrire}';

    protected $description = "Relie chaque action au sous-programme de l'EP qui décompose son programme (backfill de actions.sous_programme_ep_id)";

    public function handle(): int
    {
        if (!Schema::hasColumn('actions', 'sous_programme_ep_id')) {
            $this->error("La colonne actions.sous_programme_ep_id n'existe pas : lancez `php artisan migrate` avant cette commande.");
            return self::FAILURE;
        }

        $sec = (bool) $this->option('dry-run');

        $parId = [];
        $parProgramme = [];
        foreach (SousProgrammeEp::withTrashed()->get(['id', 'code', 'programme_budgetaire_id', 'deleted_at']) as $sp) {
            $parId[(int) $sp->id] = $sp;
            if ($sp->programme_budgetaire_id !== null) {
                $parProgramme[(int) $sp->programme_budgetaire_id][] = $sp;
            }
        }

        $codesProgramme = Programme::withTrashed()->pluck('code', 'id')->all();

        $actions = Action::withTrashed()
            ->orderBy('programme_id')->orderBy('code')->orderBy('id')
            ->get(['id', 'code', 'libelle', 'programme_id', 'sous_programme_ep_id', 'deleted_at']);

        $annonces = [];   // [id_action, id_sous_programme] à écrire
        $conformes = 0;
        $refus = [];
        $conserves = [];
        $comptes = [];    // id_sous_programme => actions qu'il portera après passage

        foreach ($actions as $action) {
            $vivants = array_values(array_filter(
                $parProgramme[(int) $action->programme_id] ?? [],
                fn($sp) => $sp->deleted_at === null
            ));

            if (count($vivants) > 1) {
                $refus[] = [$action->code, 'la base contredit la règle : '
                    . implode(', ', array_map(fn($sp) => $sp->code, $vivants)) . ' décomposent tous ce programme'];
                continue;
            }

            if (count($vivants) === 1) {
                $sp = $vivants[0];
                $comptes[(int) $sp->id] = ($comptes[(int) $sp->id] ?? 0) + 1;
                if ((int) $action->sous_programme_ep_id === (int) $sp->id) {
                    $conformes++;
                } else {
                    $annonces[] = [$action->code, (int) $action->id, (int) $sp->id];
                }
                continue;
            }

            // Aucun sous-programme vivant sur ce programme : le lien éventuel n'est pas défait.
            if ($action->sous_programme_ep_id !== null && ($porteur = $parId[(int) $action->sous_programme_ep_id] ?? null)) {
                $conserves[] = [$action->code, $porteur->code,
                    $porteur->deleted_at ? 'le sous-programme est supprimé' : "le sous-programme ne décompose pas ce programme"];
                $comptes[(int) $porteur->id] = ($comptes[(int) $porteur->id] ?? 0) + 1;
                continue;
            }

            $refus[] = [$action->code, "aucun sous-programme de l'EP ne décompose le programme « "
                . ($codesProgramme[(int) $action->programme_id] ?? $action->programme_id) . ' »'];
        }

        ksort($comptes);

        $this->info($sec
            ? 'ANALYSE — rien ne sera écrit (--dry-run)'
            : 'ÉCRITURE');

        $this->table(
            ["Sous-programme de l'EP", 'Programme du MINSANTE', 'Actions'],
            array_map(function (int $idSp, int $n) use ($parId, $codesProgramme) {
                $sp = $parId[$idSp] ?? null;
                return [
                    ($sp?->code ?? "#$idSp") . ($sp?->deleted_at ? ' (supprimé)' : ''),
                    $sp?->programme_budgetaire_id === null
                        ? '—' : ($codesProgramme[(int) $sp->programme_budgetaire_id] ?? '—'),
                    (string) $n,
                ];
            }, array_keys($comptes), array_values($comptes))
        );

        if ($annonces !== []) {
            $this->table(
                ['Action', "Décomposée par"],
                array_map(fn(array $a) => [$a[0], $parId[$a[2]]->code], $annonces)
            );
        }

        if ($refus !== []) {
            $this->table(['Action', 'Motif du refus'], $refus);
        } else {
            $this->line("Aucun refus : chaque action trouve un — et un seul — sous-programme de l'EP.");
        }

        if ($conserves !== []) {
            $this->table(['Action', 'Lien conservé vers', 'Pourquoi'], $conserves);
        }

        $this->line(sprintf(
            'Actions examinées : %d · à relier : %d · déjà conformes : %d · refusées : %d · conservées : %d',
            $actions->count(), count($annonces), $conformes, count($refus), count($conserves)
        ));

        if ($sec) {
            $this->line(sprintf('Relancez sans `--dry-run` pour écrire %d liaison(s).', count($annonces)));
            return self::SUCCESS;
        }

        if (!$this->ecrireEtVerifier($annonces)) {
            $this->error("Vérification après écritures manquée : rien n'est enregistré.");
            return self::FAILURE;
        }

        $this->info(sprintf('%d liaison(s) écrite(s), %d déjà conforme(s).', count($annonces), $conformes));
        return self::SUCCESS;
    }

    /**
     * Écrit puis se relit dans la même transaction : si un couple annoncé manque en base, ou si
     * un lien vivant attachait une action au sous-programme d'un autre programme, l'exception
     * annule toutes les écritures.
     *
     * @param array<int,array{0:string,1:int,2:int}> $annonces [code, id_action, id_sous_programme]
     */
    protected function ecrireEtVerifier(array $annonces): bool
    {
        if ($annonces === []) {
            $this->line('Vérification : aucune écriture annoncée, base inchangée.');
            return true;
        }

        try {
            DB::transaction(function () use ($annonces) {
                foreach ($annonces as [$code, $idAction, $idSp]) {
                    // `forceFill` : le backfill ne doit pas dépendre du `$fillable`, qui bougera
                    // en B2c. `saveQuietly` : pas d'événements d'activité pour une recopie de
                    // classification — et la règle posée ici est celle du garde de `Action`.
                    $action = Action::withTrashed()->whereKey($idAction)->first();
                    if ($action === null) {
                        throw new \RuntimeException("action #$idAction introuvable");
                    }
                    $action->forceFill(['sous_programme_ep_id' => $idSp])->saveQuietly();
                }

                $ecrites = DB::table('actions')
                    ->where(function ($q) use ($annonces) {
                        foreach ($annonces as [, $idAction, $idSp]) {
                            $q->orWhere(fn($w) => $w->where('id', $idAction)->where('sous_programme_ep_id', $idSp));
                        }
                    })
                    ->count();

                $incoherentes = DB::table('actions as a')
                    ->join('sous_programmes_ep as s', 's.id', '=', 'a.sous_programme_ep_id')
                    ->whereNotNull('a.programme_id')
                    ->whereNotNull('s.programme_budgetaire_id')
                    ->whereNull('s.deleted_at')
                    ->whereColumn('s.programme_budgetaire_id', '!=', 'a.programme_id')
                    ->count();

                $this->line(sprintf(
                    'Vérification : %d/%d couples annoncés retrouvés en base, %d couple(s) vivant(s) incohérent(s).',
                    $ecrites, count($annonces), $incoherentes
                ));

                if ($ecrites !== count($annonces) || $incoherentes !== 0) {
                    throw new \RuntimeException('promesse non tenue');
                }
            });
        } catch (\RuntimeException $e) {
            $this->line('Échec : ' . $e->getMessage());
            return false;
        }

        return true;
    }
}
