<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Unit\Service;

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
use MarioMatzulla\Cal\Service\ExternalCalendarImporter;
use MarioMatzulla\Cal\Service\PageCacheService;
use MarioMatzulla\Cal\Service\SlugService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(ExternalCalendarImporter::class)]
final class ExternalCalendarImporterTest extends UnitTestCase
{
    private const ICS = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//test//EN\r\nBEGIN:VEVENT\r\nUID:a\r\nDTSTAMP:20260101T000000Z\r\nSUMMARY:First\r\nDTSTART:20261110T090000Z\r\nEND:VEVENT\r\nBEGIN:VEVENT\r\nUID:b\r\nSUMMARY:Second\r\nDTSTART:20261111T090000Z\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

    /**
     * @var list<string>
     */
    private array $calls = [];

    /**
     * @var list<array<string, mixed>>
     */
    private array $inserted = [];

    /**
     * @var list<string> requested URLs
     */
    private array $requests = [];

    /**
     * @param array<string, mixed>|null $calendar
     * @param array<string, ResponseInterface|\Throwable> $responses by URL
     * @param list<FileReference> $files
     * @param list<int> $tempEvents uids of the imported events of the calendar
     */
    private function createImporter(?array $calendar, array $responses = [], array $files = [], array $tempEvents = []): ExternalCalendarImporter
    {
        $requestFactory = $this->createStub(RequestFactory::class);
        $requestFactory->method('request')->willReturnCallback(function (string $url) use ($responses): ResponseInterface {
            $this->requests[] = $url;
            $response = $responses[$url] ?? new \RuntimeException('connection refused');
            if ($response instanceof \Throwable) {
                throw $response;
            }
            return $response;
        });
        $extensionConfiguration = $this->createStub(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn(['recurrenceStart' => '2026-11-01 00:00:00', 'recurrenceEnd' => '2026-12-01 00:00:00']);

        $calendarRepository = $this->createStub(CalendarRepository::class);
        $calendarRepository->method('findRawByUid')->willReturn($calendar);
        $calendarRepository->method('updateMd5')->willReturnCallback(function (int $uid, string $md5): void {
            $this->calls[] = 'md5 ' . $uid . ' ' . ($md5 === '' ? 'empty' : 'set');
        });
        $eventRepository = $this->createStub(EventRepository::class);
        $eventRepository->method('removeTempEventsOfCalendar')->willReturnCallback(function (int $uid) use ($tempEvents): array {
            $this->calls[] = 'remove ' . $uid;
            return $tempEvents;
        });
        $eventRepository->method('insertRows')->willReturnCallback(function (array $rows): void {
            $this->inserted = $rows;
        });
        $indexRepository = $this->createStub(IndexRepository::class);
        $indexRepository->method('deleteByEventUidsAndTablename')->willReturnCallback(function (array $uids, string $table): void {
            $this->calls[] = 'index ' . implode(',', $uids) . ' ' . $table;
        });
        $fileRepository = $this->createStub(FileRepository::class);
        $fileRepository->method('findByRelation')->willReturn($files);

        $pageCacheService = $this->createStub(PageCacheService::class);
        $pageCacheService->method('flush')->willReturnCallback(function (): void {
            $this->calls[] = 'flush';
        });

        return new ExternalCalendarImporter($requestFactory, $extensionConfiguration, $calendarRepository, $eventRepository, $indexRepository, $fileRepository, new SlugService($this->createStub(ConnectionPool::class)), $pageCacheService);
    }

    private static function response(string $body, int $status = 200): ResponseInterface
    {
        $stream = new Stream('php://temp', 'rw');
        $stream->write($body);
        $stream->rewind();
        return new Response($stream, $status);
    }

    private function file(string $content, bool $readable = true): FileReference
    {
        $file = $this->createStub(File::class);
        if ($readable) {
            $file->method('getContents')->willReturn($content);
        } else {
            $file->method('getContents')->willThrowException(new \RuntimeException('missing'));
        }
        $reference = $this->createStub(FileReference::class);
        $reference->method('getOriginalFile')->willReturn($file);
        $reference->method('getName')->willReturn('calendar.ics');
        return $reference;
    }

    private static function calendar(array $values = []): array
    {
        return $values + ['uid' => 3, 'pid' => 5, 'type' => 1, 'ext_url' => 'https://example.org/a.ics', 'deleted' => 0, 'md5' => '', 'timezone' => 'Europe/Berlin'];
    }

    #[Test]
    public function isImportedType(): void
    {
        self::assertSame([false, true, true, false], array_map(ExternalCalendarImporter::isImportedType(...), [0, 1, 2, 3]));
    }

    #[Test]
    public function hasImportSource(): void
    {
        self::assertTrue($this->createImporter(null)->hasImportSource(self::calendar()));
        self::assertFalse($this->createImporter(null)->hasImportSource(self::calendar(['ext_url' => ' '])));
        self::assertFalse($this->createImporter(null)->hasImportSource(self::calendar(['deleted' => 1])));
        self::assertFalse($this->createImporter(null)->hasImportSource(self::calendar(['type' => 0])));
        self::assertTrue($this->createImporter(null, [], [$this->file(self::ICS)])->hasImportSource(self::calendar(['type' => 2])));
        self::assertFalse($this->createImporter(null)->hasImportSource(self::calendar(['type' => 2])));
    }

    #[Test]
    public function importOfAMissingCalendar(): void
    {
        self::assertSame(['status' => 'unchanged', 'count' => 0], $this->createImporter(null)->importCalendar(3));
    }

    #[Test]
    public function importOfACalendarWithoutSource(): void
    {
        self::assertSame(['status' => 'unchanged', 'count' => 0], $this->createImporter(self::calendar(['type' => 0]))->importCalendar(3));
        self::assertSame(['remove 3'], $this->calls);

        // deleted calendar: the imported events and the hash are removed
        $this->calls = [];
        self::assertSame(['status' => 'removed', 'count' => 2], $this->createImporter(self::calendar(['deleted' => 1, 'md5' => 'x']), [], [], [7, 8])->importCalendar(3));
        // the pages with calendar plugins show the change at once
        self::assertSame(['remove 3', 'index 7,8 tx_cal_event', 'md5 3 empty', 'flush', 'md5 3 empty'], $this->calls);
    }

    #[Test]
    public function importOfAnExternalCalendar(): void
    {
        $importer = $this->createImporter(self::calendar(['ext_url' => "webcal://example.org/a.ics\n\n https://example.org/b.ics "]), [
            'https://example.org/a.ics' => self::response(self::ICS),
            'https://example.org/b.ics' => self::response(str_replace(['UID:a', 'UID:b', 'First'], ['UID:c', 'UID:d', 'Third'], self::ICS)),
        ]);
        self::assertSame(['status' => 'imported', 'count' => 4], $importer->importCalendar(3));
        self::assertSame(['https://example.org/a.ics', 'https://example.org/b.ics'], $this->requests);
        self::assertSame(['remove 3', 'md5 3 set', 'flush'], $this->calls);
        self::assertSame(['a', 'b', 'c', 'd'], array_column($this->inserted, 'ics_uid'));
        $first = $this->inserted[0];
        self::assertSame(['First', 5, 3, 1, 0], [$first['title'], $first['pid'], $first['calendar_id'], $first['isTemp'], $first['type']]);
        // 09:00 UTC is 10:00 in Berlin
        self::assertSame(36000, $first['start_time']);
        self::assertSame($first['tstamp'], $first['crdate']);
    }

    #[Test]
    public function unchangedCalendarIsNotImportedAgain(): void
    {
        $importer = $this->createImporter(self::calendar(), ['https://example.org/a.ics' => self::response(self::ICS)]);
        $importer->importCalendar(3);
        $hashes = [];
        // the hash does not depend on DTSTAMP and the order of the events
        foreach ([self::ICS, str_replace('DTSTAMP:20260101T000000Z', 'DTSTAMP:20261124T120000Z', self::ICS)] as $ics) {
            $hashes[] = $this->hashOf($ics);
        }
        self::assertSame($hashes[0], $hashes[1]);

        $importer = $this->createImporter(self::calendar(['md5' => $hashes[0]]), ['https://example.org/a.ics' => self::response(self::ICS)]);
        self::assertSame(['status' => 'unchanged', 'count' => 0], $importer->importCalendar(3));
        self::assertSame(['status' => 'imported', 'count' => 2], $importer->importCalendar(3, true));
    }

    /**
     * The hash the importer stores for an ICS file (Europe/Berlin)
     */
    private function hashOf(string $ics): string
    {
        $hash = '';
        $calendarRepository = $this->createStub(CalendarRepository::class);
        $calendarRepository->method('findRawByUid')->willReturn(self::calendar());
        $calendarRepository->method('updateMd5')->willReturnCallback(function (int $uid, string $md5) use (&$hash): void {
            $hash = $md5;
        });
        $requestFactory = $this->createStub(RequestFactory::class);
        $requestFactory->method('request')->willReturn(self::response($ics));
        $eventRepository = $this->createStub(EventRepository::class);
        $eventRepository->method('removeTempEventsOfCalendar')->willReturn([]);
        (new ExternalCalendarImporter($requestFactory, $this->createStub(ExtensionConfiguration::class), $calendarRepository, $eventRepository, $this->createStub(IndexRepository::class), $this->createStub(FileRepository::class), new SlugService($this->createStub(ConnectionPool::class)), $this->createStub(PageCacheService::class)))->importCalendar(3);
        return $hash;
    }

    #[Test]
    public function importOfAnIcsFile(): void
    {
        $importer = $this->createImporter(self::calendar(['type' => 2, 'timezone' => 'Invalid/Zone']), [], [$this->file(self::ICS)]);
        self::assertSame(['status' => 'imported', 'count' => 2], $importer->importCalendar(3));
        // invalid time zone: UTC (default of the tests)
        self::assertSame(32400, $this->inserted[0]['start_time']);
    }

    /**
     * @return array<string, array{array<string, mixed>, array<string, mixed>, list<bool|string>, int}>
     */
    private function failingImports(): array
    {
        return [
            'invalid url' => [self::calendar(['ext_url' => 'ftp://example.org/a.ics']), [], [], 1794940002],
            'download fails' => [self::calendar(), [], [], 1794940003],
            'no calendar data' => [self::calendar(), ['https://example.org/a.ics' => self::response('<html></html>', 404)], [], 1794940004],
            'invalid calendar data' => [self::calendar(), ['https://example.org/a.ics' => self::response("BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nDTSTART:x\r\n")], [], 1794940001],
            'file not readable' => [self::calendar(['type' => 2]), [], [$this->file('', false)], 1795180001],
            'file without calendar data' => [self::calendar(['type' => 2]), [], [$this->file('text')], 1795180002],
        ];
    }

    #[Test]
    public function failingImportsThrowAnException(): void
    {
        foreach ($this->failingImports() as $name => [$calendar, $responses, $files, $code]) {
            try {
                $this->createImporter($calendar, $responses, $files)->importCalendar(3);
                self::fail($name . ' must throw');
            } catch (\RuntimeException $exception) {
                self::assertSame($code, $exception->getCode(), $name . ': ' . $exception->getMessage());
            }
        }
    }

    #[Test]
    public function removeImportedEvents(): void
    {
        self::assertSame(0, $this->createImporter(null)->removeImportedEvents(3));
        self::assertSame(['remove 3'], $this->calls);
    }
}
