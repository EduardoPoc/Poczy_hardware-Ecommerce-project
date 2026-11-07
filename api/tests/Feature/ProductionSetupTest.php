<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductionSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_orders_and_carts_are_complete_and_seeding_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $before = [DB::table('orders')->count(), DB::table('cart_items')->count(), DB::table('stock_movements')->count()];
        $this->assertEqualsCanonicalizing(['PENDING', 'PAID', 'SHIPPED', 'DELIVERED', 'CANCELLED'], DB::table('orders')->pluck('status')->all());
        $this->assertTrue(DB::table('carts')->whereNull('user_id')->exists());
        $this->seed(DatabaseSeeder::class);
        $this->assertSame($before, [DB::table('orders')->count(), DB::table('cart_items')->count(), DB::table('stock_movements')->count()]);
    }

    public function test_admin_password_is_prompted_and_hashed_without_demo_credentials(): void
    {
        $this->artisan('app:create-admin', ['--name' => 'Production admin', '--email' => 'prod@example.com'])
            ->expectsQuestion('Senha (mínimo 12 caracteres, maiúsculas, minúsculas, número e símbolo)', 'SenhaSegura!2026')
            ->expectsQuestion('Confirme a senha', 'SenhaSegura!2026')
            ->assertSuccessful();
        $admin = User::where('email', 'prod@example.com')->sole();
        $this->assertSame('ADMIN', $admin->role);
        $this->assertTrue(Hash::check('SenhaSegura!2026', $admin->password));
    }
}
