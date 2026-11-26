<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Service;

use MarioMatzulla\Cal\Domain\Repository\LocationRepository;
use MarioMatzulla\Cal\Events\GetLocationEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Finds the location record of the location view
 */
#[AsEventListener(identifier: 'cal/location-service')]
final class LocationService
{
    public function __construct(
        private readonly LocationRepository $locationRepository,
    ) {}

    public function __invoke(GetLocationEvent $event): void
    {
        if ($event->getType() !== 'tx_cal_location') {
            return;
        }
        $found = $this->locationRepository->findLocation($event->getUid(), $event->getStoragePageIds())->toArray();
        if ($found !== []) {
            $event->setLocation($found[0]);
            $event->stopPropagation();
        }
    }
}
