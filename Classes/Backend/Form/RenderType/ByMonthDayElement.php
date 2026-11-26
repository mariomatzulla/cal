<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Backend\Form\RenderType;

use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;

/**
 * Days of the month of a recurrence rule (BYMONTHDAY), default: the day of the start date
 */
class ByMonthDayElement extends AbstractCalFormElementHelper
{
    public function render(): array
    {
        $result = $this->initializeResultArray();
        $row = $this->data['databaseRow'];
        $byMonthDay = self::getListValue($row, 'bymonthday');
        if ($byMonthDay === '') {
            $byMonthDay = self::getStartDate($row)->format('j');
        }
        $dayRow = in_array(self::getSelectValue($row, 'freq'), ['week', 'month', 'year'], true) ? $this->renderDayRow() : '';
        $result['html'] = implode("\n", [
            '<label class="form-label t3js-formengine-label">' . htmlspecialchars($this->translate('tx_cal_event.bymonthday')) . '</label>',
            '<div id="bymonthday-container">',
            $this->renderHiddenField('bymonthday', $byMonthDay),
            $dayRow,
            '</div>',
        ]);
        $result['javaScriptModules'] = [
            JavaScriptModuleInstruction::create('@MarioMatzulla/Cal/RegisterByMonthDayChangeListener.js'),
            JavaScriptModuleInstruction::create('@MarioMatzulla/Cal/RecurUI/ByMonthDayUI.js'),
        ];
        return $result;
    }

    private function renderDayRow(): string
    {
        $html = '<div class="cal-row">';
        for ($day = 1; $day < 32; $day++) {
            $html .= '<input type="checkbox" class="bymonthday-change-action" id="bymonthday_' . $day . '" name="bymonthday_' . $day . '" value="' . $day . '"/>';
            $html .= '<label style="padding-left: 2px;padding-right: 10px;" for="bymonthday_' . $day . '">' . $day . '</label>';
        }
        return $html . '</div>';
    }
}
