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
use MarioMatzulla\Cal\Utility\RecurrenceGenerator;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Attribute\UpgradeWizard;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Upgrades\ChattyInterface;
use TYPO3\CMS\Core\Upgrades\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Core\Upgrades\UpgradeWizardInterface;

/**
 * The recurrence index contains the first date of a recurring event and leaves out the dates of the
 * exception events now, the iCalendar data contains the recurrence (RRULE, RDATE, EXDATE, deviations).
 * The wizard rebuilds the index of the recurring events and removes their iCalendar data created by cal,
 * so the backend module "Cal ICS Generator" creates it again.
 */
#[UpgradeWizard('cal_recurrence')]
final class RecurrenceUpgradeWizard implements UpgradeWizardInterface, ChattyInterface
{
    private const EVENT_TABLE = 'tx_cal_domain_model_event';
    private const PRODID = 'PRODID:-//TYPO3/NONSGML Calendar Base (cal)';
    private const REGISTRY_NAMESPACE = 'tx_cal';
    private const REGISTRY_KEY = 'recurrenceUpgradeDone';

    private ?OutputInterface $output = null;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly RecurrenceGenerator $recurrenceGenerator,
        private readonly Registry $registry,
    ) {}

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function getTitle(): string
    {
        return 'Calendar Base (cal): rebuild the recurrence index and the iCalendar data of recurring events';
    }

    public function getDescription(): string
    {
        return 'The recurrence index contains the first date of a recurring event and leaves out the days of its '
            . 'exception events now, the iCalendar data (download, CalDAV) contains the recurrence rule, additional and '
            . 'removed dates and the deviations. The wizard rebuilds the index of the recurring events and removes their '
            . 'iCalendar data created by cal, afterwards create it again in the backend module "Cal ICS Generator".';
    }

    public function getPrerequisites(): array
    {
        return [DatabaseUpdatedPrerequisite::class];
    }

    public function updateNecessary(): bool
    {
        return $this->registry->get(self::REGISTRY_NAMESPACE, self::REGISTRY_KEY) !== true && $this->findRecurringEvents() !== [];
    }

    public function executeUpdate(): bool
    {
        $events = $this->findRecurringEvents();
        $this->recurrenceGenerator->setRange();
        foreach (array_keys($events) as $uid) {
            $this->recurrenceGenerator->generateIndexForEventUid($uid);
        }
        $created = array_keys(array_filter($events, static fn(string $data): bool => str_contains($data, self::PRODID)));
        if ($created !== []) {
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::EVENT_TABLE);
            $queryBuilder->update(self::EVENT_TABLE)
                ->set('caldav_data', '')
                ->where($queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($created, Connection::PARAM_INT_ARRAY)))
                ->executeStatement();
        }
        $this->output?->writeln(sprintf(
            '%d recurring events: index rebuilt, iCalendar data of %d events removed, create it again in the backend module "Cal ICS Generator".',
            count($events),
            count($created),
        ));
        $this->registry->set(self::REGISTRY_NAMESPACE, self::REGISTRY_KEY, true);
        return true;
    }

    /**
     * @return array<int, string> uid => iCalendar data of the recurring events
     */
    private function findRecurringEvents(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::EVENT_TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $rows = $queryBuilder->select('uid', 'caldav_data')
            ->from(self::EVENT_TABLE)
            ->where(
                $queryBuilder->expr()->eq('deleted', 0),
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->in('freq', $queryBuilder->createNamedParameter(['day', 'week', 'month', 'year'], Connection::PARAM_STR_ARRAY)),
                    $queryBuilder->expr()->and(
                        $queryBuilder->expr()->neq('rdate', $queryBuilder->createNamedParameter('')),
                        $queryBuilder->expr()->in('rdate_type', $queryBuilder->createNamedParameter(['date_time', 'date', 'period'], Connection::PARAM_STR_ARRAY)),
                    ),
                ),
            )
            ->executeQuery()
            ->fetchAllAssociative();
        $events = [];
        foreach ($rows as $row) {
            $events[(int)$row['uid']] = (string)$row['caldav_data'];
        }
        return $events;
    }
}
