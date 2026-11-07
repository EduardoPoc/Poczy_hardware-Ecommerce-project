<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

abstract class DemoSeeder extends Seeder
{
    final public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Demo seeders are restricted to local and testing environments.');
        }
        $this->seedDemoData();
    }

    abstract protected function seedDemoData(): void;
}
