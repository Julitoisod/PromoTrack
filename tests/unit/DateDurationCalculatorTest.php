<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Libraries\DateDurationCalculator;
use CodeIgniter\Test\CIUnitTestCase;
use DateTime;
use InvalidArgumentException;

/**
 * Pure logic unit tests for `App\Libraries\DateDurationCalculator`.
 *
 * These tests use fixed, explicit date pairs (never "now") so results
 * are deterministic regardless of when the suite runs.
 *
 * @internal
 */
final class DateDurationCalculatorTest extends CIUnitTestCase
{
    private DateDurationCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new DateDurationCalculator();
    }

    public function testBreakdownCalculatesYearsMonthsAndDays(): void
    {
        $result = $this->calculator->breakdown('2020-01-15', '2022-07-20');

        $this->assertSame(2, $result['years']);
        $this->assertSame(6, $result['months']);
        $this->assertSame(5, $result['days']);
        $this->assertSame(30, $result['total_months']);

        $expectedDays = (new DateTime('2020-01-15'))->diff(new DateTime('2022-07-20'))->days;
        $this->assertSame($expectedDays, $result['total_days']);
    }

    public function testTotalMonthsBetweenMatchesBreakdown(): void
    {
        $this->assertSame(30, $this->calculator->totalMonthsBetween('2020-01-15', '2022-07-20'));
    }

    public function testTotalDaysBetweenMatchesBreakdown(): void
    {
        $expectedDays = (new DateTime('2020-01-15'))->diff(new DateTime('2022-07-20'))->days;

        $this->assertSame($expectedDays, $this->calculator->totalDaysBetween('2020-01-15', '2022-07-20'));
    }

    public function testAgeInYearsReturnsWholeYearsElapsed(): void
    {
        $this->assertSame(2, $this->calculator->ageInYears('2020-01-15', '2022-07-20'));
        $this->assertSame(1, $this->calculator->ageInYears('2020-01-15', '2021-07-20'));
    }

    public function testHasElapsedAtLeastMonthsBoundary(): void
    {
        $this->assertTrue($this->calculator->hasElapsedAtLeastMonths('2020-01-15', 30, '2022-07-20'));
        $this->assertTrue($this->calculator->hasElapsedAtLeastMonths('2020-01-15', 29, '2022-07-20'));
        $this->assertFalse($this->calculator->hasElapsedAtLeastMonths('2020-01-15', 31, '2022-07-20'));
    }

    public function testIsBeforeAndIsAfter(): void
    {
        $this->assertTrue($this->calculator->isBefore('2020-01-01', '2020-01-02'));
        $this->assertFalse($this->calculator->isBefore('2020-01-02', '2020-01-01'));
        $this->assertFalse($this->calculator->isBefore('2020-01-01', '2020-01-01'));

        $this->assertTrue($this->calculator->isAfter('2020-01-02', '2020-01-01'));
        $this->assertFalse($this->calculator->isAfter('2020-01-01', '2020-01-02'));
        $this->assertFalse($this->calculator->isAfter('2020-01-01', '2020-01-01'));
    }

    public function testBreakdownThrowsWhenFromDateIsAfterToDate(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator->breakdown('2022-01-01', '2020-01-01');
    }
}
