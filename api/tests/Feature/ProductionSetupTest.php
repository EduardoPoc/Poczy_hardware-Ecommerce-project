<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductionSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_images_are_published_and_reruns_preserve_custom_uploads_and_product_data(): void
    {
        Storage::fake('public');
        $this->seed(ProductSeeder::class);
        $products = Product::orderBy('id')->get();
        $this->assertCount(48, $products);
        $this->assertCount(48, Storage::disk('public')->files('products'));
        foreach ($products as $index => $product) {
            $path = 'products/prod'.($index + 1).'.jpg';
            $this->assertSame($path, $product->image_path);
            Storage::disk('public')->assertExists($path);
            $contents = Storage::disk('public')->get($path);
            $this->assertSame(file_get_contents(database_path('seeders/images/'.$path)), $contents);
            $this->assertSame('image/jpeg', getimagesizefromstring($contents)['mime']);
        }

        $movements = DB::table('stock_movements')->count();
        $products[0]->update(['image_path' => null, 'price' => '9.99', 'stock' => 1]);
        $customPath = 'products/uploaded-custom.jpg';
        $customContents = Storage::disk('public')->get('products/prod2.jpg');
        Storage::disk('public')->put($customPath, $customContents);
        $products[1]->update(['image_path' => $customPath]);
        $products[2]->update(['image_path' => 'products/prod1.jpg']);
        $this->seed(ProductSeeder::class);

        $this->assertSame('products/prod1.jpg', $products[0]->fresh()->image_path);
        $this->assertSame('9.99', $products[0]->fresh()->price);
        $this->assertSame(1, $products[0]->fresh()->stock);
        $this->assertSame($customPath, $products[1]->fresh()->image_path);
        $this->assertSame($customContents, Storage::disk('public')->get($customPath));
        $this->assertSame('products/prod3.jpg', $products[2]->fresh()->image_path);
        $this->assertDatabaseCount('products', 48);
        $this->assertSame($movements, DB::table('stock_movements')->count());
        $this->getJson('/api/products/'.$products[0]->id)->assertOk()
            ->assertJsonPath('data.image_url', '/storage/products/prod1.jpg');
    }

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
