<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Service;

use MarioMatzulla\Cal\Domain\Repository\OrganizerRepository;
use MarioMatzulla\Cal\Events\GetOrganizerEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;

#[AsEventListener(identifier: 'cal/organizer-service')]
final class OrganizerService extends BaseService
{
    /**
     * @var \MarioMatzulla\Cal\Domain\Repository\OrganizerRepository
     */
    protected $organizerRepository;

    public function __construct(OrganizerRepository $organizerRepository)
    {
        $this->organizerRepository = $organizerRepository;
    }

    public function __invoke(GetOrganizerEvent $event): void
    {
        $this->getOrganizer($event);
    }

    private function getOrganizer(GetOrganizerEvent $event): void
    {
        if ($event->getType() == 'tx_cal_organizer') {
            $foundOrganizer = $this->organizerRepository->findOrganizer($event->getUid())->toArray();
            if (!empty($foundOrganizer)) {
                $event->setOrganizer($foundOrganizer[0]);
                $event->stopPropagation();
            }
        }
    }

}
