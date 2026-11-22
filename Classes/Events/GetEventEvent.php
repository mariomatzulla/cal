<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Events;

use MarioMatzulla\Cal\Model\CalendarDateTime;
use Psr\EventDispatcher\StoppableEventInterface;

final class GetEventEvent implements StoppableEventInterface
{
    /**
     * @var string
     */
    protected $type;

    /**
     * @var int
     */
    protected $uid;

    /**
     * @var CalendarDateTime
     */
    protected $date;

    /**
     * @var \MarioMatzulla\Cal\Domain\Model\Event
     */
    protected $event;

    /**
     * @var array
     */
    protected $calendarIds;

    /**
     * @var bool
     */
    private $propagationStopped = false;

    /**
 * @return \MarioMatzulla\Cal\Model\CalendarDateTime
 */
    public function getDate()
    {
        return $this->date;
    }

    /**
       * @return array
       */
    public function getCalendarIds()
    {
        return $this->calendarIds;
    }

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return int
     */
    public function getUid()
    {
        return $this->uid;
    }

    /**
     * @return \MarioMatzulla\Cal\Domain\Model\Event
     */
    public function getEvent()
    {
        return $this->event;
    }

    /**
     * @param \MarioMatzulla\Cal\Domain\Model\Event $event
     */
    public function setEvent($event)
    {
        $this->event = $event;
    }

    /**
     * @param array<int> $calendarIds only events of these calendars (empty: no limitation)
     * @param array<int> $excludedCalendarIds never events of these calendars
     * @param array<int> $storagePageIds only events stored in these pages (empty: no limitation)
     */
    public function __construct(string $type, int $uid, CalendarDateTime $date, array $calendarIds, protected array $excludedCalendarIds = [], protected array $storagePageIds = [])
    {
        $this->type = $type;
        $this->uid = $uid;
        $this->date = $date;
        $this->calendarIds = $calendarIds;
    }

    /**
     * @return array<int>
     */
    public function getExcludedCalendarIds(): array
    {
        return $this->excludedCalendarIds;
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
