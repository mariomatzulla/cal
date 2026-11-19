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
     */
    public function __construct(\DateTime $start, \DateTime $end, array $calendarIds, protected array $excludedCalendarIds = [])
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
}
