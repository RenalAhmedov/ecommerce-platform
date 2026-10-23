<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Support\Str;

/** An empty slug is generated from the name before validation (products and categories). */
trait GeneratesSlug
{
    protected function generateSlugIfMissing(): void
    {
        if ($this->shouldGenerateSlug()) {
            $this->merge(['slug' => Str::slug((string) $this->input('name'))]);
        }
    }

    /** Update requests override this: only a cleared slug field is regenerated, a missing one is left alone. */
    protected function shouldGenerateSlug(): bool
    {
        return blank($this->input('slug'));
    }
}
