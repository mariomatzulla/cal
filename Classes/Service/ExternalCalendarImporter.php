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
use MarioMatzulla\Cal\Domain\Repository\CalendarRepository;
use MarioMatzulla\Cal\Domain\Repository\EventRepository;
use MarioMatzulla\Cal\Domain\Repository\IndexRepository;
use MarioMatzulla\Cal\Utility\CalculateDateTime;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\FileRepository;

/**
 * Imports the events of
 * - an external calendar (calendar type 1, field ext_url: one or more URLs) or
 * - an ICS file calendar (calendar type 2, field ics_file: one or more uploaded .ics files)
 * as read-only "temp" events (tx_cal_domain_model_event.isTemp = 1).
 *
 * The ICS data is hashed (calendar field md5). As long as the hash does not change, the import is skipped.
 */
#[Autoconfigure(public: true)]
final class ExternalCalendarImporter
{
    public const CALENDAR_TYPE_EXTERNAL = 1;
    public const CALENDAR_TYPE_FILE = 2;
    public const CALENDAR_TABLE = 'tx_cal_domain_model_calendar';
    public const EVENT_TABLE = 'tx_cal_domain_model_event';

    public const STATUS_IMPORTED = 'imported';
    public const STATUS_UNCHANGED = 'unchanged';
    public const STATUS_REMOVED = 'removed';

    /**
     * Part of the hash: increase it when the conversion of the events changes,
     * so all external calendars are imported again with the next run.
     */
    private const IMPORT_VERSION = 2;

    public function __construct(
        private readonly RequestFactory $requestFactory,
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly CalendarRepository $calendarRepository,
        private readonly EventRepository $eventRepository,
        private readonly IndexRepository $indexRepository,
        private readonly FileRepository $fileRepository,
        private readonly SlugService $slugService,
    ) {}

    /**
     * Calendars of these types get their events from an import (read-only in the backend and via CalDAV)
     */
    public static function isImportedType(int $type): bool
    {
        return $type === self::CALENDAR_TYPE_EXTERNAL || $type === self::CALENDAR_TYPE_FILE;
    }

    /**
     * Whether the calendar has something to import (URLs or files)
     */
    public function hasImportSource(array $calendar): bool
    {
        if (!empty($calendar['deleted'])) {
            return false;
        }
        return match ((int)($calendar['type'] ?? 0)) {
            self::CALENDAR_TYPE_EXTERNAL => trim((string)($calendar['ext_url'] ?? '')) !== '',
            self::CALENDAR_TYPE_FILE => $this->getFileReferences((int)$calendar['uid']) !== [],
            default => false,
        };
    }

    /**
     * @return array{status: string, count: int}
     * @throws \RuntimeException if the calendar could not be downloaded or parsed
     */
    public function importCalendar(int $calendarId, bool $force = false): array
    {
        $calendar = $this->calendarRepository->findRawByUid($calendarId);
        if ($calendar === null) {
            return ['status' => self::STATUS_UNCHANGED, 'count' => 0];
        }

        $contents = empty($calendar['deleted']) ? $this->loadContents($calendar) : [];
        if ($contents === []) {
            $removed = $this->removeImportedEvents($calendarId);
            if ((string)($calendar['md5'] ?? '') !== '') {
                $this->calendarRepository->updateMd5($calendarId, '');
            }
            return ['status' => $removed > 0 ? self::STATUS_REMOVED : self::STATUS_UNCHANGED, 'count' => $removed];
        }

        $timezone = $this->getCalendarTimezone($calendar);
        $md5 = $this->calculateHash($contents, $timezone->getName());
        if (!$force && $md5 === (string)($calendar['md5'] ?? '')) {
            return ['status' => self::STATUS_UNCHANGED, 'count' => 0];
        }

        [$from, $to] = $this->getImportRange();
        $rows = [];
        $now = time();
        foreach ($contents as $content) {
            try {
                $events = IcsParser::parseEvents($content, $from, $to, $timezone->getName());
            } catch (\Throwable $e) {
                throw new \RuntimeException('The calendar data could not be parsed: ' . $e->getMessage(), 1794940001, $e);
            }
            foreach ($events as $event) {
                $rows[] = array_merge($event, [
                    'pid' => (int)$calendar['pid'],
                    'calendar_id' => $calendarId,
                    'isTemp' => 1,
                    'type' => 0,
                    'tstamp' => $now,
                    'crdate' => $now,
                ]);
            }
        }

        $this->removeImportedEvents($calendarId);
        $this->eventRepository->insertRows($rows);
        $this->slugService->fillMissing(self::EVENT_TABLE, $calendarId);
        $this->calendarRepository->updateMd5($calendarId, $md5);

        return ['status' => self::STATUS_IMPORTED, 'count' => count($rows)];
    }

    /**
     * Removes all imported events of a calendar and resets the stored hash.
     */
    public function removeImportedEvents(int $calendarId): int
    {
        $uids = $this->eventRepository->removeTempEventsOfCalendar($calendarId);
        if ($uids !== []) {
            $this->indexRepository->deleteByEventUidsAndTablename($uids, 'tx_cal_event');
            $this->calendarRepository->updateMd5($calendarId, '');
        }
        return count($uids);
    }

    /**
     * ICS data of the calendar: downloaded from the URLs (type 1) or read from the files (type 2)
     *
     * @return string[]
     */
    private function loadContents(array $calendar): array
    {
        $contents = [];
        switch ((int)($calendar['type'] ?? 0)) {
            case self::CALENDAR_TYPE_EXTERNAL:
                foreach ($this->getUrls((string)($calendar['ext_url'] ?? '')) as $url) {
                    $contents[] = $this->download($url);
                }
                break;
            case self::CALENDAR_TYPE_FILE:
                foreach ($this->getFileReferences((int)$calendar['uid']) as $fileReference) {
                    $contents[] = $this->readFile($fileReference);
                }
                break;
        }
        return $contents;
    }

    /**
     * @return FileReference[]
     */
    private function getFileReferences(int $calendarId): array
    {
        return $this->fileRepository->findByRelation(self::CALENDAR_TABLE, 'ics_file', $calendarId);
    }

    private function readFile(FileReference $fileReference): string
    {
        try {
            $content = $fileReference->getOriginalFile()->getContents();
        } catch (\Throwable $e) {
            throw new \RuntimeException('The ICS file "' . $fileReference->getName() . '" could not be read: ' . $e->getMessage(), 1795180001, $e);
        }
        if (!str_contains($content, 'BEGIN:VCALENDAR')) {
            throw new \RuntimeException('The file "' . $fileReference->getName() . '" does not contain iCalendar data.', 1795180002);
        }
        return $content;
    }

    /**
     * ext_url may contain several URLs, one per line.
     *
     * @return string[]
     */
    private function getUrls(string $extUrl): array
    {
        $urls = [];
        foreach (preg_split('/\R/', $extUrl) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            // webcal:// is the same as https://
            $line = preg_replace('#^webcals?://#i', 'https://', $line) ?? $line;
            if (filter_var($line, FILTER_VALIDATE_URL) === false || !preg_match('#^https?://#i', $line)) {
                throw new \RuntimeException('Invalid URL for external calendar: ' . $line, 1794940002);
            }
            $urls[] = $line;
        }
        return $urls;
    }

    private function download(string $url): string
    {
        try {
            $response = $this->requestFactory->request($url, 'GET', [
                'timeout' => 30,
                'headers' => ['Accept' => 'text/calendar, */*;q=0.5'],
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('The external calendar could not be downloaded: ' . $e->getMessage(), 1794940003, $e);
        }
        $content = (string)$response->getBody();
        if (!str_contains($content, 'BEGIN:VCALENDAR')) {
            throw new \RuntimeException('The URL did not return iCalendar data (HTTP ' . $response->getStatusCode() . ').', 1794940004);
        }
        return $content;
    }

    /**
     * Hash over the ICS data. Only the content counts, not the formatting:
     * - DTSTAMP (time of the export) changes with every download and is ignored
     * - some servers (e.g. Google) return the VEVENTs in a different order on every download,
     *   therefore the components are sorted before hashing
     *
     * The timezone of the calendar is part of the hash: changing it converts the times
     * differently, therefore the events have to be imported again.
     *
     * @param string[] $contents
     */
    private function calculateHash(array $contents, string $timezone): string
    {
        $components = [];
        foreach ($contents as $content) {
            // Unfold folded lines first (RFC 5545, 3.1)
            $content = (string)preg_replace('/\r?\n[ \t]/', '', $content);
            $lines = preg_split('/\r?\n/', $content) ?: [];
            $lines = array_filter($lines, static fn(string $line): bool => $line !== '' && !str_starts_with($line, 'DTSTAMP'));
            $content = implode("\n", $lines);
            preg_match_all('/^BEGIN:(VEVENT|VTODO|VJOURNAL|VTIMEZONE)$.*?^END:\1$/ms', $content, $matches);
            foreach ($matches[0] as $component) {
                $components[] = $component;
            }
        }
        sort($components);
        return md5('V:' . self::IMPORT_VERSION . "\nTZ:" . $timezone . "\n" . implode("\n", $components));
    }

    /**
     * Times of the imported events are converted into the timezone stored at the calendar
     * (field "timezone"). Falls back to the server timezone if none or an invalid one is set.
     */
    private function getCalendarTimezone(array $calendar): \DateTimeZone
    {
        $timezone = trim((string)($calendar['timezone'] ?? ''));
        if ($timezone !== '') {
            try {
                return new \DateTimeZone($timezone);
            } catch (\Exception) {
                // invalid identifier, use the server timezone
            }
        }
        return new \DateTimeZone(date_default_timezone_get());
    }

    /**
     * Recurring events are expanded between the configured recurrence start and end
     * (extension configuration recurrenceStart / recurrenceEnd).
     *
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    private function getImportRange(): array
    {
        $configuration = (array)$this->extensionConfiguration->get('cal');
        $from = CalculateDateTime::parseTcaString((string)($configuration['recurrenceStart'] ?? 'cal:yearstart'));
        $to = CalculateDateTime::parseTcaString((string)($configuration['recurrenceEnd'] ?? '+3 years'));
        return [\DateTimeImmutable::createFromInterface($from), \DateTimeImmutable::createFromInterface($to)];
    }
}
