<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Upgrades\Legacy;

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
use MarioMatzulla\Cal\Service\ExternalCalendarImporter;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Resource\Enum\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\StorageRepository;

/**
 * cal 1.x stored the file of an "ICS file" calendar (type 2) as file name in tx_cal_calendar.ics_file,
 * the file itself in uploads/tx_cal/ics/. cal 4 uses a FAL file field: the file is copied into the default
 * storage (folder "cal/ics/") and referenced from the calendar.
 *
 * @internal
 */
final class IcsFileMigration
{
    public const LEGACY_FOLDER = 'uploads/tx_cal/ics/';
    public const TARGET_FOLDER = 'cal/ics/';

    public function __construct(
        private readonly LegacySchema $legacySchema,
        private readonly StorageRepository $storageRepository,
    ) {}

    /**
     * Calendars of type 2 with a file name of cal 1.x and without file reference
     *
     * @return list<array{uid: int, pid: int, file: string, path: string, exists: bool}>
     */
    public function findPending(): array
    {
        $source = $this->legacySchema->findLegacyTable('tx_cal_calendar');
        $target = ExternalCalendarImporter::CALENDAR_TABLE;
        if ($source === null || !$this->legacySchema->tableExists($target) || $this->legacySchema->findColumn($source, 'ics_file') === null) {
            return [];
        }
        $icsColumn = (string)$this->legacySchema->findColumn($source, 'ics_file');
        $queryBuilder = $this->legacySchema->createQueryBuilder($source);
        $rows = $queryBuilder->select('uid', 'pid', $icsColumn)
            ->from($source)
            ->where(
                $queryBuilder->expr()->eq('type', ExternalCalendarImporter::CALENDAR_TYPE_FILE),
                $queryBuilder->expr()->eq('deleted', 0),
                $queryBuilder->expr()->neq($icsColumn, $queryBuilder->createNamedParameter('')),
            )
            ->executeQuery()
            ->fetchAllAssociative();
        $pending = [];
        foreach ($rows as $row) {
            $uid = (int)$row['uid'];
            $files = array_filter(array_map('trim', explode(',', (string)$row[$icsColumn])), static fn(string $file): bool => $file !== '' && !is_numeric($file));
            if ($files === []
                || $this->legacySchema->count($target, ['uid' => $uid]) === 0
                || $this->legacySchema->count('sys_file_reference', ['tablenames' => $target, 'fieldname' => 'ics_file', 'uid_foreign' => $uid, 'deleted' => 0]) > 0
            ) {
                continue;
            }
            foreach ($files as $file) {
                $path = Environment::getPublicPath() . '/' . self::LEGACY_FOLDER . basename($file);
                $pending[] = ['uid' => $uid, 'pid' => (int)$row['pid'], 'file' => basename($file), 'path' => $path, 'exists' => is_file($path)];
            }
        }
        return $pending;
    }

    /**
     * @return list<string> messages
     */
    public function migrate(): array
    {
        $messages = [];
        $pending = [];
        foreach ($this->findPending() as $item) {
            if ($item['exists']) {
                $pending[] = $item;
            } else {
                $messages[] = sprintf('WARNING calendar %d: the ICS file %s does not exist, please upload it to the calendar again.', $item['uid'], self::LEGACY_FOLDER . $item['file']);
            }
        }
        if ($pending === []) {
            return $messages;
        }
        $storage = $this->storageRepository->getDefaultStorage();
        if ($storage === null) {
            return [...$messages, 'ERROR ICS files: there is no default file storage, the ICS files of the calendars could not be migrated.'];
        }
        $evaluatePermissions = $storage->getEvaluatePermissions();
        $storage->setEvaluatePermissions(false);
        $migrated = [];
        try {
            $folder = $storage->hasFolder(self::TARGET_FOLDER)
                ? $storage->getFolder(self::TARGET_FOLDER)
                : $storage->createFolder(self::TARGET_FOLDER);
            $referenceConnection = $this->legacySchema->getConnection('sys_file_reference');
            $sorting = [];
            foreach ($pending as $item) {
                $file = $storage->addFile($item['path'], $folder, $item['file'], DuplicationBehavior::RENAME, false);
                $sorting[$item['uid']] = ($sorting[$item['uid']] ?? 0) + 1;
                $referenceConnection->insert('sys_file_reference', [
                    'pid' => $item['pid'],
                    'tstamp' => time(),
                    'crdate' => time(),
                    'uid_local' => $file->getUid(),
                    'uid_foreign' => $item['uid'],
                    'tablenames' => ExternalCalendarImporter::CALENDAR_TABLE,
                    'fieldname' => 'ics_file',
                    'sorting_foreign' => $sorting[$item['uid']],
                ]);
                $migrated[$item['uid']] = $sorting[$item['uid']];
            }
        } finally {
            $storage->setEvaluatePermissions($evaluatePermissions);
        }
        $calendarConnection = $this->legacySchema->getConnection(ExternalCalendarImporter::CALENDAR_TABLE);
        foreach ($migrated as $calendarUid => $count) {
            $calendarConnection->update(ExternalCalendarImporter::CALENDAR_TABLE, ['ics_file' => $count, 'md5' => ''], ['uid' => $calendarUid]);
        }
        $messages[] = sprintf('ICS files: files of %d calendars copied to "%s" of the default storage', count($migrated), self::TARGET_FOLDER);
        return $messages;
    }
}
