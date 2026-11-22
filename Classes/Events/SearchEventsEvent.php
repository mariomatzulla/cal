<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Events;

final class SearchEventsEvent
{
    /**
     * @var \DateTime
     */
    protected $start;

    /**
     * @var \DateTime
     */
    protected $end;

    /**
     * @var array
     */
    protected $events;

    /**
     * @var array
     */
    protected $calendarIds;

    /**
     * @return array<\MarioMatzulla\Cal\Domain\Model\Event>
     */
    public function getEvents()
    {
        return $this->events;
    }

    /**
     * @param array $events
     */
    public function setEvents($events)
    {
        $this->events = $events;
    }

    /**
     * @param array<int> $calendarIds only events of these calendars (empty: no limitation)
     * @param array<int> $excludedCalendarIds never events of these calendars
     * @param array<int> $storagePageIds only events stored in these pages (empty: no limitation)
     */
    public function __construct(\DateTime $start, \DateTime $end, array $calendarIds, protected array $excludedCalendarIds = [], protected array $storagePageIds = [])
    {
        $this->start = $start;
        $this->end = $end;
        $this->calendarIds = $calendarIds;
        $this->events = [ ];
    }

    /**
     * @return array<int>
     */
    public function getExcludedCalendarIds(): array
    {
        return $this->excludedCalendarIds;
    }

    /**
     * @return \DateTime
     */
    public function getStart()
    {
        return $this->start;
    }

    /**
     * @return \DateTime
     */
    public function getEnd()
    {
        return $this->end;
    }

    /**
     * @return array
     */
    public function getCalendarIds()
    {
        return $this->calendarIds;
    }

    /**
     * Page ids of the storage folders (plugin.tx_cal.settings.pidList), empty: no limitation
     *
     * @return array<int>
     */
    public function getStoragePageIds(): array
    {
        return $this->storagePageIds;
    }
}
