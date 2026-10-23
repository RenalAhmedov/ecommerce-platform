<?php

namespace App\Services;

use App\Enums\ImportStatus;
use App\Jobs\ImportProducts;
use App\Models\ProductImport;
use App\Models\User;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductImportRepository;
use App\Repositories\ProductRepository;
use App\Support\ProductCsv;
use App\Validation\ProductRules;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Imports products from CSV, matched by SKU: a new SKU creates a product (with
 * opening stock), a known SKU updates it (stock is left alone; it changes only
 * through stock movements). Every row goes through ProductService, so imports
 * get the same validation, locks and uniqueness rules as the forms. Running
 * the same file twice is safe.
 */
class ProductImportService
{
    public const MAX_ROWS = 2000;

    private const MAX_REPORTED_ERRORS = 200;

    private const SAVE_PROGRESS_EVERY = 50;

    public function __construct(
        private readonly ProductImportRepository $imports,
        private readonly ProductRepository $products,
        private readonly CategoryRepository $categories,
        private readonly ProductService $productService,
    ) {}

    /** @throws ValidationException for a wrong header, an empty file or too many rows */
    public function start(UploadedFile $file, User $admin): ProductImport
    {
        $rows = ProductCsv::countRows($file->getRealPath());

        if ($rows === 0 || $rows > self::MAX_ROWS) {
            throw ValidationException::withMessages([
                'file' => $rows === 0 ? 'The file has no product rows.' : 'The file has '.$rows.' rows; the limit is '.self::MAX_ROWS.' per import.',
            ]);
        }

        $import = $this->imports->create($file, $admin, $rows);
        ImportProducts::dispatch($import->id);

        return $import;
    }

    /** @return Collection<int, ProductImport> */
    public function recent(int $limit = 20): Collection
    {
        return $this->imports->recent($limit);
    }

    /** Runs on the queue worker. */
    public function process(int $importId): void
    {
        $import = $this->imports->findWithUser($importId);

        if ($import === null || ! $this->imports->claim($importId)) {
            return;
        }

        $categoryIds = $this->categories->idsBySlug();
        $errors = [];

        foreach (ProductCsv::read($this->imports->filePath($import)) as $rowNumber => $row) {
            try {
                $created = $this->importRow($row, $categoryIds, $import->user);
                $created ? $import->created_count++ : $import->updated_count++;
            } catch (ValidationException $e) {
                $import->failed_count++;
                if (count($errors) < self::MAX_REPORTED_ERRORS) {
                    $errors[] = ['row' => $rowNumber, 'sku' => $row['sku'] ?? null, 'messages' => Arr::flatten($e->errors())];
                }
            }

            if (++$import->processed_rows % self::SAVE_PROGRESS_EVERY === 0) {
                $this->imports->save($import);
            }
        }

        $import->errors = $errors;
        $import->status = ImportStatus::Completed;
        $import->finished_at = now();
        $this->imports->save($import);
        $this->imports->deleteFile($import);
    }

    public function fail(int $importId, string $reason): void
    {
        $this->imports->markFailed($importId, $reason);
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, int>  $categoryIds  slug => id
     * @return bool true when a product was created, false when one was updated
     *
     * @throws ValidationException
     */
    private function importRow(array $row, array $categoryIds, ?User $admin): bool
    {
        $data = $this->toProductData($row, $categoryIds);
        $existing = $this->products->findBySkuWithTrashed($data['sku']);

        if ($existing?->trashed()) {
            throw ValidationException::withMessages(['sku' => 'This SKU belongs to a deleted product. Restore it first.']);
        }

        if ($existing !== null) {
            $data = array_filter($data, fn ($value, string $field) => $field !== 'slug' || $value !== '', ARRAY_FILTER_USE_BOTH);
            $rules = Arr::except(ProductRules::rules(), 'initial_stock');
            $this->productService->update($existing, $this->validate(Arr::except($data, 'initial_stock'), Arr::only($rules, array_keys($data))));

            return false;
        }

        $data['slug'] = $data['slug'] !== '' ? $data['slug'] : Str::slug($data['name']);
        $this->productService->create($this->validate($data, ProductRules::rules()), $admin);

        return true;
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, int>  $categoryIds
     * @return array<string, mixed>
     *
     * @throws ValidationException for an unknown category slug
     */
    private function toProductData(array $row, array $categoryIds): array
    {
        $data = [
            'sku' => Str::upper($row['sku'] ?? ''),
            'name' => $row['name'] ?? '',
            'slug' => $row['slug'] ?? '',
            'price' => $row['price'] ?? '',
            'description' => ($row['description'] ?? '') !== '' ? $row['description'] : null,
        ];

        $categorySlug = $row['category_slug'] ?? '';
        if ($categorySlug !== '') {
            $data['category_id'] = $categoryIds[$categorySlug]
                ?? throw ValidationException::withMessages(['category_slug' => "Unknown category \"{$categorySlug}\"."]);
        } elseif (array_key_exists('category_slug', $row)) {
            $data['category_id'] = null;
        }

        if (($row['is_active'] ?? '') !== '') {
            $data['is_active'] = filter_var($row['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $row['is_active'];
        }
        if (($row['stock'] ?? '') !== '') {
            $data['initial_stock'] = $row['stock'];
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array<mixed>>  $rules
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validate(array $data, array $rules): array
    {
        return Validator::make($data, $rules, ProductRules::messages())->validate();
    }
}
