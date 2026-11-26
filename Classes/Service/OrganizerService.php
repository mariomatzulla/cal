<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Service;

use MarioMatzulla\Cal\Domain\Repository\OrganizerRepository;
use MarioMatzulla\Cal\Events\GetOrganizerEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Finds the organizer record of the organizer view
 */
#[AsEventListener(identifier: 'cal/organizer-service')]
final class OrganizerService
{
    public function __construct(
        private readonly OrganizerRepository $organizerRepository,
    ) {}

    public function __invoke(GetOrganizerEvent $event): void
    {
        if ($event->getType() !== 'tx_cal_organizer') {
            return;
        }
        $found = $this->organizerRepository->findOrganizer($event->getUid(), $event->getStoragePageIds())->toArray();
        if ($found !== []) {
            $event->setOrganizer($found[0]);
            $event->stopPropagation();
        }
    }
}
