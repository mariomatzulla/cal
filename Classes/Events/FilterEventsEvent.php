<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Events;

final class FilterEventsEvent
{
    /**
     * @var array
     */
    protected $events;

    /**
     * @var array
     */
    protected $settings;

    public function __construct(array $events, array $settings)
    {
        $this->events = $events;
        $this->settings = $settings;
    }

    /**
     * @return array
     */
    public function getEvents()
    {
        return $this->events;
    }

    /**
     * @return array
     */
    public function getSettings()
    {
        return $this->settings;
    }

    /**
     * @param array $events
     */
    public function setEvents($events)
    {
        $this->events = $events;
    }
}
