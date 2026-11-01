<?php

namespace Tests\Unit\Support;

use App\Support\AmountInWords;
use PHPUnit\Framework\TestCase;

class AmountInWordsTest extends TestCase
{
    public function test_indian_numbering(): void
    {
        $this->assertSame('Rupees One Lakh Five Thousand Three Hundred Eighty Seven Only', AmountInWords::rupees('105387'));
        $this->assertSame('Rupees Fifty Five Thousand Only', AmountInWords::rupees('55000'));
        $this->assertSame('Rupees Two Crore Fifteen Lakh Only', AmountInWords::rupees('21500000'));
        $this->assertSame('Rupees Zero Only', AmountInWords::rupees('0'));
    }

    public function test_paise(): void
    {
        $this->assertSame('Rupees Ten and Fifty Paise Only', AmountInWords::rupees('10.50'));
    }
}
