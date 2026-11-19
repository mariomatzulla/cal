<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Backend\TCA;

/**
 * This file is part of the TYPO3 extension Calendar Base (cal).
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 extension Calendar Base (cal) project - inspiring people to share!
 */
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * label_userFunc implementations for the backend record titles.
 */
class Labels
{
    private const LLL = 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:';

    public function getEventRecordLabel(array &$params): void
    {
        $table = $params['table'] ?? '';
        if ($table !== 'tx_cal_domain_model_event' && $table !== 'tx_cal_domain_model_exception_event') {
            return;
        }
        $record = $this->getRecord($table, $params['row'] ?? []);
        if ($record === null) {
            return;
        }

        $label = $this->formatDate((int)($record['start_date'] ?? 0));
        $showTimes = (bool)(GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('cal', 'showTimes') ?? false);
        if ($showTimes && empty($record['allday']) && $table === 'tx_cal_domain_model_event') {
            // gmdate is ok, start_time only holds the seconds of one day
            $label .= ' ' . gmdate($this->getTimeFormat(), (int)($record['start_time'] ?? 0));
        }

        $params['title'] = trim($label . ': ' . ($record['title'] ?? ''));
    }

    public function getAttendeeRecordLabel(array &$params): void
    {
        if (($params['table'] ?? '') !== 'tx_cal_domain_model_attendee') {
            return;
        }
        $record = $this->getRecord($params['table'], $params['row'] ?? []);
        if ($record === null) {
            return;
        }

        $label = (string)($record['email'] ?? '');
        if (!empty($record['fe_user_id'])) {
            $feUser = BackendUtility::getRecord('fe_users', (int)$record['fe_user_id']);
            if ($feUser !== null) {
                $label = ($feUser['name'] ?? '') !== '' ? $feUser['name'] : $feUser['username'];
            }
        }
        $attendance = $this->translate('tx_cal_attendee.attendance.' . ($record['attendance'] ?? ''));
        $params['title'] = $label . ' (' . $attendance . ' -> ' . ($record['status'] ?? '') . ')';
    }

    public function getMonitoringRecordLabel(array &$params): void
    {
        if (($params['table'] ?? '') !== 'tx_cal_domain_model_fe_user_event_monitor_mm') {
            return;
        }
        $record = $this->getRecord($params['table'], $params['row'] ?? []);
        if ($record === null) {
            return;
        }

        $label = '';
        $foreignUid = (int)($record['uid_foreign'] ?? 0);
        switch ($record['tablenames'] ?? '') {
            case 'fe_users':
                $feUser = BackendUtility::getRecord('fe_users', $foreignUid);
                $label = $feUser === null ? '' : (($feUser['name'] ?? '') !== '' ? $feUser['name'] : $feUser['username']);
                break;
            case 'fe_groups':
                $feGroup = BackendUtility::getRecord('fe_groups', $foreignUid);
                $label = (string)($feGroup['title'] ?? '');
                break;
            case 'tx_cal_domain_model_unknown_users':
                $unknownUser = BackendUtility::getRecord('tx_cal_domain_model_unknown_users', $foreignUid);
                $label = (string)($unknownUser['email'] ?? '');
                break;
        }

        $params['title'] = $label . ' (' . $this->translate('tx_cal_fe_user_event.offset') . ': ' . (int)($record['offset'] ?? 0) . ')';
    }

    public function getDeviationRecordLabel(array &$params): void
    {
        if (($params['table'] ?? '') !== 'tx_cal_domain_model_eventdeviation') {
            return;
        }
        $record = $this->getRecord($params['table'], $params['row'] ?? []);
        if ($record === null) {
            return;
        }

        $label = $this->translate('tx_cal_event.deviation') . ': ';
        if (!empty($record['orig_start_date'])) {
            $label .= $this->formatDate((int)$record['orig_start_date']);
        }
        if (!empty($record['orig_start_time'])) {
            $label .= ' (' . gmdate($this->getTimeFormat(), (int)$record['orig_start_time']) . ')';
        }
        $params['title'] = $label;
    }

    private function getRecord(string $table, array $row): ?array
    {
        $uid = (int)($row['uid'] ?? 0);
        if ($uid <= 0) {
            // New records in FormEngine have no uid yet, use what is there
            return $row !== [] ? $row : null;
        }
        return BackendUtility::getRecord($table, $uid);
    }

    private function formatDate(int $timestamp): string
    {
        if ($timestamp === 0) {
            return '';
        }
        $format = (string)($GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'] ?? 'd.m.Y');
        return (new \DateTimeImmutable('@' . $timestamp))
            ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
            ->format($format);
    }

    private function getTimeFormat(): string
    {
        return (string)($GLOBALS['TYPO3_CONF_VARS']['SYS']['hhmm'] ?? 'H:i');
    }

    private function translate(string $key): string
    {
        $languageService = $GLOBALS['LANG'] ?? null;
        if (!$languageService instanceof LanguageService) {
            return $key;
        }
        return $languageService->sL(self::LLL . $key) ?: $key;
    }
}
