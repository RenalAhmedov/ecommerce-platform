<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductImageService;
use App\Services\ProductService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Products are created through the same services as the panel, so each gets its
 * stock row and "initial" ledger entry, and a picture that goes through the real
 * upload pipeline (the queue worker resizes it to WebP and makes the thumbnail).
 */
class ProductSeeder extends Seeder
{
    /** sku => [name, category slug, price, initial stock, active] */
    private const PRODUCTS = [
        'AUD-HP-01' => ['Wireless Headphones', 'audio', '129.90', 40, true],
        'AUD-SPK-01' => ['Bluetooth Speaker', 'audio', '59.90', 25, true],
        'AUD-EAR-01' => ['Noise-Cancelling Earbuds', 'audio', '89.00', 4, true],
        'LAP-14-01' => ['14" Ultrabook', 'laptops', '1099.00', 12, true],
        'LAP-16-01' => ['16" Creator Laptop', 'laptops', '1899.00', 6, true],
        'ACC-MOU-01' => ['Ergonomic Mouse', 'computer-accessories', '39.90', 60, true],
        'ACC-KEY-01' => ['Mechanical Keyboard', 'computer-accessories', '109.00', 30, true],
        'ACC-HUB-01' => ['USB-C Hub', 'computer-accessories', '49.50', 3, true],
        'ACC-STD-01' => ['Laptop Stand', 'computer-accessories', '34.00', 0, true],
        'KIT-MUG-01' => ['Ceramic Mug', 'kitchen', '12.50', 120, true],
        'KIT-KNF-01' => ['Chef\'s Knife', 'kitchen', '79.00', 18, true],
        'KIT-PAN-01' => ['Cast Iron Pan', 'kitchen', '45.00', 22, true],
        'KIT-KTL-01' => ['Electric Kettle', 'kitchen', '39.00', 15, true],
        'DEC-LMP-01' => ['Desk Lamp', 'decor', '49.90', 35, true],
        'DEC-PLT-01' => ['Plant Pot Set', 'decor', '24.90', 50, true],
        'DEC-CLK-01' => ['Wall Clock', 'decor', '29.90', 10, false],
        'CLO-TSH-01' => ['Organic T-Shirt', 'clothing', '19.90', 80, true],
        'CLO-HOD-01' => ['Hooded Sweatshirt', 'clothing', '49.00', 45, true],
        'BOK-ALG-01' => ['Introduction to Algorithms', 'books', '89.00', 20, true],
        'BOK-CLN-01' => ['Clean Architecture', 'books', '34.90', 2, true],
    ];

    /** Picture background per top-level category. */
    private const COLORS = [
        'electronics' => [37, 99, 235],
        'home-kitchen' => [217, 119, 6],
        'clothing' => [5, 150, 105],
        'books' => [124, 58, 237],
    ];

    private const FONT = 'vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf';

    public function run(ProductService $products, ProductImageService $images): void
    {
        $admin = User::where('email', UserSeeder::ADMIN_EMAIL)->first();

        foreach (self::PRODUCTS as $sku => [$name, $categorySlug, $price, $stock, $active]) {
            if (Product::withTrashed()->where('sku', $sku)->exists()) {
                continue;
            }

            $category = Category::where('slug', $categorySlug)->firstOrFail();
            $product = $products->create([
                'name' => $name,
                'slug' => Str::slug($name),
                'sku' => $sku,
                'description' => "{$name} from our {$category->name} range.",
                'price' => $price,
                'category_id' => $category->id,
                'is_active' => $active,
                'initial_stock' => $stock,
            ], $admin);

            $images->upload($product, $this->picture($name, $this->colorFor($category)));
        }
    }

    /** @return array{int, int, int} */
    private function colorFor(Category $category): array
    {
        while ($category->parent_id !== null) {
            $category = Category::findOrFail($category->parent_id);
        }

        return self::COLORS[$category->slug];
    }

    /** @param  array{int, int, int}  $rgb */
    private function picture(string $name, array $rgb): UploadedFile
    {
        $image = imagecreatetruecolor(800, 800);
        imagefill($image, 0, 0, imagecolorallocate($image, ...$rgb));
        $white = imagecolorallocate($image, 255, 255, 255);

        $lines = explode("\n", wordwrap($name, 14));
        foreach ($lines as $i => $line) {
            [, , $right] = imagettfbbox(56, 0, base_path(self::FONT), $line);
            $y = 400 - (count($lines) - 1) * 40 + $i * 80;
            imagettftext($image, 56, 0, intdiv(800 - $right, 2), $y + 20, $white, base_path(self::FONT), $line);
        }

        $path = tempnam(sys_get_temp_dir(), 'seed').'.png';
        imagepng($image, $path);

        return new UploadedFile($path, Str::slug($name).'.png', 'image/png', null, true);
    }
}
