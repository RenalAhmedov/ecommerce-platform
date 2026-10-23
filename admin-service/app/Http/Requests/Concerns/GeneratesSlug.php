<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Support\Str;

/**
 * Slug rules shared by products and categories (same pattern as the Nest
 * SLUG_PATTERN): an empty slug is generated from the name before validation.
 */
trait GeneratesSlug
{
    /** @return list<string> */
    protected function slugRules(): array
    {
        return ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'];
    }

    /** @return array<string, string> */
    protected function slugMessages(): array
    {
        return [
            'slug.required' => 'Could not generate a slug from the name, please provide one.',
            'slug.regex' => 'The slug may only contain lowercase letters, numbers and single hyphens.',
        ];
    }

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
