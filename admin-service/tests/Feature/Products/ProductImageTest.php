<?php

namespace Tests\Feature\Products;

use App\Jobs\ProcessProductImage;
use App\Models\Product;
use App\Models\User;
use App\Repositories\ProductImageRepository;
use App\Services\ProductImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Feature\FeatureTestCase;

class ProductImageTest extends FeatureTestCase
{
    private User $admin;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        $this->admin = User::factory()->admin()->create();
        $this->product = Product::factory()->create();
    }

    private function upload(UploadedFile $file): TestResponse
    {
        return $this->actingAs($this->admin)->post("/products/{$this->product->id}/image", ['image' => $file]);
    }

    public function test_an_upload_is_queued_and_the_request_returns_at_once(): void
    {
        Queue::fake();

        $this->upload(UploadedFile::fake()->image('photo.jpg', 1600, 1200))
            ->assertRedirect(route('products.edit', $this->product, absolute: false));

        Queue::assertPushed(ProcessProductImage::class, fn (ProcessProductImage $job) => $job->productId === $this->product->id
            && Storage::disk('local')->exists($job->uploadPath));
        $this->assertNull($this->product->refresh()->image_path);

        $this->actingAs($this->admin)->get("/products/{$this->product->id}/edit")->assertSee('being processed');
    }

    public function test_the_worker_stores_a_webp_and_a_thumbnail(): void
    {
        $this->upload(UploadedFile::fake()->image('photo.jpg', 1600, 1200))->assertSessionHasNoErrors();

        $path = $this->product->refresh()->image_path;
        $this->assertMatchesRegularExpression("#^products/{$this->product->id}/[0-9a-f-]{36}\\.webp$#", $path);

        [$width, $height, $type] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame([1200, 900, IMAGETYPE_WEBP], [$width, $height, $type]);

        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get(Product::thumbnailPathFor($path)));
        $this->assertSame([300, 300], [$width, $height]);

        $this->assertSame([], Storage::disk('local')->allFiles(), 'the original upload is deleted');
        $this->assertFalse(app(ProductImageService::class)->isProcessing($this->product));
        $this->assertStringEndsWith("/storage/{$path}", $this->product->image_url);
    }

    public function test_small_images_are_not_enlarged(): void
    {
        $this->upload(UploadedFile::fake()->image('small.png', 400, 200));

        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($this->product->refresh()->image_path));
        $this->assertSame([400, 200], [$width, $height]);
    }

    public function test_replacing_an_image_deletes_the_old_files(): void
    {
        $this->upload(UploadedFile::fake()->image('first.jpg'));
        $firstPath = $this->product->refresh()->image_path;

        $this->upload(UploadedFile::fake()->image('second.jpg'));

        $this->assertNotSame($firstPath, $this->product->refresh()->image_path);
        Storage::disk('public')->assertMissing([$firstPath, Product::thumbnailPathFor($firstPath)]);
        $this->assertCount(2, Storage::disk('public')->allFiles());
    }

    public function test_an_older_upload_never_overwrites_a_newer_one(): void
    {
        $images = app(ProductImageRepository::class);
        $older = $images->storeUpload(UploadedFile::fake()->image('older.jpg'));
        $newer = $images->storeUpload(UploadedFile::fake()->image('newer.jpg'));
        $images->markPending($this->product->id, $newer);

        app(ProductImageService::class)->process($this->product->id, $older);

        $this->assertNull($this->product->refresh()->image_path);
        Storage::disk('local')->assertMissing($older);
        Storage::disk('local')->assertExists($newer);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_removing_the_image_deletes_files_and_cancels_a_queued_upload(): void
    {
        $this->upload(UploadedFile::fake()->image('photo.jpg'));
        $path = $this->product->refresh()->image_path;

        Queue::fake();
        $this->upload(UploadedFile::fake()->image('queued.jpg'));
        $queued = Queue::pushed(ProcessProductImage::class)->first();

        $this->actingAs($this->admin)->delete("/products/{$this->product->id}/image")->assertRedirect();

        $this->assertNull($this->product->refresh()->image_path);
        Storage::disk('public')->assertMissing([$path, Product::thumbnailPathFor($path)]);

        app(ProductImageService::class)->process($this->product->id, $queued->uploadPath);
        $this->assertNull($this->product->refresh()->image_path, 'the cancelled upload did not bring an image back');
    }

    public function test_a_corrupt_file_fails_the_job_without_retrying_and_cleans_up(): void
    {
        $images = app(ProductImageRepository::class);
        Storage::disk('local')->put('product-image-uploads/broken.jpg', 'definitely not a jpeg');
        $images->markPending($this->product->id, 'product-image-uploads/broken.jpg');

        ProcessProductImage::dispatchSync($this->product->id, 'product-image-uploads/broken.jpg');

        Storage::disk('local')->assertMissing('product-image-uploads/broken.jpg');
        $this->assertNull($images->pendingUpload($this->product->id));
        $this->assertNull($this->product->refresh()->image_path);
    }

    public function test_only_reasonable_images_are_accepted(): void
    {
        $this->upload(UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'))->assertSessionHasErrors('image');
        $this->upload(UploadedFile::fake()->image('huge.jpg')->size(6000))->assertSessionHasErrors('image');
        $this->upload(UploadedFile::fake()->image('wide.png', 9000, 10))->assertSessionHasErrors('image');

        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
