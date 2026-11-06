<?php

namespace Anikeen\Id\Tests;

use Anikeen\Id\Enums\Appearance;
use Anikeen\Id\Enums\SharedPreference;

class SharedPreferenceTest extends TestCase
{
    public function test_the_shared_keys_and_values_match_anikeen_id(): void
    {
        $this->assertSame('global', SharedPreference::NAMESPACE);
        $this->assertSame('appearance', SharedPreference::APPEARANCE);
        $this->assertSame(['system', 'light', 'dark'], Appearance::all());
    }
}
