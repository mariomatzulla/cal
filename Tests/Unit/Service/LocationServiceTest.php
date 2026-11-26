<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Unit\Service;

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
use MarioMatzulla\Cal\Domain\Model\Location;
use MarioMatzulla\Cal\Domain\Model\Organizer;
use MarioMatzulla\Cal\Domain\Repository\LocationRepository;
use MarioMatzulla\Cal\Domain\Repository\OrganizerRepository;
use MarioMatzulla\Cal\Events\GetLocationEvent;
use MarioMatzulla\Cal\Events\GetOrganizerEvent;
use MarioMatzulla\Cal\Service\LocationService;
use MarioMatzulla\Cal\Service\OrganizerService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(LocationService::class)]
#[CoversClass(OrganizerService::class)]
final class LocationServiceTest extends UnitTestCase
{
    private function queryResult(array $records): QueryResultInterface
    {
        $result = self::createStub(QueryResultInterface::class);
        $result->method('toArray')->willReturn($records);
        return $result;
    }

    #[Test]
    public function locationServiceFindsTheLocation(): void
    {
        $location = new Location();
        $repository = $this->createMock(LocationRepository::class);
        $repository->expects(self::exactly(2))->method('findLocation')->with(5, [7])->willReturnOnConsecutiveCalls($this->queryResult([$location]), $this->queryResult([]));
        $service = new LocationService($repository);

        $event = new GetLocationEvent('tx_cal_location', 5, [7]);
        $service($event);
        self::assertSame($location, $event->getLocation());
        self::assertTrue($event->isPropagationStopped());

        $notFound = new GetLocationEvent('tx_cal_location', 5, [7]);
        $service($notFound);
        self::assertNull($notFound->getLocation());
        self::assertFalse($notFound->isPropagationStopped());

        // other types are handled by other listeners
        $other = new GetLocationEvent('tx_other', 5, [7]);
        $service($other);
        self::assertNull($other->getLocation());
    }

    #[Test]
    public function organizerServiceFindsTheOrganizer(): void
    {
        $organizer = new Organizer();
        $repository = $this->createMock(OrganizerRepository::class);
        $repository->expects(self::exactly(2))->method('findOrganizer')->with(6, [])->willReturnOnConsecutiveCalls($this->queryResult([$organizer]), $this->queryResult([]));
        $service = new OrganizerService($repository);

        $event = new GetOrganizerEvent('tx_cal_organizer', 6);
        $service($event);
        self::assertSame($organizer, $event->getOrganizer());
        self::assertTrue($event->isPropagationStopped());

        $notFound = new GetOrganizerEvent('tx_cal_organizer', 6);
        $service($notFound);
        self::assertNull($notFound->getOrganizer());
        self::assertFalse($notFound->isPropagationStopped());

        $other = new GetOrganizerEvent('tx_other', 6);
        $service($other);
        self::assertNull($other->getOrganizer());
    }
}
