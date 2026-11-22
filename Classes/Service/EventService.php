<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Service;

use MarioMatzulla\Cal\Domain\Model\Event;
use MarioMatzulla\Cal\Domain\Model\EventDeviation;
use MarioMatzulla\Cal\Domain\Model\EventRecDeviationModel;
use MarioMatzulla\Cal\Domain\Model\EventRecModel;
use MarioMatzulla\Cal\Domain\Repository\CalendarOwnerRelationRepository;
use MarioMatzulla\Cal\Domain\Repository\CalendarRepository;
use MarioMatzulla\Cal\Domain\Repository\EventDeviationRepository;
use MarioMatzulla\Cal\Domain\Repository\EventRepository;
use MarioMatzulla\Cal\Domain\Repository\FrontendUserRepository;
use MarioMatzulla\Cal\Domain\Repository\IndexRepository;
use MarioMatzulla\Cal\Events\GetEventEvent;
use MarioMatzulla\Cal\Events\SearchEventsEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;

#[AsEventListener(identifier: 'cal/event-service')]
final class EventService extends BaseService
{
    /**
     * @var \MarioMatzulla\Cal\Domain\Repository\EventRepository
     */
    protected $eventRepository;

    /**
     * @var \MarioMatzulla\Cal\Domain\Repository\CalendarRepository
     */
    protected $calendarRepository;

    /**
     * @var \MarioMatzulla\Cal\Domain\Repository\CalendarOwnerRelationRepository
     */
    protected $calendarOwnerRelationRepository;

    /**
     * @var \MarioMatzulla\Cal\Domain\Repository\IndexRepository
     */
    protected $indexRepository;

    /**
     * @var \MarioMatzulla\Cal\Domain\Repository\EventDeviationRepository
     */
    protected $eventDeviationRepository;

    public function __construct(
        FrontendUserRepository $frontendUserRepository,
        EventRepository $eventRepository,
        CalendarRepository $calendarRepository,
        CalendarOwnerRelationRepository $calendarOwnerRelationRepository,
        IndexRepository $indexRepository,
        EventDeviationRepository $eventDeviationRepository,
    ) {
        parent::__construct($frontendUserRepository);
        $this->eventRepository = $eventRepository;
        $this->calendarRepository = $calendarRepository;
        $this->calendarOwnerRelationRepository = $calendarOwnerRelationRepository;
        $this->indexRepository = $indexRepository;
        $this->eventDeviationRepository = $eventDeviationRepository;
    }

    public function __invoke(SearchEventsEvent | GetEventEvent $event): void
    {
        if ($event instanceof SearchEventsEvent) {
            $this->searchEvents($event);
        } elseif ($event instanceof GetEventEvent) {
            $this->getEvent($event);
        }
    }

    /**
     * @param array<int|string> $calendarLimitationList
     * @param array<int> $excludedCalendarIds
     * @return array<int>
     */
    protected function getAllowedCalendarIds(array $calendarLimitationList, array $excludedCalendarIds = []): array
    {

        // which calendars to show?
        // 1. Limitation to calendars in flexform and typoscript - if there is none, show public calendars (get a list)
        // 2. from this list show public calendars and if user is logged in, show private calendars of this user
        $publicCalendarListIds = $this->getPublicCalendarIds();

        $privateCalendarListIds = $this->getPrivateCalendarList();

        $calendarIds = array_merge($publicCalendarListIds, $privateCalendarListIds);
        $calendarIds = array_map('intval', $calendarIds);
        if (!empty($calendarLimitationList)) {
            $calendarIds = array_intersect($calendarIds, array_map('intval', $calendarLimitationList));
        }
        if (!empty($excludedCalendarIds)) {
            $calendarIds = array_diff($calendarIds, array_map('intval', $excludedCalendarIds));
        }
        return array_values(array_unique($calendarIds));
    }

    /**
     * @return array<int>
     */
    protected function getPrivateCalendarList(): array
    {
        $loggedInUser = $this->getLoggedInUser();
        if ($loggedInUser != null) {
            return $this->calendarOwnerRelationRepository->findCalendarListForFeUser((int)$loggedInUser ['uid']);
        }
        return [];
    }

    /**
     * @return array<int>
     */
    protected function getPublicCalendarIds(): array
    {
        $publicCalendarList = $this->calendarRepository->findPublicCalendarList();
        $publicCalendarListIds = [ ];
        foreach ($publicCalendarList as $calendar) {
            $publicCalendarListIds [] = $calendar->getUid();
        }
        return $publicCalendarListIds;
    }

    protected function searchEvents(SearchEventsEvent $event): void
    {
        $calendarIds = $this->getAllowedCalendarIds($event->getCalendarIds(), $event->getExcludedCalendarIds());
        if ($calendarIds === []) {
            // No calendar left to show (e.g. all calendars excluded by the filter)
            return;
        }
        $events = $this->eventRepository->findAllForEvent($event, $calendarIds, $this->findRecurringUids($event), $event->getStoragePageIds())->toArray();

        $returnList = [];
        foreach ($events as $eventResult) {
            $returnList = array_merge($returnList, $this->findRecurringEventsForEvent($event->getStart(), $event->getEnd(), $eventResult));
        }
        $event->setEvents(array_merge_recursive($event->getEvents(), $returnList));
    }

    private function findRecurringEventsForEvent(\DateTime $start, \DateTime $end, Event $eventResult)
    {
        $returnList = [];
        $returnList[] = $eventResult;
        $indexList = $this->indexRepository->findByEventUidStartAndEnd($eventResult->getUid(), $start, $end);
        foreach ($indexList as $index) {
            $deviation = $index->getEventDeviationUid() > 0
                ? $this->eventDeviationRepository->findByUid((int)$index->getEventDeviationUid())
                : null;
            if ($deviation instanceof EventDeviation) {
                $returnList[] = new EventRecDeviationModel($eventResult, $deviation, (float)$index->getStartDatetime(), (float)$index->getEndDatetime());
            } else {
                $returnList[] = new EventRecModel($eventResult, (float)$index->getStartDatetime(), (float)$index->getEndDatetime());
            }
        }
        return $returnList;
    }

    private function findRecurringUids(SearchEventsEvent $event)
    {
        return $this->indexRepository->findEventUids($event->getStart(), $event->getEnd());
    }

    protected function getEvent(GetEventEvent $event): void
    {
        if ($event->getType() == 'tx_cal_event') {
            $calendarIds = $this->getAllowedCalendarIds($event->getCalendarIds(), $event->getExcludedCalendarIds());
            if ($calendarIds === []) {
                return;
            }
            $foundEvent = $this->eventRepository->findEvent($event->getUid(), $calendarIds, $event->getStoragePageIds())->toArray();
            if (!empty($foundEvent)) {
                //find the right for the date
                $end = clone $event->getDate();
                $end->add(\DateInterval::createFromDateString('1 day'));
                $resultList = $this->findRecurringEventsForEvent($event->getDate(), $end, $foundEvent[0]);
                $ymd = $event->getDate()->format('Ymd');
                foreach ($resultList as $result) {
                    if ($result->getStartDate()->format('Ymd') == $ymd) {
                        $event->setEvent($result);
                        $event->stopPropagation();
                        return;
                    }
                }

                $event->setEvent($foundEvent[0]);
                $event->stopPropagation();
            }
        }
    }

}
