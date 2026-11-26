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
use MarioMatzulla\Cal\Domain\Model\Index;
use MarioMatzulla\Cal\Domain\Repository\IndexRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(IndexRepository::class)]
final class IndexRepositoryTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['mariomatzulla/cal'];

    private IndexRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Index.csv');
        $this->subject = $this->get(IndexRepository::class);
    }

    #[Test]
    public function findEventUidsOfTheRange(): void
    {
        // starts in the range, ends in the range, around the range; not after the range and not the exception event
        $uids = array_map('intval', $this->subject->findEventUids(new \DateTime('2026-11-01 00:00:00'), new \DateTime('2026-11-30 23:59:59')));
        sort($uids);
        self::assertSame([2, 3, 7], $uids);
    }

    #[Test]
    public function findByEventUidStartAndEnd(): void
    {
        $entries = $this->subject->findByEventUidStartAndEnd(2, new \DateTime('2026-11-05 00:00:00'), new \DateTime('2026-11-30 00:00:00'));
        self::assertContainsOnlyInstancesOf(Index::class, $entries);
        // not the index of the exception event with the uid 2
        self::assertSame(['20261109100000'], array_map(static fn(Index $entry): string => sprintf('%.0f', $entry->getStartDatetime()), $entries));
        self::assertSame(4, (int)$entries[0]->getEventDeviationUid());
        self::assertSame([], $this->subject->findByEventUidStartAndEnd(99, new \DateTime('2026-01-01'), new \DateTime('2027-01-01')));
    }

    #[Test]
    public function deleteByEventUidsAndTablename(): void
    {
        $this->subject->deleteByEventUidsAndTablename([], 'tx_cal_event');
        $this->subject->deleteByEventUidsAndTablename([2, 3], 'tx_cal_event');
        $uids = array_map('intval', $this->subject->findEventUids(new \DateTime('2026-01-01'), new \DateTime('2027-01-01')));
        sort($uids);
        self::assertSame([7, 8], $uids);
        // the index of the exception event with the uid 2 is kept
        self::assertSame([2], array_map('intval', $this->subject->findEventUids(new \DateTime('2026-01-01'), new \DateTime('2027-01-01'), 'tx_cal_exception_event')));
    }
}
