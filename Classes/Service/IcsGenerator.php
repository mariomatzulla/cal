<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Service;

/**
 * *************************************************************
 * Copyright notice
 *
 * (c) 2010-2015 Mario Matzulla (mario(at)matzullas.de)
 * All rights reserved
 *
 * This script is part of the TYPO3 project. The TYPO3 project is
 * free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 *
 * This script is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * This copyright notice MUST APPEAR in all copies of the script!
 * *************************************************************
 */
use MarioMatzulla\Cal\Domain\Repository\CalendarRepository;
use MarioMatzulla\Cal\Domain\Repository\EventRepository;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Routing\RouterInterface;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

/**
 * Creates the iCalendar data (caldav_data) of events
 */
class IcsGenerator
{
    public function __construct(
        private readonly ViewFactoryInterface $viewFactory,
        private readonly EventRepository $eventRepository,
        private readonly CalendarRepository $calendarRepository,
        private readonly SiteFinder $siteFinder,
        private readonly PackageManager $packageManager,
    ) {}

    /**
     * Events without iCalendar data
     */
    public function check()
    {
        return $this->eventRepository->check();
    }

    /**
     * Creates the iCalendar data of all events without
     */
    public function generate(ServerRequestInterface $request): void
    {
        foreach ($this->eventRepository->check() ?: [] as $event) {
            $this->updateIcs($event, $request);
        }
    }

    public function updateIcs($event, $request): void
    {
        $calendar = $event->getCalendarObject();
        if ($calendar === null || $event->getStartDate() === null || $event->getEndDate() === null || $event->getPid() <= 0) {
            return;
        }
        if ((string)$event->getIcsUid() === '') {
            $event->setIcsUid(md5($calendar->getTitle() . '_' . $calendar->getUid() . '_' . $event->getUid()));
            $this->eventRepository->updateWithIcsUid((int)$event->getUid(), (string)$event->getIcsUid());
        }
        $view = $this->viewFactory->create(
            new ViewFactoryData(
                templatePathAndFilename: 'EXT:cal/Resources/Private/Templates/Admin/Ics.html',
                request: $request,
            ),
        );
        $view->assignMultiple([
            'event' => $event,
            'calVersion' => $this->getVersion(),
            'eventUrl' => $this->generateEventUrl($event),
            'vTimeZone' => self::generateVTimeZone((string)$calendar->getTimezone()),
        ]);
        // without empty lines (of the conditions of the template), lines end with CRLF (RFC 5545)
        $lines = explode("\n", str_replace("\r\n", "\n", rtrim($view->render())));
        $event->setCaldavData(implode("\r\n", array_filter($lines, static fn(string $line): bool => trim($line) !== '')));
        $this->eventRepository->updateWithCaldavData((int)$event->getUid(), (string)$event->getCaldavData());
        $this->calendarRepository->touch((int)$calendar->getUid());
    }

    private function getVersion(): string
    {
        $version = $this->packageManager->isPackageActive('cal')
            ? (string)$this->packageManager->getPackage('cal')->getPackageMetaData()->getVersion()
            : '';
        return $version !== '' ? $version : '4.0.0';
    }

    /**
     * VTIMEZONE component of a time zone, built from the transitions of the current year:
     * a STANDARD and a DAYLIGHT component with a yearly rule for zones with daylight saving time
     */
    public static function generateVTimeZone(string $timezoneId, ?int $year = null): string
    {
        if ($timezoneId === '' || !in_array($timezoneId, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true)) {
            return '';
        }
        $timezone = new \DateTimeZone($timezoneId);
        $year ??= (int)gmdate('Y');
        $transitions = $timezone->getTransitions(gmmktime(0, 0, 0, 1, 1, $year), gmmktime(23, 59, 59, 12, 31, $year));
        $lines = ['BEGIN:VTIMEZONE', 'TZID:' . $timezoneId];
        // the first entry is the state at the start of the year, the others are the changes in the year
        $changes = array_slice($transitions, 1);
        if ($changes === []) {
            $offset = self::formatOffset((int)$transitions[0]['offset']);
            array_push($lines, 'BEGIN:STANDARD', 'TZOFFSETFROM:' . $offset, 'TZOFFSETTO:' . $offset, 'TZNAME:' . $transitions[0]['abbr'], 'DTSTART:19700101T000000', 'END:STANDARD');
        }
        $previousOffset = (int)$transitions[0]['offset'];
        foreach ($changes as $change) {
            // local time before the change
            $local = (new \DateTimeImmutable('@' . $change['ts']))->modify(($previousOffset >= 0 ? '+' : '-') . abs($previousOffset) . ' seconds');
            $day = (int)$local->format('j');
            $ordinal = $day + 7 > (int)$local->format('t') ? -1 : intdiv($day - 1, 7) + 1;
            $type = $change['isdst'] ? 'DAYLIGHT' : 'STANDARD';
            array_push(
                $lines,
                'BEGIN:' . $type,
                'TZOFFSETFROM:' . self::formatOffset($previousOffset),
                'TZOFFSETTO:' . self::formatOffset((int)$change['offset']),
                'TZNAME:' . $change['abbr'],
                'DTSTART:' . $local->format('Ymd\THis'),
                'RRULE:FREQ=YEARLY;BYMONTH=' . (int)$local->format('n') . ';BYDAY=' . $ordinal . strtoupper(substr($local->format('D'), 0, 2)),
                'END:' . $type,
            );
            $previousOffset = (int)$change['offset'];
        }
        $lines[] = 'END:VTIMEZONE';
        return implode("\r\n", $lines) . "\r\n";
    }

    private static function formatOffset(int $seconds): string
    {
        return sprintf('%s%02d%02d', $seconds < 0 ? '-' : '+', intdiv(abs($seconds), 3600), intdiv(abs($seconds) % 3600, 60));
    }

    /**
     * URL of the event view on the root page of the site of the event.
     * Records in a folder outside of a site use the first site of the installation.
     */
    public function generateEventUrl($calEvent): string
    {
        try {
            try {
                $site = $this->siteFinder->getSiteByPageId((int)$calEvent->getPid());
            } catch (SiteNotFoundException) {
                $sites = $this->siteFinder->getAllSites();
                $site = reset($sites);
                if ($site === false) {
                    return '';
                }
            }
            return (string)$site->getRouter()->generateUri(
                $site->getRootPageId(),
                [
                    'tx_cal_controller' => [
                        'action' => 'event',
                        'controller' => 'Content',
                        'day' => $calEvent->getStart()->getDay(),
                        'month' => $calEvent->getStart()->getMonth(),
                        'year' => $calEvent->getStart()->getYear(),
                        'type' => $calEvent->getModelType(),
                        'uid' => $calEvent->getUid(),
                    ],
                ],
                '',
                RouterInterface::ABSOLUTE_URL,
            );
        } catch (\Throwable) {
            return '';
        }
    }
}
