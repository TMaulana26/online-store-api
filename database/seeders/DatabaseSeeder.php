<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Acl\Database\Seeders\AclDatabaseSeeder;
use Modules\Store\Database\Seeders\StoreDatabaseSeeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AclDatabaseSeeder::class,
            StoreDatabaseSeeder::class,
        ]);
    }
}
