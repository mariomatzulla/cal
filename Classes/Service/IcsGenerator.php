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
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Fluid\View\FluidViewFactory;

class IcsGenerator
{
    public function __construct(
        private readonly FluidViewFactory $fluidViewFactory,
        private readonly EventRepository $eventRepository,
        private readonly CalendarRepository $calendarRepository,
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly ConfigurationManagerInterface $configurationManager,
    ) {}

    public function check()
    {
        return $this->eventRepository->check();
    }

    public function countEventsWithoutIcs($eventPage = 0)
    {
        return $this->eventRepository->countEventsWithoutIcs();
    }

    public function generate(ServerRequestInterface $request)
    {
        $rows = $this->eventRepository->check();
        if ($rows) {
            foreach ($rows as $event) {
                $this->updateIcs($event, $request);
            }
        }
    }

    public function updateIcs($event, $request)
    {
        // Events without start or end date (incomplete records) cannot be exported
        if ($event->getStartDate() === null || $event->getEndDate() === null) {
            return;
        }
        if ($event->getPid() > 0) {
            if ($event->getIcsUid() == null || $event->getIcsUid() == '') {
                $event->setIcsUid(md5($event->getCalendarObject()->getTitle() . '_' . $event->getCalendarObject()->getUid() . '_' . $event->getUid()));
                $this->eventRepository->updateWithIcsUid((int)$event->getUid(), (string)$event->getIcsUid());
            }
            // 3. Dem Template Variablen übergeben
            $view = $this->fluidViewFactory->create(
                new ViewFactoryData(
                    templatePathAndFilename: 'EXT:cal/Resources/Private/Templates/Admin/Ics.html',
                    request: $request,
                ),
            );

            $url = $this->generateEventUrl(1, $event);

            $extensionVersion = ExtensionManagementUtility::getExtensionVersion('cal');

            // Falls die Version z. B. '1.0.0' ist, kannst du sie direkt nutzen:
            // Fallback '1.0.0', falls sie leer sein sollte
            $versionString = $extensionVersion !== '' ? $extensionVersion : '2.0.0';

            $timezoneId = $event->getCalendarObject()->getTimezone();

            // Generiere oder lade den VTIMEZONE-Inhalt für die TZID
            $vTimeZoneString = $this->generateVTimeZoneString((string)$timezoneId);

            $view->assignMultiple([
                'event' => $event,
                'calVersion' => $versionString,
                'eventUrl' => $url,
                'vTimeZone' => $vTimeZoneString,
            ]);

            // 4. View rendern – liefert den fertigen HTML-String zurück
            $drawnIcs = rtrim($view->render());
            $lines = explode("\n", str_replace("\r\n", "\n", $drawnIcs));
            $filteredLines = array_filter($lines, function ($line) {
                return trim($line) !== '';
            });
            $cleanedIcs = implode("\r\n", $filteredLines);
            $event->setCaldavData($cleanedIcs);
            $this->eventRepository->updateWithCaldavData((int)$event->getUid(), (string)$event->getCaldavData());

            $this->calendarRepository->touch((int)$event->getCalendarObject()->getUid());
        }
    }

    /**
     * Hilfsmethode zur Erzeugung eines VTIMEZONE-Blocks über PHP DateTimeZone Transitions
     */
    private function generateVTimeZoneString(string $timezoneId): string
    {
        if (empty($timezoneId) || !in_array($timezoneId, \DateTimeZone::listIdentifiers(), true)) {
            return '';
        }

        $vTimeZone = "BEGIN:VTIMEZONE\r\n";
        $vTimeZone .= 'TZID:' . $timezoneId . "\r\n";

        // 1. US-Regel (Zweiter Sonntag im März bis Erster Sonntag im November)
        $usZones = [
            'America/New_York', 'America/Chicago', 'America/Denver',
            'America/Los_Angeles', 'America/Anchorage', 'America/St_Johns',
            'America/Santiago',
        ];

        // 2. EU / Europäische Regel (Letzter Sonntag im März bis Letzter Sonntag im Oktober)
        $euZones = [
            'Europe/Berlin', 'Europe/London', 'Europe/Athens',
            'Atlantic/Azores', 'Atlantic/South_Georgia',
        ];

        // 3. Australien / Südhalbkugel (Erster Sonntag im Oktober bis Erster Sonntag im April)
        $ausZones = [
            'Australia/Sydney', 'Australia/Adelaide', 'Australia/Lord_Howe', 'Pacific/Auckland',
        ];

        if (in_array($timezoneId, $usZones, true)) {
            // US Daylight & Standard mit RRULE
            $vTimeZone .= "BEGIN:DAYLIGHT\r\n";
            $vTimeZone .= "TZOFFSETFROM:-0500\r\n";
            $vTimeZone .= "TZOFFSETTO:-0400\r\n";
            $vTimeZone .= "TZNAME:EDT\r\n";
            $vTimeZone .= "DTSTART:20070311T020000\r\n";
            $vTimeZone .= "RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=2SU\r\n";
            $vTimeZone .= "END:DAYLIGHT\r\n";
            $vTimeZone .= "BEGIN:STANDARD\r\n";
            $vTimeZone .= "TZOFFSETFROM:-0400\r\n";
            $vTimeZone .= "TZOFFSETTO:-0500\r\n";
            $vTimeZone .= "TZNAME:EST\r\n";
            $vTimeZone .= "DTSTART:20071104T020000\r\n";
            $vTimeZone .= "RRULE:FREQ=YEARLY;BYMONTH=11;BYDAY=1SU\r\n";
            $vTimeZone .= "END:STANDARD\r\n";

        } elseif (in_array($timezoneId, $euZones, true)) {
            // EU Daylight & Standard mit RRULE
            $vTimeZone .= "BEGIN:DAYLIGHT\r\n";
            $vTimeZone .= "TZOFFSETFROM:+0100\r\n";
            $vTimeZone .= "TZOFFSETTO:+0200\r\n";
            $vTimeZone .= "TZNAME:CEST\r\n";
            $vTimeZone .= "DTSTART:19810329T020000\r\n";
            $vTimeZone .= "RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU\r\n";
            $vTimeZone .= "END:DAYLIGHT\r\n";
            $vTimeZone .= "BEGIN:STANDARD\r\n";
            $vTimeZone .= "TZOFFSETFROM:+0200\r\n";
            $vTimeZone .= "TZOFFSETTO:+0100\r\n";
            $vTimeZone .= "TZNAME:CET\r\n";
            $vTimeZone .= "DTSTART:19961025T030000\r\n";
            $vTimeZone .= "RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU\r\n";
            $vTimeZone .= "END:STANDARD\r\n";

        } elseif (in_array($timezoneId, $ausZones, true)) {
            // Australien / Südhalbkugel (Sommerzeit läuft umgekehrt: Okt - Apr)
            $vTimeZone .= "BEGIN:DAYLIGHT\r\n";
            $vTimeZone .= "TZOFFSETFROM:+1000\r\n";
            $vTimeZone .= "TZOFFSETTO:+1100\r\n";
            $vTimeZone .= "TZNAME:AEDT\r\n";
            $vTimeZone .= "DTSTART:20081005T020000\r\n";
            $vTimeZone .= "RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=1SU\r\n";
            $vTimeZone .= "END:DAYLIGHT\r\n";
            $vTimeZone .= "BEGIN:STANDARD\r\n";
            $vTimeZone .= "TZOFFSETFROM:+1100\r\n";
            $vTimeZone .= "TZOFFSETTO:+1000\r\n";
            $vTimeZone .= "TZNAME:AEST\r\n";
            $vTimeZone .= "DTSTART:20080406T030000\r\n";
            $vTimeZone .= "RRULE:FREQ=YEARLY;BYMONTH=4;BYDAY=1SU\r\n";
            $vTimeZone .= "END:STANDARD\r\n";

        } else {
            // Fallback / Keine Sommerzeit (z.B. Tokio, Dubai, UTC, etc.)
            $tz = new \DateTimeZone($timezoneId);
            $offsetSeconds = $tz->getOffset(new \DateTime('now', new \DateTimeZone('UTC')));
            $offsetFormatted = $this->formatOffset($offsetSeconds);

            $vTimeZone .= "BEGIN:STANDARD\r\n";
            $vTimeZone .= 'TZOFFSETFROM:' . $offsetFormatted . "\r\n";
            $vTimeZone .= 'TZOFFSETTO:' . $offsetFormatted . "\r\n";
            $vTimeZone .= "TZNAME:UTC\r\n";
            $vTimeZone .= "DTSTART:19700101T000000\r\n";
            $vTimeZone .= "END:STANDARD\r\n";
        }

        $vTimeZone .= "END:VTIMEZONE\r\n";

        return $vTimeZone;
    }

    /**
     * Hilfsmethode: Formatiert Sekunden-Offsets in das iCal-Format (z.B. +0200 oder -0500)
     */
    private function formatOffset(int $offsetInSeconds): string
    {
        $sign = $offsetInSeconds < 0 ? '-' : '+';
        $absSeconds = abs($offsetInSeconds);
        $hours = floor($absSeconds / 3600);
        $minutes = floor(($absSeconds / 60) % 60);

        return sprintf('%s%02d%02d', $sign, $hours, $minutes);
    }

    /**
     * Erzeugt eine saubere Frontend-URL für einen Kalendereintrag mittels PageRouter
     */
    public function generateEventUrl(int $pageUid, $calEvent): string
    {
        try {
            // 1. Site über die Seiten-ID (pid) des Eintrags ermitteln
            $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
            $site = $siteFinder->getSiteByPageId($pageUid);

            // 2. Arguments of the plugin, the same as the links of the templates (see Configuration/Routes/Cal.yaml)
            $arguments = [
                'tx_cal_controller' => [
                    'action' => 'event',
                    'controller' => 'Content',
                    'day' => $calEvent->getStart()->getDay(),
                    'month' => $calEvent->getStart()->getMonth(),
                    'year' => $calEvent->getStart()->getYear(),
                    'type' => $calEvent->getModelType(),
                    'uid' => $calEvent->getUid(),
                ],
            ];

            // 3. URI über den PageRouter der Site generieren lassen
            // Als drittes Argument kannst du z.B. TYPO3\CMS\Core\Routing\PageRouter::ABSOLUTE_URL übergeben,
            // um direkt eine absolute URL (inkl. Domain) zu erhalten.
            $uri = $site->getRouter()->generateUri(
                $pageUid,
                $arguments,
                '', // Zusätzlicher Fragment-Anker (z.B. '#top'), falls gewünscht
                \TYPO3\CMS\Core\Routing\RouterInterface::ABSOLUTE_URL,
            );

            return (string)$uri;

        } catch (\Throwable $e) {
            // Fallback, falls z. B. keine Site zur PID gefunden wird
            return '';
        }
    }
}
