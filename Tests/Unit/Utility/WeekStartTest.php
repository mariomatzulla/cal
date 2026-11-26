<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Unit\Utility;

/**
 * This file is part of the TYPO3 extension Calendar Base (cal).
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */
use MarioMatzulla\Cal\Utility\WeekStart;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(WeekStart::class)]
final class WeekStartTest extends UnitTestCase
{
    protected function tearDown(): void
    {
        WeekStart::set(WeekStart::MONDAY);
        parent::tearDown();
    }

    #[Test]
    public function mondayIsTheDefault(): void
    {
        self::assertSame(WeekStart::MONDAY, WeekStart::get());
    }

    public static function setDataProvider(): array
    {
        return [
            'int 0' => [0, WeekStart::SUNDAY],
            'string 0' => ['0', WeekStart::SUNDAY],
            'sunday' => ['sunday', WeekStart::SUNDAY],
            'Sunday with spaces' => [' Sunday ', WeekStart::SUNDAY],
            'su' => ['su', WeekStart::SUNDAY],
            'sun' => ['sun', WeekStart::SUNDAY],
            'int 1' => [1, WeekStart::MONDAY],
            'monday' => ['monday', WeekStart::MONDAY],
            'empty' => ['', WeekStart::MONDAY],
            'null' => [null, WeekStart::MONDAY],
            'unknown' => ['saturday', WeekStart::MONDAY],
        ];
    }

    #[Test]
    #[DataProvider('setDataProvider')]
    public function setAcceptsSundayAndFallsBackToMonday(int|string|null $value, int $expected): void
    {
        WeekStart::set($value);
        self::assertSame($expected, WeekStart::get());
    }

    #[Test]
    public function indexOfReturnsThePositionInTheWeek(): void
    {
        WeekStart::set('monday');
        self::assertSame(6, WeekStart::indexOf(0));
        self::assertSame(0, WeekStart::indexOf(1));
        self::assertSame(5, WeekStart::indexOf(6));

        WeekStart::set('sunday');
        self::assertSame(0, WeekStart::indexOf(0));
        self::assertSame(1, WeekStart::indexOf(1));
        self::assertSame(6, WeekStart::indexOf(6));
    }

    #[Test]
    public function weekOfYearUsesIsoWeeksForMonday(): void
    {
        WeekStart::set('monday');
        self::assertSame(6, WeekStart::weekOfYear(new \DateTimeImmutable('2026-02-08')));
        self::assertSame(7, WeekStart::weekOfYear(new \DateTimeImmutable('2026-02-09')));
    }

    #[Test]
    public function weekOfYearCountsSundayToTheFollowingWeekForSunday(): void
    {
        WeekStart::set('sunday');
        self::assertSame(7, WeekStart::weekOfYear(new \DateTimeImmutable('2026-02-08')));
        self::assertSame(7, WeekStart::weekOfYear(new \DateTime('2026-02-14')));
        self::assertSame(8, WeekStart::weekOfYear(new \DateTime('2026-02-15')));
    }

    #[Test]
    public function firstDayOfWeekReturnsMondayOrSundayAtMidnight(): void
    {
        WeekStart::set('monday');
        self::assertSame('2026-02-09 00:00:00', WeekStart::firstDayOfWeek(2026, 7)->format('Y-m-d H:i:s'));

        WeekStart::set('sunday');
        self::assertSame('2026-02-08 00:00:00', WeekStart::firstDayOfWeek(2026, 7)->format('Y-m-d H:i:s'));
    }
}
