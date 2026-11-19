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

/**
 * Imports the events of an external calendar (calendar type 1, field ext_url) as
 * read-only "temp" events (tx_cal_domain_model_event.isTemp = 1).
 *
 * The downloaded ICS data is hashed (calendar field md5). As long as the hash does
 * not change, the import is skipped.
 */
#[Autoconfigure(public: true)]
final class ExternalCalendarImporter
{
    public const CALENDAR_TYPE_EXTERNAL = 1;

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
    ) {}

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

        $isExternal = (int)$calendar['type'] === self::CALENDAR_TYPE_EXTERNAL && empty($calendar['deleted']);
        $urls = $isExternal ? $this->getUrls((string)($calendar['ext_url'] ?? '')) : [];
        if ($urls === []) {
            $removed = $this->removeImportedEvents($calendarId);
            if ((string)($calendar['md5'] ?? '') !== '') {
                $this->calendarRepository->updateMd5($calendarId, '');
            }
            return ['status' => $removed > 0 ? self::STATUS_REMOVED : self::STATUS_UNCHANGED, 'count' => $removed];
        }

        $contents = [];
        foreach ($urls as $url) {
            $contents[] = $this->download($url);
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
                throw new \RuntimeException('The external calendar could not be parsed: ' . $e->getMessage(), 1794940001, $e);
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
     * Hash over the downloaded data. Only the content counts, not the formatting:
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
