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
use MarioMatzulla\Cal\Domain\Model\Category;
use MarioMatzulla\Cal\Domain\Model\Event;
use MarioMatzulla\Cal\Domain\Model\EventDeviation;
use MarioMatzulla\Cal\Domain\Model\EventRecDeviationModel;
use MarioMatzulla\Cal\Domain\Model\EventRecModel;
use MarioMatzulla\Cal\Domain\Model\Location;
use MarioMatzulla\Cal\Domain\Model\Organizer;
use MarioMatzulla\Cal\Tests\Unit\AccessorAssertionsTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(Event::class)]
#[CoversClass(EventRecModel::class)]
#[CoversClass(EventRecDeviationModel::class)]
final class EventTest extends UnitTestCase
{
    use AccessorAssertionsTrait;

    #[Test]
    public function accessors(): void
    {
        $event = new Event();
        self::assertSame('tx_cal_event', $event->getModelType());
        self::assertSame('default_catheader', $event->getHeaderstyle());
        self::assertSame('default_catbody', $event->getBodystyle());
        self::assertSame('', $event->getTeaser());
        $this->assertAccessors($event, [
            'caldavData' => 'BEGIN:VCALENDAR', 'icsUid' => 'uid@example.org', 'rdate' => '20261125', 'exception' => true,
            'deviation' => 'deviation', 'freq' => 'week', 'until' => new \DateTime('2026-12-31'), 'cnt' => 5,
            'byday' => 'MO,WE', 'bymonthday' => '15', 'bymonth' => '11', 'intrval' => 2, 'rdateText' => 'text', 'rdateType' => 'date',
            'attachment' => 'attachment', 'crdate' => 1764000000, 'categories' => 'categories', 'organizerId' => 'organizer id',
            'image' => 'image', 'locationId' => 'location id', 'headerstyle' => 'green_catheader', 'bodystyle' => 'green_catbody',
            'calendarId' => 'calendar id', 'allday' => true, 'location' => 'Town hall', 'organizer' => 'Club', 'teaser' => 'Teaser',
            'description' => 'Description', 'endDate' => new \DateTime('2026-11-26'), 'endTime' => new \DateTime('11:00'),
            'startTime' => new \DateTime('10:00'), 'startDate' => new \DateTime('2026-11-25'), 'title' => 'Event', 'type' => 1,
            'page' => 12, 'extUrl' => 'https://example.org', 'locationPid' => 13, 'locationLink' => 'https://example.org/location',
            'organizerPid' => 14, 'organizerLink' => 'https://example.org/organizer',
        ]);
        self::assertSame(1, $event->getEventType());
        $event->setTeaser(null);
        $event->setExtUrl(null);
        $event->setLocationLink(null);
        $event->setOrganizerLink(null);
        self::assertSame('', $event->getTeaser() . $event->getExtUrl() . $event->getLocationLink() . $event->getOrganizerLink());
    }

    private static function category(string $color, int $priority = 0, ?Category $parent = null): Category
    {
        static $uid = 0;
        $category = new Category();
        $category->_setProperty('uid', ++$uid);
        $category->setColor($color);
        $category->setColorPriority($priority);
        if ($parent !== null) {
            $category->setParent($parent);
        }
        return $category;
    }

    #[Test]
    public function colorWithoutCategories(): void
    {
        $event = new Event();
        self::assertSame('', $event->getColor());
        self::assertSame('', $event->getColorStyle());
        $event->setCategories([self::category('')]);
        self::assertSame('', $event->getColor());
    }

    #[Test]
    public function colorOfTheCategoryWithTheHighestPriorityWins(): void
    {
        $event = new Event();
        $event->setCategories([self::category('#111111', 1), self::category('#222222', 2), self::category('#333333', 1)]);
        self::assertSame('#222222', $event->getColor());
        self::assertSame('--cal-event-color: #222222; --cal-event-border-color: color-mix(in srgb, #222222 70%, #000);', $event->getColorStyle());
    }

    #[Test]
    public function colorOfTheDeeperCategoryWinsWithTheSamePriority(): void
    {
        $parent = self::category('#111111');
        $event = new Event();
        $event->setCategories([$parent, self::category('#222222', 0, $parent)]);
        self::assertSame('#222222', $event->getColor());
    }

    #[Test]
    public function relatedObjects(): void
    {
        $event = new Event();
        self::assertNull($event->getLocationObject());
        self::assertNull($event->getOrganizerObject());

        $event->setLocationId(new ObjectStorage());
        $event->setOrganizerId(new ObjectStorage());
        $event->setCalendarId(new ObjectStorage());
        self::assertNull($event->getLocationObject());
        self::assertNull($event->getOrganizerObject());
        self::assertNull($event->getCalendarObject());

        $location = new Location();
        $organizer = new Organizer();
        $calendar = new Calendar();
        $event->getLocationId()->attach($location);
        $event->getOrganizerId()->attach($organizer);
        $event->getCalendarId()->attach($calendar);
        self::assertSame($location, $event->getLocationObject());
        self::assertSame($organizer, $event->getOrganizerObject());
        self::assertSame($calendar, $event->getCalendarObject());
    }

    private static function event(?string $startDate, ?string $startTime, ?string $endDate, ?string $endTime, bool $allday = false): Event
    {
        $event = new Event();
        $event->setAllday($allday);
        $event->setStartDate($startDate === null ? null : new \DateTime($startDate));
        $event->setStartTime($startTime === null ? null : new \DateTime('1970-01-01 ' . $startTime));
        $event->setEndDate($endDate === null ? null : new \DateTime($endDate));
        $event->setEndTime($endTime === null ? null : new \DateTime('1970-01-01 ' . $endTime));
        return $event;
    }

    public static function startEndDataProvider(): array
    {
        return [
            'with times' => ['2026-11-25', '10:00', '2026-11-25', '11:30', false, '20261125100000', '20261125113000', '20261125113000'],
            'without times' => ['2026-11-25', null, '2026-11-26', null, false, '20261125000000', '20261126000000', '20261126000000'],
            'end at midnight of the start day' => ['2026-11-25', '22:00', '2026-11-25', '00:00', false, '20261125220000', '20261126000000', '20261126000000'],
            'all day' => ['2026-11-25', null, '2026-11-26', null, true, '20261125000000', '20261126000000', '20261127000000'],
            'without end date' => ['2026-11-25', '10:00', null, '12:00', false, '20261125100000', '20261125120000', '20261125120000'],
        ];
    }

    #[Test]
    #[DataProvider('startEndDataProvider')]
    public function startAndEnd(?string $startDate, ?string $startTime, ?string $endDate, ?string $endTime, bool $allday, string $start, string $end, string $icsEnd): void
    {
        $event = self::event($startDate, $startTime, $endDate, $endTime, $allday);
        self::assertSame($start, $event->getStart()->format('YmdHis'));
        self::assertSame($end, $event->getEnd()->format('YmdHis'));
        self::assertSame($icsEnd, $event->getIcsEnd()->format('YmdHis'));
    }

    #[Test]
    public function startAndEndWithoutDatesAreToday(): void
    {
        $event = self::event(null, null, null, null);
        self::assertSame(gmdate('Ymd') . '000000', $event->getStart()->format('YmdHis'));
        self::assertSame(gmdate('Ymd') . '000000', $event->getEnd()->format('YmdHis'));
    }

    private static function fullEvent(): Event
    {
        $event = self::event('2026-11-25', '10:00', '2026-11-25', '11:00');
        $event->_setProperty('uid', 42);
        $event->setTitle('Event');
        $event->setLocation('Town hall');
        $event->setOrganizer('Club');
        $event->setDescription('Description');
        $event->setTeaser('Teaser');
        $event->setCaldavData('data');
        $event->setHeaderstyle('blue_catheader');
        $event->setBodystyle('blue_catbody');
        $event->setType(2);
        $event->setPage(3);
        $event->setExtUrl('https://example.org');
        $event->setLocationPid(4);
        $event->setLocationLink('https://example.org/l');
        $event->setOrganizerPid(5);
        $event->setOrganizerLink('https://example.org/o');
        $event->setCnt(6);
        $event->setUntil('until');
        $event->setFreq('day');
        $event->setImage(['image']);
        $event->setCategories([self::category('#123456')]);
        $event->setLocationId(new ObjectStorage());
        $event->setOrganizerId(new ObjectStorage());
        $event->setCalendarId(new ObjectStorage());
        return $event;
    }

    #[Test]
    public function recurringInstanceDelegatesToTheEvent(): void
    {
        $event = self::fullEvent();
        $instance = new EventRecModel($event, 20261202140000, 20261202153000);
        self::assertSame('20261202140000', $instance->getStart()->format('YmdHis'));
        self::assertSame('20261202153000', $instance->getEnd()->format('YmdHis'));
        self::assertSame('20261202153000', $instance->getIcsEnd()->format('YmdHis'));
        self::assertSame('20261202', $instance->getStartdate()->format('Ymd'));
        self::assertSame('140000', $instance->getStarttime()->format('His'));
        self::assertSame('20261202', $instance->getEnddate()->format('Ymd'));
        self::assertSame('153000', $instance->getEndtime()->format('His'));

        foreach (['Location', 'Organizer', 'LocationId', 'OrganizerId', 'LocationObject', 'OrganizerObject', 'CaldavData',
            'Description', 'Teaser', 'HeaderStyle', 'BodyStyle', 'Title', 'Uid', 'ModelType', 'EventType', 'Type', 'Page',
            'ExtUrl', 'LocationPid', 'LocationLink', 'OrganizerPid', 'OrganizerLink', 'Cnt', 'Categories', 'Color',
            'ColorStyle', 'Until', 'Image', 'CalendarObject', 'Freq'] as $name) {
            self::assertSame($event->{'get' . $name}(), $instance->{'get' . $name}(), $name);
        }
        self::assertSame($event->isAllday(), $instance->isAllday());
    }

    #[Test]
    public function recurringInstanceEndsTheNextDayAtMidnight(): void
    {
        $instance = new EventRecModel(self::fullEvent(), 20261202220000, 20261202000000);
        self::assertSame('20261203000000', $instance->getEnd()->format('YmdHis'));
    }

    #[Test]
    public function recurringAllDayInstance(): void
    {
        $event = self::fullEvent();
        $event->setAllday(true);
        $instance = new EventRecModel($event, 20261202000000, 20261202000000);
        self::assertSame('20261202000000', $instance->getEnd()->format('YmdHis'));
        self::assertSame('20261203000000', $instance->getIcsEnd()->format('YmdHis'));
    }

    #[Test]
    public function recurringInstanceWithoutTimes(): void
    {
        $instance = new class (self::fullEvent(), 20261202140000, 20261202153000) extends EventRecModel {
            public function getStartTime()
            {
                return null;
            }

            public function getEndTime()
            {
                return null;
            }
        };
        self::assertSame('20261202000000', $instance->getStart()->format('YmdHis'));
        self::assertSame('20261202000000', $instance->getEnd()->format('YmdHis'));
    }

    #[Test]
    public function deviationOverridesTitleAndImage(): void
    {
        $event = self::fullEvent();
        $deviation = new EventDeviation();
        $deviation->_setProperty('uid', 7);
        $deviation->setTitle('');
        $deviation->setImage([]);
        $instance = new EventRecDeviationModel($event, $deviation, 20261202140000, 20261202153000);
        self::assertSame(7, $instance->getDeviationId());
        self::assertSame('Event', $instance->getTitle());
        self::assertSame(['image'], $instance->getImage());

        $deviation->setTitle('Changed');
        $deviation->setImage(['other']);
        self::assertSame('Changed', $instance->getTitle());
        self::assertSame(['other'], $instance->getImage());
    }
}
