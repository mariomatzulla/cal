<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Service;

/**
 * This file is part of the TYPO3 extension Calendar Base (cal).
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */
use MarioMatzulla\Cal\Domain\Model\Event;
use MarioMatzulla\Cal\Domain\Model\EventBusyModel;
use MarioMatzulla\Cal\Domain\Model\EventRecModel;
use MarioMatzulla\Cal\Domain\Repository\CalendarOwnerRelationRepository;
use MarioMatzulla\Cal\Domain\Repository\FrontendUserRepository;
use MarioMatzulla\Cal\Events\FilterEventsEvent;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Frontend\Cache\CacheInstruction;

/**
 * Private events (iCalendar CLASS PRIVATE / CONFIDENTIAL, e.g. set in a CalDAV client):
 * - visitors see them as "busy": only their time, without title, texts, location and links (EventBusyModel).
 *   The text is plugin.tx_cal.settings.view.privateEvents.busyTitle, default: the label "Busy".
 * - the owners of the calendar (logged in frontend user or one of its groups, or the user has the calendar
 *   for CalDAV) see the complete event,
 *   marked as private. These pages are not cached, so other visitors never get the private data.
 * Runs after the category filter, which needs the categories of the events.
 */
#[AsEventListener(identifier: 'cal/private-event-service', after: 'cal/category-service')]
class PrivateEventService
{
    public const LABEL = 'LLL:EXT:cal/Resources/Private/Language/locallang.xlf:l_busy';

    public function __construct(
        private readonly Context $context,
        private readonly CalendarOwnerRelationRepository $calendarOwnerRelationRepository,
        private readonly FrontendUserRepository $frontendUserRepository,
    ) {}

    public function __invoke(FilterEventsEvent $event): void
    {
        $title = null;
        $ownCalendars = null;
        $shownToOwner = false;
        $events = [];
        foreach ($event->getEvents() as $calendarEvent) {
            if (($calendarEvent instanceof Event || $calendarEvent instanceof EventRecModel) && $calendarEvent->isPrivate() && !$calendarEvent->isBusy()) {
                $parent = $calendarEvent instanceof EventRecModel ? $calendarEvent->getParentEvent() : $calendarEvent;
                $ownCalendars ??= $this->getOwnCalendarIds();
                if (in_array((int)$parent->getCalendarObject()?->getUid(), $ownCalendars, true)) {
                    $shownToOwner = true;
                } else {
                    $title ??= $this->getBusyTitle($event->getSettings());
                    $calendarEvent = new EventBusyModel(
                        $parent,
                        (float)$calendarEvent->getStart()->format('YmdHis'),
                        (float)$calendarEvent->getEnd()->format('YmdHis'),
                        $title,
                    );
                }
            }
            $events[] = $calendarEvent;
        }
        $event->setEvents($events);
        if ($shownToOwner) {
            $this->disablePageCache();
        }
    }

    /**
     * Calendars of the logged in frontend user: owned by the user or one of its groups (field "Owner" of
     * the calendar) and the calendars of the user for CalDAV (field "Calendars" of the user)
     *
     * @return list<int>
     */
    protected function getOwnCalendarIds(): array
    {
        $userId = (int)$this->context->getPropertyFromAspect('frontend.user', 'id', 0);
        if ($userId <= 0) {
            return [];
        }
        $groupIds = array_values(array_filter(
            array_map('intval', (array)$this->context->getPropertyFromAspect('frontend.user', 'groupIds', [])),
            static fn(int $uid): bool => $uid > 0,
        ));
        $calendars = array_map('intval', $this->calendarOwnerRelationRepository->findCalendarListForFeUser($userId, $groupIds));
        // the calendars of the user for CalDAV (field "Calendars" of the frontend user)
        foreach (explode(',', (string)($this->frontendUserRepository->findByUid($userId)['tx_cal_calendar'] ?? '')) as $value) {
            // "3" or "tx_cal_domain_model_calendar_3"
            if (preg_match('/(\d+)$/', trim($value), $match)) {
                $calendars[] = (int)$match[1];
            }
        }
        return array_values(array_unique($calendars));
    }

    /**
     * @param array<string, mixed> $settings of the plugin
     */
    protected function getBusyTitle(array $settings): string
    {
        $title = trim((string)($settings['view']['privateEvents']['busyTitle'] ?? ''));
        return $title !== '' ? $title : (string)(LocalizationUtility::translate(self::LABEL) ?? 'Busy');
    }

    /**
     * The page contains private data of the logged in user
     */
    protected function disablePageCache(): void
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        $cacheInstruction = $request instanceof ServerRequestInterface ? $request->getAttribute('frontend.cache.instruction') : null;
        if ($cacheInstruction instanceof CacheInstruction) {
            $cacheInstruction->disableCache('EXT:cal: private events of the logged in user');
        }
    }
}
