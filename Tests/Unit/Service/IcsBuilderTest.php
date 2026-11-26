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
use MarioMatzulla\Cal\Domain\Model\Category;
use MarioMatzulla\Cal\Domain\Model\Event;
use MarioMatzulla\Cal\Domain\Model\Location;
use MarioMatzulla\Cal\Domain\Model\Organizer;
use MarioMatzulla\Cal\Service\IcsBuilder;
use MarioMatzulla\Cal\Service\IcsGenerator;
use MarioMatzulla\Cal\Service\IcsRecurrence;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Sabre\VObject\Reader;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(IcsBuilder::class)]
final class IcsBuilderTest extends UnitTestCase
{
    private string $timezone = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->timezone = date_default_timezone_get();
        date_default_timezone_set('UTC');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->timezone);
        parent::tearDown();
    }

    private function subject(string $recurrence = '', array $overrides = []): IcsBuilder
    {
        $icsRecurrence = self::createStub(IcsRecurrence::class);
        $icsRecurrence->method('getRecurrenceLines')->willReturn($recurrence);
        $icsRecurrence->method('getOverrides')->willReturn($overrides);
        return new IcsBuilder($icsRecurrence);
    }

    private static function storage(object ...$objects): ObjectStorage
    {
        $storage = new ObjectStorage();
        foreach ($objects as $object) {
            $storage->attach($object);
        }
        return $storage;
    }

    private static function event(string $timezone = 'Europe/Berlin'): Event
    {
        $calendar = new Calendar();
        $calendar->setTimezone($timezone);
        $event = new Event();
        $event->setCalendarId(self::storage($calendar));
        $event->setIcsUid('abc@example.org');
        $event->setTitle('Tom & Jerry, live');
        $event->setStartDate(new \DateTime('2026-11-02'));
        $event->setStartTime(new \DateTime('10:00'));
        $event->setEndDate(new \DateTime('2026-11-02'));
        $event->setEndTime(new \DateTime('11:30'));
        return $event;
    }

    private const RESOURCES = ['description' => '', 'attachments' => [], 'images' => []];

    #[Test]
    public function completeEvent(): void
    {
        $event = self::event();
        $first = new Category();
        $first->setTitle('A,b');
        $first->setColor('#d42020');
        $second = new Category();
        $second->setTitle('C');
        $event->setCategories(self::storage($first, $second));
        $location = new Location();
        $location->setName('Town hall');
        $location->setLatitude(52.5);
        $location->setLongitude(13.4);
        $event->setLocationId(self::storage($location));
        $organizer = new Organizer();
        $organizer->setName('Club; e.V.');
        $organizer->setEmail(' club@example.org ');
        $event->setOrganizerId(self::storage($organizer));
        $event->setStatus('tentative');
        $event->setClassification('PRIVATE');

        $vcalendar = $this->subject(
            "RRULE:FREQ=WEEKLY\nEXDATE;TZID=Europe/Berlin:20261109T100000",
            [[
                'recurrenceId' => ';TZID=Europe/Berlin:20261116T100000', 'start' => ';TZID=Europe/Berlin:20261116T140000',
                'end' => ';TZID=Europe/Berlin:20261116T150000', 'title' => 'Moved\, later', 'description' => 'Other room', 'location' => 'Room 2',
            ]],
        )->build(
            $event,
            '4.1.0',
            'https://example.org/?a=1&b=2',
            ['crdate' => 1795046400, 'tstamp' => 1795132800, 'sequence' => 3],
            ['description' => '<p>Line 1 &amp; more</p><p>Line 2</p>', 'attachments' => ['https://example.org/a.pdf'], 'images' => ['https://example.org/i.jpg']],
            IcsGenerator::generateVTimeZone('Europe/Berlin', 2026),
        );
        $data = $vcalendar->serialize();
        self::assertStringContainsString("PRODID:-//TYPO3/NONSGML Calendar Base (cal) V4.1.0//EN\r\n", $data);
        self::assertStringContainsString("BEGIN:VTIMEZONE\r\nTZID:Europe/Berlin\r\n", $data);

        $parsed = Reader::read($data);
        $master = $parsed->select('VEVENT')[0];
        $values = [];
        foreach ($master->children() as $property) {
            $values[] = $property->serialize();
        }
        self::assertSame([
            "UID:abc@example.org\r\n",
            "DTSTAMP:20261120T000000Z\r\n",
            "CREATED:20261119T000000Z\r\n",
            "LAST-MODIFIED:20261120T000000Z\r\n",
            "SEQUENCE:3\r\n",
            "DTSTART;TZID=Europe/Berlin:20261102T100000\r\n",
            "DTEND;TZID=Europe/Berlin:20261102T113000\r\n",
            "RRULE:FREQ=WEEKLY\r\n",
            "EXDATE;TZID=Europe/Berlin:20261109T100000\r\n",
            "SUMMARY:Tom & Jerry\\, live\r\n",
            "DESCRIPTION:Line 1 & more\\nLine 2\r\n",
            "X-ALT-DESC;FMTTYPE=text/html:<!DOCTYPE html><html><body><p>Line 1 &amp\; mo\r\n re</p><p>Line 2</p></body></html>\r\n",
            "URL;VALUE=URI:https://example.org/?a=1&b=2\r\n",
            "LOCATION:Town hall\r\n",
            "GEO:52.5;13.4\r\n",
            "ORGANIZER;CN=\"Club; e.V.\":mailto:club@example.org\r\n",
            "CATEGORIES:A\\,b,C\r\n",
            "STATUS:TENTATIVE\r\n",
            "CLASS:PRIVATE\r\n",
            "COLOR:crimson\r\n",
            "ATTACH:https://example.org/a.pdf\r\n",
            "ATTACH:https://example.org/i.jpg\r\n",
            "IMAGE;VALUE=URI:https://example.org/i.jpg\r\n",
        ], $values);

        $override = $parsed->select('VEVENT')[1];
        self::assertSame('20261116T100000', (string)$override->{'RECURRENCE-ID'});
        self::assertSame(['abc@example.org', '20261120T000000Z', '3', '20261116T140000', '20261116T150000', 'Moved, later', 'Other room', 'Room 2'], [
            (string)$override->UID, (string)$override->DTSTAMP, (string)$override->SEQUENCE, (string)$override->DTSTART, (string)$override->DTEND,
            (string)$override->SUMMARY, (string)$override->DESCRIPTION, (string)$override->LOCATION,
        ]);
    }

    #[Test]
    public function minimalAllDayEvent(): void
    {
        $event = self::event('');
        $event->setAllday(true);
        $event->setEndDate(new \DateTime('2026-11-03'));
        $event->setExtUrl('https://example.com/event');
        $event->setLocation('Hall');
        $organizer = new Organizer();
        $organizer->setName('Without e-mail');
        $event->setOrganizerId(self::storage($organizer));
        $before = time();
        $vEvent = $this->subject('', [['recurrenceId' => ';VALUE=DATE:20261109', 'start' => ';VALUE=DATE:20261110', 'end' => ';VALUE=DATE:20261111', 'title' => 'Moved', 'description' => '', 'location' => '']])
            ->build($event, '4.0.0', 'https://example.org/event', ['crdate' => 0, 'tstamp' => 0, 'sequence' => 0], self::RESOURCES)
            ->select('VEVENT')[0];
        self::assertSame(['20261102', '20261104', 'https://example.com/event', 'Hall'], [(string)$vEvent->DTSTART, (string)$vEvent->DTEND, (string)$vEvent->URL, (string)$vEvent->LOCATION]);
        self::assertGreaterThanOrEqual($before, $vEvent->DTSTAMP->getDateTime()->getTimestamp());
        foreach (['CREATED', 'LAST-MODIFIED', 'DESCRIPTION', 'X-ALT-DESC', 'ORGANIZER', 'CATEGORIES', 'COLOR', 'GEO', 'ATTACH', 'IMAGE', 'RRULE', 'VTIMEZONE'] as $name) {
            self::assertFalse(isset($vEvent->$name), $name);
        }
    }

    #[Test]
    public function withoutTimeZoneTheTimesAreUtc(): void
    {
        $event = self::event('Unknown/Zone');
        $event->setDescription('plain');
        $location = new Location();
        $location->setName('');
        $location->setLatitude(100.0);
        $location->setLongitude(13.4);
        $event->setLocationId(self::storage($location));
        $vEvent = $this->subject()->build($event, '4.0.0', '', ['crdate' => 0, 'tstamp' => 0, 'sequence' => 0], ['description' => 'plain text'] + self::RESOURCES)->select('VEVENT')[0];
        self::assertSame(['20261102T100000Z', '20261102T113000Z', 'plain text'], [(string)$vEvent->DTSTART, (string)$vEvent->DTEND, (string)$vEvent->DESCRIPTION]);
        foreach (['X-ALT-DESC', 'URL', 'LOCATION', 'GEO'] as $name) {
            self::assertFalse(isset($vEvent->$name), $name);
        }
        // event without calendar
        $event->setCalendarId(new ObjectStorage());
        self::assertSame('20261102T100000Z', (string)$this->subject()->build($event, '4.0.0', '', ['crdate' => 0, 'tstamp' => 0, 'sequence' => 0], self::RESOURCES)->select('VEVENT')[0]->DTSTART);
    }

    #[Test]
    public function helpers(): void
    {
        self::assertSame('red', IcsBuilder::colorName('#F00'));
        self::assertSame('mediumseagreen', IcsBuilder::colorName(' #53a062'));
        self::assertSame('', IcsBuilder::colorName('blue'));
        self::assertSame('', IcsBuilder::colorName(''));
        self::assertSame("A & B\nC\nD\n\nE", IcsBuilder::htmlToText("<p>A &amp; B</p><p>C<br>D</p>\n\n\n<ul><li>E</li></ul>"));
        // wall clock time of the server (UTC in this test) without time zone
        self::assertSame('20261102T100000 UTC', IcsBuilder::toDateTime(new \DateTimeImmutable('2026-11-02 10:00'), null)->format('Ymd\THis e'));
        self::assertSame('20261102T100000 Europe/Berlin', IcsBuilder::toDateTime(new \DateTimeImmutable('2026-11-02 10:00'), new \DateTimeZone('Europe/Berlin'))->format('Ymd\THis e'));
    }

    #[Test]
    public function keepClientDataTakesOverThePropertiesCalDoesNotWrite(): void
    {
        $vcalendar = Reader::read("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:cal\r\nBEGIN:VEVENT\r\nUID:a\r\nDTSTAMP:20261126T100000Z\r\nDTSTART:20261201T100000Z\r\nSUMMARY:New\r\nEND:VEVENT\r\n"
            . "BEGIN:VEVENT\r\nUID:a\r\nRECURRENCE-ID:20261208T100000Z\r\nDTSTAMP:20261126T100000Z\r\nDTSTART:20261208T120000Z\r\nSUMMARY:Moved\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n");
        IcsBuilder::keepClientData($vcalendar, "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:client\r\nBEGIN:VEVENT\r\nUID:a\r\nDTSTAMP:20261120T100000Z\r\nDTSTART:20261201T100000Z\r\nSUMMARY:Old\r\n"
            . "CLASS:PRIVATE\r\nATTENDEE;CN=Anna:mailto:anna@example.org\r\nX-MOZ-GENERATION:3\r\nBEGIN:VALARM\r\nACTION:DISPLAY\r\nTRIGGER:-PT15M\r\nEND:VALARM\r\nEND:VEVENT\r\n"
            // the same occurrence with another time zone
            . "BEGIN:VEVENT\r\nUID:a\r\nRECURRENCE-ID;TZID=Europe/Berlin:20261208T110000\r\nDTSTART:20261208T120000Z\r\nSTATUS:TENTATIVE\r\nEND:VEVENT\r\n"
            // an occurrence cal does not have any more
            . "BEGIN:VEVENT\r\nUID:a\r\nRECURRENCE-ID:20261215T100000Z\r\nDTSTART:20261215T120000Z\r\nSTATUS:CANCELLED\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n");
        $data = $vcalendar->serialize();
        // STATUS and CLASS of the event are fields of cal
        self::assertStringContainsString("SUMMARY:New\r\nATTENDEE;CN=Anna:mailto:anna@example.org\r\nX-MOZ-GENERATION:3\r\nBEGIN:VALARM\r\nACTION:DISPLAY\r\nTRIGGER:-PT15M\r\nEND:VALARM\r\nEND:VEVENT", $data);
        self::assertStringNotContainsString('CLASS:PRIVATE', $data);
        self::assertStringContainsString("SUMMARY:Moved\r\nSTATUS:TENTATIVE\r\nEND:VEVENT", $data);
        self::assertStringNotContainsString('SUMMARY:Old', $data);
        self::assertStringNotContainsString('CANCELLED', $data);
        self::assertSame(2, count($vcalendar->select('VEVENT')));

        // without usable previous data nothing changes
        $before = $vcalendar->serialize();
        IcsBuilder::keepClientData($vcalendar, '');
        IcsBuilder::keepClientData($vcalendar, 'no calendar data');
        IcsBuilder::keepClientData($vcalendar, "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:Test\r\nEND:VCARD\r\n");
        self::assertSame($before, $vcalendar->serialize());
    }
}
