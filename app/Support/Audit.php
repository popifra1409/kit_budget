<?php
// app/Support/Audit.php

namespace App\Support;

use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Point d'entree unique de l'audit : a quel module appartient un modele,
 * quel est son libelle, faut-il l'observer, quelles formes son type prend en base.
 * Toutes les informations viennent de config/audit.php.
 */
class Audit
{
    /** @var array<string, array{module: string, libelle: string}>|null */
    protected static ?array $index = null;

    /** Index classe => [module, libelle], construit une seule fois par requete. */
    public static function index(): array
    {
        if (static::$index !== null) {
            return static::$index;
        }

        static::$index = [];

        foreach (config('audit.modules', []) as $module => $definition) {
            foreach ($definition['modeles'] ?? [] as $classe => $libelle) {
                static::$index[$classe] = ['module' => $module, 'libelle' => $libelle];
            }
        }

        return static::$index;
    }

    /** Classe complete a partir d'un type stocke (alias de morph map ou nom complet). */
    public static function classeDepuisType(?string $type): ?string
    {
        if (blank($type)) {
            return null;
        }

        return Relation::getMorphedModel($type) ?? $type;
    }

    public static function moduleDe(?string $classeOuType): ?string
    {
        $classe = static::classeDepuisType($classeOuType);

        return $classe ? (static::index()[$classe]['module'] ?? null) : null;
    }

    public static function libelleModule(?string $module): ?string
    {
        return $module ? (config("audit.modules.{$module}.libelle") ?? $module) : null;
    }

    public static function libelleModele(?string $classeOuType): ?string
    {
        $classe = static::classeDepuisType($classeOuType);

        return $classe ? (static::index()[$classe]['libelle'] ?? null) : null;
    }

    /** Options du filtre "Module" : cle => libelle. */
    public static function optionsModules(): array
    {
        return collect(config('audit.modules', []))
            ->mapWithKeys(fn($definition, $module) => [$module => $definition['libelle'] ?? $module])
            ->all();
    }

    /** Options du filtre "Type de document", groupees par module. */
    public static function optionsModelesParModule(): array
    {
        return collect(config('audit.modules', []))
            ->mapWithKeys(fn($definition, $module) => [
                ($definition['libelle'] ?? $module) => collect($definition['modeles'] ?? [])->sort()->all(),
            ])
            ->all();
    }

    /** Toutes les formes possibles d'un type en base : nom complet + alias de morph map. */
    public static function formesDuType(string $classe): array
    {
        $alias = array_search($classe, Relation::morphMap(), true);

        return array_values(array_filter([$classe, $alias ?: null]));
    }

    /** Toutes les valeurs de subject_type correspondant aux modeles d'un module. */
    public static function typesDuModule(string $module): array
    {
        return collect(config("audit.modules.{$module}.modeles", []))
            ->keys()
            ->flatMap(fn($classe) => static::formesDuType($classe))
            ->unique()
            ->values()
            ->all();
    }

    /** Resultats de chargement deja connus (evite de retenter un fichier defectueux). */
    protected static array $chargeables = [];

    /**
     * La classe existe-t-elle ET son fichier est-il lisible par PHP ?
     * ⚠️ Ne leve JAMAIS d'exception : un fichier de modele defectueux (erreur de syntaxe)
     *    ne doit pas empecher l'application de demarrer. Il est simplement ignore par
     *    l'audit et signale dans le log et par 'php artisan audit:couverture'.
     */
    public static function classeChargeable(string $classe): bool
    {
        if (array_key_exists($classe, static::$chargeables)) {
            return static::$chargeables[$classe];
        }

        try {
            return static::$chargeables[$classe] = class_exists($classe);
        } catch (\Throwable $e) {
            Log::error("Audit : modèle ignoré, fichier illisible ({$classe})", [
                'erreur'  => $e->getMessage(),
                'fichier' => $e->getFile(),
                'ligne'   => $e->getLine(),
            ]);

            return static::$chargeables[$classe] = false;
        }
    }

    public static function estJournaliseNativement(string $classe): bool
    {
        return static::classeChargeable($classe)
            && in_array(LogsActivity::class, class_uses_recursive($classe), true);
    }

    /**
     * Le modele peut-il etre rattache a une entree du journal ?
     * Avec Relation::enforceMorphMap(), un modele absent de la morph map
     * ne peut pas etre journalise (ClassMorphViolationException).
     */
    public static function estDansMorphMap(string $classe): bool
    {
        return !Relation::requiresMorphMap()
            || array_search($classe, Relation::morphMap(), true) !== false;
    }

    public static function estExclu(string $classe): bool
    {
        return in_array($classe, config('audit.modeles_exclus', []), true);
    }

    /** Modeles a observer : listes, existants, non exclus, pas deja journalises nativement. */
    public static function classesAObserver(): array
    {
        return collect(static::index())
            ->keys()
            ->filter(fn($classe) => static::classeChargeable($classe)
                && is_subclass_of($classe, Model::class)
                && !static::estExclu($classe)
                && !static::estJournaliseNativement($classe))
            ->values()
            ->all();
    }

    /** A appeler une fois, dans AppServiceProvider::boot(). */
    public static function enregistrerObservateurs(): void
    {
        try {
            foreach (static::classesAObserver() as $classe) {
                $classe::observe(AuditObserver::class);
            }
        } catch (\Throwable $e) {
            // L'audit ne doit jamais empecher l'application de demarrer
            Log::error("Audit : enregistrement des observateurs interrompu", ['erreur' => $e->getMessage()]);
        }
    }

    public static function doitJournaliser(): bool
    {
        return !app()->runningInConsole() || (bool) config('audit.journaliser_console', false);
    }
}
