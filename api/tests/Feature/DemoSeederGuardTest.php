<?php

namespace Tests\Feature;

use Database\Seeders\CatalogSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoOrdersSeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\UserSeeder;
use RuntimeException;
use Tests\TestCase;

class DemoSeederGuardTest extends TestCase
{
    public function test_demo_seeders_fail_before_accessing_production_data(): void
    {
        $this->app->instance('env', 'production');
        foreach ([DatabaseSeeder::class, CatalogSeeder::class, ProductSeeder::class, UserSeeder::class, DemoOrdersSeeder::class] as $class) {
            try {
                (new $class)->run();
                $this->fail('Demo data must not be seeded in production.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Demo seeders are restricted to local and testing environments.', $exception->getMessage());
            }
        }
    }

    public function test_noninteractive_admin_creation_cannot_install_a_default_password(): void
    {
        $this->artisan('app:create-admin', ['--no-interaction' => true])->assertFailed();
    }
}
