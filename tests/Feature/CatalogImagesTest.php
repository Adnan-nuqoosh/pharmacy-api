<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\CatalogImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class CatalogImagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
    }

    private function fields(string $resource): array
    {
        $data = ['name' => 'Catalog '.uniqid(), 'is_active' => '1'];
        if ($resource === 'products') {
            $category = Category::create(['name' => 'Parent', 'slug' => uniqid('parent-')]);
            $data += ['category_id' => $category->id, 'price' => '25'];
        }

        return $data;
    }

    public function test_create_replace_preserve_remove_and_public_display_for_all_resources(): void
    {
        foreach (['products' => 'product', 'categories' => 'category', 'brands' => 'brand'] as $resource => $key) {
            $base = '/api/admin/'.$resource;
            $created = $this->post($base, $this->fields($resource) + ['image' => UploadedFile::fake()->image('first.jpg')], ['Accept' => 'application/json'])
                ->assertCreated()->json('data.'.$key);
            $id = $created['id'];
            Storage::disk('public')->assertExists($created['image']);
            $this->assertStringStartsWith('http', $created['image_url']);
            $this->getJson('/api/'.$resource.'/'.$created['slug'])->assertOk()->assertJsonPath('data.'.$key.'.image_url', $created['image_url']);
            $this->patchJson($base.'/'.$id, ['name' => 'Edited '.$id])->assertOk()->assertJsonPath('data.'.$key.'.image', $created['image']);
            $this->post($base.'/'.$id, ['image' => ''], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.'.$key.'.image', $created['image']);
            $updated = $this->post($base.'/'.$id, ['image' => UploadedFile::fake()->image('next.png')], ['Accept' => 'application/json'])
                ->assertOk()->json('data.'.$key);
            Storage::disk('public')->assertMissing($created['image']);
            Storage::disk('public')->assertExists($updated['image']);
            $this->patchJson($base.'/'.$id, ['remove_image' => true])->assertOk()->assertJsonPath('data.'.$key.'.image_url', null);
            Storage::disk('public')->assertMissing($updated['image']);
        }
    }

    public function test_legacy_category_icon_and_brand_logo_aliases(): void
    {
        $category = $this->post('/api/admin/categories', ['name' => 'Legacy', 'icon' => UploadedFile::fake()->image('icon.png')], ['Accept' => 'application/json'])
            ->assertCreated()->json('data.category');
        $this->assertSame($category['icon'], $category['image']);
        $this->assertSame($category['icon_url'], $category['image_url']);
        $this->post('/api/admin/brands', ['name' => 'Logo', 'logo' => UploadedFile::fake()->image('logo.png')], ['Accept' => 'application/json'])->assertCreated();
    }

    public function test_validation_rejects_wrong_type_oversize_and_conflicting_uploads_without_files_left_behind(): void
    {
        foreach (['products', 'categories', 'brands'] as $resource) {
            $base = '/api/admin/'.$resource;
            foreach ([UploadedFile::fake()->create('script.php', 1, 'text/plain'), UploadedFile::fake()->image('large.jpg')->size(5121)] as $file) {
                $this->post($base, $this->fields($resource) + ['image' => $file], ['Accept' => 'application/json'])->assertStatus(422);
            }
            $this->post($base, $this->fields($resource) + ['image' => UploadedFile::fake()->image('file.jpg'), 'remove_image' => '1'], ['Accept' => 'application/json'])->assertStatus(422);
        }
        $this->post('/api/admin/categories', ['name' => 'Both', 'image' => UploadedFile::fake()->image('one.jpg'), 'icon' => UploadedFile::fake()->image('two.jpg')], ['Accept' => 'application/json'])->assertStatus(422);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_brand_linking_filtering_unlinking_and_delete_protection(): void
    {
        $brand = Brand::create(['name' => 'Acme', 'slug' => 'acme']);
        $product = $this->postJson('/api/admin/products', $this->fields('products') + ['brand_id' => $brand->id])
            ->assertCreated()->assertJsonPath('data.product.brand.name', 'Acme')->assertJsonPath('data.product.brand_name', 'Acme')->json('data.product');
        $this->getJson('/api/products?brand_id='.$brand->id)->assertOk()->assertJsonCount(1, 'data.products.data');
        $this->deleteJson('/api/admin/brands/'.$brand->id)->assertStatus(422);
        $this->patchJson('/api/admin/products/'.$product['id'], ['brand_id' => 999999])->assertStatus(422);
        $this->patchJson('/api/admin/products/'.$product['id'], ['brand_id' => null])->assertOk()->assertJsonPath('data.product.brand_name', null);
        $this->deleteJson('/api/admin/brands/'.$brand->id)->assertOk();
        $this->assertDatabaseHas('products', ['id' => $product['id']]);
    }

    public function test_inactive_brands_are_hidden_from_public_catalog(): void
    {
        Brand::create(['name' => 'Hidden', 'slug' => 'hidden', 'is_active' => false]);
        $this->getJson('/api/brands')->assertOk()->assertJsonCount(0, 'data.brands');
        $this->getJson('/api/brands/hidden')->assertNotFound();
    }

    public function test_customer_cannot_upload_or_manage_catalog(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => false]));
        foreach (['products', 'categories', 'brands'] as $resource) {
            $this->postJson('/api/admin/'.$resource, ['name' => 'Unauthorized'])->assertForbidden();
        }
    }

    public function test_unauthenticated_upload_is_rejected(): void
    {
        auth()->forgetGuards();
        $this->postJson('/api/admin/brands', ['name' => 'Unauthorized'])->assertUnauthorized();
    }

    public function test_existing_absolute_urls_and_null_images_are_preserved(): void
    {
        $category = Category::create(['name' => 'Old', 'slug' => 'old', 'icon' => 'https://example.com/old.png']);
        $this->assertSame('https://example.com/old.png', $category->image_url);
        $this->patchJson('/api/admin/categories/'.$category->id, ['name' => 'Renamed'])->assertOk()->assertJsonPath('data.category.image_url', 'https://example.com/old.png');
        $this->assertNull((new Product)->image_url);
    }

    public function test_duplicate_generated_slug_returns_validation_error(): void
    {
        foreach (['products', 'categories', 'brands'] as $resource) {
            $fields = $this->fields($resource);
            $this->postJson('/api/admin/'.$resource, $fields)->assertCreated();
            $this->postJson('/api/admin/'.$resource, $fields)->assertStatus(422);
        }
    }

    public function test_database_failure_keeps_old_file_and_removes_new_upload(): void
    {
        Storage::disk('public')->put('brands/old.png', 'old');
        $brand = \Mockery::mock(Brand::class)->makePartial();
        $brand->image = 'brands/old.png';
        $brand->shouldReceive('save')->once()->andThrow(new RuntimeException('Simulated database failure'));
        $request = Request::create('/api/admin/brands/1', 'POST', [], [], ['image' => UploadedFile::fake()->image('new.png')]);
        try {
            CatalogImage::save($brand, $request, [], 'brands');
            $this->fail('Expected save failure');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated database failure', $exception->getMessage());
        }
        $this->assertSame(['brands/old.png'], Storage::disk('public')->allFiles('brands'));
    }
}
