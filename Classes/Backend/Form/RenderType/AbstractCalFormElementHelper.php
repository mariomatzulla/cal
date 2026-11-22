<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Backend\Form\RenderType;

use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class ElementHelper
 */
abstract class AbstractCalFormElementHelper extends AbstractFormElement
{
    public function __construct(private IconFactory $iconFactory) {}
    /**
     * @return string
     */
    public function getGarbageIcon(): string
    {

        return $this->iconFactory
        ->getIcon('actions-delete', IconSize::SMALL)
        ->render();
    }

    /**
     * @return string
     */
    public function getNewIcon(): string
    {
        return $this->iconFactory
        ->getIcon('actions-add', IconSize::SMALL)
        ->render();
    }

    /**
     * @return string
     */
    public function getEveryMonthText(): string
    {

        return $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.recurs_every_month');
    }

    /**
     * @return string
     */
    public function getSelectedMonthText(): string
    {

        return $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.recurs_selected_months');
    }

    /**
     * First weekday of the weekday lists: the site setting plugin.tx_cal.settings.view.weekStartDay
     * of the site of the page (default Monday)
     *
     * @param mixed $pageID
     * @return string "MO" or "SU"
     */
    public function getWeekStartDay($pageID): string
    {
        try {
            $site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByPageId((int)$pageID);
            $value = (string)$site->getSettings()->get('plugin.tx_cal.settings.view.weekStartDay', 'monday');
        } catch (SiteNotFoundException) {
            $value = 'monday';
        }
        return strtolower(trim($value)) === 'sunday' ? 'SU' : 'MO';
    }

    /**
     * @return array
     */
    public function getCountsArray(): array
    {

        return [
            1 => $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.byday_count_first'),
            2 => $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.byday_count_second'),
            3 => $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.byday_count_third'),
            4 => $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.byday_count_fourth'),
            5 => $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.byday_count_fifth'),
            - 3 => $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.byday_count_thirdtolast'),
            - 2 => $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.byday_count_secondtolast'),
            - 1 => $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.byday_count_last'),
        ];
    }

    /**
     * @param mixed $startDay
     * @return array
     */
    public function getWeekdaysArray($startDay): array
    {

        $weekdays = [ ];

        if ($startDay === 'SU') {
            $weekdays ['SU'] = $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.byday_sunday');
        }

        $weekdays ['MO'] = $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.byday_monday');
        $weekdays ['TU'] = $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.byday_tuesday');
        $weekdays ['WE'] = $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.byday_wednesday');
        $weekdays ['TH'] = $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.byday_thursday');
        $weekdays ['FR'] = $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.byday_friday');
        $weekdays ['SA'] = $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.byday_saturday');

        if ($startDay !== 'SU') {
            $weekdays ['SU'] = $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.byday_sunday');
        }

        return $weekdays;
    }

    /**
     * @return array
     */
    public function getMonthsArray(): array
    {

        return [
            1 => $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.bymonth_january'),
            2 => $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.bymonth_february'),
            3 => $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.bymonth_march'),
            4 => $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.bymonth_april'),
            5 => $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.bymonth_may'),
            6 => $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.bymonth_june'),
            7 => $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.bymonth_july'),
            8 => $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.bymonth_august'),
            9 => $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.bymonth_september'),
            10 => $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.bymonth_october'),
            11 => $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.bymonth_november'),
            12 => $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.bymonth_december'),
        ];
    }

    /**
     * @param mixed $input
     * @return mixed
     */
    public static function removeNewlines($input)
    {

        return str_replace([
            "\r\n",
            "\n",
            "\r",
            "\t",
        ], '', (string)$input);
    }
}
