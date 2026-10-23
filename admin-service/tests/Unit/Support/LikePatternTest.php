<?php

namespace Tests\Unit\Support;

use App\Support\LikePattern;
use Tests\Unit\UnitTestCase;

class LikePatternTest extends UnitTestCase
{
    public function test_search_text_is_wrapped_and_wildcards_are_escaped(): void
    {
        $this->assertSame('%mug%', LikePattern::contains('mug'));
        $this->assertSame('%50\%\_off\\\\%', LikePattern::contains('50%_off\\'));
    }
}
