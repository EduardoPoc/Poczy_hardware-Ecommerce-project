<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends DemoSeeder
{
    protected function seedDemoData(): void
    {
        DB::transaction(function (): void {
            $this->call([ProductSeeder::class, UserSeeder::class, DemoOrdersSeeder::class]);
        });
    }
}
