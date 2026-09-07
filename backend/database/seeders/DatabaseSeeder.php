<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Permission rows are global (not tenant-scoped) and the catalog is
        // idempotent, so it is safe to seed for every environment. Organization
        // SLA defaults are deliberately skipped here — Phase 10 owns rich
        // seeding and orgs create their own policies/calendars via the API.
        $this->call([
            PermissionSeeder::class,
        ]);

        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
