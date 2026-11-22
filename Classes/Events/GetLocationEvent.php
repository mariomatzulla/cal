<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Events;

use Psr\EventDispatcher\StoppableEventInterface;

final class GetLocationEvent implements StoppableEventInterface
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
     * @var \MarioMatzulla\Cal\Model\LocationInterface
     */
    protected $location;

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

    /**
     * @param array<int> $storagePageIds only records stored in these pages (empty: no limitation)
     */
    public function __construct(string $type, int $uid, protected array $storagePageIds = [])
    {
        $this->type = $type;
        $this->uid = $uid;
    }

    /**
     * @return \MarioMatzulla\Cal\Model\LocationInterface
     */
    public function getLocation()
    {
        return $this->location;
    }

    /**
     * @param \MarioMatzulla\Cal\Model\LocationInterface $location
     */
    public function setLocation($location)
    {
        $this->location = $location;
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
