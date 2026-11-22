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
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Upgrades\ChattyInterface;
use TYPO3\CMS\Core\Upgrades\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Core\Upgrades\UpgradeWizardInterface;

/**
 * The event types "Meeting" (3) and "Todo" (4) were removed, these events become normal events (0).
 */
#[UpgradeWizard('cal_eventTypes')]
final class EventTypeUpgradeWizard implements UpgradeWizardInterface, ChattyInterface
{
    private const EVENT_TABLE = 'tx_cal_domain_model_event';
    private const REMOVED_TYPES = [3, 4];

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
        return 'Calendar Base (cal): convert meetings and todos to normal events';
    }

    public function getDescription(): string
    {
        return 'The event types "Meeting" and "Todo" were removed. Events of these types become events with description.';
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
            ->where($this->getCondition($queryBuilder))
            ->executeQuery()
            ->fetchOne() > 0;
    }

    public function executeUpdate(): bool
    {
        $queryBuilder = $this->createQueryBuilder();
        $count = $queryBuilder->update(self::EVENT_TABLE)
            ->set('type', 0)
            ->where($this->getCondition($queryBuilder))
            ->executeStatement();
        $this->output?->writeln(sprintf('%d events converted to normal events.', $count));
        return true;
    }

    private function getCondition(QueryBuilder $queryBuilder): string
    {
        return (string)$queryBuilder->expr()->in(
            'type',
            $queryBuilder->createNamedParameter(self::REMOVED_TYPES, Connection::PARAM_INT_ARRAY),
        );
    }

    private function createQueryBuilder(): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::EVENT_TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        return $queryBuilder;
    }
}
