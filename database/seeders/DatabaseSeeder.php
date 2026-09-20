<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * `php artisan migrate:fresh --seed` gives a working demo: reference data, 15 people (see UserSeeder for the logins), 35
 * assets, ~70 repair requests in every status, and the Livechat board.
 *
 * Production only ever gets the reference data — never demo accounts with well-known passwords. The demo seeders create
 * rows rather than look for existing ones: run them on an empty database (`migrate:fresh --seed`).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ReferenceDataSeeder::class);

        if (app()->isProduction()) {
            $this->command?->warn('Production: seeded the reference data only (no demo users, assets, requests or chat).');

            return;
        }

        $this->call([
            UserSeeder::class,
            DemoDataSeeder::class,
            ChatSeeder::class,
        ]);
    }
}
