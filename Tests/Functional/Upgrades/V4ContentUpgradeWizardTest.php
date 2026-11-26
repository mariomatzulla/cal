<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Functional\Upgrades;

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
use MarioMatzulla\Cal\Upgrades\Legacy\LegacySchema;
use MarioMatzulla\Cal\Upgrades\V4ContentUpgradeWizard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Upgrades\DatabaseUpdatedPrerequisite;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(V4ContentUpgradeWizard::class)]
#[CoversClass(LegacySchema::class)]
final class V4ContentUpgradeWizardTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['mariomatzulla/cal'];

    private function row(string $table, int $uid): array
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        return $queryBuilder->select('*')->from($table)->where($queryBuilder->expr()->eq('uid', $uid))->executeQuery()->fetchAssociative() ?: [];
    }

    #[Test]
    public function nothingToMigrate(): void
    {
        $wizard = $this->get(V4ContentUpgradeWizard::class);
        self::assertStringStartsWith('Calendar Base (cal) 1.x => 4.0: ', $wizard->getTitle());
        self::assertNotSame('', $wizard->getDescription());
        self::assertSame([DatabaseUpdatedPrerequisite::class], $wizard->getPrerequisites());
        self::assertFalse($wizard->updateNecessary());
        $output = new BufferedOutput();
        $wizard->setOutput($output);
        self::assertTrue($wizard->executeUpdate());
        self::assertSame('', $output->fetch());
    }

    #[Test]
    public function pluginsShortcutsPermissionsAndTemplatesAreMigrated(): void
    {
        $pool = $this->get(ConnectionPool::class);
        // the column list_type was removed in TYPO3 14, installations of cal 1.x still have it
        $pool->getConnectionForTable('tt_content')->executeStatement('ALTER TABLE tt_content ADD COLUMN list_type varchar(255) NOT NULL DEFAULT \'\'');
        $content = $pool->getConnectionForTable('tt_content');
        $content->insert('tt_content', ['uid' => 1, 'pid' => 1, 'CType' => 'list', 'list_type' => 'cal_controller']);
        $content->insert('tt_content', ['uid' => 2, 'pid' => 1, 'CType' => 'list', 'list_type' => 'news_pi1']);
        $content->insert('tt_content', ['uid' => 3, 'pid' => 1, 'CType' => 'shortcut', 'records' => 'tx_cal_event_5,tt_content_1,tx_cal_location_2,tx_cal_unknown_3']);
        $content->insert('tt_content', ['uid' => 4, 'pid' => 1, 'CType' => 'shortcut', 'records' => 'tx_cal_unknown_3']);
        $pool->getConnectionForTable('be_groups')->insert('be_groups', [
            'uid' => 1,
            'pid' => 0,
            'title' => 'Editors',
            'tables_select' => 'pages,tx_cal_event,tx_cal_category,tx_cal_domain_model_event',
            'tables_modify' => 'tx_cal_location',
            'non_exclude_fields' => 'tx_cal_event:category_id,tx_cal_event:title,tx_cal_calendar,pages:title',
            'explicit_allowdeny' => 'tt_content:list_type:cal_controller,tt_content:CType:text',
        ]);
        $pool->getConnectionForTable('be_groups')->insert('be_groups', ['uid' => 2, 'pid' => 0, 'title' => 'Others', 'tables_select' => 'pages', 'non_exclude_fields' => 'tx_cal_domain_model_event:title']);
        $template = $pool->getConnectionForTable('sys_template');
        $template->insert('sys_template', ['uid' => 1, 'pid' => 1, 'include_static_file' => 'EXT:fluid_styled_content/Configuration/TypoScript/,EXT:cal/Configuration/TypoScript/ts/,EXT:cal/Configuration/TypoScript/css,EXT:cal/Configuration/TypoScript/ajax/,EXT:cal/Configuration/TypoScript/ics/']);
        $template->insert('sys_template', ['uid' => 2, 'pid' => 1, 'include_static_file' => 'EXT:cal/Configuration/TypoScript']);

        $wizard = $this->get(V4ContentUpgradeWizard::class);
        self::assertTrue($wizard->updateNecessary());
        $output = new BufferedOutput();
        $wizard->setOutput($output);
        self::assertTrue($wizard->executeUpdate());
        $messages = $output->fetch();

        self::assertSame(['cal_controller', ''], [$this->row('tt_content', 1)['CType'], $this->row('tt_content', 1)['list_type']]);
        self::assertSame('list', $this->row('tt_content', 2)['CType']);
        self::assertSame('tx_cal_domain_model_event_5,tt_content_1,tx_cal_domain_model_location_2,tx_cal_unknown_3', $this->row('tt_content', 3)['records']);
        self::assertSame('tx_cal_unknown_3', $this->row('tt_content', 4)['records']);

        $group = $this->row('be_groups', 1);
        self::assertSame('pages,tx_cal_domain_model_event,sys_category', $group['tables_select']);
        self::assertSame('tx_cal_domain_model_location', $group['tables_modify']);
        self::assertSame('tx_cal_domain_model_event:categories,tx_cal_domain_model_event:title,tx_cal_domain_model_calendar,pages:title', $group['non_exclude_fields']);
        self::assertSame('tt_content:CType:cal_controller,tt_content:CType:text', $group['explicit_allowdeny']);
        self::assertSame('pages', $this->row('be_groups', 2)['tables_select']);

        self::assertSame('EXT:fluid_styled_content/Configuration/TypoScript/,EXT:cal/Configuration/TypoScript,EXT:cal/Configuration/TypoScript/Styles', $this->row('sys_template', 1)['include_static_file']);
        self::assertSame('EXT:cal/Configuration/TypoScript', $this->row('sys_template', 2)['include_static_file']);

        self::assertStringContainsString('1 calendar plugins converted', $messages);
        self::assertStringContainsString('1 shortcut elements updated', $messages);
        self::assertStringContainsString('permissions of 1 backend user groups updated', $messages);
        self::assertStringContainsString('static templates of 1 TypoScript records replaced', $messages);
        self::assertFalse($wizard->updateNecessary());
    }
}
