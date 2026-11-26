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
use MarioMatzulla\Cal\Backend\Form\RenderType\ByDayElement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(ByDayElement::class)]
#[CoversClass(AbstractCalFormElementHelper::class)]
final class ByDayElementTest extends UnitTestCase
{
    use FormElementTestTrait;

    #[Test]
    public function weeklyRuleShowsCheckboxes(): void
    {
        // 25.11.2026 is a wednesday
        $result = $this->createElement(ByDayElement::class, ['freq' => ['week'], 'byday' => '', 'start_date' => new \DateTimeImmutable('2026-11-25')])->render();
        self::assertSame('WE', self::hiddenValue($result['html'], 'byday'));
        self::assertSame(['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'], self::values($result['html'], '/name="byday_(\w+)"/'));
        self::assertStringContainsString('byday_short', $result['html']);
        self::assertStringNotContainsString('add-byday', $result['html']);
        self::assertCount(2, $result['javaScriptModules']);
    }

    #[Test]
    public function weeklyRuleWithSundayAsFirstDay(): void
    {
        $html = $this->createElement(ByDayElement::class, ['pid' => 1, 'freq' => 'week', 'byday' => 'null,MO,null'])->render()['html'];
        self::assertSame('MO', self::hiddenValue($html, 'byday'));
        self::assertSame(['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA'], self::values($html, '/name="byday_(\w+)"/'));
    }

    #[Test]
    public function monthlyRuleShowsOrdinalAndWeekday(): void
    {
        $html = $this->createElement(ByDayElement::class, ['pid' => 2, 'freq' => ['month'], 'byday' => ''])->render()['html'];
        self::assertSame('MO,TU,WE,TH,FR,SA,SU', self::hiddenValue($html, 'byday'));
        self::assertSame(['', '1', '2', '3', '4', '5', '-3', '-2', '-1', '', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'], self::values($html, '/<option value="([^"]*)"/'));
        self::assertStringContainsString('byday_count_thirdtolast', $html);
        self::assertStringContainsString('recurs_every_month', $html);
        self::assertStringContainsString('[icon]tx_cal_event.remove_recurrence', $html);
        self::assertStringContainsString('[icon]tx_cal_event.add_recurrence', $html);
    }

    #[Test]
    public function yearlyRuleKeepsTheValue(): void
    {
        $html = $this->createElement(ByDayElement::class, ['freq' => ['year'], 'byday' => '20mo,3null,-1FR,"x'])->render()['html'];
        self::assertSame('20MO,-1FR', self::hiddenValue($html, 'byday'));
        self::assertStringContainsString('recurs_selected_months', $html);

        $html = $this->createElement(ByDayElement::class, ['freq' => ['year']])->render()['html'];
        self::assertSame('MO,TU,WE,TH,FR,SA,SU', self::hiddenValue($html, 'byday'));
    }

    #[Test]
    public function otherFrequenciesShowOnlyTheField(): void
    {
        $html = $this->createElement(ByDayElement::class, ['freq' => ['day'], 'byday' => 'MO'])->render()['html'];
        self::assertSame('MO', self::hiddenValue($html, 'byday'));
        self::assertStringNotContainsString('cal-row', $html);
    }
}
