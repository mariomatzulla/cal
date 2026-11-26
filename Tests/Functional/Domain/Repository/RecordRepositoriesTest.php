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
use MarioMatzulla\Cal\Domain\Model\EventDeviation;
use MarioMatzulla\Cal\Domain\Repository\CalendarOwnerRelationRepository;
use MarioMatzulla\Cal\Domain\Repository\EventDeviationRepository;
use MarioMatzulla\Cal\Domain\Repository\FrontendUserRepository;
use MarioMatzulla\Cal\Domain\Repository\LocationRepository;
use MarioMatzulla\Cal\Domain\Repository\OrganizerRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(LocationRepository::class)]
#[CoversClass(OrganizerRepository::class)]
#[CoversClass(EventDeviationRepository::class)]
#[CoversClass(CalendarOwnerRelationRepository::class)]
#[CoversClass(FrontendUserRepository::class)]
final class RecordRepositoriesTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['mariomatzulla/cal'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Records.csv');
    }

    #[Test]
    public function findLocationAndOrganizer(): void
    {
        $locations = $this->get(LocationRepository::class);
        self::assertSame('Town hall', $locations->findLocation(1)->getFirst()->getName());
        self::assertSame('Church', $locations->findLocation(2, [6])->getFirst()->getName());
        self::assertNull($locations->findLocation(1, [6])->getFirst());

        $organizers = $this->get(OrganizerRepository::class);
        self::assertSame('Club', $organizers->findOrganizer(1)->getFirst()->getName());
        self::assertSame('Choir', $organizers->findOrganizer(2, [6])->getFirst()->getName());
        self::assertNull($organizers->findOrganizer(1, [6])->getFirst());
    }

    #[Test]
    public function findDeviationsOfAnEvent(): void
    {
        $deviations = $this->get(EventDeviationRepository::class)->findByEventUid(2);
        self::assertContainsOnlyInstancesOf(EventDeviation::class, $deviations);
        self::assertSame(['Moved', 'Cancelled'], array_map(static fn(EventDeviation $deviation): string => $deviation->getTitle(), $deviations));
        self::assertSame(36000, (int)$deviations[0]->getOrigStartTime());
        self::assertSame([], $this->get(EventDeviationRepository::class)->findByEventUid(99));
    }

    #[Test]
    public function findCalendarsOfAUserAndTheGroups(): void
    {
        $owners = $this->get(CalendarOwnerRelationRepository::class);
        self::assertSame([1, 2], $owners->findCalendarListForFeUser(9));
        self::assertSame([1, 2, 3], $owners->findCalendarListForFeUser(9, [4, 5]));
        self::assertSame([3], $owners->findCalendarListForFeUser(11, [4]));
        self::assertSame([], $owners->findCalendarListForFeUser(11));
    }

    #[Test]
    public function findFrontendUsers(): void
    {
        $users = $this->get(FrontendUserRepository::class);
        self::assertSame('anna', $users->findByUid(9)['username']);
        self::assertSame('anna', $users->findByName('anna')['username']);
        // without restrictions
        self::assertSame('bob', $users->findByName('bob')['username']);
        self::assertNull($users->findByUid(99));
        self::assertNull($users->findByName('unknown'));
    }
}
