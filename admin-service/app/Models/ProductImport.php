<?php

namespace App\Models;

use App\Enums\ImportStatus;
use App\Models\Concerns\UsesTimestampTz;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One CSV upload and its result report; owned by the admin service. */
#[Fillable(['user_id', 'original_name', 'file_path', 'total_rows'])]
class ProductImport extends Model
{
    use UsesTimestampTz;

    protected $attributes = [
        'status' => 'queued',
        'errors' => '[]',
        'processed_rows' => 0,
        'created_count' => 0,
        'updated_count' => 0,
        'failed_count' => 0,
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'errors' => 'array',
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'created_count' => 'integer',
            'updated_count' => 'integer',
            'failed_count' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
