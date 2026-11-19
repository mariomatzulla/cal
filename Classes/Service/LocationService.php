<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Service;

use MarioMatzulla\Cal\Domain\Repository\LocationRepository;
use MarioMatzulla\Cal\Events\GetLocationEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;

#[AsEventListener(identifier: 'cal/location-service')]
final class LocationService extends BaseService
{
    /**
     * @var \MarioMatzulla\Cal\Domain\Repository\LocationRepository
     */
    protected $locationRepository;

    public function __construct(LocationRepository $locationRepository)
    {
        $this->locationRepository = $locationRepository;
    }

    public function __invoke(GetLocationEvent $event): void
    {
        $this->getLocation($event);
    }

    private function getLocation(GetLocationEvent $event): void
    {
        if ($event->getType() == 'tx_cal_location') {
            $foundLocation = $this->locationRepository->findLocation($event->getUid())->toArray();
            if (!empty($foundLocation)) {
                $event->setLocation($foundLocation[0]);
                $event->stopPropagation();
            }
        }
    }

}
