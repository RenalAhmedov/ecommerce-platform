<?php

namespace Tests\Unit\Services;

use App\Jobs\ProcessProductImage;
use App\Models\Product;
use App\Repositories\ProductImageRepository;
use App\Repositories\ProductRepository;
use App\Services\CatalogNotifier;
use App\Services\ProductImageService;
use App\Support\ImageResizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Mockery\MockInterface;
use Tests\Unit\UnitTestCase;

class ProductImageServiceTest extends UnitTestCase
{
    private CatalogNotifier&MockInterface $catalog;

    private const UPLOAD = 'product-image-uploads/new.jpg';

    private ProductRepository&MockInterface $products;

    private ProductImageRepository&MockInterface $images;

    private ImageResizer&MockInterface $resizer;

    private ProductImageService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->products = $this->mock(ProductRepository::class);
        $this->images = $this->mock(ProductImageRepository::class);
        $this->resizer = $this->mock(ImageResizer::class);
        $this->catalog = $this->mock(CatalogNotifier::class);
        $this->catalog->shouldReceive('catalogChanged')->byDefault();
        $this->service = app(ProductImageService::class);
    }

    private function product(?string $imagePath = null): Product
    {
        $product = Product::factory()->make(['id' => 42]);
        $product->image_path = $imagePath;
        $product->syncOriginal();

        return $product;
    }

    private function givenResizedImages(): void
    {
        $this->resizer->shouldReceive('fitAsWebp')->once()->with('original-bytes', 1200)->andReturn('main-bytes');
        $this->resizer->shouldReceive('squareAsWebp')->once()->with('original-bytes', 300)->andReturn('thumb-bytes');
        $this->images->shouldReceive('storeImage')->once()->with(42, 'main-bytes', 'thumb-bytes')->andReturn('products/42/new.webp');
    }

    public function test_upload_stores_the_original_marks_it_newest_and_queues_processing(): void
    {
        Bus::fake();
        $file = UploadedFile::fake()->create('photo.jpg', 10, 'image/jpeg');
        $this->images->shouldReceive('storeUpload')->once()->with($file)->andReturn(self::UPLOAD);
        $this->images->shouldReceive('markPending')->once()->with(42, self::UPLOAD);

        $this->service->upload($this->product(), $file);

        Bus::assertDispatched(ProcessProductImage::class, fn (ProcessProductImage $job) => $job->productId === 42 && $job->uploadPath === self::UPLOAD);
    }

    public function test_processing_swaps_the_image_and_cleans_up_old_files(): void
    {
        $product = $this->product('products/42/old.webp');
        $this->images->shouldReceive('readUpload')->with(self::UPLOAD)->andReturn('original-bytes');
        $this->images->shouldReceive('pendingUpload')->with(42)->andReturn(self::UPLOAD);
        $this->givenResizedImages();
        $this->products->shouldReceive('lockByIdWithTrashed')->with(42)->andReturn($product);
        $this->products->shouldReceive('save')->once()->with($product);
        $this->images->shouldReceive('clearPending')->once()->with(42);
        $this->images->shouldReceive('deleteImage')->once()->with('products/42/old.webp');
        $this->images->shouldReceive('deleteUpload')->once()->with(self::UPLOAD);
        $this->catalog->shouldReceive('catalogChanged')->once()->with('product image changed');

        $this->service->process(42, self::UPLOAD);

        $this->assertSame('products/42/new.webp', $product->image_path);
    }

    public function test_an_upload_that_is_no_longer_the_newest_is_dropped_untouched(): void
    {
        $this->images->shouldReceive('readUpload')->andReturn('original-bytes');
        $this->images->shouldReceive('pendingUpload')->andReturn('product-image-uploads/newer.jpg');
        $this->resizer->shouldNotReceive('fitAsWebp');
        $this->images->shouldNotReceive('storeImage');
        $this->images->shouldReceive('deleteUpload')->once()->with(self::UPLOAD);

        $this->service->process(42, self::UPLOAD);
    }

    public function test_a_newer_upload_arriving_during_processing_wins(): void
    {
        $this->images->shouldReceive('readUpload')->andReturn('original-bytes');
        $this->images->shouldReceive('pendingUpload')->andReturn(self::UPLOAD, 'product-image-uploads/newer.jpg');
        $this->givenResizedImages();
        $this->products->shouldReceive('lockByIdWithTrashed')->andReturn($this->product('products/42/old.webp'));
        $this->products->shouldNotReceive('save');
        $this->catalog->shouldNotReceive('catalogChanged');
        $this->images->shouldReceive('deleteImage')->once()->with('products/42/new.webp');
        $this->images->shouldReceive('deleteUpload')->once()->with(self::UPLOAD);

        $this->service->process(42, self::UPLOAD);
    }

    public function test_images_for_a_product_that_no_longer_exists_are_discarded(): void
    {
        $this->images->shouldReceive('readUpload')->andReturn('original-bytes');
        $this->images->shouldReceive('pendingUpload')->andReturn(self::UPLOAD);
        $this->givenResizedImages();
        $this->products->shouldReceive('lockByIdWithTrashed')->andReturnNull();
        $this->images->shouldReceive('deleteImage')->once()->with('products/42/new.webp');
        $this->images->shouldReceive('deleteUpload')->once();

        $this->service->process(42, self::UPLOAD);
    }

    public function test_a_missing_upload_file_is_skipped(): void
    {
        $this->images->shouldReceive('readUpload')->andReturnNull();
        $this->resizer->shouldNotReceive('fitAsWebp');
        $this->images->shouldReceive('deleteUpload')->once();

        $this->service->process(42, self::UPLOAD);
    }

    public function test_removing_the_image_cancels_a_queued_upload_and_deletes_the_files(): void
    {
        $product = $this->product('products/42/old.webp');
        $this->images->shouldReceive('clearPending')->once()->with(42)->ordered();
        $this->products->shouldReceive('lockById')->with(42)->andReturn($product);
        $this->products->shouldReceive('save')->once()->with($product);
        $this->images->shouldReceive('deleteImage')->once()->with('products/42/old.webp');
        $this->catalog->shouldReceive('catalogChanged')->once()->with('product image removed');

        $this->assertNull($this->service->remove($product)->image_path);
    }

    public function test_a_failed_job_only_clears_the_marker_if_it_was_the_newest(): void
    {
        $this->images->shouldReceive('pendingUpload')->andReturn(self::UPLOAD, 'product-image-uploads/newer.jpg');
        $this->images->shouldReceive('clearPending')->once()->with(42);
        $this->images->shouldReceive('deleteUpload')->twice()->with(self::UPLOAD);

        $this->service->discard(42, self::UPLOAD);
        $this->service->discard(42, self::UPLOAD);
    }
}
