<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Upgrades;

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
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Attribute\UpgradeWizard;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Upgrades\ChattyInterface;
use TYPO3\CMS\Core\Upgrades\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Core\Upgrades\UpgradeWizardInterface;

/**
 * The iCalendar data (CalDAV) created by earlier versions of cal 4 had a wrong DTEND for all-day events (the last day
 * instead of the following day, clients showed a multi-day event on its first day only) and for events ending
 * at midnight. The data created by cal is removed, the backend module "Cal ICS Generator" creates it again.
 * Data stored by CalDAV clients and imported events are not touched.
 */
#[UpgradeWizard('cal_icsData')]
final class IcsDataUpgradeWizard implements UpgradeWizardInterface, ChattyInterface
{
    private const EVENT_TABLE = 'tx_cal_domain_model_event';
    private const PRODID = 'PRODID:-//TYPO3/NONSGML Calendar Base (cal)';

    private ?OutputInterface $output = null;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function getTitle(): string
    {
        return 'Calendar Base (cal): recreate the iCalendar data of the events';
    }

    public function getDescription(): string
    {
        return 'The iCalendar data (CalDAV) created by cal had a wrong end of all-day events: CalDAV clients showed '
            . 'events of several days on their first day only. The wizard removes the data created by cal, afterwards '
            . 'create it again in the backend module "Cal ICS Generator". Data of CalDAV clients and imported events '
            . 'are kept.';
    }

    public function getPrerequisites(): array
    {
        return [DatabaseUpdatedPrerequisite::class];
    }

    public function updateNecessary(): bool
    {
        $queryBuilder = $this->createQueryBuilder();
        return (int)$queryBuilder->count('uid')
            ->from(self::EVENT_TABLE)
            ->where(...$this->getConditions($queryBuilder))
            ->executeQuery()
            ->fetchOne() > 0;
    }

    public function executeUpdate(): bool
    {
        $queryBuilder = $this->createQueryBuilder();
        $count = $queryBuilder->update(self::EVENT_TABLE)
            ->set('caldav_data', '')
            ->where(...$this->getConditions($queryBuilder))
            ->executeStatement();
        $this->output?->writeln(sprintf(
            '%d events: iCalendar data removed, create it again in the backend module "Cal ICS Generator".',
            $count,
        ));
        return true;
    }

    /**
     * Events of cal (not deleted) with iCalendar data created by cal
     *
     * @return list<string>
     */
    private function getConditions(QueryBuilder $queryBuilder): array
    {
        return [
            $queryBuilder->expr()->eq('deleted', 0),
            $queryBuilder->expr()->like(
                'caldav_data',
                $queryBuilder->createNamedParameter('%' . $queryBuilder->escapeLikeWildcards(self::PRODID) . '%'),
            ),
        ];
    }

    private function createQueryBuilder(): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::EVENT_TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        return $queryBuilder;
    }
}
