<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// Static templates for sites that do not use site sets (Configuration/Sets/*).
ExtensionManagementUtility::addStaticFile('cal', 'Configuration/TypoScript', 'Calendar Base (cal)');
ExtensionManagementUtility::addStaticFile('cal', 'Configuration/TypoScript/Styles', 'Calendar Base (cal): default styles');
