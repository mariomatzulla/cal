<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Backend\Form\RenderType;

use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;

/**
 * Months of a yearly recurrence rule (BYMONTH), default: the month of the start date
 */
class ByMonthElement extends AbstractCalFormElementHelper
{
    public function render(): array
    {
        $result = $this->initializeResultArray();
        $row = $this->data['databaseRow'];
        $byMonth = self::getListValue($row, 'bymonth');
        if ($byMonth === '') {
            $byMonth = self::getStartDate($row)->format('n');
        }
        $out = [
            '<label class="form-label t3js-formengine-label">' . htmlspecialchars($this->translate('tx_cal_event.bymonth')) . '</label>',
            '<div id="bymonth-container" style="margin-bottom: 5px;">',
            $this->renderHiddenField('bymonth', $byMonth),
        ];
        foreach ($this->getMonthsArray() as $value => $label) {
            $out[] = '<div class="cal-row" style="display: inline-block; margin-right: 2em">';
            $out[] = '<input type="checkbox" class="bymonth-change-action" id="bymonth_' . $value . '" name="bymonth_' . $value . '" value="' . $value . '"/>';
            $out[] = '<label style="padding-left: 2px;" for="bymonth_' . $value . '">' . htmlspecialchars($label) . '</label>';
            $out[] = '</div>';
        }
        $out[] = '</div>';
        $result['html'] = implode("\n", $out);
        $result['javaScriptModules'] = [
            JavaScriptModuleInstruction::create('@MarioMatzulla/Cal/RegisterByMonthChangeListener.js'),
            JavaScriptModuleInstruction::create('@MarioMatzulla/Cal/RecurUI/ByMonthUI.js'),
        ];
        return $result;
    }
}
