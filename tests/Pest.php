<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\UserSeeder;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/** @return array<string, mixed> */
function workerFixture(string $name): array
{
    return json_decode((string) file_get_contents(__DIR__."/Fixtures/worker/{$name}.json"), true, flags: JSON_THROW_ON_ERROR);
}

/** @param  array<string, mixed>|callable|PromiseInterface  $response */
function fakeWorker(mixed $response): void
{
    Http::fake(['*/v1/extract' => is_array($response) ? Http::response($response) : $response]);
}

function seedBasics(): void
{
    Storage::fake('local');
    (new CategorySeeder)->run();
    (new UserSeeder)->run();
}

function owner(): User
{
    return User::role(Role::Owner->value)->firstOrFail();
}

function accountant(): User
{
    return User::role(Role::Accountant->value)->firstOrFail();
}
