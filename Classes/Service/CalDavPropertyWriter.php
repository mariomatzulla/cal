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
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\Reader;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Takes over the categories, the URL and the organizer of an event written by a CalDAV client into the
 * fields of cal. Only values changed in the client are written (compared with the iCalendar data stored
 * before), so values cal exports (e.g. the URL of the event view) do not overwrite the fields.
 * Categories and organizers are searched and created in the storage folder of the event (the folder of
 * its calendar) only.
 *
 * - CATEGORIES: the categories (sys_category) with these titles, missing categories are created
 * - URL: the external URL of the event (exported as URL)
 * - ORGANIZER: the organizer record with this e-mail address, created if missing. Without e-mail
 *   address the name is stored as text.
 */
class CalDavPropertyWriter
{
    private const EVENT_TABLE = 'tx_cal_domain_model_event';
    private const ORGANIZER_TABLE = 'tx_cal_domain_model_organizer';
    private const CATEGORY_TABLE = 'sys_category';
    private const CATEGORY_MM_TABLE = 'sys_category_record_mm';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * @param int $pid storage folder of the event: categories and organizers are searched and created there
     * @param string $previous the iCalendar data stored before, empty for a new event
     */
    public function write(int $eventUid, int $pid, string $calendarData, string $previous): void
    {
        $new = self::readProperties($calendarData);
        if ($new === null) {
            return;
        }
        $old = self::readProperties($previous) ?? ['categories' => [], 'titles' => [], 'url' => '', 'organizer' => ['', '']];
        $values = [];
        if ($new['url'] !== $old['url']) {
            $values['ext_url'] = $new['url'];
        }
        if ($new['organizer'] !== $old['organizer']) {
            $values += $this->getOrganizerValues($pid, ...$new['organizer']);
        }
        if ($new['categories'] !== $old['categories']) {
            $values['categories'] = $this->writeCategories($eventUid, $pid, $new['titles']);
        }
        if ($values !== []) {
            $this->connectionPool->getConnectionForTable(self::EVENT_TABLE)->update(self::EVENT_TABLE, $values, ['uid' => $eventUid]);
        }
    }

    /**
     * Categories (lower case titles, sorted, for the comparison), their titles (lower case => title of the
     * client), URL and organizer ([e-mail, name]) of the event (VEVENT without RECURRENCE-ID), null: no event
     *
     * @return array{categories: list<string>, titles: array<string, string>, url: string, organizer: array{0: string, 1: string}}|null
     */
    private static function readProperties(string $data): ?array
    {
        if (trim($data) === '') {
            return null;
        }
        try {
            $vcalendar = Reader::read($data, Reader::OPTION_FORGIVING);
        } catch (\Throwable) {
            return null;
        }
        if (!$vcalendar instanceof VCalendar) {
            return null;
        }
        foreach ($vcalendar->select('VEVENT') as $vEvent) {
            if (!$vEvent instanceof VEvent || isset($vEvent->{'RECURRENCE-ID'})) {
                continue;
            }
            $titles = [];
            foreach ($vEvent->select('CATEGORIES') as $property) {
                foreach ($property->getParts() as $title) {
                    $title = mb_substr(trim((string)$title), 0, 255);
                    if ($title !== '') {
                        $titles[mb_strtolower($title)] ??= $title;
                    }
                }
            }
            $categories = array_keys($titles);
            sort($categories);
            $organizer = ['', ''];
            $property = $vEvent->select('ORGANIZER')[0] ?? null;
            if ($property !== null) {
                $organizer = [
                    mb_strtolower(trim((string)preg_replace('/^mailto:/i', '', trim((string)$property->getValue())))),
                    trim((string)($property['CN'] ?? '')),
                ];
            }
            return [
                'categories' => $categories,
                'titles' => $titles,
                'url' => trim((string)($vEvent->URL ?? '')),
                'organizer' => $organizer,
            ];
        }
        return null;
    }

    /**
     * The organizer record with the e-mail address in the storage folder, created if missing.
     * Without e-mail address the name as text.
     *
     * @return array{organizer_id: int, organizer: string}
     */
    private function getOrganizerValues(int $pid, string $email, string $name): array
    {
        if ($email === '') {
            return ['organizer_id' => 0, 'organizer' => mb_substr($name, 0, 128)];
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::ORGANIZER_TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $uid = (int)$queryBuilder->select('uid')
            ->from(self::ORGANIZER_TABLE)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', 0),
                $queryBuilder->expr()->comparison('LOWER(' . $queryBuilder->quoteIdentifier('email') . ')', '=', $queryBuilder->createNamedParameter($email)),
            )
            ->orderBy('uid')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();
        if ($uid <= 0) {
            $connection = $this->connectionPool->getConnectionForTable(self::ORGANIZER_TABLE);
            $connection->insert(self::ORGANIZER_TABLE, [
                'pid' => $pid,
                'name' => mb_substr($name !== '' ? $name : $email, 0, 128),
                'email' => mb_substr($email, 0, 64),
                'tstamp' => time(),
                'crdate' => time(),
            ]);
            $uid = (int)$connection->lastInsertId();
        }
        return ['organizer_id' => $uid, 'organizer' => ''];
    }

    /**
     * Replaces the categories of the event by the categories with these titles (case insensitive) in the
     * storage folder, missing categories are created there
     *
     * @param array<string, string> $titles lower case => title
     * @return int number of categories
     */
    private function writeCategories(int $eventUid, int $pid, array $titles): int
    {
        $uids = [];
        if ($titles !== []) {
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::CATEGORY_TABLE);
            $queryBuilder->getRestrictions()->removeAll();
            foreach ($queryBuilder->select('uid', 'title')
                ->from(self::CATEGORY_TABLE)
                ->where(
                    $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, Connection::PARAM_INT)),
                    $queryBuilder->expr()->eq('deleted', 0),
                    $queryBuilder->expr()->eq('sys_language_uid', 0),
                )
                ->orderBy('uid')
                ->executeQuery()
                ->fetchAllAssociative() as $row) {
                $uids[mb_strtolower(trim((string)$row['title']))] ??= (int)$row['uid'];
            }
        }
        $categories = $this->connectionPool->getConnectionForTable(self::CATEGORY_TABLE);
        $connection = $this->connectionPool->getConnectionForTable(self::CATEGORY_MM_TABLE);
        $relation = ['uid_foreign' => $eventUid, 'tablenames' => self::EVENT_TABLE, 'fieldname' => 'categories'];
        $connection->delete(self::CATEGORY_MM_TABLE, $relation, ['uid_foreign' => Connection::PARAM_INT]);
        $sorting = 0;
        foreach ($titles as $key => $title) {
            if (!isset($uids[$key])) {
                $categories->insert(self::CATEGORY_TABLE, ['pid' => $pid, 'title' => $title, 'tstamp' => time(), 'crdate' => time()]);
                $uids[$key] = (int)$categories->lastInsertId();
            }
            $connection->insert(self::CATEGORY_MM_TABLE, $relation + ['uid_local' => $uids[$key], 'sorting' => 0, 'sorting_foreign' => ++$sorting]);
        }
        return count($titles);
    }
}
