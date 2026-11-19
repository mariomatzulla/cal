<?php

declare(strict_types=1);

use MarioMatzulla\Cal\Task\ImportExternalCalendarTask;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// Scheduler task "Import external calendar" (only if EXT:scheduler is installed)
if (!ExtensionManagementUtility::isLoaded('scheduler')) {
    return;
}

$lll = 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:';

ExtensionManagementUtility::addTCAcolumns(
    'tx_scheduler_task',
    [
        ImportExternalCalendarTask::FIELD => [
            'label' => $lll . 'tx_scheduler_task.tx_cal_calendar',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_cal_domain_model_calendar',
                'foreign_table_where' => 'AND {#tx_cal_domain_model_calendar}.{#type} = 1 ORDER BY {#tx_cal_domain_model_calendar}.{#title}',
                'minitems' => 1,
                'maxitems' => 1,
                'default' => 0,
            ],
        ],
    ],
);

ExtensionManagementUtility::addRecordType(
    [
        'label' => $lll . 'tx_scheduler_task.importExternalCalendar',
        'description' => $lll . 'tx_scheduler_task.importExternalCalendar.description',
        'value' => ImportExternalCalendarTask::class,
        'icon' => 'cal-calendar-exturl',
        'group' => 'cal',
    ],
    '
        --div--;core.form.tabs:general,
            tasktype,
            task_group,
            description,
            ' . ImportExternalCalendarTask::FIELD . ',
        --div--;core.form.tabs:timing,
            --palette--;;execution,
        --div--;core.form.tabs:access,
            disable,
        --div--;core.form.tabs:extended,',
    [],
    '',
    'tx_scheduler_task',
);
