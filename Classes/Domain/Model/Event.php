<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Domain\Model;

use DateTime;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

/**
 * This file is part of the TYPO3 extension Calendar Base (cal).
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 extension Calendar Base (cal) project - inspiring people to share!
 */

/**
 * Class Event
 */
class Event extends AbstractEntity
{
    /**
     * @var string
     */
    protected $title;

    // TODO This property comes from rendering -> WeekView;
    public $matrixValue;

    /**
     * @var string
     */
    protected $modelType = 'tx_cal_event';

    protected $headerstyle = 'default_catheader';

    protected $bodystyle = 'default_catbody';

    /**
     * @var bool
     */
    protected $allday;

    /**
     * @var string
     */
    protected $location;

    /**
     * @var ObjectStorage<Location>
     */
    protected $locationId;

    /**
     * @var string
     */
    protected $organizer;

    /**
     * @var ObjectStorage<Organizer>
     */
    protected $organizerId;

    /**
     * @var string
     */
    protected $description;

    /**
     * Short text for list views (field "teaser", shown in the backend if enabled in the extension configuration)
     *
     * @var string
     */
    protected $teaser = '';

    /**
     * @var \DateTime|null
     */
    protected $crdate;

    /**
     * @var \DateTime
     */
    protected $startDate;

    /**
     * @var \DateTime
     */
    protected $startTime;

    /**
     * @var \DateTime
     */
    protected $endDate;

    /**
     * @var \DateTime
     */
    protected $endTime;

    /**
     * @var ObjectStorage<Calendar>
     */
    protected $calendarId;

    /**
     * @var bool
     */
    protected $hidden;

    /**
     * Images: Fal media items
     *
     * @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\TYPO3\CMS\Extbase\Domain\Model\FileReference>
     * @lazy
     */
    protected $image;

    /**
     * Images: Fal media items
     *
     * @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\TYPO3\CMS\Extbase\Domain\Model\FileReference>
     * @lazy
     */
    protected $attachment;

    /**
     * Categories
     *
     * @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\MarioMatzulla\Cal\Domain\Model\Category>
     * @lazy
     */
    protected $categories;

    /**
     * Deviation
     *
     * @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\MarioMatzulla\Cal\Domain\Model\EventDeviation>
     * @lazy
     */
    protected $deviation;

    /**
     * @var string
     */
    protected $freq;

    /**
     * @var \DateTime|null
     */
    protected $until;

    /**
     * @var int
     */
    protected $cnt;

    /**
     * @var string
     */
    protected $byday;

    /**
     * @var string
     */
    protected $bymonthday;

    /**
     * @var string
     */
    protected $bymonth;

    /**
     * @var int
     */
    protected $intrval;

    /**
     * @var string
     */
    protected $rdateText;

    /**
     * @var string
     */
    protected $rdateType;

    /**
     * @var string
     */
    protected $byweekno;

    /**
     * @var string
     */
    protected $byyearday;

    /**
     * @var string
     */
    protected $byhour;

    /**
     * @var string
     */
    protected $byminute;

    /**
     * @var string
     */
    protected $bysecond;

    /**
     * @var string
     */
    protected $bysetpos;

    /**
     * @var string
     */
    protected $wkst = '';

    /**
     * @var bool
     */
    protected $exception = false;

    /**
     * @var string
     */
    protected $rdate = '';

    /**
     * @var string
     */
    protected $icsUid = '';

    /**
     * @var string
     */
    protected $caldavData;

    /**
     * @return string
     */
    public function getCaldavData()
    {
        return $this->caldavData;
    }

    /**
     * @param string $caldavData
     */
    public function setCaldavData($caldavData)
    {
        $this->caldavData = $caldavData;
    }

    /**
     * @return string
     */
    public function getIcsUid()
    {
        return $this->icsUid;
    }

    /**
     * @param string $icsUid
     */
    public function setIcsUid($icsUid)
    {
        $this->icsUid = $icsUid;
    }

    /**
     * @return string
     */
    public function getRdate()
    {
        return $this->rdate;
    }

    /**
     * @param string $rdate
     */
    public function setRdate($rdate)
    {
        $this->rdate = $rdate;
    }

    /**
     * @return bool
     */
    public function isException()
    {
        return $this->exception;
    }

    /**
     * @param bool $exception
     */
    public function setException($exception)
    {
        $this->exception = $exception;
    }

    /**
     * @return string
     */
    public function getWkst()
    {
        return $this->wkst;
    }

    /**
     * @param string $wkst
     */
    public function setWkst($wkst)
    {
        $this->wkst = $wkst;
    }

    /**
     * @return string
     */
    public function getBysetpos()
    {
        return $this->bysetpos;
    }

    /**
     * @param string $bysetpos
     */
    public function setBysetpos($bysetpos)
    {
        $this->bysetpos = $bysetpos;
    }

    /**
     * @return string
     */
    public function getBysecond()
    {
        return $this->bysecond;
    }

    /**
     * @param string $bysecond
     */
    public function setBysecond($bysecond)
    {
        $this->bysecond = $bysecond;
    }

    /**
     * @return string
     */
    public function getByhour()
    {
        return $this->byhour;
    }

    /**
     * @return string
     */
    public function getByminute()
    {
        return $this->byminute;
    }

    /**
     * @param string $byhour
     */
    public function setByhour($byhour)
    {
        $this->byhour = $byhour;
    }

    /**
     * @param string $byminute
     */
    public function setByminute($byminute)
    {
        $this->byminute = $byminute;
    }

    /**
     * @return string
     */
    public function getByyearday()
    {
        return $this->byyearday;
    }

    /**
     * @param string $byyearday
     */
    public function setByyearday($byyearday)
    {
        $this->byyearday = $byyearday;
    }

    /**
     * @param \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\MarioMatzulla\Cal\Domain\Model\EventDeviation> $deviation
     */
    public function setDeviation($deviation)
    {
        $this->deviation = $deviation;
    }

    /**
     * @return string
     */
    public function getByweekno()
    {
        return $this->byweekno;
    }

    /**
     * @param string $byweekno
     */
    public function setByweekno($byweekno)
    {
        $this->byweekno = $byweekno;
    }

    /**
     * @return string
     */
    public function getFreq()
    {
        return $this->freq;
    }

    /**
     * @return \DateTime|null
     */
    public function getUntil()
    {
        return $this->until;
    }

    /**
     * @return number
     */
    public function getCnt()
    {
        return $this->cnt;
    }

    /**
     * @return string
     */
    public function getByday()
    {
        return $this->byday;
    }

    /**
     * @return string
     */
    public function getBymonthday()
    {
        return $this->bymonthday;
    }

    /**
     * @return string
     */
    public function getBymonth()
    {
        return $this->bymonth;
    }

    /**
     * @return number
     */
    public function getIntrval()
    {
        return $this->intrval;
    }

    /**
     * @return string
     */
    public function getRdateText()
    {
        return $this->rdateText;
    }

    /**
     * @return string
     */
    public function getRdateType()
    {
        return $this->rdateType;
    }

    /**
     * @param string $freq
     */
    public function setFreq($freq)
    {
        $this->freq = $freq;
    }

    /**
     * @param \DateTime|null $until
     */
    public function setUntil($until)
    {
        $this->until = $until;
    }

    /**
     * @param number $cnt
     */
    public function setCnt($cnt)
    {
        $this->cnt = $cnt;
    }

    /**
     * @param string $byday
     */
    public function setByday($byday)
    {
        $this->byday = $byday;
    }

    /**
     * @param string $bymonthday
     */
    public function setBymonthday($bymonthday)
    {
        $this->bymonthday = $bymonthday;
    }

    /**
     * @param string $bymonth
     */
    public function setBymonth($bymonth)
    {
        $this->bymonth = $bymonth;
    }

    /**
     * @param number $intrval
     */
    public function setIntrval($intrval)
    {
        $this->intrval = $intrval;
    }

    /**
     * @param string $rdateText
     */
    public function setRdateText($rdateText)
    {
        $this->rdateText = $rdateText;
    }

    /**
     * @param string $rdateType
     */
    public function setRdateType($rdateType)
    {
        $this->rdateType = $rdateType;
    }

    /**
     * @return \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\MarioMatzulla\Cal\Domain\Model\EventDeviation>
     */
    public function getDeviation()
    {
        return $this->deviation;
    }

    /**
     * @return \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\TYPO3\CMS\Extbase\Domain\Model\FileReference>
     */
    public function getAttachment()
    {
        return $this->attachment;
    }

    /**
     * @param \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\TYPO3\CMS\Extbase\Domain\Model\FileReference> $attachment
     */
    public function setAttachment($attachment)
    {
        $this->attachment = $attachment;
    }

    /**
     * @return \DateTime|null
     */
    public function getCrdate()
    {
        return $this->crdate;
    }

    /**
     * @param \DateTime|null $crdate
     */
    public function setCrdate($crdate)
    {
        $this->crdate = $crdate;
    }

    /**
     * @return \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\MarioMatzulla\Cal\Domain\Model\Category>
     */
    public function getCategories()
    {
        return $this->categories;
    }

    /**
     * Color of the event, taken from its categories. Only categories with an own color count:
     * 1. the highest color priority (field tx_cal_color_priority) wins,
     * 2. with the same priority the more specific category (deeper in the category tree) wins,
     * 3. otherwise the first one.
     * Empty if no category has a color (then the default color of the CSS is used).
     */
    public function getColor(): string
    {
        $color = '';
        $best = null;
        foreach ($this->getCategories() ?? [] as $category) {
            if (!$category instanceof Category || $category->getColor() === '') {
                continue;
            }
            $rank = [$category->getColorPriority(), $category->getDepth()];
            if ($best === null || $rank > $best) {
                $best = $rank;
                $color = $category->getColor();
            }
        }
        return $color;
    }

    /**
     * CSS custom properties for the color of the event, for a style attribute:
     * --cal-event-color and a darker --cal-event-border-color (see categories.css / tokens.css)
     */
    public function getColorStyle(): string
    {
        $color = $this->getColor();
        if ($color === '') {
            return '';
        }
        return '--cal-event-color: ' . $color . '; --cal-event-border-color: color-mix(in srgb, ' . $color . ' 70%, #000);';
    }

    /**
     * @param \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\MarioMatzulla\Cal\Domain\Model\Category> $categories
     */
    public function setCategories($categories)
    {
        $this->categories = $categories;
    }

    /**
     * @return \TYPO3\CMS\Extbase\Persistence\ObjectStorage<Organizer>
     */
    public function getOrganizerId()
    {
        return $this->organizerId;
    }

    /**
     * @param \TYPO3\CMS\Extbase\Persistence\ObjectStorage<Organizer> $organizerId
     */
    public function setOrganizerId($organizerId)
    {
        $this->organizerId = $organizerId;
    }

    /**
     * @return Organizer|null the first organizer
     */
    public function getOrganizerObject()
    {
        if ($this->organizerId) {
            $list = $this->organizerId->toArray();
            if (count($list) > 0) {
                return $this->organizerId->toArray() [0];
            }
        }
        return null;
    }

    /**
     * @return \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\TYPO3\CMS\Extbase\Domain\Model\FileReference>
     */
    public function getImage()
    {
        return $this->image;
    }

    /**
     * @param \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\TYPO3\CMS\Extbase\Domain\Model\FileReference> $image
     */
    public function setImage($image)
    {
        $this->image = $image;
    }

    /**
     * @return \TYPO3\CMS\Extbase\Persistence\ObjectStorage<Location>
     */
    public function getLocationId()
    {
        return $this->locationId;
    }

    /**
     * @return Location|null the first location
     */
    public function getLocationObject()
    {
        if ($this->locationId) {
            $list = $this->locationId->toArray();
            if (count($list) > 0) {
                return $this->locationId->toArray() [0];
            }
        }
        return null;
    }

    /**
     * @param \TYPO3\CMS\Extbase\Persistence\ObjectStorage<Location> $locationId
     */
    public function setLocationId($locationId)
    {
        $this->locationId = $locationId;
    }

    /**
     * @return string
     */
    public function getHeaderstyle()
    {
        return $this->headerstyle;
    }

    /**
     * @return string
     */
    public function getBodystyle()
    {
        return $this->bodystyle;
    }

    /**
     * @param string $headerstyle
     */
    public function setHeaderstyle($headerstyle)
    {
        $this->headerstyle = $headerstyle;
    }

    /**
     * @param string $bodystyle
     */
    public function setBodystyle($bodystyle)
    {
        $this->bodystyle = $bodystyle;
    }

    /**
     * @return \TYPO3\CMS\Extbase\Persistence\ObjectStorage<Calendar>
     */
    public function getCalendarId()
    {
        return $this->calendarId;
    }

    /**
     * @return Calendar|null
     */
    public function getCalendarObject()
    {
        return $this->calendarId->toArray() [0] ?? null;
    }

    /**
     * @param \TYPO3\CMS\Extbase\Persistence\ObjectStorage<Calendar> $calendarId
     */
    public function setCalendarId($calendarId)
    {
        $this->calendarId = $calendarId;
    }

    /**
     * @return string
     */
    public function getModelType()
    {
        return $this->modelType;
    }

    public function getStart()
    {
        $start = new \MarioMatzulla\Cal\Model\CalendarDateTime();
        $start->setDate((int)$this->getStartDate()->format('Y'), (int)$this->getStartDate()->format('m'), (int)$this->getStartDate()->format('d'));
        if ($this->getStartTime() !== null) {
            $start->setTime((int)$this->getStartTime()->format('H'), (int)$this->getStartTime()->format('i'));
        } else {
            $start->setTime(0, 0);
        }
        return $start;
    }

    public function getEnd()
    {
        $end = new \MarioMatzulla\Cal\Model\CalendarDateTime();
        $end->setDate((int)$this->getEndDate()->format('Y'), (int)$this->getEndDate()->format('m'), (int)$this->getEndDate()->format('d'));
        if ($this->getEndTime() !== null) {
            $end->setTime((int)$this->getEndTime()->format('H'), (int)$this->getEndTime()->format('i'));
        } else {
            $end->setTime(0, 0);
        }
        return $end;
    }

    /**
     * @param string $modelType
     */
    private function setModelType($modelType)
    {
        $this->modelType = $modelType;
    }

    /**
     * @return bool
     */
    public function isAllday()
    {
        return $this->allday;
    }

    /**
     * @param bool $allday
     */
    public function setAllday($allday)
    {
        $this->allday = $allday;
    }

    /**
     * @return string
     */
    public function getLocation()
    {
        return $this->location;
    }

    /**
     * @return string
     */
    public function getOrganizer()
    {
        return $this->organizer;
    }

    /**
     * @return string
     */
    public function getTeaser(): string
    {
        return (string)$this->teaser;
    }

    public function setTeaser(?string $teaser): void
    {
        $this->teaser = (string)$teaser;
    }

    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @return \DateTime
     */
    public function getEndDate()
    {
        return $this->endDate;
    }

    /**
     * @return \DateTime
     */
    public function getEndTime()
    {
        return $this->endTime;
    }

    /**
     * @param string $location
     */
    public function setLocation($location)
    {
        $this->location = $location;
    }

    /**
     * @param string $organizer
     */
    public function setOrganizer($organizer)
    {
        $this->organizer = $organizer;
    }

    /**
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * @param \DateTime $endDate
     */
    public function setEndDate($endDate)
    {
        $this->endDate = $endDate;
    }

    /**
     * @param \DateTime $endTime
     */
    public function setEndTime($endTime)
    {
        $this->endTime = $endTime;
    }

    /**
     * @return \DateTime
     */
    public function getStartTime()
    {
        return $this->startTime;
    }

    /**
     * @param \DateTime $startTime
     */
    public function setStartTime($startTime)
    {
        $this->startTime = $startTime;
    }

    /**
     * @return \DateTime
     */
    public function getStartDate()
    {
        return $this->startDate;
    }

    /**
     * @param \DateTime $startDate
     */
    public function setStartDate($startDate)
    {
        $this->startDate = $startDate;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }
}
