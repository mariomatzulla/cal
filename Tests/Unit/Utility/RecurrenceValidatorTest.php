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
use MarioMatzulla\Cal\Domain\Model\Event;
use MarioMatzulla\Cal\Utility\RecurrenceValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(RecurrenceValidator::class)]
final class RecurrenceValidatorTest extends UnitTestCase
{
    /**
     * @param array<string, mixed> $rule
     */
    private static function validate(array $rule): Event
    {
        $event = new Event();
        // Wednesday, 25.11.2026
        $event->setStartDate(new \DateTime('2026-11-25'));
        foreach ($rule as $property => $value) {
            $event->{'set' . ucfirst($property)}($value);
        }
        RecurrenceValidator::validateEvent($event);
        return $event;
    }

    /**
     * @return array{string, string, string, string, int, int}
     */
    private static function rule(Event $event): array
    {
        return [(string)$event->getFreq(), (string)$event->getBymonth(), (string)$event->getBymonthday(), (string)$event->getByday(), $event->getIntrval(), $event->getCnt()];
    }

    private const ALL_MONTHS = '1,2,3,4,5,6,7,8,9,10,11,12';
    private const ALL_DAYS = '1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31';
    private const ALL_WEEKDAYS = 'SU,MO,TU,WE,TH,FR,SA';

    public static function invalidFrequencyDataProvider(): array
    {
        return [
            'empty' => [''],
            'none' => ['none'],
            'hour' => ['hour'],
            'unknown' => ['weekly'],
            'null' => [null],
        ];
    }

    #[Test]
    #[DataProvider('invalidFrequencyDataProvider')]
    public function invalidFrequencyMeansNoRecurrence(?string $freq): void
    {
        $event = self::validate(['freq' => $freq, 'bymonth' => '13', 'intrval' => 0]);
        self::assertSame('none', $event->getFreq());
        // the rest of the rule is not touched
        self::assertSame('13', $event->getBymonth());
        self::assertSame(0, $event->getIntrval());
    }

    public static function ruleDataProvider(): array
    {
        return [
            'daily, all weekdays' => [
                ['freq' => 'day', 'bymonth' => '3', 'bymonthday' => '4', 'byday' => 'MO'],
                ['day', self::ALL_MONTHS, '25', self::ALL_WEEKDAYS, 1, 9999999],
            ],
            'weekly without weekday uses the weekday of the start' => [
                ['freq' => 'week', 'bymonth' => '3', 'bymonthday' => '4', 'intrval' => 2, 'cnt' => 5],
                ['week', self::ALL_MONTHS, self::ALL_DAYS, 'WE', 2, 5],
            ],
            'weekly ignores ordinals and invalid weekdays' => [
                ['freq' => 'week', 'byday' => 'mo, 2FR,XX,,+1SA'],
                ['week', self::ALL_MONTHS, self::ALL_DAYS, 'MO,FR,SA', 1, 9999999],
            ],
            'monthly by day of the month' => [
                ['freq' => 'month', 'bymonth' => '3', 'bymonthday' => '15,0,-1,32,-32'],
                ['month', self::ALL_MONTHS, '15,-1', self::ALL_WEEKDAYS, 1, 9999999],
            ],
            'monthly without values uses the day of the start' => [
                ['freq' => 'month'],
                ['month', self::ALL_MONTHS, '25', self::ALL_WEEKDAYS, 1, 9999999],
            ],
            'monthly by weekday with ordinal' => [
                ['freq' => 'month', 'byday' => '3WE,-1FR,+2MO,0TU'],
                ['month', self::ALL_MONTHS, self::ALL_DAYS, '3WE,-1FR,2MO,TU', 1, 9999999],
            ],
            'yearly without values uses month and day of the start' => [
                ['freq' => 'year'],
                ['year', '11', '25', self::ALL_WEEKDAYS, 1, 9999999],
            ],
            'yearly by month' => [
                ['freq' => 'year', 'bymonth' => '12,3,3,0,13,x'],
                ['year', '3,12', '25', self::ALL_WEEKDAYS, 1, 9999999],
            ],
            'yearly by day of the month in all months' => [
                ['freq' => 'year', 'bymonthday' => '1'],
                ['year', self::ALL_MONTHS, '1', self::ALL_WEEKDAYS, 1, 9999999],
            ],
            'yearly by weekday of the year' => [
                ['freq' => 'year', 'byday' => '20MO'],
                ['year', '', self::ALL_DAYS, '20MO', 1, 9999999],
            ],
            'value "all" of the old form' => [
                ['freq' => 'year', 'bymonth' => 'All', 'bymonthday' => 'all', 'byday' => 'ALL'],
                ['year', self::ALL_MONTHS, '25', self::ALL_WEEKDAYS, 1, 9999999],
            ],
            'negative interval and count' => [
                ['freq' => 'day', 'intrval' => -2, 'cnt' => -1],
                ['day', self::ALL_MONTHS, '25', self::ALL_WEEKDAYS, 1, 9999999],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $rule
     * @param array{string, string, string, string, int, int} $expected
     */
    #[Test]
    #[DataProvider('ruleDataProvider')]
    public function ruleIsNormalized(array $rule, array $expected): void
    {
        self::assertSame($expected, self::rule(self::validate($rule)));
    }
}
