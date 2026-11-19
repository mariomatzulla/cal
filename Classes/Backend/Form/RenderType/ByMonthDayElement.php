<?php

declare(strict_types=1)
;

namespace MarioMatzulla\Cal\Backend\Form\RenderType;

use MarioMatzulla\Cal\Model\CalendarDateTime;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;

/**
 * Class ByMonthDayElement
 */
class ByMonthDayElement extends AbstractCalFormElementHelper
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

        $row ['bymonthday'] = implode(',', array_filter(explode(',', (string)$row ['bymonthday']), fn($value) => $value != 'null'));

        $startdate = new CalendarDateTime();
        $startdate->setTimestamp($row ['start_date']->getTimestamp());

        if ($row ['bymonthday'] == '') {
            $row ['bymonthday'] = $startdate->getDay();
        }

        switch ($row ['freq'] [0]) {
            case 'week':
                $dayRow = $this->getByMonthDayRow($this->getEveryMonthText());
                $html = $this->byMonthDay_select();
                break;
            case 'month':
                $dayRow = $this->getByMonthDayRow($this->getEveryMonthText());
                $html = $this->byMonthDay_select();
                break;
            case 'year':
                $dayRow = $this->getByMonthDayRow($this->getSelectedMonthText());
                $html = $this->byMonthDay_select();
                break;
        }

        $out = [ ];
        $out [] = '<label class="form-label t3js-formengine-label">' . $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.bymonthday') . '</label>';
        $out [] = '<div id="bymonthday-container">';
        $out [] = '<input type="hidden" name="data[' . $table . '][' . $uid . '][bymonthday]" id="data[' . $table . '][' . $uid . '][bymonthday]" value="' . $row ['bymonthday'] . '" />';
        $out [] = $dayRow;
        $out [] = '</div>';
        $out [] = $html;

        $result ['html'] = implode("\n", $out);
        $result['javaScriptModules'] = [
            JavaScriptModuleInstruction::create('@MarioMatzulla/Cal/RegisterByMonthDayChangeListener.js'),
            JavaScriptModuleInstruction::create('@MarioMatzulla/Cal/RecurUI/ByMonthDayUI.js'),
        ];

        return $result;
    }

    /**
     * @return string
     */
    public function byMonthDay_select(): string
    {

        $out = [ ];

        return implode("\n", $out);
    }

    /**
     * @param mixed $endString
     * @return mixed
     */
    public function getByMonthDayRow($endString)
    {

        $html = '<div class="cal-row">';

        for ($i = 1; $i < 32; $i++) {
            $name = 'bymonthday_' . $i;
            $html .= '<input type="checkbox" class="bymonthday-change-action" id="' . $name . '" name="' . $name . '" value="' . $i . '"/>';
            $html .= '<label style="padding-left: 2px;padding-right: 10px;" for="' . $name . '">' . $i . '</label>';
        }

        $html .= '</div>';

        return $this->removeNewlines($html);
    }
}
