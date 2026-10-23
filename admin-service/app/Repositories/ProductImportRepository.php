<?php

namespace App\Repositories;

use App\Enums\ImportStatus;
use App\Models\ProductImport;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductImportRepository
{
    private const DISK = 'local';

    private const DIRECTORY = 'product-imports';

    public function create(UploadedFile $file, User $admin, int $totalRows): ProductImport
    {
        return ProductImport::create([
            'user_id' => $admin->id,
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $file->storeAs(self::DIRECTORY, Str::uuid().'.csv', self::DISK),
            'total_rows' => $totalRows,
        ]);
    }

    /** @return Collection<int, ProductImport> */
    public function recent(int $limit): Collection
    {
        return ProductImport::with('user')->latest('id')->limit($limit)->get();
    }

    public function findWithUser(int $id): ?ProductImport
    {
        return ProductImport::with('user')->find($id);
    }

    /**
     * Atomically moves a queued import to "processing". Only one caller can
     * win, so a job delivered twice never imports the same file twice.
     */
    public function claim(int $id): bool
    {
        return ProductImport::whereKey($id)
            ->where('status', ImportStatus::Queued)
            ->update(['status' => ImportStatus::Processing, 'started_at' => now()]) === 1;
    }

    public function save(ProductImport $import): void
    {
        $import->save();
    }

    public function markFailed(int $id, string $reason): void
    {
        ProductImport::whereKey($id)
            ->whereIn('status', [ImportStatus::Queued, ImportStatus::Processing])
            ->update([
                'status' => ImportStatus::Failed,
                'errors' => json_encode([['row' => null, 'sku' => null, 'messages' => [$reason]]]),
                'finished_at' => now(),
            ]);
    }

    public function filePath(ProductImport $import): string
    {
        return Storage::disk(self::DISK)->path($import->file_path);
    }

    public function deleteFile(ProductImport $import): void
    {
        Storage::disk(self::DISK)->delete($import->file_path);
    }
}
