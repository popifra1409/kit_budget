<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        channels: __DIR__.'/../routes/channels.php',
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        // ── Exclusions CSRF ──────────────────────────────────────
        $middleware->validateCsrfTokens(except: [
            'livewire/update',
            'livewire/upload-file',
            'livewire/preview-file/*',
            'portal/login',
            'budget/login',
            'comptable/login',
            'financier/login',
            'marches/login',
        ]);

        // ── Alias middleware modules ─────────────────────────────
        $middleware->alias([
            'module.access' => \App\Http\Middleware\EnsureModuleAccess::class,
        ]);

        // ── Middlewares web existants ────────────────────────────
        $middleware->web(append: [
            \App\Http\Middleware\CheckUserActive::class,
            \App\Http\Middleware\SessionSecurityMiddleware::class,
            \App\Http\Middleware\AutoLogoutAfterInactivity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Les refus d'accès réellement opposés — `->authorize()` de Filament, `abort(403)`
        // des contrôleurs — passent tous par là. C'est le seul point que les deux chemins
        // traversent : les journaliser écran par écran en laisserait sortir le prochain
        // fichier. `renderable` et non `reportable`, parce que le framework ne signale
        // jamais ces deux exceptions (elles sont dans sa liste internalDontReport).
        $exceptions->renderable(function (Throwable $e, Illuminate\Http\Request $request): void {
            App\Models\ActivityLog::journaliserRefusOppose($e);
        });
    })->create();
