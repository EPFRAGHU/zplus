<?php

namespace Tests\Unit\Support;

use App\Support\Ifsc;
use PHPUnit\Framework\TestCase;

class IfscTest extends TestCase
{
    public function test_validation(): void
    {
        $this->assertTrue(Ifsc::isValid('CNRB0002957'));
        $this->assertTrue(Ifsc::isValid('sbin0010908'));
        $this->assertFalse(Ifsc::isValid('BARB0VJNIVI00000')); // 16 chars, seen in the GeM workbook
        $this->assertFalse(Ifsc::isValid('CNRB1002957'));       // 5th char must be 0
        $this->assertFalse(Ifsc::isValid(null));
    }
}
