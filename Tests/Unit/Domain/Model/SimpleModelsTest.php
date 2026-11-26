<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Unit\Domain\Model;

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
use MarioMatzulla\Cal\Domain\Model\Calendar;
use MarioMatzulla\Cal\Domain\Model\EventDeviation;
use MarioMatzulla\Cal\Domain\Model\Index;
use MarioMatzulla\Cal\Tests\Unit\AccessorAssertionsTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(Calendar::class)]
#[CoversClass(Index::class)]
#[CoversClass(EventDeviation::class)]
final class SimpleModelsTest extends UnitTestCase
{
    use AccessorAssertionsTrait;

    #[Test]
    public function calendarAccessors(): void
    {
        $calendar = new Calendar();
        self::assertSame('tx_cal_calendar', $calendar->getModelType());
        $this->assertAccessors($calendar, ['title' => 'Calendar', 'timezone' => 'Europe/Berlin', 'owner' => 'owner']);
    }

    #[Test]
    public function indexAccessors(): void
    {
        $this->assertAccessors(new Index(), [
            'tablename' => 'tx_cal_event',
            'startDatetime' => new \DateTime('2026-11-25 10:00'),
            'endDatetime' => new \DateTime('2026-11-25 11:00'),
            'eventUid' => 1,
            'eventDeviationUid' => 2,
        ]);
    }

    #[Test]
    public function eventDeviationAccessors(): void
    {
        $deviation = new EventDeviation();
        self::assertSame('tx_cal_event_deviation', $deviation->getModelType());
        $this->assertAccessors($deviation, [
            'refEventId' => 3,
            'origStartDate' => new \DateTime('2026-11-25'),
            'origEndDate' => new \DateTime('2026-11-26'),
            'origStartTime' => 36000,
            'origEndTime' => 39600,
        ]);
    }
}
