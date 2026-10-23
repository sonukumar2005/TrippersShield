<?php

namespace Tests\Unit;

use App\Services\PremiumCalculatorService;
use InvalidArgumentException;
use Tests\TestCase;

class PremiumCalculatorTest extends TestCase
{
    private PremiumCalculatorService $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new PremiumCalculatorService();
    }

    /** @test */
    public function basic_domestic_10_days()
    {
        // 10 × $1.50 × 1.0 = $15.00
        $this->assertEquals(15.00, $this->calc->calculate('basic', 'domestic', '2027-01-01', '2027-01-11'));
    }

    /** @test */
    public function standard_international_10_days()
    {
        // 10 × $3.00 × 1.5 = $45.00
        $this->assertEquals(45.00, $this->calc->calculate('standard', 'international', '2027-01-01', '2027-01-11'));
    }

    /** @test */
    public function premium_schengen_10_days()
    {
        // 10 × $6.00 × 2.0 = $120.00
        $this->assertEquals(120.00, $this->calc->calculate('premium', 'schengen', '2027-01-01', '2027-01-11'));
    }

    /** @test */
    public function minimum_premium_is_applied()
    {
        // 1 × $1.50 × 1.0 = $1.50 → minimum $5.00 applies
        $this->assertEquals(5.00, $this->calc->calculate('basic', 'domestic', '2027-01-01', '2027-01-02'));
    }

    /** @test */
    public function it_throws_for_invalid_plan()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calc->calculate('ultra', 'domestic', '2027-01-01', '2027-01-10');
    }

    /** @test */
    public function it_throws_for_invalid_destination()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calc->calculate('basic', 'mars', '2027-01-01', '2027-01-10');
    }

    /** @test */
    public function it_throws_when_end_before_start()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calc->calculate('basic', 'domestic', '2027-01-10', '2027-01-01');
    }
}
