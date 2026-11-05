<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductImageUploadTest extends TestCase
{
    use DatabaseMigrations;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC';

    private const JPEG = '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwDi6KKK+ZP3E//Z';

    private const WEBP = 'UklGRjwAAABXRUJQVlA4IDAAAADQAQCdASoBAAEAAUAmJaACdLoB+AADsAD+8ut//NgVzXPv9//S4P0uD9Lg/9KQAAA=';

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $admin = User::create(['name' => 'Admin', 'email' => 'images@example.com', 'password' => 'SenhaAdmin!2026']);
        $admin->forceFill(['role' => 'ADMIN'])->save();
        $this->actingAs($admin, 'web');
        $this->category = Category::create(['name' => 'Componentes']);
    }

    private function upload(string $name, string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'poczy-image-');
        file_put_contents($path, $contents);
        $this->beforeApplicationDestroyed(function () use ($path): void {
            unlink($path);
        });

        return new UploadedFile($path, $name, null, UPLOAD_ERR_OK, true);
    }

    /** @return array<string, mixed> */
    private function productData(): array
    {
        return ['category_id' => $this->category->id, 'name' => 'Produto com imagem', 'price' => '99.90', 'stock' => 0];
    }

    /** @return array<string, array{string, string, string}> */
    public static function imageFormats(): array
    {
        return [
            'png' => ['produto.png', self::PNG, 'image/png'],
            'jpg' => ['produto.jpg', self::JPEG, 'image/jpeg'],
            'jpeg' => ['produto.jpeg', self::JPEG, 'image/jpeg'],
            'webp' => ['produto.webp', self::WEBP, 'image/webp'],
        ];
    }

    #[DataProvider('imageFormats')]
    public function test_upload_stores_supported_images_and_only_the_relative_path_in_the_database(string $name, string $encoded, string $mime): void
    {
        $contents = base64_decode($encoded);
        $image = $this->upload($name, $contents);
        $this->assertSame($mime, $image->getMimeType());
        $id = $this->post('/api/admin/products', $this->productData() + ['image' => $image], ['Accept' => 'application/json'])
            ->assertCreated()->json('data.id');
        $product = Product::findOrFail($id);

        $this->assertMatchesRegularExpression('/^products\/[a-zA-Z0-9]{40}\.(png|jpe?g|webp)$/', $product->image_path);
        $this->assertFalse(Schema::hasColumn('products', 'image_url'));
        Storage::disk('public')->assertExists($product->image_path);
        $this->assertSame($contents, Storage::disk('public')->get($product->image_path));
        $this->getJson('/api/products/'.$id)->assertOk()->assertJsonPath('data.image_url', '/storage/'.$product->image_path);
    }

    public function test_multipart_update_preserves_typed_specifications_and_replaces_the_previous_file(): void
    {
        $this->category->update(['attributes' => [
            'wireless' => ['name' => 'Sem fio', 'type' => 'boolean'],
            'capacity' => ['name' => 'Capacidade', 'type' => 'integer'],
        ]]);
        $id = $this->post('/api/admin/products', $this->productData() + ['image' => $this->upload('original.png', base64_decode(self::PNG))])
            ->assertCreated()->json('data.id');
        $previousPath = Product::findOrFail($id)->image_path;
        $this->patchJson('/api/admin/products/'.$id, ['name' => 'Nome atualizado'])->assertOk();
        $this->assertSame($previousPath, Product::findOrFail($id)->image_path);

        $this->post('/api/admin/products/'.$id, [
            '_method' => 'PATCH',
            'image' => $this->upload('nova.webp', base64_decode(self::WEBP)),
            'specifications' => json_encode(['wireless' => false, 'capacity' => 0]),
            'is_active' => '0',
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('data.specifications.wireless', false)
            ->assertJsonPath('data.specifications.capacity', 0)
            ->assertJsonPath('data.is_active', false);

        $path = Product::findOrFail($id)->image_path;
        $this->assertNotSame($previousPath, $path);
        Storage::disk('public')->assertMissing($previousPath);
        Storage::disk('public')->assertExists($path);
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    public function test_invalid_formats_disguised_files_and_oversized_images_are_rejected(): void
    {
        foreach ([
            $this->upload('falsa.png', 'isto nao e uma imagem'),
            $this->upload('animacao.gif', base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7')),
            $this->upload('arquivo.php', base64_decode(self::PNG)),
            $this->upload('grande.png', base64_decode(self::PNG).str_repeat("\0", 5120 * 1024)),
        ] as $image) {
            $this->post('/api/admin/products', $this->productData() + ['image' => $image], ['Accept' => 'application/json'])
                ->assertUnprocessable()->assertJsonValidationErrors('image');
        }
        $this->assertDatabaseCount('products', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_clients_cannot_supply_urls_or_choose_a_storage_path(): void
    {
        foreach ([
            ['image_url' => 'https://example.com/image.png'],
            ['image_path' => '../private/file.png'],
            ['image' => 'https://example.com/image.png'],
        ] as $input) {
            $this->postJson('/api/admin/products', $this->productData() + $input)
                ->assertUnprocessable()->assertJsonValidationErrors(array_keys($input));
        }
        $this->assertDatabaseCount('products', 0);
    }

    public function test_failed_writes_remove_new_files_and_preserve_the_existing_image(): void
    {
        $invalid = ['specifications' => ['unknown_attribute' => true]];
        $this->post('/api/admin/products', $this->productData() + $invalid + ['image' => $this->upload('nova.png', base64_decode(self::PNG))], ['Accept' => 'application/json'])
            ->assertUnprocessable();
        $this->assertSame([], Storage::disk('public')->allFiles());

        $id = $this->post('/api/admin/products', $this->productData() + ['image' => $this->upload('original.png', base64_decode(self::PNG))])
            ->assertCreated()->json('data.id');
        $path = Product::findOrFail($id)->image_path;
        $this->post('/api/admin/products/'.$id, ['_method' => 'PATCH', 'image' => $this->upload('nova.webp', base64_decode(self::WEBP))] + $invalid, ['Accept' => 'application/json'])
            ->assertUnprocessable();
        $this->assertSame($path, Product::findOrFail($id)->image_path);
        $this->assertSame([$path], Storage::disk('public')->allFiles());
    }

    public function test_product_deletion_removes_the_file_only_when_deletion_succeeds(): void
    {
        $id = $this->post('/api/admin/products', $this->productData() + ['image' => $this->upload('original.png', base64_decode(self::PNG))])
            ->assertCreated()->json('data.id');
        $path = Product::findOrFail($id)->image_path;
        $this->deleteJson('/api/admin/products/'.$id)->assertNoContent();
        Storage::disk('public')->assertMissing($path);

        $data = $this->productData();
        $data['stock'] = 1;
        $id = $this->post('/api/admin/products', $data + ['image' => $this->upload('historico.png', base64_decode(self::PNG))])
            ->assertCreated()->json('data.id');
        $path = Product::findOrFail($id)->image_path;
        $this->deleteJson('/api/admin/products/'.$id)->assertConflict();
        Storage::disk('public')->assertExists($path);
    }

    public function test_image_column_rename_is_reversible_and_preserves_existing_values(): void
    {
        $product = Product::create($this->productData() + ['image_path' => 'products/existente.png']);
        $migration = require database_path('migrations/2026_11_05_105642_rename_image_url_to_image_path_on_products_table.php');
        $migration->down();
        $this->assertSame('products/existente.png', DB::table('products')->where('id', $product->id)->value('image_url'));
        $migration->up();
        $this->assertSame('products/existente.png', $product->fresh()->image_path);
    }
}
