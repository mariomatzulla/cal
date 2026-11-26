<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal;

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
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\TypoScript\TypoScriptStringFactory;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

/**
 * Settings of the calendar plugin: TypoScript merged with the FlexForm of the rendered content element.
 */
trait General
{
    /**
     * FlexForm field => [section in dontListenToFlexForm, path in the settings]
     */
    private const FLEXFORM_FIELDS = [
        'allowedViews' => ['general', 'view.allowedViews'],
        'weekStartDay' => ['general', 'view.weekStartDay'],
        'dayStart' => ['day', 'view.day.dayStart'],
        'dayEnd' => ['day', 'view.day.dayEnd'],
        'gridLength' => ['day', 'view.day.gridLength'],
        'eventViewPid' => ['event', 'view.event.eventViewPid'],
        'starttime' => ['list', 'view.list.starttime'],
        'endtime' => ['list', 'view.list.endtime'],
        'showAllImages' => ['list', 'view.list.event.card.showAllImages'],
        'maxEvents' => ['list', 'view.list.maxEvents'],
        'showIcsLinks' => ['ics', 'view.ics.showIcsLinks'],
        'categorySelection' => ['filters', 'view.category'],
        'categoryMode' => ['filters', 'view.categoryMode'],
        'calendarSelection' => ['filters', 'view.calendar'],
        'calendarMode' => ['filters', 'view.calendarMode'],
        'myTS' => ['general', ''],
    ];

    protected array $settings;

    public function setSettings(array $settings): void
    {
        $this->settings = $settings;
    }

    public function getSettings(): array
    {
        return $this->settings;
    }

    /**
     * @param array|null $contentElement the tt_content record of the rendered plugin. Its FlexForm
     *                                   is used, so every plugin has its own configuration.
     * @param int $pageId without content element (plugin rendered by TypoScript, e.g. the iCalendar page type):
     *                    the FlexForms of the calendar plugins on this page are used
     */
    public function initSettings(?array $contentElement = null, int $pageId = 0): void
    {
        $this->setSettings($this->getTyposcriptConfig());
        $flexForms = $contentElement !== null ? [(string)($contentElement['pi_flexform'] ?? '')] : $this->getFlexFormsOfPage($pageId);
        $flexFormValues = [];
        foreach ($flexForms as $flexForm) {
            $values = array_filter($this->getFlexform2ConfigArray($flexForm), static fn($value): bool => $value !== '' && $value !== null);
            $flexFormValues = array_replace_recursive($flexFormValues, $values);
        }
        $this->mergeSettingsWithFlexformValues($flexFormValues);
    }

    /**
     * FlexForms of the visible calendar plugins of a page (in the order of the page)
     *
     * @return list<string>
     */
    protected function getFlexFormsOfPage(int $pageId): array
    {
        if ($pageId <= 0) {
            return [];
        }
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tt_content');
        return array_map('strval', $queryBuilder->select('pi_flexform')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter('cal_controller')),
            )
            ->orderBy('sorting')
            ->executeQuery()
            ->fetchFirstColumn());
    }

    /**
     * Non-empty FlexForm values override the TypoScript settings, unless
     * - plugin.tx_cal.settings.dontListenToFlexForm = 1 (all fields) or
     * - plugin.tx_cal.settings.dontListenToFlexForm.<section>.<field> = 1 (single fields)
     */
    protected function mergeSettingsWithFlexformValues(array $flexForm): void
    {
        $ignore = $this->settings['dontListenToFlexForm'] ?? 0;
        if (!is_array($ignore) && (int)$ignore === 1) {
            return;
        }
        foreach (self::FLEXFORM_FIELDS as $field => [$section, $path]) {
            $value = $flexForm[$field] ?? null;
            if ($value === null || $value === '' || (is_array($ignore) && (int)($ignore[$section][$field] ?? 0) === 1)) {
                continue;
            }
            if ($field === 'myTS') {
                // TypoScript of the plugin, relative to plugin.tx_cal.settings
                $typoScriptTree = GeneralUtility::makeInstance(TypoScriptStringFactory::class)
                    ->parseFromStringWithIncludes('tx_cal_controller_flexform_TS', (string)$value);
                $this->settings = array_replace_recursive(
                    $this->settings,
                    GeneralUtility::makeInstance(TypoScriptService::class)->convertTypoScriptArrayToPlainArray($typoScriptTree->toArray()),
                );
                continue;
            }
            $this->settings = ArrayUtility::setValueByPath($this->settings, $path, $value, '.');
        }
    }

    /**
     * Settings of the plugin:
     * 1. plugin.tx_cal.settings (all cal plugins, provided by the site set / static template)
     * 2. plugin.tx_cal_controller.settings (only the plugin "Controller")
     * 3. legacy: settings directly below plugin.tx_cal_controller (cal < 4), still read but deprecated
     */
    protected function getTyposcriptConfig(): array
    {
        return self::buildSettings(GeneralUtility::makeInstance(TypoScriptService::class)->convertTypoScriptArrayToPlainArray(
            GeneralUtility::makeInstance(ConfigurationManagerInterface::class)
                ->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT)
        ));
    }

    /**
     * @param array $tsConf the full TypoScript as plain array
     */
    protected static function buildSettings(array $tsConf): array
    {
        $settings = (array)($tsConf['plugin']['tx_cal']['settings'] ?? []);
        $pluginConfiguration = (array)($tsConf['plugin']['tx_cal_controller'] ?? []);

        $legacySettings = self::getLegacyPluginSettings($pluginConfiguration);
        if ($legacySettings !== []) {
            trigger_error(
                'cal: settings below "plugin.tx_cal_controller" (' . implode(', ', array_keys($legacySettings)) . ') are deprecated, '
                . 'use "plugin.tx_cal.settings" (or "plugin.tx_cal_controller.settings") instead.',
                E_USER_DEPRECATED
            );
            $settings = array_replace_recursive($settings, $legacySettings);
        }

        return array_replace_recursive($settings, (array)($pluginConfiguration['settings'] ?? []));
    }

    /**
     * Settings of cal < 4 directly below plugin.tx_cal_controller, without the Extbase configuration
     * (settings, persistence, features, mvc, _LOCAL_LANG and the template paths of "view").
     */
    private static function getLegacyPluginSettings(array $pluginConfiguration): array
    {
        $legacy = array_diff_key($pluginConfiguration, array_flip(['settings', 'persistence', 'features', 'mvc', '_LOCAL_LANG', 'ignoreFlexFormSettingsIfEmpty']));
        if (isset($legacy['view']) && is_array($legacy['view'])) {
            $legacy['view'] = array_diff_key($legacy['view'], array_flip(['templateRootPaths', 'partialRootPaths', 'layoutRootPaths', 'pluginNamespace']));
            if ($legacy['view'] === []) {
                unset($legacy['view']);
            }
        }
        return $legacy;
    }

    /**
     * FlexForm XML => [field => value] of all sheets. Field names with dots (e.g. "settings.view.x")
     * become nested arrays.
     */
    private function getFlexform2ConfigArray(string $xml): array
    {
        $data = trim($xml) !== '' ? GeneralUtility::xml2array($xml) : [];
        $result = [];
        foreach ((is_array($data) ? (array)($data['data'] ?? []) : []) as $sheet) {
            foreach ((array)($sheet['lDEF'] ?? []) as $field => $value) {
                $result = ArrayUtility::setValueByPath($result, (string)$field, is_array($value) ? ($value['vDEF'] ?? '') : '', '.');
            }
        }
        return $result;
    }
}
