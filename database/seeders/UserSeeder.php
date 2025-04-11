<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (Role::cases() as $role) {
            RoleModel::findOrCreate($role->value, 'web');
        }

        $people = [
            [config('invoices.demo.owner_email'), 'Marina Volkova', Role::Owner, 'ru'],
            [config('invoices.demo.accountant_email'), 'Elena Lebedeva', Role::Accountant, 'ru'],
        ];
        foreach ($people as [$email, $name, $role, $locale]) {
            $user = User::query()->firstOrCreate(['email' => $email], [
                'name' => $name,
                'locale' => $locale,
                // demo accounts sign in with the buttons on the login page, not with a password
                'password' => Str::password(32),
            ]);
            $user->syncRoles([$role->value]);
        }
    }
}
