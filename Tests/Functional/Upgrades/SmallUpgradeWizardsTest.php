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
use MarioMatzulla\Cal\Upgrades\CategoryColorDefaultUpgradeWizard;
use MarioMatzulla\Cal\Upgrades\EventTypeUpgradeWizard;
use MarioMatzulla\Cal\Upgrades\IcsDataUpgradeWizard;
use MarioMatzulla\Cal\Upgrades\L10nParentUpgradeWizard;
use MarioMatzulla\Cal\Upgrades\SlugUpgradeWizard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Upgrades\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Core\Upgrades\UpgradeWizardInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(EventTypeUpgradeWizard::class)]
#[CoversClass(CategoryColorDefaultUpgradeWizard::class)]
#[CoversClass(IcsDataUpgradeWizard::class)]
#[CoversClass(L10nParentUpgradeWizard::class)]
#[CoversClass(SlugUpgradeWizard::class)]
final class SmallUpgradeWizardsTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['mariomatzulla/cal'];

    private function connection(string $table): Connection
    {
        return $this->get(ConnectionPool::class)->getConnectionForTable($table);
    }

    /**
     * @return array<int, mixed> uid => value of the field (also deleted records)
     */
    private function values(string $table, string $field): array
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $rows = $queryBuilder->select('uid', $field)->from($table)->orderBy('uid')->executeQuery()->fetchAllAssociative();
        return array_column($rows, $field, 'uid');
    }

    private static function assertWizardInformation(UpgradeWizardInterface $wizard): void
    {
        self::assertStringStartsWith('Calendar Base (cal): ', $wizard->getTitle());
        self::assertNotSame('', $wizard->getDescription());
        self::assertSame([DatabaseUpdatedPrerequisite::class], $wizard->getPrerequisites());
    }

    #[Test]
    public function eventTypes(): void
    {
        foreach ([1 => 0, 2 => 3, 3 => 4, 4 => 2] as $uid => $type) {
            $this->connection('tx_cal_domain_model_event')->insert('tx_cal_domain_model_event', ['uid' => $uid, 'pid' => 5, 'type' => $type, 'deleted' => (int)($uid === 3)]);
        }
        $wizard = $this->get(EventTypeUpgradeWizard::class);
        self::assertWizardInformation($wizard);
        self::assertTrue($wizard->updateNecessary());
        $output = new BufferedOutput();
        $wizard->setOutput($output);
        self::assertTrue($wizard->executeUpdate());
        self::assertSame([1 => 0, 2 => 0, 3 => 0, 4 => 2], array_map('intval', $this->values('tx_cal_domain_model_event', 'type')));
        self::assertStringContainsString('2 events converted', $output->fetch());
        self::assertFalse($wizard->updateNecessary());
    }

    #[Test]
    public function categoryColorDefault(): void
    {
        foreach ([1 => '#668cd9', 2 => '#ff0000', 3 => '#668cd9'] as $uid => $color) {
            $this->connection('sys_category')->insert('sys_category', ['uid' => $uid, 'pid' => 5, 'title' => 'Category ' . $uid, 'tx_cal_color' => $color]);
        }
        $wizard = $this->get(CategoryColorDefaultUpgradeWizard::class);
        self::assertWizardInformation($wizard);
        self::assertStringContainsString('#668cd9', $wizard->getDescription());
        self::assertTrue($wizard->updateNecessary());
        self::assertTrue($wizard->executeUpdate());
        self::assertSame([1 => '', 2 => '#ff0000', 3 => ''], $this->values('sys_category', 'tx_cal_color'));
        self::assertFalse($wizard->updateNecessary());
    }

    #[Test]
    public function icsData(): void
    {
        $cal = "BEGIN:VCALENDAR\r\nPRODID:-//TYPO3/NONSGML Calendar Base (cal) V2.0.0//EN\r\nEND:VCALENDAR";
        $client = "BEGIN:VCALENDAR\r\nPRODID:-//Apple Inc.//iCal//EN\r\nEND:VCALENDAR";
        foreach ([1 => [$cal, 0], 2 => [$client, 0], 3 => [$cal, 1], 4 => ['', 0]] as $uid => [$data, $deleted]) {
            $this->connection('tx_cal_domain_model_event')->insert('tx_cal_domain_model_event', ['uid' => $uid, 'pid' => 5, 'caldav_data' => $data, 'deleted' => $deleted]);
        }
        $wizard = $this->get(IcsDataUpgradeWizard::class);
        self::assertWizardInformation($wizard);
        self::assertTrue($wizard->updateNecessary());
        $output = new BufferedOutput();
        $wizard->setOutput($output);
        self::assertTrue($wizard->executeUpdate());
        self::assertSame([1 => '', 2 => $client, 3 => $cal, 4 => ''], $this->values('tx_cal_domain_model_event', 'caldav_data'));
        self::assertStringContainsString('1 events', $output->fetch());
        self::assertFalse($wizard->updateNecessary());
        // the data created again (backend module "Cal ICS Generator") is not removed again
        $this->connection('tx_cal_domain_model_event')->update('tx_cal_domain_model_event', ['caldav_data' => $cal], ['uid' => 1]);
        self::assertFalse($wizard->updateNecessary());
    }

    #[Test]
    public function slugs(): void
    {
        $this->connection('tx_cal_domain_model_location')->insert('tx_cal_domain_model_location', ['uid' => 1, 'pid' => 5, 'name' => 'Town hall', 'slug' => '']);
        $wizard = $this->get(SlugUpgradeWizard::class);
        self::assertWizardInformation($wizard);
        self::assertTrue($wizard->updateNecessary());
        $output = new BufferedOutput();
        $wizard->setOutput($output);
        self::assertTrue($wizard->executeUpdate());
        self::assertSame([1 => 'town-hall'], $this->values('tx_cal_domain_model_location', 'slug'));
        self::assertStringContainsString('tx_cal_domain_model_location: 1 URL segments', $output->fetch());
        self::assertFalse($wizard->updateNecessary());
    }

    #[Test]
    public function l10nParent(): void
    {
        $wizard = $this->get(L10nParentUpgradeWizard::class);
        self::assertWizardInformation($wizard);
        // no old columns
        self::assertFalse($wizard->updateNecessary());
        self::assertTrue($wizard->executeUpdate());

        // old columns of cal 1.x, the column of the organizers was renamed by the database analyzer
        $event = $this->connection('tx_cal_domain_model_event');
        $event->executeStatement('ALTER TABLE tx_cal_domain_model_event ADD COLUMN l18n_parent int NOT NULL DEFAULT 0');
        $event->executeStatement('ALTER TABLE tx_cal_domain_model_event ADD COLUMN l18n_diffsource text');
        $organizer = $this->connection('tx_cal_domain_model_organizer');
        $organizer->executeStatement('ALTER TABLE tx_cal_domain_model_organizer ADD COLUMN zzz_deleted_l18n_parent int NOT NULL DEFAULT 0');
        // no records to migrate
        self::assertFalse($wizard->updateNecessary());

        $event->insert('tx_cal_domain_model_event', ['uid' => 1, 'pid' => 5, 'sys_language_uid' => 0]);
        $event->insert('tx_cal_domain_model_event', ['uid' => 2, 'pid' => 5, 'sys_language_uid' => 1, 'l18n_parent' => 1, 'l18n_diffsource' => 'a:0:{}']);
        $event->insert('tx_cal_domain_model_event', ['uid' => 3, 'pid' => 5, 'sys_language_uid' => 1, 'l10n_parent' => 1, 'l18n_parent' => 9, 'l10n_diffsource' => 'kept', 'l18n_diffsource' => 'old']);
        $organizer->insert('tx_cal_domain_model_organizer', ['uid' => 1, 'pid' => 5, 'sys_language_uid' => 1, 'zzz_deleted_l18n_parent' => 4]);

        self::assertTrue($wizard->updateNecessary());
        self::assertTrue($wizard->executeUpdate());
        // an existing l10n_parent is kept
        self::assertSame([1 => 0, 2 => 1, 3 => 1], array_map('intval', $this->values('tx_cal_domain_model_event', 'l10n_parent')));
        self::assertSame('a:0:{}', $this->values('tx_cal_domain_model_event', 'l10n_diffsource')[2]);
        self::assertSame('kept', $this->values('tx_cal_domain_model_event', 'l10n_diffsource')[3]);
        self::assertSame([1 => 4], array_map('intval', $this->values('tx_cal_domain_model_organizer', 'l10n_parent')));
        self::assertFalse($wizard->updateNecessary());
    }

    #[Test]
    public function l10nParentOfAMissingTable(): void
    {
        $this->connection('tx_cal_domain_model_location')->executeStatement('DROP TABLE tx_cal_domain_model_location');
        $wizard = $this->get(L10nParentUpgradeWizard::class);
        self::assertFalse($wizard->updateNecessary());
        self::assertTrue($wizard->executeUpdate());
    }
}
