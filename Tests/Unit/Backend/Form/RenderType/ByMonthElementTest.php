<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Unit\Backend\Form\RenderType;

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
use MarioMatzulla\Cal\Backend\Form\RenderType\AbstractCalFormElementHelper;
use MarioMatzulla\Cal\Backend\Form\RenderType\ByMonthDayElement;
use MarioMatzulla\Cal\Backend\Form\RenderType\ByMonthElement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(ByMonthElement::class)]
#[CoversClass(ByMonthDayElement::class)]
#[CoversClass(AbstractCalFormElementHelper::class)]
final class ByMonthElementTest extends UnitTestCase
{
    use FormElementTestTrait;

    public static function startDateDataProvider(): array
    {
        return [
            // the date of the object, not converted to UTC
            'date object' => [new \DateTimeImmutable('2026-12-01 00:00', new \DateTimeZone('Europe/Berlin')), '12', '1'],
            'timestamp' => [(string)mktime(0, 0, 0, 11, 15, 2026), '11', '15'],
            'iso date of a new record' => ['2026-03-31T00:00:00Z', '3', '31'],
            'invalid' => ['invalid date', gmdate('n'), gmdate('j')],
            'no date' => [null, gmdate('n'), gmdate('j')],
        ];
    }

    #[Test]
    #[DataProvider('startDateDataProvider')]
    public function defaultIsTheStartDate(mixed $startDate, string $month, string $day): void
    {
        $html = $this->createElement(ByMonthElement::class, ['bymonth' => 'null', 'start_date' => $startDate])->render()['html'];
        self::assertSame($month, self::hiddenValue($html, 'bymonth'));
        $html = $this->createElement(ByMonthDayElement::class, ['freq' => ['month'], 'bymonthday' => '', 'start_date' => $startDate])->render()['html'];
        self::assertSame($day, self::hiddenValue($html, 'bymonthday'));
    }

    #[Test]
    public function byMonthShowsTheMonths(): void
    {
        $result = $this->createElement(ByMonthElement::class, ['bymonth' => '3,9'])->render();
        self::assertSame('3,9', self::hiddenValue($result['html'], 'bymonth'));
        self::assertSame(range(1, 12), array_map('intval', self::values($result['html'], '/name="bymonth_(\d+)"/')));
        self::assertStringContainsString('bymonth_december', $result['html']);
        self::assertCount(2, $result['javaScriptModules']);
    }

    #[Test]
    public function byMonthDayShowsTheDaysForWeeklyMonthlyAndYearlyRules(): void
    {
        foreach (['week', 'month', 'year'] as $freq) {
            $result = $this->createElement(ByMonthDayElement::class, ['freq' => [$freq], 'bymonthday' => '1,-1'])->render();
            self::assertSame('1,-1', self::hiddenValue($result['html'], 'bymonthday'));
            self::assertSame(range(1, 31), array_map('intval', self::values($result['html'], '/name="bymonthday_(\d+)"/')), $freq);
        }
        self::assertCount(2, $result['javaScriptModules']);

        $html = $this->createElement(ByMonthDayElement::class, ['freq' => ['day'], 'bymonthday' => '5'])->render()['html'];
        self::assertSame([], self::values($html, '/name="bymonthday_(\d+)"/'));
    }
}
