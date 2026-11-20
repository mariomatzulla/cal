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
use TYPO3\CMS\Core\Upgrades\ChattyInterface;
use TYPO3\CMS\Core\Upgrades\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Core\Upgrades\UpgradeWizardInterface;

/**
 * cal 1.x => 4.0: content elements, backend user groups and TypoScript templates.
 *
 * - Plugin "list_type = cal_controller" => content element "CType = cal_controller"
 * - Shortcut content elements referencing events: tx_cal_event_<uid> => tx_cal_domain_model_event_<uid>
 * - be_groups: table permissions, allowed exclude fields and the explicitly allowed plugin
 * - sys_template: the static templates of cal 1.x => "Calendar Base (cal)" / "...: default styles"
 */
#[UpgradeWizard('cal_v4Content')]
final class V4ContentUpgradeWizard implements UpgradeWizardInterface, ChattyInterface
{
    private const PLUGIN = 'cal_controller';

    /**
     * Static templates of cal 1.x => static templates of cal 4.x (null: removed without replacement)
     */
    private const STATIC_TEMPLATES = [
        'EXT:cal/Configuration/TypoScript/ts/' => 'EXT:cal/Configuration/TypoScript',
        'EXT:cal/Configuration/TypoScript/ts_standard/' => 'EXT:cal/Configuration/TypoScript',
        'EXT:cal/Configuration/TypoScript/css/' => 'EXT:cal/Configuration/TypoScript/Styles',
        'EXT:cal/Configuration/TypoScript/css_standard/' => 'EXT:cal/Configuration/TypoScript/Styles',
        // the ICS page type is part of the main template now
        'EXT:cal/Configuration/TypoScript/ics/' => 'EXT:cal/Configuration/TypoScript',
        'EXT:cal/Configuration/TypoScript/ajax/' => null,
        'EXT:cal/Configuration/TypoScript/fe-editing/' => null,
        'EXT:cal/Configuration/TypoScript/rss_feed/' => null,
    ];

    private ?OutputInterface $output = null;

    public function __construct(
        private readonly LegacySchema $legacySchema,
    ) {}

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function getTitle(): string
    {
        return 'Calendar Base (cal) 1.x => 4.0: migrate plugins, permissions and TypoScript templates';
    }

    public function getDescription(): string
    {
        return 'Converts the plugins "list_type = cal_controller" into the content element "cal_controller", updates shortcut '
            . 'content elements and the permissions of backend user groups to the new table names and replaces the static '
            . 'TypoScript templates of cal 1.x. TypoScript of your own below "plugin.tx_cal_controller" keeps working '
            . '(deprecated), but should be moved to "plugin.tx_cal.settings".';
    }

    public function getPrerequisites(): array
    {
        return [DatabaseUpdatedPrerequisite::class];
    }

    public function updateNecessary(): bool
    {
        return $this->findPlugins() !== []
            || $this->findShortcuts() !== []
            || $this->findBackendGroups() !== []
            || $this->findTemplates() !== [];
    }

    public function executeUpdate(): bool
    {
        $this->migratePlugins();
        $this->migrateShortcuts();
        $this->migrateBackendGroups();
        $this->migrateTemplates();
        return true;
    }

    private function migratePlugins(): void
    {
        $plugins = $this->findPlugins();
        $listType = $this->legacySchema->findColumn('tt_content', 'list_type');
        $connection = $this->legacySchema->getConnection('tt_content');
        foreach ($plugins as $uid) {
            $fields = ['CType' => self::PLUGIN];
            if ($listType !== null) {
                $fields[$listType] = '';
            }
            $connection->update('tt_content', $fields, ['uid' => $uid]);
        }
        if ($plugins !== []) {
            $this->write(sprintf('tt_content: %d calendar plugins converted to the content element "%s"', count($plugins), self::PLUGIN));
        }
    }

    /**
     * @return list<int>
     */
    private function findPlugins(): array
    {
        $listType = $this->legacySchema->findColumn('tt_content', 'list_type');
        if ($listType === null) {
            return [];
        }
        $queryBuilder = $this->legacySchema->createQueryBuilder('tt_content');
        return array_map('intval', $queryBuilder->select('uid')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter('list')),
                $queryBuilder->expr()->eq($listType, $queryBuilder->createNamedParameter(self::PLUGIN)),
            )
            ->executeQuery()
            ->fetchFirstColumn());
    }

    private function migrateShortcuts(): void
    {
        $connection = $this->legacySchema->getConnection('tt_content');
        $shortcuts = $this->findShortcuts();
        foreach ($shortcuts as $uid => $records) {
            $connection->update('tt_content', ['records' => $this->renameRecordReferences($records)], ['uid' => $uid]);
        }
        if ($shortcuts !== []) {
            $this->write(sprintf('tt_content: %d shortcut elements updated', count($shortcuts)));
        }
    }

    /**
     * @return array<int, string> uid => records
     */
    private function findShortcuts(): array
    {
        if ($this->legacySchema->findColumn('tt_content', 'records') === null) {
            return [];
        }
        $queryBuilder = $this->legacySchema->createQueryBuilder('tt_content');
        $rows = $queryBuilder->select('uid', 'records')
            ->from('tt_content')
            ->where($queryBuilder->expr()->like('records', $queryBuilder->createNamedParameter('%tx_cal_%')))
            ->executeQuery()
            ->fetchAllAssociative();
        $result = [];
        foreach ($rows as $row) {
            if ($this->renameRecordReferences((string)$row['records']) !== (string)$row['records']) {
                $result[(int)$row['uid']] = (string)$row['records'];
            }
        }
        return $result;
    }

    /**
     * "tx_cal_event_12,tt_content_3" => "tx_cal_domain_model_event_12,tt_content_3"
     */
    private function renameRecordReferences(string $records): string
    {
        $items = array_map(static function (string $item): string {
            if (preg_match('/^(tx_cal_[a-z_]+)_(\d+)$/', trim($item), $matches) && isset(LegacySchema::RECORD_TABLES[$matches[1]])) {
                return LegacySchema::RECORD_TABLES[$matches[1]] . '_' . $matches[2];
            }
            return $item;
        }, explode(',', $records));
        return implode(',', $items);
    }

    private function migrateBackendGroups(): void
    {
        $connection = $this->legacySchema->getConnection('be_groups');
        $groups = $this->findBackendGroups();
        foreach ($groups as $uid => $fields) {
            $connection->update('be_groups', $fields, ['uid' => $uid]);
        }
        if ($groups !== []) {
            $this->write(sprintf('be_groups: permissions of %d backend user groups updated', count($groups)));
        }
    }

    /**
     * @return array<int, array<string, string>> uid => changed fields
     */
    private function findBackendGroups(): array
    {
        $columns = array_filter(
            ['tables_select', 'tables_modify', 'non_exclude_fields', 'explicit_allowdeny'],
            fn(string $column): bool => $this->legacySchema->findColumn('be_groups', $column) === $column,
        );
        if ($columns === []) {
            return [];
        }
        $queryBuilder = $this->legacySchema->createQueryBuilder('be_groups');
        $queryBuilder->select('uid', ...$columns)->from('be_groups');
        $constraints = [];
        foreach ($columns as $column) {
            $constraints[] = $queryBuilder->expr()->like($column, $queryBuilder->createNamedParameter('%tx_cal_%'));
        }
        if (in_array('explicit_allowdeny', $columns, true)) {
            $constraints[] = $queryBuilder->expr()->like(
                'explicit_allowdeny',
                $queryBuilder->createNamedParameter('%list_type:' . self::PLUGIN . '%'),
            );
        }
        $rows = $queryBuilder->where($queryBuilder->expr()->or(...$constraints))->executeQuery()->fetchAllAssociative();

        $result = [];
        foreach ($rows as $row) {
            $fields = [];
            foreach ($columns as $column) {
                $value = (string)($row[$column] ?? '');
                $newValue = match ($column) {
                    'tables_select', 'tables_modify' => $this->renameTableList($value),
                    'non_exclude_fields' => $this->renameExcludeFields($value),
                    'explicit_allowdeny' => str_replace('tt_content:list_type:' . self::PLUGIN, 'tt_content:CType:' . self::PLUGIN, $value),
                };
                if ($newValue !== $value) {
                    $fields[$column] = $newValue;
                }
            }
            if ($fields !== []) {
                $result[(int)$row['uid']] = $fields;
            }
        }
        return $result;
    }

    private function renameTableList(string $value): string
    {
        $tables = array_map(
            static fn(string $table): string => LegacySchema::getTableNameMap()[trim($table)] ?? trim($table),
            array_filter(explode(',', $value), static fn(string $table): bool => trim($table) !== ''),
        );
        return implode(',', array_values(array_unique($tables)));
    }

    /**
     * "tx_cal_event:title" => "tx_cal_domain_model_event:title"
     */
    private function renameExcludeFields(string $value): string
    {
        $fields = array_map(static function (string $field): string {
            [$table, $column] = array_pad(explode(':', trim($field), 2), 2, '');
            $table = LegacySchema::getTableNameMap()[$table] ?? $table;
            if ($table === 'tx_cal_domain_model_event' && $column === 'category_id') {
                $column = 'categories';
            }
            return $column !== '' ? $table . ':' . $column : $table;
        }, array_filter(explode(',', $value), static fn(string $field): bool => trim($field) !== ''));
        return implode(',', array_values(array_unique($fields)));
    }

    private function migrateTemplates(): void
    {
        $connection = $this->legacySchema->getConnection('sys_template');
        $templates = $this->findTemplates();
        foreach ($templates as $uid => $includes) {
            $connection->update('sys_template', ['include_static_file' => $includes], ['uid' => $uid]);
        }
        if ($templates !== []) {
            $this->write(sprintf(
                'sys_template: static templates of %d TypoScript records replaced. Sites using site sets should use the set '
                . '"Calendar Base (cal)" instead.',
                count($templates),
            ));
        }
    }

    /**
     * @return array<int, string> uid => new include_static_file
     */
    private function findTemplates(): array
    {
        if ($this->legacySchema->findColumn('sys_template', 'include_static_file') === null) {
            return [];
        }
        $queryBuilder = $this->legacySchema->createQueryBuilder('sys_template');
        $rows = $queryBuilder->select('uid', 'include_static_file')
            ->from('sys_template')
            ->where($queryBuilder->expr()->like('include_static_file', $queryBuilder->createNamedParameter('%EXT:cal/Configuration/TypoScript/%')))
            ->executeQuery()
            ->fetchAllAssociative();
        $result = [];
        foreach ($rows as $row) {
            $value = (string)$row['include_static_file'];
            $includes = [];
            foreach (explode(',', $value) as $include) {
                $include = trim($include);
                $normalized = rtrim($include, '/') . '/';
                if (array_key_exists($normalized, self::STATIC_TEMPLATES)) {
                    $include = self::STATIC_TEMPLATES[$normalized];
                }
                if ($include !== null && $include !== '' && !in_array($include, $includes, true)) {
                    $includes[] = $include;
                }
            }
            $newValue = implode(',', $includes);
            if ($newValue !== $value) {
                $result[(int)$row['uid']] = $newValue;
            }
        }
        return $result;
    }

    private function write(string $message): void
    {
        $this->output?->writeln($message);
    }
}
