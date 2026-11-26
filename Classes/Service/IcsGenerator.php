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
use MarioMatzulla\Cal\Domain\Model\Event;
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
        private readonly IcsBuilder $icsBuilder,
    ) {}

    /**
     * Events without iCalendar data
     */
    public function check()
    {
        return $this->eventRepository->check();
    }

    /**
     * Events without iCalendar data with the reason why none can be created (see getSkipReason())
     *
     * @return list<array{event: Event, reason: string}>
     */
    public function checkWithReasons(): array
    {
        $result = [];
        foreach ($this->eventRepository->check() ?: [] as $event) {
            $result[] = ['event' => $event, 'reason' => self::getSkipReason($event)];
        }
        return $result;
    }

    /**
     * Why no iCalendar data can be created for an event: "calendar" (none or not visible), "startDate",
     * "endDate", "page" (stored on page 0), empty if it can be created
     */
    public static function getSkipReason(Event $event): string
    {
        return match (true) {
            $event->getCalendarObject() === null => 'calendar',
            $event->getStartDate() === null => 'startDate',
            $event->getEndDate() === null => 'endDate',
            $event->getPid() <= 0 => 'page',
            default => '',
        };
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
        if ($calendar === null || self::getSkipReason($event) !== '') {
            return;
        }
        if ((string)$event->getIcsUid() === '') {
            $event->setIcsUid(md5($calendar->getTitle() . '_' . $calendar->getUid() . '_' . $event->getUid()));
            $this->eventRepository->updateWithIcsUid((int)$event->getUid(), (string)$event->getIcsUid());
        }
        // the description with resolved links and the URLs of the files are rendered by Fluid (typolink)
        $view = $this->viewFactory->create(
            new ViewFactoryData(
                templatePathAndFilename: 'EXT:cal/Resources/Private/Templates/Admin/IcsResources.html',
                request: $request,
            ),
        );
        $view->assign('event', $event);
        $resources = self::parseResources($view->render());

        $meta = $this->eventRepository->findIcsMetaData((int)$event->getUid());
        $vcalendar = $this->icsBuilder->build(
            $event,
            $this->getVersion(),
            $this->generateEventUrl($event),
            ['crdate' => $meta['crdate'], 'tstamp' => $meta['tstamp'], 'sequence' => $meta['sequence']],
            $resources,
            self::generateVTimeZone((string)$calendar->getTimezone()),
        );
        // alarms, attendees etc. of a CalDAV client are kept
        IcsBuilder::keepClientData($vcalendar, $meta['data']);
        // SEQUENCE (RFC 5545 3.8.7.4) is incremented when the data changes
        $sequence = $meta['sequence'];
        if ($meta['data'] !== '' && self::withoutRevision($meta['data']) !== self::withoutRevision($vcalendar->serialize())) {
            $sequence++;
            foreach ($vcalendar->select('VEVENT') as $vEvent) {
                $vEvent->remove('SEQUENCE');
                $vEvent->add('SEQUENCE', $sequence);
            }
        }
        $event->setCaldavData(rtrim($vcalendar->serialize()));
        $this->eventRepository->updateWithCaldavData((int)$event->getUid(), (string)$event->getCaldavData(), $sequence);
        $this->calendarRepository->touch((int)$calendar->getUid());
    }

    /**
     * Rendered template IcsResources.html => description (HTML) and URLs of the attachments and images
     *
     * @return array{description: string, attachments: list<string>, images: list<string>}
     */
    public static function parseResources(string $rendered): array
    {
        $parts = explode('<!--cal:', $rendered);
        $result = ['description' => '', 'attachments' => [], 'images' => []];
        foreach ($parts as $part) {
            [$name, $content] = array_pad(explode('-->', $part, 2), 2, '');
            if ($name === 'description') {
                $result['description'] = trim($content);
            } elseif ($name === 'attachments' || $name === 'images') {
                $urls = array_map(static fn(string $url): string => html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5), explode("\n", $content));
                $result[$name] = array_values(array_filter($urls, static fn(string $url): bool => $url !== ''));
            }
        }
        return $result;
    }

    /**
     * iCalendar data without the properties which change with every save (DTSTAMP, LAST-MODIFIED, SEQUENCE)
     */
    private static function withoutRevision(string $data): string
    {
        $lines = explode("\n", str_replace(["\r\n ", "\r\n\t", "\r\n"], ['', '', "\n"], $data));
        $lines = array_filter($lines, static fn(string $line): bool => !preg_match('/^(DTSTAMP|LAST-MODIFIED|SEQUENCE)[;:]/', $line));
        return trim(implode("\n", $lines));
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
