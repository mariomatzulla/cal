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
use MarioMatzulla\Cal\Upgrades\V4CategoriesUpgradeWizard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Upgrades\DatabaseUpdatedPrerequisite;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(V4CategoriesUpgradeWizard::class)]
#[CoversClass(LegacySchema::class)]
final class V4CategoriesUpgradeWizardTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['mariomatzulla/cal'];

    private function connection(string $table): Connection
    {
        return $this->get(ConnectionPool::class)->getConnectionForTable($table);
    }

    private function row(string $table, int $uid): array
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        return $queryBuilder->select('*')->from($table)->where($queryBuilder->expr()->eq('uid', $uid))->executeQuery()->fetchAssociative() ?: [];
    }

    private function wizard(BufferedOutput $output): V4CategoriesUpgradeWizard
    {
        // new LegacySchema: the tests change the tables after the container was built
        $wizard = new V4CategoriesUpgradeWizard(new LegacySchema($this->get(ConnectionPool::class)), $this->get(Registry::class));
        $wizard->setOutput($output);
        return $wizard;
    }

    private static function flexForm(string $categories): string
    {
        return '<?xml version="1.0" encoding="utf-8" standalone="yes" ?><T3FlexForms><data><sheet index="sDEF"><language index="lDEF">'
            . "<field index=\"categorySelection\">\n<value index=\"vDEF\">" . $categories . '</value></field>'
            . '</language></sheet></data></T3FlexForms>';
    }

    #[Test]
    public function nothingToMigrate(): void
    {
        $output = new BufferedOutput();
        $wizard = $this->get(V4CategoriesUpgradeWizard::class);
        $wizard->setOutput($output);
        self::assertStringStartsWith('Calendar Base (cal) 1.x => 4.0: ', $wizard->getTitle());
        self::assertNotSame('', $wizard->getDescription());
        self::assertSame([DatabaseUpdatedPrerequisite::class], $wizard->getPrerequisites());
        self::assertFalse($wizard->updateNecessary());
        self::assertTrue($wizard->executeUpdate());
        self::assertSame('', $output->fetch());
    }

    #[Test]
    public function categoriesRelationsPluginsAndColorsAreMigrated(): void
    {
        $category = $this->connection('sys_category');
        // cal 1.x with categoryService = sys_category had the column headerstyle in sys_category
        $category->executeStatement('ALTER TABLE sys_category ADD COLUMN headerstyle varchar(30) NOT NULL DEFAULT \'\'');
        $category->insert('sys_category', ['uid' => 1, 'pid' => 5, 'title' => 'Existing', 'headerstyle' => 'red_catheader']);
        $category->insert('sys_category', ['uid' => 2, 'pid' => 5, 'title' => 'Unknown style', 'headerstyle' => 'blue_catheader']);

        $category->executeStatement('CREATE TABLE tx_cal_category (uid int, pid int, crdate int, tstamp int, deleted int NOT NULL DEFAULT 0, hidden int NOT NULL DEFAULT 0, title varchar(255), headerstyle varchar(30), parent_category int, l18n_parent int, sys_language_uid int)');
        $category->insert('tx_cal_category', ['uid' => 1, 'pid' => 5, 'crdate' => 10, 'tstamp' => 10, 'title' => 'Sports', 'headerstyle' => 'green_catheader', 'parent_category' => 0, 'l18n_parent' => 0, 'sys_language_uid' => 0]);
        $category->insert('tx_cal_category', ['uid' => 2, 'pid' => 5, 'crdate' => 10, 'tstamp' => 10, 'title' => 'Football', 'headerstyle' => 'default_catheader', 'parent_category' => 1, 'l18n_parent' => 0, 'sys_language_uid' => 0]);
        $category->insert('tx_cal_category', ['uid' => 3, 'pid' => 5, 'crdate' => 10, 'tstamp' => 10, 'title' => 'Sport (en)', 'headerstyle' => 'unknown', 'parent_category' => 0, 'l18n_parent' => 1, 'sys_language_uid' => 1]);

        $category->executeStatement('CREATE TABLE tx_cal_event_category_mm (uid_local int, uid_foreign int, sorting int)');
        foreach ([[5, 1, 1], [5, 2, 2], [6, 9, 1], [0, 1, 1]] as [$event, $legacyCategory, $sorting]) {
            $category->insert('tx_cal_event_category_mm', ['uid_local' => $event, 'uid_foreign' => $legacyCategory, 'sorting' => $sorting]);
        }
        $events = $this->connection('tx_cal_domain_model_event');
        $events->insert('tx_cal_domain_model_event', ['uid' => 5, 'pid' => 5, 'title' => 'Match']);
        $events->insert('tx_cal_domain_model_event', ['uid' => 6, 'pid' => 5, 'title' => 'Other']);
        // relation created already (e.g. by an editor): Football (uid 4 after the migration) => event 5
        $this->connection('sys_category_record_mm')->insert('sys_category_record_mm', ['uid_local' => 4, 'uid_foreign' => 5, 'tablenames' => 'tx_cal_domain_model_event', 'fieldname' => 'categories']);

        $content = $this->connection('tt_content');
        $content->executeStatement('ALTER TABLE tt_content ADD COLUMN list_type varchar(255) NOT NULL DEFAULT \'\'');
        $content->insert('tt_content', ['uid' => 1, 'pid' => 5, 'CType' => 'cal_controller', 'pi_flexform' => self::flexForm('1,2,9')]);
        $content->insert('tt_content', ['uid' => 2, 'pid' => 5, 'CType' => 'text', 'pi_flexform' => self::flexForm('1')]);
        $content->insert('tt_content', ['uid' => 3, 'pid' => 5, 'CType' => 'list', 'list_type' => 'cal_controller', 'pi_flexform' => self::flexForm('')]);
        $content->insert('tt_content', ['uid' => 4, 'pid' => 5, 'CType' => 'list', 'list_type' => 'cal_controller', 'pi_flexform' => self::flexForm('2')]);

        $output = new BufferedOutput();
        $wizard = $this->wizard($output);
        self::assertTrue($wizard->updateNecessary());
        self::assertTrue($wizard->executeUpdate());
        $messages = $output->fetch();

        $mapping = $this->get(Registry::class)->get('tx_cal', 'v4CategoryMapping');
        self::assertSame([1 => 3, 2 => 4, 3 => 5], $mapping);
        self::assertSame(['Sports', '#53a062', 0], [$this->row('sys_category', 3)['title'], $this->row('sys_category', 3)['tx_cal_color'], (int)$this->row('sys_category', 3)['parent']]);
        self::assertSame(['Football', '', 3], [$this->row('sys_category', 4)['title'], $this->row('sys_category', 4)['tx_cal_color'], (int)$this->row('sys_category', 4)['parent']]);
        self::assertSame([1, 3, ''], [(int)$this->row('sys_category', 5)['sys_language_uid'], (int)$this->row('sys_category', 5)['l10n_parent'], $this->row('sys_category', 5)['tx_cal_color']]);
        // header styles of sys_category (cal 1.x with categoryService = sys_category)
        self::assertSame('#d42020', $this->row('sys_category', 1)['tx_cal_color']);
        self::assertSame('', $this->row('sys_category', 2)['tx_cal_color']);

        $relations = $this->connection('sys_category_record_mm')->select(['uid_local', 'uid_foreign', 'sorting_foreign'], 'sys_category_record_mm', ['tablenames' => 'tx_cal_domain_model_event', 'fieldname' => 'categories'], [], ['uid_local' => 'ASC'])->fetchAllAssociative();
        self::assertSame([[3, 5, 1], [4, 5, 0]], array_map(static fn(array $row): array => array_map('intval', array_values($row)), $relations));
        self::assertSame(2, (int)$this->row('tx_cal_domain_model_event', 5)['categories']);
        self::assertSame(0, (int)$this->row('tx_cal_domain_model_event', 6)['categories']);

        self::assertSame(self::flexForm('3,4'), $this->row('tt_content', 1)['pi_flexform']);
        self::assertSame(self::flexForm('1'), $this->row('tt_content', 2)['pi_flexform']);
        self::assertSame(self::flexForm(''), $this->row('tt_content', 3)['pi_flexform']);
        self::assertSame(self::flexForm('4'), $this->row('tt_content', 4)['pi_flexform']);

        self::assertStringContainsString('tx_cal_category => sys_category: 3 categories created', $messages);
        self::assertStringContainsString('sys_category_record_mm: 1 category relations of events created', $messages);
        self::assertStringContainsString('tt_content: category selection of 2 calendar plugins updated', $messages);
        self::assertStringContainsString('sys_category: color of 1 categories taken from the header style', $messages);

        // second run: everything is migrated, the plugins are not migrated twice
        $wizard = $this->wizard($output);
        self::assertFalse($wizard->updateNecessary());
        self::assertTrue($wizard->executeUpdate());
        self::assertSame('', $output->fetch());
        self::assertSame(self::flexForm('3,4'), $this->row('tt_content', 1)['pi_flexform']);

        // a relation of cal 1.x added later (e.g. by a second import of the old data)
        $category->insert('tx_cal_event_category_mm', ['uid_local' => 6, 'uid_foreign' => 1, 'sorting' => 1]);
        $wizard = $this->wizard($output);
        self::assertTrue($wizard->updateNecessary());
        self::assertTrue($wizard->executeUpdate());
        self::assertStringContainsString('1 category relations of events created', $output->fetch());
        self::assertSame(1, (int)$this->row('tx_cal_domain_model_event', 6)['categories']);
        self::assertFalse($wizard->updateNecessary());
    }

    #[Test]
    public function categoriesWithoutRelationTable(): void
    {
        $category = $this->connection('sys_category');
        $category->executeStatement('CREATE TABLE zzz_deleted_tx_cal_category (uid int, pid int, title varchar(255))');
        $category->insert('zzz_deleted_tx_cal_category', ['uid' => 7, 'pid' => 5, 'title' => 'Concerts']);
        $output = new BufferedOutput();
        $wizard = $this->wizard($output);
        self::assertTrue($wizard->updateNecessary());
        self::assertTrue($wizard->executeUpdate());
        self::assertSame('zzz_deleted_tx_cal_category => sys_category: 1 categories created' . PHP_EOL, $output->fetch());
        $uid = $this->get(Registry::class)->get('tx_cal', 'v4CategoryMapping')[7];
        self::assertSame(['Concerts', 0, ''], [$this->row('sys_category', $uid)['title'], (int)$this->row('sys_category', $uid)['parent'], $this->row('sys_category', $uid)['tx_cal_color']]);
        self::assertFalse($wizard->updateNecessary());
    }
}
