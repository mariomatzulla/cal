<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Backend\TCA;

/**
 * This file is part of the TYPO3 extension Calendar Base (cal).
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 extension Calendar Base (cal) project - inspiring people to share!
 */
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * itemsProcFunc implementations for TCA and FlexForm select fields.
 */
class ItemsProcFunc
{
    /**
     * Adds the times of a day in steps of 30 minutes (FlexForm: day view start/end).
     */
    public function getDayTimes(array &$params): void
    {
        $format = (string)($GLOBALS['TYPO3_CONF_VARS']['SYS']['hhmm'] ?? 'H:i');
        $interval = 60 * 30;
        $dayLength = 60 * 60 * 24;
        for ($time = 0; $time < $dayLength; $time += $interval) {
            // gmdate is ok, as $time only holds the seconds of one day
            $params['items'][] = [
                'label' => gmdate($format, $time),
                'value' => gmdate('His', $time),
            ];
        }
        // Entry for the end of the day
        $params['items'][] = [
            'label' => gmdate($format, $dayLength - 1),
            'value' => '2400',
        ];
    }

    /**
     * Adds the records of a table (itemsProcFunc_config.table) to the items.
     * Records of the current page are listed first. Non-admin backend users only
     * see records stored in their web mounts.
     */
    public function getRecords(array &$params): void
    {
        $config = $params['config']['itemsProcFunc_config'] ?? [];
        $table = (string)($config['table'] ?? '');
        if ($table === '' || !isset($GLOBALS['TCA'][$table])) {
            return;
        }
        $orderBy = (string)($config['orderBy'] ?? '');
        $pid = (int)($params['row']['pid'] ?? 0);

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->select('*')->from($table);

        $pageIds = $this->getAllowedPageIds();
        if ($pageIds !== null) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->in(
                    'pid',
                    $queryBuilder->createNamedParameter($pageIds, Connection::PARAM_INT_ARRAY),
                ),
            );
        }

        $languageField = (string)($GLOBALS['TCA'][$table]['ctrl']['languageField'] ?? '');
        if ($languageField !== '') {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->in(
                    $languageField,
                    $queryBuilder->createNamedParameter([-1, 0], Connection::PARAM_INT_ARRAY),
                ),
            );
        }

        if ($pid > 0) {
            // Records of the current page first
            $queryBuilder->addSelectLiteral(
                'CASE WHEN ' . $queryBuilder->quoteIdentifier('pid') . ' = '
                . $queryBuilder->createNamedParameter($pid, Connection::PARAM_INT) . ' THEN 0 ELSE 1 END AS cal_sorting',
            );
            $queryBuilder->orderBy('cal_sorting');
        }
        if ($orderBy !== '') {
            [$field, $direction] = array_pad(GeneralUtility::trimExplode(' ', $orderBy, true), 2, 'ASC');
            $queryBuilder->addOrderBy((string)$field, strtoupper((string)$direction) === 'DESC' ? 'DESC' : 'ASC');
        }

        $result = $queryBuilder->executeQuery();
        while ($row = $result->fetchAssociative()) {
            $params['items'][] = [
                'label' => BackendUtility::getRecordTitle($table, $row),
                'value' => $row['uid'],
            ];
        }
    }

    /**
     * @return int[]|null null = no restriction (admin)
     */
    private function getAllowedPageIds(): ?array
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if (!$backendUser instanceof BackendUserAuthentication || $backendUser->isAdmin()) {
            return null;
        }
        $mounts = array_map('intval', $backendUser->getWebmounts());
        if ($mounts === []) {
            return [0];
        }
        return GeneralUtility::makeInstance(PageRepository::class)->getPageIdsRecursive($mounts, 99);
    }
}
