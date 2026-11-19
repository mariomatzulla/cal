<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Backend\Form;

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
use TYPO3\CMS\Backend\Form\FormDataProvider\EvaluateDisplayConditions;
use TYPO3\CMS\Backend\Form\FormDataProviderInterface;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Shows events imported from an external calendar (isTemp = 1) read-only in the backend form.
 * Saving is additionally blocked in CalendarDataHandlerHook.
 */
final class TempEventReadOnlyDataProvider implements FormDataProviderInterface
{
    public static function register(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['formDataGroup']['tcaDatabaseRecord'][self::class] = [
            'depends' => [
                EvaluateDisplayConditions::class,
            ],
        ];
    }

    public function addData(array $result): array
    {
        if ($result['tableName'] !== 'tx_cal_domain_model_event'
            || $result['command'] !== 'edit'
            || empty($result['databaseRow']['isTemp'])
        ) {
            return $result;
        }

        foreach ($result['processedTca']['columns'] as &$fieldConfig) {
            $fieldConfig['config']['readOnly'] = true;
        }
        unset($fieldConfig);

        $languageService = $GLOBALS['LANG'] ?? null;
        $message = $languageService instanceof LanguageService
            ? $languageService->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.isTemp.info')
            : '';
        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class,
            $message !== '' ? $message : 'This event was imported from an external calendar and cannot be changed.',
            '',
            ContextualFeedbackSeverity::INFO,
        );
        GeneralUtility::makeInstance(FlashMessageService::class)->getMessageQueueByIdentifier()->enqueue($flashMessage);

        return $result;
    }
}
