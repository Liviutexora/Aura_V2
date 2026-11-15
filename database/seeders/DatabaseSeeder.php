<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('local'), 403, 'Demo seeding is local-only.');

        $userPassword = env('AURA_DEMO_USER_PASSWORD');
        $adminPassword = env('AURA_DEMO_ADMIN_PASSWORD');
        if (! $userPassword || ! $adminPassword) {
            throw new \RuntimeException('Set AURA_DEMO_USER_PASSWORD and AURA_DEMO_ADMIN_PASSWORD in the local .env before seeding.');
        }

        foreach ([
            ['name' => 'Liviu', 'email' => 'liviu@exemple.ro', 'role' => 'user', 'password' => $userPassword],
            ['name' => 'Liviu Admin', 'email' => 'liviuadmin@exemple.ro', 'role' => 'super_admin', 'password' => $adminPassword],
        ] as $definition) {
            $user = User::firstOrNew(['email' => strtolower($definition['email'])]);
            $user->name ??= $definition['name'];
            $user->role ??= $definition['role'];
            $user->status ??= 'active';
            $user->referral_code ??= $this->referralCode($user->name);
            if (! $user->exists) {
                $user->password = Hash::make($definition['password']);
            }
            $user->save();
            $user->profile()->firstOrCreate([]);
        }
    }

    private function referralCode(string $name): string
    {
        $firstName = Str::of(explode(' ', trim($name))[0])->ascii()->replaceMatches('/[^A-Za-z0-9]/', '')->value() ?: 'Aura';
        do {
            $code = $firstName.Str::upper(Str::random(5));
        } while (User::where('referral_code', $code)->exists());

        return $code;
    }
}
