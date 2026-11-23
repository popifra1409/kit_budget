<?php
// app/Filament/Admin/Pages/Corbeille.php

namespace App\Filament\Admin\Pages;

use App\Console\Commands\CorbeilleEtat;
use App\Console\Commands\CorbeilleRestaurer;
use App\Models\User;
use Filament\Actions\Action as ActionDePage;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action as ActionDeLigne;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Console\Input\ArrayInput;

/**
 * La corbeille à l'écran : voir ce qui est mort, et le faire revenir.
 *
 * `corbeille:etat` et `corbeille:restaurer` répondent déjà en console, mais rien ici n'était
 * atteignable sans un terminal. Cette page n'ajoute AUCUNE décision : elle appelle
 * `CorbeilleRestaurer::plan()` pour savoir ce qui est restaurable et `ecrire()` pour le faire.
 * Deux implémentations de « restaurable » — une ici, une là-bas — finiraient par se contredire,
 * et le seul désaccord qui compte est « accepté à l'écran, refusé en console ».
 *
 * Trois choses volontaires :
 *  - le bouton « Faire revenir » est TOUJOURS là. Un refus s'affiche, il ne se cache pas : si
 *    l'action disparaît selon l'état de la ligne, plus rien ne dit pourquoi elle n'y est pas ;
 *  - la prédiction (ce que le jeton entraîne, ce qui reste mort sous le parent) est calculée à
 *    l'ouverture de la confirmation, pas dans la liste : `famille()` interroge les 70 modèles
 *    câblés par ligne tracée, et une liste de 25 lignes coûterait 1 750 requêtes ;
 *  - la liste passe par `withoutGlobalScopes()` : le scope exercice masquerait précisément les
 *    lignes d'un exercice clos ou d'un autre responsable, qui sont la raison d'être de l'écran.
 *
 * Purger définitivement n'est pas ici : c'est #73, et le trait refuse aujourd'hui la purge d'une
 * famille attribuée ligne à ligne (gardes `deleting`) — ce serait un bouton qui ne sait pas ce
 * qu'il fait.
 *
 * Panel : elle était dans « Contrôle & clôture » du module Budget, qui est replié par défaut — d'où
 * le « je ne vois pas le lien ». Elle vit désormais dans « Sécurité et audit » (/admin), groupe
 * jamais replié, parce que la corbeille parle des 70 tables câblées du SIGB et non du Budget seul.
 */
class Corbeille extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box-x-mark';
    protected static ?string $navigationGroup = 'Sécurité et audit';
    protected static ?string $navigationLabel = 'Corbeille';
    protected static ?string $title = 'Corbeille';
    // 2 : `ActivityResource` (le journal d'audit) porte le 1 dans ce groupe.
    protected static ?int $navigationSort = 2;
    protected static string $view = 'filament.admin.pages.corbeille';

    /** Le nom de table choisi. JAMAIS `$table` : `{{ $this->table }}` rend le Filament\Tables\Table. */
    public ?string $choix = null;

    /** Inventaire de la requête, une fois par rendu (Livewire le recalcule à chaque aller-retour). */
    private ?array $inventaire = null;

    public static function canAccess(): bool
    {
        $u = auth()->user();

        return $u && ($u->hasAnyRole(['super_admin', 'admin']) || $u->can('view_corbeille'));
    }

    public function mount(): void
    {
        $cet = array_keys($this->inventaire());
        $this->choix = $cet[0] ?? ($this->modelesCables()[0] ?? null);

        if ($this->choix === null) {
            Notification::make()->warning()
                ->title('La corbeille ne connaît aucune table')
                ->body('Le trait HasCorbeille n’est posé sur aucun modèle, ou la colonne corbeille_group manque.')
                ->send();
        }
    }

    /**
     * Tables que la corbeille connaît, avec leur nombre de lignes mortes — seulement celles qui
     * en portent, sinon la liste est un cimetière de 70 entrées vides.
     */
    public function inventaire(): array
    {
        if ($this->inventaire !== null) {
            return $this->inventaire;
        }

        $parTable = [];
        foreach ($this->modelesCables() as $modele) {
            $table = (new $modele)->getTable();
            $n = $modele::onlyTrashed()->withoutGlobalScopes()->count();
            if ($n > 0) {
                $parTable[$table] = ['modele' => $modele, 'morts' => $n];
            }
        }
        ksort($parTable);

        return $this->inventaire = $parTable;
    }

    protected function getFormSchema(): array
    {
        return [
            Select::make('choix')
                ->label('Table')
                ->options(fn() => collect($this->inventaire())
                    ->mapWithKeys(fn($d, $t) => [$t => $t . ' — ' . $d['morts'] . ' ligne(s) morte(s)']))
                ->required()
                ->live()
                ->afterStateUpdated(fn() => $this->resetTable()),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn() => $this->requete())
            ->defaultSort('deleted_at', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('intitule')
                    ->label('Ce qui est en corbeille')
                    ->getStateUsing(fn(Model $r) => $this->etat()->intitule($r))
                    ->limit(60),
                TextColumn::make('deleted_at')
                    ->label('Jeté le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('deleted_par')
                    ->label('Par qui')
                    // getStateUsing plutôt que formatUsing : `formatUsing` est arrivé avec une
                    // version précise du vendor, et la colonne virtuelle ne coûte rien ici.
                    ->getStateUsing(fn(Model $r) => $this->nomUtilisateur($r->deleted_by))
                    ->placeholder('—'),
                TextColumn::make('corbeille_group')
                    ->label('Jeton')
                    ->limit(8)
                    ->placeholder('aucun — revient seul'),
            ])
            ->actions([
                // ActionDeLigne et non ActionDePage : `$table->actions()` appelle `->table()` sur
                // chaque action (Tables/Table/Concerns/HasActions.php) — la classe de page n'a pas
                // cette méthode, la page meurt au rendu et non à l'appel du geste.
                ActionDeLigne::make('ramener')
                    ->label('Faire revenir')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(fn(Model $r) => 'Faire revenir « ' . $this->etat()->intitule($r) . ' »')
                    // Lazy : c'est ici, pour UNE ligne, que le coût de la prédiction a le droit
                    // d'être payé.
                    ->modalDescription(fn(Model $r) => $this->prediction($r))
                    ->action(fn(Model $r) => $this->ramener($r)),
            ])
            ->emptyStateIcon('heroicon-o-archive-box')
            ->emptyStateHeading('Rien dans cette table')
            ->emptyStateDescription('Aucune ligne morte pour « ' . ($this->choix ?? '—') . ' » — ou la corbeille ne connaît pas cette table.');
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionDePage::make('ramenerTout')
                ->label('Faire revenir tout ce qui est accepté')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Faire revenir la table entière ?')
                ->modalDescription(fn() => $this->predictionRacine())
                ->action(fn() => $this->ramenerTout()),
        ];
    }

    /**
     * Voir n'est pas faire. Le contrôle est DANS le geste, pas sur le bouton : une action cachée
     * par `visible()`/`authorize()` n'est plus appelable et le refus devient muet — on veut le
     * contraire, un bouton présent qui dit pourquoi il refuse.
     */
    public function peutRestaurer(): bool
    {
        $u = auth()->user();

        return (bool) $u && ($u->hasAnyRole(['super_admin', 'admin']) || $u->can('restaurer_corbeille'));
    }

    protected function refusDeDroit(): array
    {
        $this->refus('Droit insuffisant',
            'Vous voyez la corbeille, vous ne pouvez pas y toucher — il manque la permission restaurer_corbeille.');

        return ['ok' => false, 'plan' => null, 'ecriture' => null, 'refus' => 'DROIT'];
    }

    /**
     * Le geste pour UNE ligne. `public` pour que le témoin le joue sans passer par le DOM.
     */
    public function ramener(Model $r): array
    {
        if (! $this->peutRestaurer()) {
            return $this->refusDeDroit();
        }

        $plan = $this->restaurateur()->plan((string) $this->choix, [(string) $r->getKey()]);

        if ($plan['erreur'] !== null) {
            $this->refus('Cette table n’est pas dans la corbeille', $plan['erreur']);

            return ['ok' => false, 'plan' => $plan, 'ecriture' => null];
        }

        if (!$plan['aRamener']) {
            $this->refus('Restaurations refusées', $this->resume($plan['refus']));

            return ['ok' => false, 'plan' => $plan, 'ecriture' => null];
        }

        $ecriture = $this->restaurateur()->ecrire($plan['aRamener']);

        if ($ecriture['echouees']) {
            $this->refus('Écriture refusée par la base',
                $this->resume($ecriture['echouees'])
                . (count($ecriture['faite']) ? ' — ' . count($ecriture['faite']) . ' autre(s) rendue(s).' : '.'));
        } else {
            Notification::make()->success()
                ->title(count($ecriture['faite']) . ' ligne(s) rendue(s)')
                ->body($this->resume($ecriture['libelles']))
                ->send();
        }

        $this->avertirCredits($plan['modele'], $ecriture['faite']);
        $this->inventaire = null;
        $this->resetTable();

        return ['ok' => ! $ecriture['echouees'], 'plan' => $plan, 'ecriture' => $ecriture];
    }

    /** Le geste pour TOUTES les lignes acceptées de la table choisie. */
    public function ramenerTout(): array
    {
        if (! $this->peutRestaurer()) {
            return $this->refusDeDroit();
        }

        $ids = $this->idsMorts();
        if ($ids === []) {
            $this->refus('Rien à faire revenir', 'Aucune ligne morte dans « ' . ($this->choix ?? '—') . ' ».');

            return ['ok' => false, 'plan' => null, 'ecriture' => null];
        }

        $plan = $this->restaurateur()->plan((string) $this->choix, $ids);

        if ($plan['erreur'] !== null) {
            $this->refus('Cette table n’est pas dans la corbeille', $plan['erreur']);

            return ['ok' => false, 'plan' => $plan, 'ecriture' => null];
        }

        if (!$plan['aRamener']) {
            $this->refus('Tout est refusé', $this->resume($plan['refus']));

            return ['ok' => false, 'plan' => $plan, 'ecriture' => null];
        }

        $ecriture = $this->restaurateur()->ecrire($plan['aRamener']);

        if ($ecriture['echouees']) {
            $this->refus('Écriture refusée par la base',
                $this->resume($ecriture['echouees']) . ' — ' . count($ecriture['faite']) . ' rendue(s) quand même.');
        } else {
            Notification::make()->success()
                ->title(count($ecriture['faite']) . ' ligne(s) rendue(s)')
                ->body(count($plan['refus']) . ' refusée(s) : ' . $this->resume($plan['refus']))
                ->send();
        }

        $this->avertirCredits($plan['modele'], $ecriture['faite']);
        $this->inventaire = null;
        $this->resetTable();

        return ['ok' => ! $ecriture['echouees'], 'plan' => $plan, 'ecriture' => $ecriture];
    }

    /** Ce que la confirmation doit dire : refus, ou prédiction du trait. */
    public function prediction(Model $r): string
    {
        if (! $this->peutRestaurer()) {
            return 'Vous voyez la corbeille sans pouvoir y toucher : il manque la permission restaurer_corbeille.';
        }

        $plan = $this->restaurateur()->plan((string) $this->choix, [(string) $r->getKey()]);

        if ($plan['erreur'] !== null) {
            return $plan['erreur'];
        }

        if (!$plan['aRamener']) {
            return 'Refus : ' . $this->resume($plan['refus']) . ' Rien ne sera écrit.';
        }

        return $plan['aRamener'][0]['libelle'];
    }

    public function predictionRacine(): string
    {
        $ids = $this->idsMorts();
        if ($ids === []) {
            return 'Aucune ligne morte dans « ' . ($this->choix ?? '—') . ' ».';
        }

        $plan = $this->restaurateur()->plan((string) $this->choix, $ids);

        if ($plan['erreur'] !== null) {
            return $plan['erreur'];
        }

        return count($plan['aRamener']) . ' ligne(s) reviendraient ('
            . array_sum($plan['descendants']) . ' entraînée(s) par leur jeton, '
            . $this->resume($plan['enfantsRestes']) . ' resteraient mortes sous un parent rendu), '
            . count($plan['refus']) . ' refusée(s) : ' . $this->resume($plan['refus']) . '.';
    }

    /** Les ids morts de la table choisie, dans l’ordre du plus récent. */
    public function idsMorts(): array
    {
        $modele = $this->modele();
        if ($modele === null) {
            return [];
        }

        // `(new $modele)->getKeyName()` et non `$modele->getKeyName()` : `modele()` renvoie un
        // nom de classe, pas une instance — la flèche dessus est une Error à l'exécution, et
        // `php -l` ne la voit pas.
        return $modele::onlyTrashed()->withoutGlobalScopes()
            ->orderByDesc('deleted_at')->limit(200)
            ->pluck((new $modele)->getKeyName())
            ->map(fn($v) => (string) $v)->all();
    }

    public function requete()
    {
        $modele = $this->modele();

        // Aucune table morte connue : on rend une requête vide sur le premier modèle câblé, parce
        // que Filament exige un Builder et qu'un écran vide vaut mieux qu'une exception.
        $modele ??= $this->modelesCables()[0] ?? null;

        return $modele === null
            ? User::query()->whereRaw('1 = 0')
            : $modele::onlyTrashed()->withoutGlobalScopes();
    }

    public function modele(): ?string
    {
        return $this->inventaire()[$this->choix]['modele']
            ?? ($this->choix === null ? null : ($this->parTable()[$this->choix] ?? null));
    }

    protected function parTable(): array
    {
        static $cache = null;

        if ($cache === null) {
            $cache = [];
            foreach ($this->modelesCables() as $modele) {
                $cache[(new $modele)->getTable()] = $modele;
            }
        }

        return $cache;
    }

    protected function modelesCables(): array
    {
        return $this->etat()->modelesCables();
    }

    /**
     * L'inventaire, l'intitulé et l'ascendant viennent de `corbeille:etat`, jamais d'une copie.
     * `modelesCables()` lit `$this->option('table')` : sans input, Symfony lève la main — d'où le
     * ArrayInput vide, exactement comme le fait `CorbeilleRestaurer::audit()`.
     */
    protected function etat(): CorbeilleEtat
    {
        $etat = new CorbeilleEtat();
        $etat->setLaravel(app());
        $etat->setInput(new ArrayInput([], $etat->getDefinition()));

        return $etat;
    }

    protected function restaurateur(): CorbeilleRestaurer
    {
        return tap(new CorbeilleRestaurer(), fn($c) => $c->setLaravel(app()));
    }

    /**
     * `User` est hors corbeille (décision Q1) : pas de `withTrashed()` ici, la macro n'existe pas.
     * Une seule requête, une table de quelques centaines de lignes — les 70 comptes sans login du
     * parc sont justement ceux dont on veut voir le nom dans « par qui ».
     */
    protected function nomUtilisateur($id): string
    {
        if ($id === null || $id === '') {
            return 'anonyme';
        }

        static $noms = null;

        if ($noms === null) {
            $noms = User::query()->pluck('name', 'id')->all();
        }

        return $noms[(int) $id] ?? '#' . $id;
    }

    protected function refus(string $titre, string $corps): void
    {
        Notification::make()->danger()->title($titre)->body($corps)->send();
    }

    /**
     * Rendun engagement ne rend aucun crédit : `lignes_budgetaires.engage` est une colonne stockée
     * et rien ne la recalculé à la restauration. Le dire ici, c'est le contrat #75 — pas un détail.
     */
    protected function avertirCredits(?string $modele, array $faite): void
    {
        if ($faite === [] || ! is_a((string) $modele, \App\Models\Engagement::class, true)) {
            return;
        }

        Notification::make()->warning()
            ->title('Crédits non rétablis')
            ->body('Engagement(s) rendu(s) : les crédits ne suivent pas tout seuls — vérifiez avec php artisan donnees:correctifs.')
            ->send();
    }

    protected function resume(array $valeurs): string
    {
        if ($valeurs === []) {
            return 'aucun';
        }

        return implode(' · ', array_map(
            fn($v, $k) => is_int($k) ? (string) $v : "{$k}:{$v}",
            $valeurs,
            array_keys($valeurs)
        ));
    }
}
