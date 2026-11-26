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
use MarioMatzulla\Cal\Domain\Model\Calendar;
use MarioMatzulla\Cal\Domain\Model\Event;
use MarioMatzulla\Cal\Domain\Repository\CalendarRepository;
use MarioMatzulla\Cal\Domain\Repository\EventRepository;
use MarioMatzulla\Cal\Service\IcsBuilder;
use MarioMatzulla\Cal\Service\IcsGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use Sabre\VObject\Component\VCalendar;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Package\MetaData;
use TYPO3\CMS\Core\Package\PackageInterface;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Routing\RouterInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Core\View\ViewInterface;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(IcsGenerator::class)]
final class IcsGeneratorTest extends UnitTestCase
{
    /**
     * @var array<string, mixed> arguments of IcsBuilder::build()
     */
    private array $assigned = [];

    /**
     * @var array{crdate: int, tstamp: int, sequence: int, data: string}
     */
    private array $meta = ['crdate' => 1, 'tstamp' => 2, 'sequence' => 3, 'data' => ''];

    private string $summary = 'Test';

    /**
     * @var list<string>
     */
    private array $calls = [];

    /**
     * @param string $site 'page': site of the page of the event, 'first': first site of the installation, 'none', 'error'
     */
    private function createGenerator(?EventRepository $eventRepository = null, string $version = '4.1.0', string $site = 'page'): IcsGenerator
    {
        $view = self::createStub(ViewInterface::class);
        $view->method('render')->willReturn("\n<!--cal:description--><p>Text</p>\n<!--cal:attachments-->\nhttps://example.org/a.pdf?x=1&amp;y=2\n\n<!--cal:images-->\nhttps://example.org/i.jpg\n");
        $viewFactory = self::createStub(ViewFactoryInterface::class);
        $viewFactory->method('create')->willReturnCallback(function (ViewFactoryData $data) use ($view): ViewInterface {
            self::assertSame('EXT:cal/Resources/Private/Templates/Admin/IcsResources.html', $data->templatePathAndFilename);
            return $view;
        });

        if ($eventRepository === null) {
            $eventRepository = self::createStub(EventRepository::class);
            $eventRepository->method('updateWithIcsUid')->willReturnCallback(function (int $uid, string $icsUid): void {
                $this->calls[] = 'ics_uid ' . $uid . ' ' . $icsUid;
            });
            $eventRepository->method('updateWithCaldavData')->willReturnCallback(function (int $uid, string $data, ?int $sequence = null): void {
                $this->calls[] = 'caldav_data ' . $uid . ' ' . $sequence . ' ' . json_encode($data);
            });
            $eventRepository->method('findIcsMetaData')->willReturnCallback(fn(): array => $this->meta);
        }
        $calendarRepository = self::createStub(CalendarRepository::class);
        $calendarRepository->method('touch')->willReturnCallback(function (int $uid): void {
            $this->calls[] = 'touch ' . $uid;
        });

        $router = self::createStub(RouterInterface::class);
        $router->method('generateUri')->willReturnCallback(static fn(int $page, array $arguments): Uri => new Uri('https://example.org/' . $page . '?' . http_build_query($arguments)));
        $siteObject = self::createStub(Site::class);
        $siteObject->method('getRouter')->willReturn($router);
        $siteObject->method('getRootPageId')->willReturn($site === 'first' ? 2 : 1);
        $siteFinder = self::createStub(SiteFinder::class);
        if ($site === 'page') {
            $siteFinder->method('getSiteByPageId')->willReturn($siteObject);
        } elseif ($site === 'error') {
            $siteFinder->method('getSiteByPageId')->willThrowException(new \RuntimeException('error'));
        } else {
            $siteFinder->method('getSiteByPageId')->willThrowException(new SiteNotFoundException('no site', 1));
            $siteFinder->method('getAllSites')->willReturn($site === 'first' ? ['main' => $siteObject] : []);
        }

        $metaData = self::createStub(MetaData::class);
        $metaData->method('getVersion')->willReturn($version);
        $package = self::createStub(PackageInterface::class);
        $package->method('getPackageMetaData')->willReturn($metaData);
        $packageManager = self::createStub(PackageManager::class);
        $packageManager->method('isPackageActive')->willReturn($version !== 'inactive');
        $packageManager->method('getPackage')->willReturn($package);

        $icsBuilder = self::createStub(IcsBuilder::class);
        $icsBuilder->method('build')->willReturnCallback(function (Event $event, string $version, string $eventUrl, array $meta, array $resources, string $vTimeZone): VCalendar {
            $this->assigned = ['event' => $event, 'calVersion' => $version, 'eventUrl' => $eventUrl, 'meta' => $meta, 'resources' => $resources, 'vTimeZone' => $vTimeZone];
            $vcalendar = new VCalendar(['PRODID' => '-//test//EN']);
            $vEvent = $vcalendar->createComponent('VEVENT', null, false);
            $vEvent->add('UID', 'x');
            $vEvent->add('DTSTAMP', new \DateTimeImmutable('@' . time()));
            $vEvent->add('SEQUENCE', $meta['sequence']);
            $vEvent->add('SUMMARY', $this->summary);
            $vcalendar->add($vEvent);
            return $vcalendar;
        });
        return new IcsGenerator($viewFactory, $eventRepository, $calendarRepository, $siteFinder, $packageManager, $icsBuilder);
    }

    private static function event(string $timezone = 'Europe/Berlin', string $icsUid = ''): Event
    {
        $calendar = new Calendar();
        $calendar->_setProperty('uid', 3);
        $calendar->setTitle('Calendar');
        $calendar->setTimezone($timezone);
        $calendars = new ObjectStorage();
        $calendars->attach($calendar);
        $event = new Event();
        $event->_setProperty('uid', 7);
        $event->setPid(5);
        $event->setCalendarId($calendars);
        $event->setIcsUid($icsUid);
        $event->setStartDate(new \DateTime('2026-11-25'));
        $event->setEndDate(new \DateTime('2026-11-25'));
        return $event;
    }

    #[Test]
    public function updateIcsCreatesTheIcalendarData(): void
    {
        $event = self::event();
        $this->createGenerator()->updateIcs($event, self::createStub(ServerRequestInterface::class));
        $icsUid = md5('Calendar_3_7');
        self::assertSame($icsUid, $event->getIcsUid());
        self::assertStringStartsWith("BEGIN:VCALENDAR\r\n", $event->getCaldavData());
        self::assertStringEndsWith("SUMMARY:Test\r\nEND:VEVENT\r\nEND:VCALENDAR", $event->getCaldavData());
        // without previous data the sequence is not changed
        self::assertSame(['ics_uid 7 ' . $icsUid, 'caldav_data 7 3 ' . json_encode($event->getCaldavData()), 'touch 3'], $this->calls);

        self::assertSame($event, $this->assigned['event']);
        self::assertSame('4.1.0', $this->assigned['calVersion']);
        self::assertStringStartsWith('https://example.org/1?tx_cal_controller', $this->assigned['eventUrl']);
        self::assertStringContainsString('uid%5D=7', $this->assigned['eventUrl']);
        self::assertStringContainsString("TZID:Europe/Berlin\r\n", $this->assigned['vTimeZone']);
        self::assertSame(['crdate' => 1, 'tstamp' => 2, 'sequence' => 3], $this->assigned['meta']);
        self::assertSame(['description' => '<p>Text</p>', 'attachments' => ['https://example.org/a.pdf?x=1&y=2'], 'images' => ['https://example.org/i.jpg']], $this->assigned['resources']);
    }

    #[Test]
    public function theSequenceIsIncrementedWhenTheDataChanges(): void
    {
        $generator = $this->createGenerator();
        $event = self::event();
        $generator->updateIcs($event, null);
        // the same data (except DTSTAMP, LAST-MODIFIED, SEQUENCE)
        $this->meta['data'] = str_replace('SEQUENCE:3', "SEQUENCE:3\r\nLAST-MODIFIED:20260101T000000Z", (string)$event->getCaldavData());
        $this->calls = [];
        $generator->updateIcs($event, null);
        self::assertStringStartsWith('caldav_data 7 3 ', $this->calls[0]);

        $this->summary = 'Changed';
        $this->calls = [];
        $generator->updateIcs($event, null);
        self::assertStringStartsWith('caldav_data 7 4 ', $this->calls[0]);
        self::assertStringContainsString("SEQUENCE:4\r\n", (string)$event->getCaldavData());
    }

    #[Test]
    public function alarmsAndAttendeesOfACalDavClientAreKept(): void
    {
        $generator = $this->createGenerator();
        $event = self::event();
        $generator->updateIcs($event, null);
        $this->meta['data'] = str_replace("SUMMARY:Test\r\n", "SUMMARY:Test\r\nATTENDEE:mailto:anna@example.org\r\nBEGIN:VALARM\r\nACTION:DISPLAY\r\nTRIGGER:-PT15M\r\nEND:VALARM\r\n", (string)$event->getCaldavData());
        $this->calls = [];
        $generator->updateIcs($event, null);
        self::assertStringContainsString("ATTENDEE:mailto:anna@example.org\r\nBEGIN:VALARM\r\nACTION:DISPLAY\r\nTRIGGER:-PT15M\r\nEND:VALARM\r\n", (string)$event->getCaldavData());
        // nothing else changed: the same sequence
        self::assertStringStartsWith('caldav_data 7 3 ', $this->calls[0]);
    }

    #[Test]
    public function parseResources(): void
    {
        self::assertSame(['description' => '', 'attachments' => [], 'images' => []], IcsGenerator::parseResources(''));
        self::assertSame(['description' => 'Text', 'attachments' => [], 'images' => []], IcsGenerator::parseResources("<!--cal:description-->Text\n<!--cal:other-->x"));
    }

    #[Test]
    public function updateIcsKeepsAnExistingUid(): void
    {
        $event = self::event('', 'existing@example.org');
        $this->createGenerator(null, '', 'none')->updateIcs($event, null);
        self::assertSame('existing@example.org', $event->getIcsUid());
        self::assertSame(['caldav_data 7 3 ' . json_encode($event->getCaldavData()), 'touch 3'], $this->calls);
        // fallbacks: version of cal 4, no URL without site, no time zone
        self::assertSame(['4.0.0', '', ''], [$this->assigned['calVersion'], $this->assigned['eventUrl'], $this->assigned['vTimeZone']]);

        $this->createGenerator(null, 'inactive')->updateIcs(self::event(), null);
        self::assertSame('4.0.0', $this->assigned['calVersion']);
    }

    #[Test]
    public function eventUrlOfRecordsOutsideOfASite(): void
    {
        $event = self::event();
        self::assertStringStartsWith('https://example.org/2?', $this->createGenerator(null, '4.1.0', 'first')->generateEventUrl($event));
        self::assertSame('', $this->createGenerator(null, '4.1.0', 'none')->generateEventUrl($event));
        self::assertSame('', $this->createGenerator(null, '4.1.0', 'error')->generateEventUrl($event));
    }

    #[Test]
    public function updateIcsSkipsEventsWithoutDatesOrPage(): void
    {
        $generator = $this->createGenerator();
        foreach (['setStartDate', 'setEndDate', 'setPid'] as $setter) {
            $event = self::event();
            $event->$setter($setter === 'setPid' ? 0 : null);
            $generator->updateIcs($event, null);
        }
        // without calendar (calendar_id is a required field, but the record may be broken)
        $event = self::event();
        $event->setCalendarId(new ObjectStorage());
        $generator->updateIcs($event, null);
        self::assertSame([], $this->calls);
    }

    #[Test]
    public function generateUpdatesAllEventsWithoutIcalendarData(): void
    {
        $eventRepository = $this->createMock(EventRepository::class);
        $eventRepository->expects(self::exactly(3))->method('check')->willReturnOnConsecutiveCalls([self::event(), self::event()], [], [self::event()]);
        $eventRepository->expects(self::exactly(2))->method('updateWithCaldavData');
        $eventRepository->expects(self::exactly(2))->method('findIcsMetaData')->willReturn($this->meta);
        $generator = $this->createGenerator($eventRepository);
        $generator->generate(self::createStub(ServerRequestInterface::class));
        self::assertSame([], $generator->check());
        self::assertCount(1, $generator->check());
    }

    #[Test]
    public function checkShowsWhyNoDataCanBeCreated(): void
    {
        $withoutCalendar = self::event();
        $withoutCalendar->setCalendarId(new ObjectStorage());
        $withoutStart = self::event();
        $withoutStart->setStartDate(null);
        $withoutEnd = self::event();
        $withoutEnd->setEndDate(null);
        $rootPage = self::event();
        $rootPage->setPid(0);
        $events = [self::event(), $withoutCalendar, $withoutStart, $withoutEnd, $rootPage];
        $eventRepository = self::createStub(EventRepository::class);
        $eventRepository->method('check')->willReturnOnConsecutiveCalls($events, []);
        $generator = $this->createGenerator($eventRepository);
        $result = $generator->checkWithReasons();
        self::assertSame(['', 'calendar', 'startDate', 'endDate', 'page'], array_column($result, 'reason'));
        self::assertSame($events, array_column($result, 'event'));
        self::assertSame([], $generator->checkWithReasons());
    }

    public static function vTimeZoneDataProvider(): array
    {
        return [
            'daylight saving time, northern hemisphere' => ['Europe/Berlin', [
                'BEGIN:VTIMEZONE', 'TZID:Europe/Berlin',
                'BEGIN:DAYLIGHT', 'TZOFFSETFROM:+0100', 'TZOFFSETTO:+0200', 'TZNAME:CEST', 'DTSTART:20260329T020000', 'RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU', 'END:DAYLIGHT',
                'BEGIN:STANDARD', 'TZOFFSETFROM:+0200', 'TZOFFSETTO:+0100', 'TZNAME:CET', 'DTSTART:20261025T030000', 'RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU', 'END:STANDARD',
                'END:VTIMEZONE',
            ]],
            'daylight saving time, second sunday' => ['America/Los_Angeles', [
                'BEGIN:VTIMEZONE', 'TZID:America/Los_Angeles',
                'BEGIN:DAYLIGHT', 'TZOFFSETFROM:-0800', 'TZOFFSETTO:-0700', 'TZNAME:PDT', 'DTSTART:20260308T020000', 'RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=2SU', 'END:DAYLIGHT',
                'BEGIN:STANDARD', 'TZOFFSETFROM:-0700', 'TZOFFSETTO:-0800', 'TZNAME:PST', 'DTSTART:20261101T020000', 'RRULE:FREQ=YEARLY;BYMONTH=11;BYDAY=1SU', 'END:STANDARD',
                'END:VTIMEZONE',
            ]],
            'southern hemisphere' => ['Australia/Sydney', [
                'BEGIN:VTIMEZONE', 'TZID:Australia/Sydney',
                'BEGIN:STANDARD', 'TZOFFSETFROM:+1100', 'TZOFFSETTO:+1000', 'TZNAME:AEST', 'DTSTART:20260405T030000', 'RRULE:FREQ=YEARLY;BYMONTH=4;BYDAY=1SU', 'END:STANDARD',
                'BEGIN:DAYLIGHT', 'TZOFFSETFROM:+1000', 'TZOFFSETTO:+1100', 'TZNAME:AEDT', 'DTSTART:20261004T020000', 'RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=1SU', 'END:DAYLIGHT',
                'END:VTIMEZONE',
            ]],
            'without daylight saving time' => ['Asia/Kolkata', [
                'BEGIN:VTIMEZONE', 'TZID:Asia/Kolkata',
                'BEGIN:STANDARD', 'TZOFFSETFROM:+0530', 'TZOFFSETTO:+0530', 'TZNAME:IST', 'DTSTART:19700101T000000', 'END:STANDARD',
                'END:VTIMEZONE',
            ]],
            'negative offset with minutes' => ['America/St_Johns', [
                'BEGIN:VTIMEZONE', 'TZID:America/St_Johns',
                'BEGIN:DAYLIGHT', 'TZOFFSETFROM:-0330', 'TZOFFSETTO:-0230', 'TZNAME:NDT', 'DTSTART:20260308T020000', 'RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=2SU', 'END:DAYLIGHT',
                'BEGIN:STANDARD', 'TZOFFSETFROM:-0230', 'TZOFFSETTO:-0330', 'TZNAME:NST', 'DTSTART:20261101T020000', 'RRULE:FREQ=YEARLY;BYMONTH=11;BYDAY=1SU', 'END:STANDARD',
                'END:VTIMEZONE',
            ]],
        ];
    }

    /**
     * @param list<string> $lines
     */
    #[Test]
    #[DataProvider('vTimeZoneDataProvider')]
    public function generateVTimeZone(string $timezone, array $lines): void
    {
        self::assertSame(implode("\r\n", $lines) . "\r\n", IcsGenerator::generateVTimeZone($timezone, 2026));
    }

    #[Test]
    public function generateVTimeZoneOfUnknownZones(): void
    {
        self::assertSame('', IcsGenerator::generateVTimeZone(''));
        self::assertSame('', IcsGenerator::generateVTimeZone('Mars/Olympus'));
        self::assertStringContainsString('TZID:UTC', IcsGenerator::generateVTimeZone('UTC'));
    }
}
