<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Helpers\FiscalHelper;

class FiscalHelperTest extends TestCase
{
    public function test_cep_strips_mask()
    {
        $this->assertEquals('61762470', FiscalHelper::cep('61762-470'));
        $this->assertEquals('61762470', FiscalHelper::cep('61.762-470'));
    }

    public function test_truncate_counts_chars_not_bytes()
    {
        $this->assertEquals('ação', FiscalHelper::truncate('açãoXYZ', 4));
        $this->assertEquals(120, mb_strlen(FiscalHelper::truncate(str_repeat('a', 122), 120)));
    }
}
