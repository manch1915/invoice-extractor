<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Validation\DocumentValidator;
use App\Enums\Role;
use App\Infrastructure\Integrations\GoogleSheets;
use App\Infrastructure\Integrations\Xero;
use App\Infrastructure\Worker\WorkerClient;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Originals and page images live on one disk; swap it for S3 in config, not in code.
        $this->app->bind(Filesystem::class, fn () => Storage::disk((string) config('invoices.storage_disk')));

        $this->app->singleton(DocumentValidator::class, fn () => new DocumentValidator);

        $this->app->bind(WorkerClient::class, fn ($app) => new WorkerClient(
            $app->make(Http::class),
            (string) config('invoices.worker.url'),
            (string) config('invoices.worker.token'),
            (int) config('invoices.worker.timeout'),
            (string) config('invoices.worker.engine'),
        ));

        $this->app->bind(GoogleSheets::class, fn ($app) => new GoogleSheets(
            $app->make(Http::class),
            (string) config('invoices.google.client_id'),
            (string) config('invoices.google.client_secret'),
        ));
        $this->app->bind(Xero::class, fn ($app) => new Xero(
            $app->make(Http::class),
            (string) config('invoices.xero.client_id'),
            (string) config('invoices.xero.client_secret'),
        ));
    }

    public function boot(): void
    {
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
        Model::preventAccessingMissingAttributes(! $this->app->isProduction());

        Gate::define('manage-settings', fn (User $user) => $user->hasRole(Role::Owner->value));
        Gate::define('delete-documents', fn (User $user) => $user->hasRole(Role::Owner->value));
    }
}
