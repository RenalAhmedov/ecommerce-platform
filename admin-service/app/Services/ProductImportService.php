<?php

namespace App\Services;

use App\Enums\ImportStatus;
use App\Jobs\ImportProducts;
use App\Models\ProductImport;
use App\Models\User;
use App\Repositories\ProductImportRepository;
use App\Support\ProductCsv;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ProductImportService
{
    public const MAX_ROWS = 2000;

    private const MAX_REPORTED_ERRORS = 200;

    private const SAVE_PROGRESS_EVERY = 50;

    public function __construct(
        private readonly ProductImportRepository $imports,
        private readonly ProductRowImporter $rows,
        private readonly CategoryService $categories,
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

        Log::info('Product import queued', ['import_id' => $import->id, 'file' => $import->original_name, 'rows' => $rows]);

        return $import;
    }

    /** @return Collection<int, ProductImport> */
    public function recent(int $limit = 20): Collection
    {
        return $this->imports->recent($limit);
    }

    public function process(int $importId): void
    {
        $import = $this->imports->findWithUser($importId);

        if ($import === null || ! $this->imports->claim($importId)) {
            Log::info('Product import skipped: already claimed or gone', ['import_id' => $importId]);

            return;
        }

        $categoryIds = $this->categories->idsBySlug();
        $errors = [];

        foreach (ProductCsv::read($this->imports->filePath($import)) as $rowNumber => $row) {
            try {
                $this->rows->import($row, $categoryIds, $import->user) ? $import->created_count++ : $import->updated_count++;
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

        Log::info('Product import completed', [
            'import_id' => $import->id,
            'rows' => $import->processed_rows,
            'created' => $import->created_count,
            'updated' => $import->updated_count,
            'failed' => $import->failed_count,
        ]);
    }

    public function fail(int $importId, string $reason): void
    {
        $this->imports->markFailed($importId, $reason);
    }
}
