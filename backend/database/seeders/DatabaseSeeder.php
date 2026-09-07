<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database. The demo dataset (PRD §56) is only
     * created when the Merlion tenant does not exist yet, so `migrate --seed`
     * stays idempotent on repeat runs.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
        ]);

        if (! User::query()->where('email', 'admin@demo.opsflow.local')->exists()) {
            $this->call(DemoSeeder::class);
        }

        $org = Organization::query()->where('slug', 'merlion-digital')->first()
            ?? Organization::query()->firstOrCreate(
                ['slug' => 'demo'],
                ['name' => 'Demo Organization', 'country_code' => 'SG', 'timezone' => 'Asia/Singapore', 'currency' => 'SGD', 'status' => 'ACTIVE'],
            );

        User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'organization_id' => $org->id, 'status' => 'ACTIVE'],
        );
    }
}
