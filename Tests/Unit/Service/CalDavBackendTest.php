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
use MarioMatzulla\Cal\Domain\Repository\CalendarRepository;
use MarioMatzulla\Cal\Domain\Repository\EventRepository;
use MarioMatzulla\Cal\Domain\Repository\FrontendUserRepository;
use MarioMatzulla\Cal\Service\CalDavBackend;
use MarioMatzulla\Cal\Service\CalDavRecurrenceWriter;
use MarioMatzulla\Cal\Service\PageCacheService;
use MarioMatzulla\Cal\Service\SlugService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\Exception\NotImplemented;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(CalDavBackend::class)]
final class CalDavBackendTest extends UnitTestCase
{
    private const ICS = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:abc\r\nSUMMARY:Meeting\r\nDTSTART:20261125T090000Z\r\nDTEND:20261125T100000Z\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

    /**
     * @var list<string>
     */
    private array $calls = [];

    /**
     * @param array<int, array<string, mixed>> $calendars calendar records by uid
     * @param array<string, array<string, mixed>> $records event records by ics_uid, a key starting with "deleted:" is deleted
     */
    private function createBackend(array $calendars = [], array $records = []): CalDavBackend
    {
        $calendarRepository = $this->createStub(CalendarRepository::class);
        $calendarRepository->method('findRawByUid')->willReturnCallback(static fn(int $uid): ?array => $calendars[$uid] ?? null);
        $calendarRepository->method('findByUids')->willReturnCallback(static fn(string $uids): array => array_values(array_intersect_key($calendars, array_flip(array_map('intval', explode(',', $uids))))));
        $calendarRepository->method('touch')->willReturnCallback(function (int $uid): void {
            $this->calls[] = 'touch ' . $uid;
        });

        $eventRepository = $this->createStub(EventRepository::class);
        $eventRepository->method('findAllForCalendarNative')->willReturn(array_values($records));
        $eventRepository->method('findAllForCalendarAndUri')->willReturnCallback(static function (int $calendarId, string $icsUid, bool $includeDeleted) use ($records): array|false {
            return $records[$icsUid] ?? ($includeDeleted ? ($records['deleted:' . $icsUid] ?? false) : false);
        });
        $eventRepository->method('updateEvent')->willReturnCallback(function (int $calendarId, string $icsUid, array $values): void {
            $this->calls[] = 'update ' . $calendarId . ' ' . $icsUid . ' ' . implode(',', array_keys($values)) . (isset($values['deleted']) ? ' deleted=' . $values['deleted'] : '');
        });
        $eventRepository->method('persistEvent')->willReturnCallback(function (array $values): void {
            $this->calls[] = 'persist pid=' . $values['pid'] . ' calendar=' . $values['calendar_id'] . ' uid=' . $values['ics_uid'] . ' title=' . $values['title'] . ' crdate=' . ($values['crdate'] === $values['tstamp'] ? 'tstamp' : 'other');
        });

        $userRepository = $this->createStub(FrontendUserRepository::class);
        $userRepository->method('findByName')->willReturnCallback(static fn(string $name): ?array => $name === 'anna' ? ['tx_cal_calendar' => '1,2'] : null);

        $recurrenceWriter = $this->createStub(CalDavRecurrenceWriter::class);
        $recurrenceWriter->method('write')->willReturnCallback(function (int $uid, array $details): void {
            $this->calls[] = 'recurrence ' . $uid . ' ' . json_encode($details);
        });

        $pageCacheService = $this->createStub(PageCacheService::class);
        $pageCacheService->method('flush')->willReturnCallback(function (): void {
            $this->calls[] = 'flush';
        });

        // without TCA the slug service does nothing
        return new CalDavBackend($eventRepository, $calendarRepository, $userRepository, new SlugService($this->createStub(ConnectionPool::class)), $recurrenceWriter, $pageCacheService);
    }

    private const CALENDARS = [
        1 => ['uid' => 1, 'pid' => 5, 'title' => 'Club', 'tstamp' => 100, 'type' => 0, 'timezone' => 'Europe/Berlin'],
        2 => ['uid' => 2, 'pid' => 5, 'title' => 'Holidays', 'tstamp' => 200, 'type' => 1, 'timezone' => ''],
    ];

    private static function record(string $icsUid, string $data = self::ICS): array
    {
        return ['uid' => 9, 'ics_uid' => $icsUid, 'tstamp' => 300, 'caldav_data' => $data];
    }

    #[Test]
    public function getCalendarsForUser(): void
    {
        $calendars = $this->createBackend(self::CALENDARS)->getCalendarsForUser('principals/anna');
        self::assertCount(2, $calendars);
        self::assertSame(1, $calendars[0]['id']);
        self::assertSame('Club', $calendars[0]['uri']);
        self::assertSame('Club', $calendars[0]['{DAV:}displayname']);
        self::assertSame('principals/anna', $calendars[0]['principaluri']);
        self::assertSame('W/"100"', $calendars[0]['{http://calendarserver.org/}getctag']);
        self::assertSame('http://sabredav.org/ns/sync-token/100', $calendars[0]['{http://sabredav.org/ns}sync-token']);
        self::assertInstanceOf(SupportedCalendarComponentSet::class, $calendars[0]['{urn:ietf:params:xml:ns:caldav}supported-calendar-component-set']);
        self::assertFalse($calendars[0]['{http://sabredav.org/ns}read-only']);
        // imported calendars are read-only
        self::assertTrue($calendars[1]['{http://sabredav.org/ns}read-only']);

        self::assertSame([], $this->createBackend(self::CALENDARS)->getCalendarsForUser('principals/unknown'));
    }

    #[Test]
    public function getCalendarObjects(): void
    {
        $objects = $this->createBackend([], ['abc' => self::record('abc'), 'empty' => self::record('empty', ' ')])->getCalendarObjects('1');
        self::assertSame([[
            'id' => 9,
            'uri' => 'abc.ics',
            'lastmodified' => 300,
            'calendarid' => 1,
            'calendardata' => self::ICS,
            'etag' => '"' . md5(rtrim(self::ICS)) . '"',
            'size' => strlen(self::ICS),
            'component' => 'vevent',
        ]], $objects);
    }

    #[Test]
    public function getCalendarObject(): void
    {
        $backend = $this->createBackend([], ['abc' => self::record('abc'), 'old.ics' => self::record('old.ics')]);
        self::assertSame('abc.ics', $backend->getCalendarObject(1, 'abc.ics')['uri']);
        // uid stored with the extension by older versions
        self::assertSame('old.ics', $backend->getCalendarObject(1, 'old.ics')['uri']);
        self::assertNull($backend->getCalendarObject(1, 'missing.ics'));
    }

    #[Test]
    public function createCalendarObject(): void
    {
        $backend = $this->createBackend(self::CALENDARS, ['deleted:old' => self::record('old')]);
        self::assertSame('"' . md5(rtrim(self::ICS)) . '"', $backend->createCalendarObject(1, 'new.ics', self::ICS));
        // a deleted event with the same uid is restored
        $backend->createCalendarObject(1, 'old.ics', self::ICS);
        self::assertSame('persist pid=5 calendar=1 uid=new title=Meeting crdate=tstamp', $this->calls[0]);
        // the pages with calendar plugins show the change at once
        self::assertSame(['touch 1', 'flush'], [$this->calls[1], $this->calls[2]]);
        self::assertStringStartsWith('update 1 old ', $this->calls[3]);
        self::assertStringEndsWith(' deleted=0', $this->calls[3]);
        self::assertStringContainsString('caldav_data', $this->calls[3]);
        self::assertStringNotContainsString('ics_uid', $this->calls[3]);
    }

    #[Test]
    public function createCalendarObjectInAMissingCalendar(): void
    {
        $this->expectException(NotFound::class);
        $this->createBackend()->createCalendarObject(3, 'new.ics', self::ICS);
    }

    #[Test]
    public function importedCalendarsAreReadOnly(): void
    {
        $backend = $this->createBackend(self::CALENDARS, ['abc' => self::record('abc')]);
        foreach (['createCalendarObject', 'updateCalendarObject', 'deleteCalendarObject'] as $method) {
            try {
                $backend->$method(2, 'abc.ics', self::ICS);
                self::fail($method . ' must throw');
            } catch (Forbidden) {
            }
        }
        self::assertSame([], $this->calls);
    }

    #[Test]
    public function updateCalendarObject(): void
    {
        $backend = $this->createBackend(self::CALENDARS, ['abc' => self::record('abc')]);
        self::assertSame('"' . md5(rtrim(self::ICS)) . '"', $backend->updateCalendarObject(1, 'abc.ics', self::ICS));
        self::assertStringStartsWith('update 1 abc ', $this->calls[0]);
        self::assertSame('recurrence 9 {"exdates":[],"overrides":[]}', $this->calls[1]);
        self::assertSame(['touch 1', 'flush'], array_slice($this->calls, 2));

        $this->expectException(NotFound::class);
        $backend->updateCalendarObject(1, 'missing.ics', self::ICS);
    }

    #[Test]
    public function deleteCalendarObject(): void
    {
        $backend = $this->createBackend(self::CALENDARS, ['abc' => self::record('abc')]);
        $backend->deleteCalendarObject(1, 'missing.ics');
        self::assertSame([], $this->calls);
        $backend->deleteCalendarObject(1, 'abc.ics');
        self::assertSame(['update 1 abc deleted,tstamp deleted=1', 'touch 1', 'flush'], $this->calls);
    }

    #[Test]
    public function calendarsCannotBeCreatedOrDeleted(): void
    {
        $backend = $this->createBackend();
        foreach ([fn() => $backend->createCalendar('principals/anna', 'new', []), fn() => $backend->deleteCalendar(1)] as $call) {
            try {
                $call();
                self::fail('must throw');
            } catch (NotImplemented) {
            }
        }
        self::assertTrue(true);
    }

    #[Test]
    public function uriAndIcsUid(): void
    {
        self::assertSame('abc', CalDavBackend::uriToIcsUid('abc.ICS'));
        self::assertSame('abc', CalDavBackend::uriToIcsUid('abc'));
        self::assertSame('abc.ics', CalDavBackend::icsUidToUri('abc'));
        self::assertSame('abc.ics', CalDavBackend::icsUidToUri('abc.ics'));
    }
}
