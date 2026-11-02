<?php

namespace Tests\Feature;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class TextInputValidationTest extends TestCase
{
    public function test_description_rules_match_text_column_byte_limits_in_create_and_update_requests(): void
    {
        foreach ([StoreCategoryRequest::class, UpdateCategoryRequest::class, StoreProductRequest::class, UpdateProductRequest::class] as $class) {
            $request = new $class;
            $rules = ['description' => $request->rules()['description']];
            foreach ([str_repeat('a', 65535), str_repeat('é', 32767).'a', null] as $valid) {
                $this->assertTrue(Validator::make(['description' => $valid], $rules)->passes(), $class);
            }
            foreach ([str_repeat('a', 65536), str_repeat('é', 32768), []] as $invalid) {
                $this->assertTrue(Validator::make(['description' => $invalid], $rules)->fails(), $class);
            }
        }
    }

    public function test_oversized_descriptions_return_http_validation_errors_before_writes(): void
    {
        $admin = new User(['name' => 'Admin']);
        $admin->id = 1;
        $admin->role = 'ADMIN';
        $this->actingAs($admin, 'web');
        foreach (['/api/admin/categories', '/api/admin/products'] as $path) {
            $this->postJson($path, ['description' => str_repeat('é', 32768)])
                ->assertUnprocessable()->assertJsonValidationErrors('description');
        }
    }
}
