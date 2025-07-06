<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;

class ResetDemo extends Command
{
    protected $signature = 'demo:reset {--force : run outside demo mode}';

    protected $description = 'Wipe the workspace and load the sample documents again';

    public function handle(Filesystem $disk): int
    {
        if (! config('invoices.demo.enabled') && ! $this->option('force')) {
            $this->error('Demo mode is off; pass --force to wipe anyway.');

            return self::FAILURE;
        }

        foreach (['originals', 'pages', 'exports'] as $directory) {
            $disk->deleteDirectory($directory);
        }
        $this->call('migrate:fresh', ['--seed' => true, '--force' => true]);

        return $this->call('demo:seed');
    }
}
