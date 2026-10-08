<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy;

use Illuminate\Support\ServiceProvider;

/**
 * One policy and one tool locator per process. The switches come from the
 * config unless the program binds its own SwitchStore (Anton: its tenant
 * settings); a program serving several tenants passes one per tenant to
 * Preingest::withSwitches().
 */
final class FormatPolicyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/format-policy.php', 'format-policy');

        $this->app->singleton(FormatPolicyRepository::class, fn (): FormatPolicyRepository => new ConfigFormatPolicyRepository);
        $this->app->singleton(ToolLocator::class);
        $this->app->singletonIf(SwitchStore::class, fn (): SwitchStore => new ArraySwitchStore((array) config('format-policy.switches', [])));
        $this->app->when(Preingest::class)->needs('$workRoot')->give(
            fn (): string => (string) (config('format-policy.work_dir') ?? storage_path('framework/preingest')),
        );
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/format-policy.php' => config_path('format-policy.php')], 'format-policy-config');
    }
}
