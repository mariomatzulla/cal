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
use MarioMatzulla\Cal\Upgrades\Legacy\LegacySchema;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Attribute\UpgradeWizard;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Upgrades\ChattyInterface;
use TYPO3\CMS\Core\Upgrades\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Core\Upgrades\UpgradeWizardInterface;

/**
 * cal 1.x => 4.0: the own category records (tx_cal_category, ext_conf "categoryService = tx_cal_category")
 * become sys_category records. Parent categories, translations, the relations to the events and the category
 * selection of the plugins are migrated, the header style of a category becomes its color.
 * For cal 1.x with "categoryService = sys_category" only the color is taken over.
 */
#[UpgradeWizard('cal_v4Categories')]
final class V4CategoriesUpgradeWizard implements UpgradeWizardInterface, ChattyInterface
{
    private const REGISTRY_NAMESPACE = 'tx_cal';
    private const REGISTRY_MAPPING = 'v4CategoryMapping';
    private const REGISTRY_PLUGINS = 'v4CategoryPlugins';
    private const EVENT_TABLE = 'tx_cal_domain_model_event';

    /**
     * Header styles of cal 1.x (Page TSconfig options.tx_cal_controller.headerStyles) => color.
     * "default_catheader" means "no own color".
     */
    private const HEADER_STYLE_COLORS = [
        'default_catheader' => '',
        'green_catheader' => '#53a062',
        'orange_catheader' => '#e84f25',
        'pink_catheader' => '#b257a2',
        'red_catheader' => '#d42020',
        'yellow_catheader' => '#b88f0b',
        'grey_catheader' => '#73738c',
    ];

    private ?OutputInterface $output = null;

    public function __construct(
        private readonly LegacySchema $legacySchema,
        private readonly Registry $registry,
    ) {}

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function getTitle(): string
    {
        return 'Calendar Base (cal) 1.x => 4.0: migrate categories to sys_category';
    }

    public function getDescription(): string
    {
        return 'Creates a sys_category record for every tx_cal_category record (with parent categories and translations), '
            . 'moves the category relations of the events to sys_category_record_mm and updates the category selection of '
            . 'the calendar plugins. The header style of a category (e.g. "green_catheader") becomes the category color.';
    }

    public function getPrerequisites(): array
    {
        return [DatabaseUpdatedPrerequisite::class];
    }

    public function updateNecessary(): bool
    {
        $categoryTable = $this->legacySchema->findLegacyTable('tx_cal_category');
        if ($categoryTable !== null && $this->findUnmigratedCategories($categoryTable) !== []) {
            return true;
        }
        if ($this->getMapping() !== [] && ($this->findMissingRelations() !== [] || $this->findPluginsToUpdate() !== [])) {
            return true;
        }
        return $this->findSysCategoriesWithHeaderStyle() !== [];
    }

    public function executeUpdate(): bool
    {
        $categoryTable = $this->legacySchema->findLegacyTable('tx_cal_category');
        if ($categoryTable !== null) {
            $this->migrateCategories($categoryTable);
        }
        if ($this->getMapping() !== []) {
            $this->migrateRelations();
            $this->migratePlugins();
        }
        $this->migrateHeaderStyles();
        return true;
    }

    private function migrateCategories(string $categoryTable): void
    {
        $rows = $this->findUnmigratedCategories($categoryTable);
        if ($rows === []) {
            return;
        }
        $mapping = $this->getMapping();
        $connection = $this->legacySchema->getConnection('sys_category');
        $targetColumns = $this->legacySchema->getColumns('sys_category');
        $headerStyle = $this->legacySchema->findColumn($categoryTable, 'headerstyle');
        $parentColumn = $this->legacySchema->findColumn($categoryTable, 'parent_category');
        $translationParent = $this->legacySchema->findColumn($categoryTable, 'l18n_parent');

        foreach ($rows as $row) {
            $category = [
                'pid' => (int)$row['pid'],
                'tstamp' => (int)($row['tstamp'] ?? time()),
                'crdate' => (int)($row['crdate'] ?? time()),
                'deleted' => (int)($row['deleted'] ?? 0),
                'hidden' => (int)($row['hidden'] ?? 0),
                'starttime' => (int)($row['starttime'] ?? 0),
                'endtime' => (int)($row['endtime'] ?? 0),
                'sorting' => (int)($row['sorting'] ?? 0),
                'title' => (string)($row['title'] ?? ''),
                'sys_language_uid' => (int)($row['sys_language_uid'] ?? 0),
            ];
            if (isset($targetColumns['tx_cal_color']) && $headerStyle !== null) {
                $category['tx_cal_color'] = self::HEADER_STYLE_COLORS[(string)$row[$headerStyle]] ?? '';
            }
            $category = array_intersect_key($category, array_flip($targetColumns));
            $connection->insert('sys_category', $category);
            $mapping[(int)$row['uid']] = (int)$connection->lastInsertId();
        }
        $this->registry->set(self::REGISTRY_NAMESPACE, self::REGISTRY_MAPPING, $mapping);

        // Second pass: parent categories and translations reference other categories
        foreach ($rows as $row) {
            $fields = [];
            $parent = $parentColumn !== null ? (int)$row[$parentColumn] : 0;
            if ($parent > 0 && isset($mapping[$parent])) {
                $fields['parent'] = $mapping[$parent];
            }
            $l10nParent = $translationParent !== null ? (int)$row[$translationParent] : 0;
            if ($l10nParent > 0 && isset($mapping[$l10nParent]) && isset($targetColumns['l10n_parent'])) {
                $fields['l10n_parent'] = $mapping[$l10nParent];
            }
            if ($fields !== []) {
                $connection->update('sys_category', $fields, ['uid' => $mapping[(int)$row['uid']]]);
            }
        }
        $this->write(sprintf('%s => sys_category: %d categories created', $categoryTable, count($rows)));
    }

    /**
     * tx_cal_event_category_mm (uid_local = event, uid_foreign = category) => sys_category_record_mm
     */
    private function migrateRelations(): void
    {
        $relations = $this->findMissingRelations();
        if ($relations === []) {
            return;
        }
        $connection = $this->legacySchema->getConnection('sys_category_record_mm');
        $connection->bulkInsert(
            'sys_category_record_mm',
            array_map(static fn(array $relation): array => array_values($relation), $relations),
            array_keys($relations[0]),
        );
        $this->updateCategoryCounters(array_unique(array_column($relations, 'uid_foreign')));
        $this->write(sprintf('sys_category_record_mm: %d category relations of events created', count($relations)));
    }

    /**
     * @return list<array{uid_local: int, uid_foreign: int, tablenames: string, fieldname: string, sorting_foreign: int}>
     */
    private function findMissingRelations(): array
    {
        $mmTable = $this->legacySchema->findLegacyTable('tx_cal_event_category_mm');
        $mapping = $this->getMapping();
        if ($mmTable === null || $mapping === []) {
            return [];
        }
        $existing = [];
        $queryBuilder = $this->legacySchema->createQueryBuilder('sys_category_record_mm');
        $rows = $queryBuilder->select('uid_local', 'uid_foreign')
            ->from('sys_category_record_mm')
            ->where(
                $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter(self::EVENT_TABLE)),
                $queryBuilder->expr()->eq('fieldname', $queryBuilder->createNamedParameter('categories')),
            )
            ->executeQuery()
            ->fetchAllAssociative();
        foreach ($rows as $row) {
            $existing[$row['uid_local'] . '|' . $row['uid_foreign']] = true;
        }

        $relations = [];
        $sortingColumn = $this->legacySchema->findColumn($mmTable, 'sorting');
        $queryBuilder = $this->legacySchema->createQueryBuilder($mmTable);
        foreach ($queryBuilder->select('*')->from($mmTable)->executeQuery()->fetchAllAssociative() as $row) {
            $category = $mapping[(int)$row['uid_foreign']] ?? 0;
            $event = (int)$row['uid_local'];
            if ($category === 0 || $event === 0 || isset($existing[$category . '|' . $event])) {
                continue;
            }
            $existing[$category . '|' . $event] = true;
            $relations[] = [
                'uid_local' => $category,
                'uid_foreign' => $event,
                'tablenames' => self::EVENT_TABLE,
                'fieldname' => 'categories',
                'sorting_foreign' => $sortingColumn !== null ? (int)$row[$sortingColumn] : 0,
            ];
        }
        return $relations;
    }

    /**
     * @param array<int|string, int|string> $eventUids
     */
    private function updateCategoryCounters(array $eventUids): void
    {
        $eventConnection = $this->legacySchema->getConnection(self::EVENT_TABLE);
        foreach ($eventUids as $eventUid) {
            $count = $this->legacySchema->count('sys_category_record_mm', [
                'uid_foreign' => (int)$eventUid,
                'tablenames' => self::EVENT_TABLE,
                'fieldname' => 'categories',
            ]);
            $eventConnection->update(self::EVENT_TABLE, ['categories' => $count], ['uid' => (int)$eventUid]);
        }
    }

    /**
     * The FlexForm field "categorySelection" contains uids of tx_cal_category.
     */
    private function migratePlugins(): void
    {
        $plugins = $this->findPluginsToUpdate();
        if ($plugins === []) {
            return;
        }
        $mapping = $this->getMapping();
        $done = $this->registry->get(self::REGISTRY_NAMESPACE, self::REGISTRY_PLUGINS, []);
        $connection = $this->legacySchema->getConnection('tt_content');
        foreach ($plugins as $plugin) {
            $flexForm = preg_replace_callback(
                '#(<field index="categorySelection">\s*<value index="vDEF">)([^<]*)(</value>)#',
                static function (array $matches) use ($mapping): string {
                    $uids = array_filter(array_map('intval', explode(',', $matches[2])));
                    $newUids = array_filter(array_map(static fn(int $uid): int => $mapping[$uid] ?? 0, $uids));
                    return $matches[1] . implode(',', $newUids) . $matches[3];
                },
                (string)$plugin['pi_flexform'],
            );
            $connection->update('tt_content', ['pi_flexform' => $flexForm], ['uid' => (int)$plugin['uid']]);
            $done[] = (int)$plugin['uid'];
        }
        $this->registry->set(self::REGISTRY_NAMESPACE, self::REGISTRY_PLUGINS, $done);
        $this->write(sprintf('tt_content: category selection of %d calendar plugins updated', count($plugins)));
    }

    /**
     * Calendar plugins (old list_type or new CType) with a category selection, not migrated yet.
     *
     * @return list<array{uid: int, pi_flexform: string}>
     */
    private function findPluginsToUpdate(): array
    {
        $done = array_flip($this->registry->get(self::REGISTRY_NAMESPACE, self::REGISTRY_PLUGINS, []));
        $queryBuilder = $this->legacySchema->createQueryBuilder('tt_content');
        $rows = $queryBuilder->select('uid', 'pi_flexform')
            ->from('tt_content')
            ->where($queryBuilder->expr()->like('pi_flexform', $queryBuilder->createNamedParameter('%<field index="categorySelection">%')))
            ->executeQuery()
            ->fetchAllAssociative();
        $listType = $this->legacySchema->findColumn('tt_content', 'list_type');
        $plugins = [];
        foreach ($rows as $row) {
            if (isset($done[(int)$row['uid']]) || !$this->isCalendarPlugin((int)$row['uid'], $listType)) {
                continue;
            }
            if (preg_match('#<field index="categorySelection">\s*<value index="vDEF">\s*[0-9]#', (string)$row['pi_flexform'])) {
                $plugins[] = ['uid' => (int)$row['uid'], 'pi_flexform' => (string)$row['pi_flexform']];
            }
        }
        return $plugins;
    }

    private function isCalendarPlugin(int $uid, ?string $listTypeColumn): bool
    {
        $row = $this->legacySchema->fetchRow('tt_content', $listTypeColumn !== null ? ['CType', $listTypeColumn] : ['CType'], ['uid' => $uid]);
        return ($row['CType'] ?? '') === 'cal_controller'
            || ($listTypeColumn !== null && ($row[$listTypeColumn] ?? '') === 'cal_controller');
    }

    /**
     * cal 1.x with categoryService = sys_category: sys_category.headerstyle => tx_cal_color
     */
    private function migrateHeaderStyles(): void
    {
        $count = 0;
        $connection = $this->legacySchema->getConnection('sys_category');
        foreach ($this->findSysCategoriesWithHeaderStyle() as $uid => $color) {
            $connection->update('sys_category', ['tx_cal_color' => $color], ['uid' => $uid]);
            $count++;
        }
        if ($count > 0) {
            $this->write(sprintf('sys_category: color of %d categories taken from the header style', $count));
        }
    }

    /**
     * @return array<int, string> uid => color
     */
    private function findSysCategoriesWithHeaderStyle(): array
    {
        $headerStyle = $this->legacySchema->findColumn('sys_category', 'headerstyle');
        if ($headerStyle === null || $this->legacySchema->findColumn('sys_category', 'tx_cal_color') === null) {
            return [];
        }
        $queryBuilder = $this->legacySchema->createQueryBuilder('sys_category');
        $rows = $queryBuilder->select('uid', $headerStyle)
            ->from('sys_category')
            ->where(
                $queryBuilder->expr()->eq('tx_cal_color', $queryBuilder->createNamedParameter('')),
                $queryBuilder->expr()->neq($headerStyle, $queryBuilder->createNamedParameter('')),
            )
            ->executeQuery()
            ->fetchAllAssociative();
        $result = [];
        foreach ($rows as $row) {
            $color = self::HEADER_STYLE_COLORS[(string)$row[$headerStyle]] ?? '';
            if ($color !== '') {
                $result[(int)$row['uid']] = $color;
            }
        }
        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function findUnmigratedCategories(string $categoryTable): array
    {
        $mapping = $this->getMapping();
        $queryBuilder = $this->legacySchema->createQueryBuilder($categoryTable);
        $rows = $queryBuilder->select('*')->from($categoryTable)->orderBy('uid')->executeQuery()->fetchAllAssociative();
        return array_values(array_filter($rows, static fn(array $row): bool => !isset($mapping[(int)$row['uid']])));
    }

    /**
     * @return array<int, int> uid of tx_cal_category => uid of sys_category
     */
    private function getMapping(): array
    {
        $mapping = $this->registry->get(self::REGISTRY_NAMESPACE, self::REGISTRY_MAPPING, []);
        return is_array($mapping) ? $mapping : [];
    }

    private function write(string $message): void
    {
        $this->output?->writeln($message);
    }
}
