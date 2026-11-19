<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal;

trait General
{
    /**
     * @var array
     */
    protected $plugins = [
        'cal_controller',
    ];

    /**
     * @var \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface
     */
    protected $configManager;

    /**
     * @var \TYPO3\CMS\Core\TypoScript\TypoScriptService
     */
    protected $typoScriptService;

    /**
     * @var array
     */
    protected array $settings;

    /**
     * @var array
     */
    protected $configuration;

    /* ======================================================================================= */
    /* P R O P E R T I E S                                                                     */
    /* ======================================================================================= */
    /**
     * Sets the settings
     *
     * @param array $settings
     */
    public function setSettings($settings)
    {
        $this->settings = $settings;
    }

    /**
     * @param array|null $contentElement the tt_content record of the rendered plugin. Its FlexForm
     *                                   is used, so every plugin has its own configuration.
     *                                   Without it the plugins of the settings page are read.
     */
    public function initSettings(?array $contentElement = null)
    {
        $this->setSettings($this->getTyposcriptConfig());
        if (empty($this->settings['dontListenToFlexForm'])) {
            // get flexform configuration
            $flexformSettings = isset($contentElement['pi_flexform'])
                ? $this->getFlexform2ConfigArray((string)$contentElement['pi_flexform'])
                : $this->getFlexformSettings(0);
            $this->mergeSettingsWithFlexformValues($flexformSettings);
        }
    }
    /**
     * Returns the settings
     *
     * @return array	the typoscript settings
     */
    public function getSettings()
    {
        return $this->settings;
    }

    /**
     * Sets the configurationManager
     *
     * @param \TYPO3\CMS\Extbase\Configuration\ConfigurationManager $configurationManager
     */
    public function setConfigurationManager($configurationManager)
    {
        $this->configManager = $configurationManager;
    }

    /* ======================================================================================= */
    /* P U B L I C   M E T H O D S                                                             */
    /* ======================================================================================= */
    /**
     * Read plugins on current page and add's the given flexform configuration
     *
     * @param	int					$uid				a specific plugin uid
     * @return	array
     */
    public function getFlexformSettings(int $uid): array
    {
        // get flexform configuration
        $def = $this->getPi_Flexforms($uid);
        $flexformSettings = [];
        foreach ($def as $data) {
            // settings
            $flexformArray = $this->getFlexform2ConfigArray($data['pi_flexform']);
            if (!empty($flexformArray)) {
                $flexformSettings = array_replace_recursive($flexformSettings, $flexformArray);

            }
        }
        return $flexformSettings;
    }

    protected function mergeSettingsWithFlexformValues($piFlexForm)
    {

        if (! isset($this->settings ['dontListenToFlexForm'] ['general'] ['calendarName']) || $this->settings ['dontListenToFlexForm'] ['general'] ['calendarName'] !== 1) {
            self::updateIfNotEmpty($this->settings ['calendarName'], $piFlexForm ['calendarName']);
        }
        if (! isset($this->settings ['dontListenToFlexForm'] ['general'] ['allowSubscribe']) || $this->settings ['dontListenToFlexForm'] ['general'] ['allowSubscribe'] !== 1) {
            self::updateIfNotEmpty($this->settings ['allowSubscribe'], $piFlexForm ['subscription'] == 1 ? 1 : - 1);
        }
        if (! isset($this->settings ['dontListenToFlexForm'] ['general'] ['subscribeFeUser']) || $this->settings ['dontListenToFlexForm'] ['general'] ['subscribeFeUser'] !== 1) {
            self::updateIfNotEmpty($this->settings ['subscribeFeUser'], $piFlexForm ['subscription'] == 2 ? 1 : - 1);
        }
        if (! isset($this->settings ['dontListenToFlexForm'] ['general'] ['subscribeWithCaptcha']) || $this->settings ['dontListenToFlexForm'] ['general'] ['subscribeWithCaptcha'] !== 1) {
            self::updateIfNotEmpty($this->settings ['subscribeWithCaptcha'], $piFlexForm ['subscribeWithCaptcha']);
        }
        if (! isset($this->settings ['dontListenToFlexForm'] ['general'] ['allowedViews']) || $this->settings ['dontListenToFlexForm'] ['general'] ['allowedViews'] !== 1) {
            self::updateIfNotEmpty($this->settings ['view'] ['allowedViews'], $piFlexForm ['allowedViews']);
        }
        if (! isset($this->settings ['dontListenToFlexForm'] ['general'] ['calendarDistance']) || $this->settings ['dontListenToFlexForm'] ['general'] ['calendarDistance'] !== 1) {
            self::updateIfNotEmpty($this->settings ['view'] ['calendar'] ['nearbyDistance'], (int)($piFlexForm ['calendarDistance']));
        }

        if (! isset($this->settings ['dontListenToFlexForm'] ['day'] ['dayViewPid']) || $this->settings ['dontListenToFlexForm'] ['day'] ['dayViewPid'] !== 1) {
            self::updateIfNotEmpty($this->settings ['view'] ['day'] ['dayViewPid'], $piFlexForm ['dayViewPid']);
        }
        if (! isset($this->settings ['dontListenToFlexForm'] ['day'] ['dayStart']) || $this->settings ['dontListenToFlexForm'] ['day'] ['dayStart'] !== 1) {
            self::updateIfNotEmpty($this->settings ['view'] ['day'] ['dayStart'], $piFlexForm ['dayStart']);
        }
        if (! isset($this->settings ['dontListenToFlexForm'] ['day'] ['dayEnd']) || $this->settings ['dontListenToFlexForm'] ['day'] ['dayEnd'] !== 1) {
            self::updateIfNotEmpty($this->settings ['view'] ['day'] ['dayEnd'], $piFlexForm ['dayEnd']);
        }
        if (! isset($this->settings ['dontListenToFlexForm'] ['day'] ['gridLength']) || $this->settings ['dontListenToFlexForm'] ['day'] ['gridLength'] !== 1) {
            self::updateIfNotEmpty($this->settings ['view'] ['day'] ['gridLength'], $piFlexForm ['gridLength']);
        }
        if (! isset($this->settings ['dontListenToFlexForm'] ['day'] ['weekViewPid']) || $this->settings ['dontListenToFlexForm'] ['day'] ['weekViewPid'] !== 1) {
            self::updateIfNotEmpty($this->settings ['view'] ['week'] ['weekViewPid'], $piFlexForm ['weekViewPid']);
        }

        if (! isset($this->settings ['dontListenToFlexForm'] ['month'] ['monthViewPid']) || $this->settings ['dontListenToFlexForm'] ['month'] ['monthViewPid'] !== 1) {
            self::updateIfNotEmpty($this->settings ['view'] ['month'] ['monthViewPid'], $piFlexForm ['monthViewPid']);
        }
        if (! isset($this->settings ['dontListenToFlexForm'] ['month'] ['monthMakeMiniCal']) || $this->settings ['dontListenToFlexForm'] ['month'] ['monthMakeMiniCal'] !== 1) {
            self::updateIfNotEmpty($this->settings ['view'] ['month'] ['monthMakeMiniCal'], $piFlexForm ['monthMakeMiniCal']);
        }
        if (! isset($this->settings ['dontListenToFlexForm'] ['month'] ['monthShowListView']) || $this->settings ['dontListenToFlexForm'] ['month'] ['monthShowListView'] !== 1) {
            self::updateIfNotEmpty($this->settings ['view'] ['month'] ['showListInMonthView'], $piFlexForm ['monthShowListView']);
        }

        if (! isset($this->settings ['dontListenToFlexForm'] ['year'] ['yearViewPid']) || $this->settings ['dontListenToFlexForm'] ['year'] ['yearViewPid'] !== 1) {
            self::updateIfNotEmpty($this->settings ['view'] ['year'] ['yearViewPid'], $piFlexForm ['yearViewPid']);
        }

        if (! isset($this->settings ['dontListenToFlexForm'] ['event'] ['eventViewPid']) || $this->settings ['dontListenToFlexForm'] ['event'] ['eventViewPid'] !== 1) {
            self::updateIfNotEmpty($this->settings ['view'] ['event'] ['eventViewPid'], $piFlexForm ['eventViewPid']);
        }
        if (! isset($this->settings ['dontListenToFlexForm'] ['event'] ['isPreview']) || $this->settings ['dontListenToFlexForm'] ['event'] ['isPreview'] !== 1) {
            self::updateIfNotEmpty($this->settings ['view'] ['event'] ['isPreview'], $piFlexForm ['isPreview']);
        }

        if (! isset($this->settings ['dontListenToFlexForm'] ['list'] ['listViewPid']) || $this->settings ['dontListenToFlexForm'] ['list'] ['listViewPid'] !== 1) {
            self::updateIfNotEmpty($this->settings ['view'] ['list'] ['listViewPid'], $piFlexForm ['listViewPid']);
        }
        if (! isset($this->settings ['dontListenToFlexForm'] ['list'] ['starttime']) || $this->settings ['dontListenToFlexForm'] ['list'] ['starttime'] !== 1) {
            self::updateIfNotEmpty($this->settings ['view'] ['list'] ['starttime'], $piFlexForm ['starttime']);
        }
        if (! isset($this->settings ['dontListenToFlexForm'] ['list'] ['endtime']) || $this->settings ['dontListenToFlexForm'] ['list'] ['endtime'] !== 1) {
            self::updateIfNotEmpty($this->settings ['view'] ['list'] ['endtime'], $piFlexForm ['endtime']);
        }
        if (! isset($this->settings ['dontListenToFlexForm'] ['list'] ['showAllImages']) || $this->settings ['dontListenToFlexForm'] ['list'] ['showAllImages'] !== 1) {
            self::updateIfNotEmpty($this->settings ['view'] ['list'] ['event'] ['card'] ['showAllImages'], $piFlexForm ['showAllImages'] ?? null);
        }
        if (! isset($this->settings ['dontListenToFlexForm'] ['list'] ['maxEvents']) || $this->settings ['dontListenToFlexForm'] ['list'] ['maxEvents'] !== 1) {
            self::updateIfNotEmpty($this->settings ['view'] ['list'] ['maxEvents'], $piFlexForm ['maxEvents']);
        }
        if (! isset($this->settings ['dontListenToFlexForm'] ['list'] ['maxRecurringEvents']) || $this->settings ['dontListenToFlexForm'] ['list'] ['maxRecurringEvents'] !== 1) {
            self::updateIfNotEmpty($this->settings ['view'] ['list'] ['maxRecurringEvents'], $piFlexForm ['maxRecurringEvents']);
        }
        if (! isset($this->settings ['dontListenToFlexForm'] ['list'] ['usePageBrowser']) || $this->settings ['dontListenToFlexForm'] ['list'] ['usePageBrowser'] !== 1) {
            self::updateIfNotEmpty($this->settings ['view'] ['list'] ['pageBrowser'] ['usePageBrowser'], $piFlexForm ['usePageBrowser']);
        }
        if (! isset($this->settings ['dontListenToFlexForm'] ['list'] ['recordsPerPage']) || $this->settings ['dontListenToFlexForm'] ['list'] ['recordsPerPage'] !== 1) {
            self::updateIfNotEmpty($this->settings ['view'] ['list'] ['pageBrowser'] ['recordsPerPage'], $piFlexForm ['recordsPerPage']);
        }
        if (! isset($this->settings ['dontListenToFlexForm'] ['list'] ['pagesCount']) || $this->settings ['dontListenToFlexForm'] ['list'] ['pagesCount'] !== 1) {
            self::updateIfNotEmpty($this->settings ['view'] ['list'] ['pageBrowser'] ['pagesCount'], $piFlexForm ['pagesCount']);
        }

        if (! isset($this->settings ['dontListenToFlexForm'] ['ics'] ['showIcsLinks']) || $this->settings ['dontListenToFlexForm'] ['ics'] ['showIcsLinks'] !== 1) {
            self::updateIfNotEmpty($this->settings ['view'] ['ics'] ['showIcsLinks'], $piFlexForm ['showIcsLinks']);
        }

        if (! isset($this->settings ['dontListenToFlexForm'] ['other'] ['showLogin']) || $this->settings ['dontListenToFlexForm'] ['other'] ['showLogin'] !== 1) {
            self::updateIfNotEmpty($this->settings ['view'] ['other'] ['showLogin'], $piFlexForm ['showLogin']);
        }
        if (! isset($this->settings ['dontListenToFlexForm'] ['other'] ['showSearch']) || $this->settings ['dontListenToFlexForm'] ['other'] ['showSearch'] !== 1) {
            self::updateIfNotEmpty($this->settings ['view'] ['other'] ['showSearch'], $piFlexForm ['showSearch']);
        }
        if (! isset($this->settings ['dontListenToFlexForm'] ['other'] ['showJumps']) || $this->settings ['dontListenToFlexForm'] ['other'] ['showJumps'] !== 1) {
            self::updateIfNotEmpty($this->settings ['view'] ['other'] ['showJumps'], $piFlexForm ['showJumps']);
        }
        if (! isset($this->settings ['dontListenToFlexForm'] ['other'] ['showCalendarSelection']) || $this->settings ['dontListenToFlexForm'] ['other'] ['showCalendarSelection'] !== 1) {
            self::updateIfNotEmpty($this->settings ['view'] ['other'] ['showCalendarSelection'], $piFlexForm ['showCalendarSelection']);
        }
        if (! isset($this->settings ['dontListenToFlexForm'] ['other'] ['showCategorySelection']) || $this->settings ['dontListenToFlexForm'] ['other'] ['showCategorySelection'] !== 1) {
            self::updateIfNotEmpty($this->settings ['view'] ['other'] ['showCategorySelection'], $piFlexForm ['showCategorySelection']);
        }
        if (! isset($this->settings ['dontListenToFlexForm'] ['other'] ['showTomorrowEvents']) || $this->settings ['dontListenToFlexForm'] ['other'] ['showTomorrowEvents'] !== 1) {
            self::updateIfNotEmpty($this->settings ['view'] ['other'] ['showTomorrowEvents'], $piFlexForm ['showTomorrowEvents']);
        }

        if (! isset($this->settings ['dontListenToFlexForm'] ['filters'] ['categorySelection']) || $this->settings ['dontListenToFlexForm'] ['filters'] ['categorySelection'] !== 1) {
            self::updateIfNotEmpty($this->settings ['view'] ['category'], $piFlexForm ['categorySelection']);
        }
        if (! isset($this->settings ['dontListenToFlexForm'] ['filters'] ['categoryMode']) || $this->settings ['dontListenToFlexForm'] ['filters'] ['categoryMode'] !== 1) {
            self::updateIfNotEmpty($this->settings ['view'] ['categoryMode'], $piFlexForm ['categoryMode']);
        }
        if (! isset($this->settings ['dontListenToFlexForm'] ['filters'] ['calendarSelection']) || $this->settings ['dontListenToFlexForm'] ['filters'] ['calendarSelection'] !== 1) {
            self::updateIfNotEmpty($this->settings ['view'] ['calendar'], $piFlexForm ['calendarSelection']);
        }
        if (! isset($this->settings ['dontListenToFlexForm'] ['filters'] ['calendarMode']) || $this->settings ['dontListenToFlexForm'] ['filters'] ['calendarMode'] !== 1) {
            self::updateIfNotEmpty($this->settings ['view'] ['calendarMode'], $piFlexForm ['calendarMode']);
        }

        if ($piFlexForm ['myTS']) {
            $typoScriptStringFactory = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\TypoScript\TypoScriptStringFactory::class);
            // Parse the new Typoscript
            $typoScriptTree = $typoScriptStringFactory->parseFromStringWithIncludes('tx_cal_controller_flexform_TS', $piFlexForm ['myTS']);
            $tsConf = $this->typoScriptService->convertTypoScriptArrayToPlainArray($typoScriptTree->toArray());
            $this->settings = array_replace_recursive($this->settings, $tsConf);
        }
    }

    /**
     * @param mixed $confVar
     * @param mixed $newConfVar
     */
    protected static function updateIfNotEmpty(&$confVar, $newConfVar)
    {

        // Values missing in the FlexForm (null) or left empty must not override TypoScript
        if ($newConfVar !== null && $newConfVar !== '') {
            $confVar = $newConfVar;
        }
    }

    /* ======================================================================================= */
    /* P R O T E C T E D   M E T H O D S                                                       */
    /* ======================================================================================= */
    /**
     * Settings of the plugin:
     * 1. plugin.tx_cal.settings (all cal plugins, provided by the site set / static template)
     * 2. plugin.tx_cal_controller.settings (only the plugin "Controller")
     * 3. legacy: settings directly below plugin.tx_cal_controller (cal < 4), still read but deprecated
     */
    protected function getTyposcriptConfig()
    {
        // get object- and configration manager / instantiate services
        $this->configManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::class);
        $this->typoScriptService = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\TypoScript\TypoScriptService::class);

        $tsConf = $this->typoScriptService->convertTypoScriptArrayToPlainArray(
            $this->configManager->getConfiguration(\TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT)
        );
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
     * Select tt_content's pi_flexforms of "tx_cal" and "maagitproduct_checkout" elements on current page
     *
     * @param	int							$uid				a specific plugin uid
     * @return	array											the flexform values
     */
    protected function getPi_Flexforms(int $uid)
    {
        // get relevant pid
        $pid = $this->getSettingsPid('', $uid);

        // get flexform settings from the maagitproduct plugins of given page
        $queryBuilder = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ConnectionPool::class)->getQueryBuilderForTable('tt_content');
        $queryBuilder
            ->select('tt_content.*')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq(
                    'tt_content.pid',
                    $queryBuilder->createNamedParameter($pid, \TYPO3\CMS\Core\Database\Connection::PARAM_INT),
                ),
                $queryBuilder->expr()->in(
                    'tt_content.CType',
                    $queryBuilder->createNamedParameter($this->plugins, \TYPO3\CMS\Core\Database\Connection::PARAM_STR_ARRAY),
                ),
            );
        if (!empty($uid)) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq(
                    'tt_content.uid',
                    $queryBuilder->createNamedParameter($uid, \TYPO3\CMS\Core\Database\Connection::PARAM_INT),
                ),
            );
        }
        $records = $queryBuilder
            ->executeQuery()
            ->fetchAllAssociative();
        return ($records !== false) ? $records : [];
    }

    /* ======================================================================================= */
    /* P R I V A T E   M E T H O D S                                                           */
    /* ======================================================================================= */
    /**
     * Convert given flexform value to array
     *
     * @param	$xml						string				the pi_flexform as xml string
     * @return								array				the flexform data as config array
     */
    private function getFlexform2ConfigArray($xml)
    {
        $flexformData = [];
        try {
            if (!empty($xml)) {
                $xmlArray = \TYPO3\CMS\Core\Utility\GeneralUtility::xml2array($xml);
                foreach ($xmlArray as $sheets) {
                    foreach ($sheets as $elements) {
                        foreach ($elements as $fields) {
                            foreach ($fields as $key => $value) {
                                $temp = $this->getFFValue($key, $value['vDEF'], []);
                                $flexformData = array_merge_recursive($flexformData, $temp);
                            }
                        }
                    }
                }
            }
        } catch (\Exception $ex) {
        }
        return $flexformData;
    }

    /**
     * Get the flexform value as key => value pair.
     * Recursive function, for exploding keys with subkeys in a array structure
     *
     * @param	$key						string				the key from pi_flexform (can be key with subkey, e.g. settings.delivery.method)
     * @param	$value						string				the value from pi_flexform
     * @param	$array						array				the parent array for creating recursively array structure
     * @param	$delimiter					string				the delimiter of "keys with subkeys", default '.'
     * @param	$key						string				the key from pi_flexform (can be key with subkey, e.g. settings.delivery.method)
     * @return								array				the flexform data as config array
     */
    private function getFFValue($key, $value, $array, $delimiter = '.')
    {
        $key = (string)$key;
        if (!str_contains($key, $delimiter)) {
            $array = [$key => $value];
            return $array;
        }

        $segment = substr($key, 0, (int)strpos($key, $delimiter));
        $newKey = substr($key, (int)strpos($key, $delimiter) + 1);
        $array = [$segment => $this->getFFValue($newKey, $value, $array)];
        return $array;

    }

    /**
     * Get pid with the plugin settings
     * (e.g., if plugin is not on the current page)
     *
     * @param	string				$plugin			the plugin name to search
     * @param	int					$uid			a specific plugin uid
     * @return						int				the pid with the plugin settings
     */
    private function getSettingsPid($plugin, int $uid)
    {
        // initialization
        $queryBuilder = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ConnectionPool::class)->getQueryBuilderForTable('tt_content');
        $plugin = ($plugin != null) ? $plugin : $this->getCurrentPlugin();
        $pid = null;

        // second attention, get pid from current plugin
        if ($plugin != null) {
            $queryBuilder
            ->select('tt_content.pid')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq(
                    'tt_content.CType',
                    $queryBuilder->createNamedParameter($plugin, \TYPO3\CMS\Core\Database\Connection::PARAM_STR),
                ),
            );
            $records = $queryBuilder->executeQuery()->fetchAllAssociative();
            $pid = (!empty($records)) ? $records[0]['pid'] : $pid;
        }
        // third attention, get pid from given plugin uid
        if ($uid > 0) {
            $queryBuilder
                ->select('tt_content.pid')
                ->from('tt_content')
                ->where(
                    $queryBuilder->expr()->eq(
                        'tt_content.uid',
                        $queryBuilder->createNamedParameter($uid, \TYPO3\CMS\Core\Database\Connection::PARAM_INT),
                    ),
                );
            $records = $queryBuilder->executeQuery()->fetchAllAssociative();
            $pid = (!empty($records)) ? $records[0]['pid'] : $pid;
        }

        // fourth attention, get pid from typoscript settings
        $pid = (isset($this->settings['pageid']) && !empty($this->settings['pageid'])) ? $this->settings['pageid'] : $pid;

        // return pid
        return $pid;
    }

    /**
     * Get url parameter "controller" and return current plugin name
     *
     * @return						string				the current plugin name
     */
    private function getCurrentPlugin()
    {
        return 'cal_controller';
    }
}
