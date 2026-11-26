<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Functional\Domain\Repository;

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
use MarioMatzulla\Cal\Domain\Repository\BaseRepository;
use MarioMatzulla\Cal\Domain\Repository\CalendarRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(CalendarRepository::class)]
#[CoversClass(BaseRepository::class)]
final class CalendarRepositoryTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['mariomatzulla/cal'];

    private CalendarRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Calendars.csv');
        $this->subject = $this->get(CalendarRepository::class);
    }

    #[Test]
    public function findPublicCalendarListReturnsCalendarsWithoutOwner(): void
    {
        $calendars = $this->subject->findPublicCalendarList()->toArray();
        self::assertContainsOnlyInstancesOf(Calendar::class, $calendars);
        self::assertSame(['Club', 'Holidays'], array_map(static fn(Calendar $calendar): string => $calendar->getTitle(), $calendars));
    }

    #[Test]
    public function findByUidsReturnsTheRecordsWithoutDeleted(): void
    {
        self::assertSame([1, 3], array_column($this->subject->findByUids('3, 1,4,x'), 'uid'));
        self::assertSame([], $this->subject->findByUids(''));
        self::assertSame([], $this->subject->findByUids('0'));
    }

    #[Test]
    public function findRawByUidIncludesDeletedRecords(): void
    {
        self::assertSame('Deleted', $this->subject->findRawByUid(4)['title']);
        self::assertNull($this->subject->findRawByUid(99));
    }

    #[Test]
    public function touchAndUpdateMd5SetTheTimestamp(): void
    {
        $this->subject->touch(1);
        $this->subject->updateMd5(2, 'def');
        $club = $this->subject->findRawByUid(1);
        $holidays = $this->subject->findRawByUid(2);
        self::assertEqualsWithDelta(time(), $club['tstamp'], 5);
        self::assertEqualsWithDelta(time(), $holidays['tstamp'], 5);
        self::assertSame('def', $holidays['md5']);
        // other records are not changed
        self::assertSame(300, (int)$this->subject->findRawByUid(3)['tstamp']);
    }
}
