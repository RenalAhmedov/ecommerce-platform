<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Support\Str;

trait GeneratesSlug
{
    protected function generateSlugIfMissing(): void
    {
        if ($this->shouldGenerateSlug()) {
            $this->merge(['slug' => Str::slug((string) $this->input('name'))]);
        }
    }

    protected function shouldGenerateSlug(): bool
    {
        return blank($this->input('slug'));
    }
}
