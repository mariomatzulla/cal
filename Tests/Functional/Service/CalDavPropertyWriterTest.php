<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Functional\Service;

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
use MarioMatzulla\Cal\Service\CalDavPropertyWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(CalDavPropertyWriter::class)]
final class CalDavPropertyWriterTest extends FunctionalTestCase
{
    private const EVENT_TABLE = 'tx_cal_domain_model_event';

    protected array $testExtensionsToLoad = ['mariomatzulla/cal'];

    protected function setUp(): void
    {
        parent::setUp();
        $pool = $this->get(ConnectionPool::class);
        $pool->getConnectionForTable(self::EVENT_TABLE)->insert(self::EVENT_TABLE, [
            'uid' => 1, 'pid' => 5, 'calendar_id' => 1, 'title' => 'Meeting', 'organizer' => 'Somebody', 'ext_url' => 'https://example.org/old',
        ]);
        $categories = $pool->getConnectionForTable('sys_category');
        // [uid, pid, title, language, deleted]: only the default language of the storage folder 5 counts
        foreach ([[1, 5, 'Sport', 0, 0], [2, 5, 'Music', 0, 0], [3, 5, 'Musik', 1, 0], [4, 5, 'sport', 0, 0], [5, 9, 'Theatre', 0, 0], [6, 5, 'Theatre', 0, 1]] as [$uid, $pid, $title, $language, $deleted]) {
            $categories->insert('sys_category', ['uid' => $uid, 'pid' => $pid, 'title' => $title, 'sys_language_uid' => $language, 'deleted' => $deleted]);
        }
        $pool->getConnectionForTable('sys_category_record_mm')->insert('sys_category_record_mm', [
            'uid_local' => 2, 'uid_foreign' => 1, 'tablenames' => self::EVENT_TABLE, 'fieldname' => 'categories', 'sorting_foreign' => 1,
        ]);
        $organizers = $pool->getConnectionForTable('tx_cal_domain_model_organizer');
        $organizers->insert('tx_cal_domain_model_organizer', ['uid' => 3, 'pid' => 5, 'name' => 'Club', 'email' => 'Club@Example.org']);
        // another storage folder
        $organizers->insert('tx_cal_domain_model_organizer', ['uid' => 4, 'pid' => 9, 'name' => 'Anna', 'email' => 'anna@example.org']);
    }

    private static function ics(string $properties, string $override = ''): string
    {
        return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//test//EN\r\nBEGIN:VEVENT\r\nUID:a\r\nDTSTART:20261201T100000Z\r\nSUMMARY:Meeting\r\n" . $properties . "END:VEVENT\r\n"
            . ($override !== '' ? "BEGIN:VEVENT\r\nUID:a\r\nRECURRENCE-ID:20261208T100000Z\r\nDTSTART:20261208T120000Z\r\n" . $override . "END:VEVENT\r\n" : '')
            . "END:VCALENDAR\r\n";
    }

    private function event(): array
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable(self::EVENT_TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        return $queryBuilder->select('ext_url', 'organizer', 'organizer_id', 'categories')->from(self::EVENT_TABLE)
            ->where($queryBuilder->expr()->eq('uid', 1))->executeQuery()->fetchAssociative() ?: [];
    }

    /**
     * @return list<int>
     */
    private function categories(): array
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('sys_category_record_mm');
        return array_map('intval', $queryBuilder->select('uid_local')->from('sys_category_record_mm')
            ->where(
                $queryBuilder->expr()->eq('uid_foreign', 1),
                $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter(self::EVENT_TABLE)),
                $queryBuilder->expr()->eq('fieldname', $queryBuilder->createNamedParameter('categories')),
            )->orderBy('sorting_foreign')->executeQuery()->fetchFirstColumn());
    }

    /**
     * @return array<string, mixed>|false
     */
    private function record(string $table, int $uid): array|false
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        return $queryBuilder->select('*')->from($table)->where($queryBuilder->expr()->eq('uid', $uid))->executeQuery()->fetchAssociative();
    }

    #[Test]
    public function valuesChangedInTheClientAreTakenOver(): void
    {
        $previous = self::ics("URL:https://example.org/cal/meeting\r\nCATEGORIES:Music\r\nORGANIZER;CN=Club:mailto:club@example.org\r\n");
        $writer = $this->get(CalDavPropertyWriter::class);

        // nothing changed in the client (only the case of the categories): the fields are kept
        $writer->write(1, 5, self::ics("CATEGORIES:music\r\nURL:https://example.org/cal/meeting\r\nORGANIZER;CN=Club:mailto:club@example.org\r\n"), $previous);
        self::assertSame(['ext_url' => 'https://example.org/old', 'organizer' => 'Somebody', 'organizer_id' => 0, 'categories' => 0], $this->event());
        self::assertSame([2], $this->categories());

        // changed: categories by title, organizer record by e-mail address (in the storage folder 5)
        $writer->write(1, 5, self::ics("URL:https://example.org/new\r\nCATEGORIES:SPORT,Music\r\nCATEGORIES:music\r\nORGANIZER;CN=The club:MAILTO:CLUB@example.org\r\n", "CATEGORIES:Ignored\r\n"), $previous);
        self::assertSame(['ext_url' => 'https://example.org/new', 'organizer' => '', 'organizer_id' => 3, 'categories' => 2], $this->event());
        self::assertSame([1, 2], $this->categories());

        // missing categories and organizers are created in the storage folder (also if other folders have them)
        $current = self::ics("URL:https://example.org/new\r\nCATEGORIES:SPORT,Music\r\nORGANIZER;CN=The club:MAILTO:CLUB@example.org\r\n");
        $writer->write(1, 5, self::ics("CATEGORIES:Theatre,Musik\r\nORGANIZER;CN=Anna:mailto:anna@example.org\r\n"), $current);
        $event = $this->event();
        self::assertSame(['', '', 2], [$event['ext_url'], $event['organizer'], $event['categories']]);
        $organizer = $this->record('tx_cal_domain_model_organizer', (int)$event['organizer_id']);
        self::assertSame([5, 'Anna', 'anna@example.org'], [(int)$organizer['pid'], $organizer['name'], $organizer['email']]);
        self::assertGreaterThan(4, (int)$event['organizer_id']);
        $created = $this->categories();
        self::assertCount(2, $created);
        foreach ($created as $index => $uid) {
            $category = $this->record('sys_category', $uid);
            self::assertSame([5, ['Theatre', 'Musik'][$index], 0], [(int)$category['pid'], $category['title'], (int)$category['sys_language_uid']]);
            self::assertGreaterThan(6, $uid);
        }

        // without e-mail address the name as text, without name the e-mail address
        $writer->write(1, 5, self::ics("ORGANIZER;CN=Bob:\r\n"), self::ics(''));
        self::assertSame(['Bob', 0], [$this->event()['organizer'], $this->event()['organizer_id']]);
        $writer->write(1, 5, self::ics("ORGANIZER:mailto:bob@example.org\r\n"), self::ics(''));
        $organizer = $this->record('tx_cal_domain_model_organizer', (int)$this->event()['organizer_id']);
        self::assertSame(['bob@example.org', 'bob@example.org'], [$organizer['name'], $organizer['email']]);
        // removed values are removed
        $writer->write(1, 5, self::ics(''), self::ics("CATEGORIES:Sport\r\nORGANIZER:mailto:bob@example.org\r\n"));
        self::assertSame(['', 0, 0], [$this->event()['organizer'], $this->event()['organizer_id'], $this->event()['categories']]);
        self::assertSame([], $this->categories());
    }

    #[Test]
    public function aNewEventTakesOverAllValues(): void
    {
        $this->get(CalDavPropertyWriter::class)->write(1, 5, self::ics("URL:https://example.org/x\r\nCATEGORIES:Sport\r\n"), '');
        self::assertSame(['ext_url' => 'https://example.org/x', 'organizer' => 'Somebody', 'organizer_id' => 0, 'categories' => 1], $this->event());
        self::assertSame([1], $this->categories());
    }

    #[Test]
    public function invalidDataChangesNothing(): void
    {
        $writer = $this->get(CalDavPropertyWriter::class);
        $writer->write(1, 5, 'no calendar data', '');
        $writer->write(1, 5, "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:Test\r\nEND:VCARD\r\n", '');
        // only changed occurrences, no event
        $writer->write(1, 5, "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:x\r\nBEGIN:VEVENT\r\nUID:a\r\nRECURRENCE-ID:20261208T100000Z\r\nDTSTART:20261208T120000Z\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n", '');
        // invalid previous data: like a new event
        $writer->write(1, 5, self::ics(''), 'invalid');
        self::assertSame(['ext_url' => 'https://example.org/old', 'organizer' => 'Somebody', 'organizer_id' => 0, 'categories' => 0], $this->event());
    }
}
