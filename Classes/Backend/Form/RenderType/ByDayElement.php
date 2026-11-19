<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Backend\Form\RenderType;

use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;

/**
 * Class ByDayElement
 */
class ByDayElement extends AbstractCalFormElementHelper
{
    /**
     * @return array
     */
    public function render(): array
    {

        $result = $this->initializeResultArray();

        $table = $this->data ['tableName'];
        $row = $this->data ['databaseRow'];

        $uid = $row ['uid'];
        $html = '';
        $dayRow = '';

        $row ['byday'] = implode(',', array_filter(explode(',', (string)$row ['byday']), fn($value) => $value != 'null'));

        $startDay = $this->getWeekStartDay($row ['pid']);

        $startdate = $row ['start_date'];
        $formatter = new \IntlDateFormatter(
            'en_US', // English Locale
            \IntlDateFormatter::FULL,
            \IntlDateFormatter::FULL,
            null,
            \IntlDateFormatter::GREGORIAN,
            'EEEEEE', // 2 Letters
        );

        switch ($row ['freq'] [0]) {
            case 'day':
                break;
            case 'week':
                $dayRow = $this->byDay_checkbox($startDay);
                if ($row ['byday'] == '') {
                    $row ['byday'] = mb_strtoupper((string)$formatter->format($startdate));
                }
                break;
            case 'month':
                $dayRow = $this->getByDayRow($startDay, $this->getEveryMonthText());
                $html = $this->byDay_select();
                if ($row ['byday'] == '') {
                    $row ['byday'] = 'MO,TU,WE,TH,FR,SA,SU';
                }
                break;
            case 'year':
                $dayRow = $this->getByDayRow($startDay, $this->getSelectedMonthText());
                $html = $this->byDay_select();
                if ($row ['byday'] == '') {
                    $row ['byday'] = 'MO,TU,WE,TH,FR,SA,SU';
                }
                break;
        }

        $out = [ ];
        $out [] = '<label class="form-label t3js-formengine-label">' . $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.byday_short') . '</label>';
        $out [] = '<div id="byday-container">';
        $out [] = '<input type="hidden" name="data[' . $table . '][' . $uid . '][byday]" id="data[' . $table . '][' . $uid . '][byday]" value="' . $row ['byday'] . '" />';
        $out [] = $dayRow;
        $out [] = '</div>';
        $out [] = $html;

        $result ['html'] = implode("\n", $out);
        $result['javaScriptModules'] = [
            JavaScriptModuleInstruction::create('@MarioMatzulla/Cal/RegisterByDayChangeListener.js'),
            JavaScriptModuleInstruction::create('@MarioMatzulla/Cal/RecurUI/ByDayUI.js'),
        ];

        return $result;
    }

    /**
     * @param mixed $startDay
     * @param mixed $endString
     * @return mixed
     */
    public function getByDayRow($startDay, $endString)
    {

        $html = '<div class="cal-row">';

        $html .= '<select class="form-control count byday-select-change-action" style="width: 48px; display: inline;">';
        $html .= '<option value="" />';
        foreach ($this->getCountsArray() as $value => $label) {
            $html .= '<option value="' . $value . '">' . $label . '</option>';
        }
        $html .= '</select>&nbsp;&nbsp;';

        $html .= '<select class="form-control day byday-select-change-action" style="width: 48px; display: inline;">';
        $html .= '<option value="" />';

        foreach ($this->getWeekdaysArray($startDay) as $value => $label) {
            $html .= '<option value="' . $value . '">' . $label . '</option>';
        }
        $html .= '</select>';

        $html .= ' ' . $endString;
        $html .= '<a href="#" class="remove-byday">' . $this->getGarbageIcon() . $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.remove_recurrence') . '</a>';
        $html .= '</div>';

        return $this->removeNewlines($html);
    }

    /**
     * @param mixed $startDay
     * @return string
     */
    public function byDay_checkbox($startDay): string
    {

        $out = [ ];

        foreach ($this->getWeekdaysArray($startDay) as $value => $label) {
            $name = 'byday_' . $value;

            $out [] = '<div class="cal-row">';
            $out [] = '<input class="byday-change-action" style="padding: 0; margin: 0;" type="checkbox" name="' . $name . '" value="' . $value . '" />';
            $out [] = '<label style="padding-left: 2px;" for="' . $name . '">' . $label . '</label>';
            $out [] = '</div>';
        }

        return implode("\n", $out);
    }

    /**
     * @return string
     */
    public function byDay_select(): string
    {

        $out = [ ];
        $out [] = '<div style="padding: 5px 0"><a href="#" class="add-byday">' . $this->getNewIcon() . $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.add_recurrence') . '</a></div>';

        return implode("\n", $out);
    }
}
