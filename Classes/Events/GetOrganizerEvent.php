<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Events;

use Psr\EventDispatcher\StoppableEventInterface;

final class GetOrganizerEvent implements StoppableEventInterface
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
     * @var \MarioMatzulla\Cal\Model\OrganizerInterface
     */
    protected $organizer;

    /**
     * @var bool
     */
    private $propagationStopped = false;

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

    public function __construct(string $type, int $uid)
    {
        $this->type = $type;
        $this->uid = $uid;
    }

    /**
     * @return \MarioMatzulla\Cal\Model\OrganizerInterface
     */
    public function getOrganizer()
    {
        return $this->organizer;
    }

    /**
     * @param \MarioMatzulla\Cal\Model\OrganizerInterface $organizer
     */
    public function setOrganizer($organizer)
    {
        $this->organizer = $organizer;
    }
}
