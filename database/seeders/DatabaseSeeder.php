<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Fresh installs should use the browser setup wizard at /setup.
     * Run DemoSeeder manually if you want sample data: php artisan db:seed --class=DemoSeeder
     */
    public function run(): void
    {
        $this->command?->info('No default users seeded. Visit /setup to create the administrator account.');
    }
}
