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
use TYPO3\CMS\Core\Attribute\UpgradeWizard;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Upgrades\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Core\Upgrades\UpgradeWizardInterface;

/**
 * The first version of the category color had the default "#668cd9" (light blue), which was written
 * into every existing category. Now an empty color means "no own color" (the default color of the CSS
 * is used), so categories can be distinguished that really have a color. This wizard empties the old default.
 */
#[UpgradeWizard('cal_categoryColorDefault')]
final readonly class CategoryColorDefaultUpgradeWizard implements UpgradeWizardInterface
{
    private const OLD_DEFAULT = '#668cd9';

    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    public function getTitle(): string
    {
        return 'Calendar Base (cal): remove the old default color of categories';
    }

    public function getDescription(): string
    {
        return 'Categories with the former default color ' . self::OLD_DEFAULT . ' get "no color", so only categories '
            . 'with an explicitly chosen color define the color of their events.';
    }

    public function getPrerequisites(): array
    {
        return [DatabaseUpdatedPrerequisite::class];
    }

    public function updateNecessary(): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_category');
        $queryBuilder->getRestrictions()->removeAll();
        return (int)$queryBuilder->count('uid')
            ->from('sys_category')
            ->where($queryBuilder->expr()->eq('tx_cal_color', $queryBuilder->createNamedParameter(self::OLD_DEFAULT)))
            ->executeQuery()
            ->fetchOne() > 0;
    }

    public function executeUpdate(): bool
    {
        $this->connectionPool->getConnectionForTable('sys_category')
            ->update('sys_category', ['tx_cal_color' => ''], ['tx_cal_color' => self::OLD_DEFAULT]);
        return true;
    }
}
