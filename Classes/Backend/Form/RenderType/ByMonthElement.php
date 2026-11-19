<?php

declare(strict_types=1)
;

namespace MarioMatzulla\Cal\Backend\Form\RenderType;

use MarioMatzulla\Cal\Model\CalendarDateTime;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;

/**
 * Class ByMonthElement
 */
class ByMonthElement extends AbstractCalFormElementHelper
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

        $row ['bymonth'] = implode(',', array_filter(explode(',', (string)$row ['bymonth']), fn($value) => $value != 'null'));

        $startdate = new CalendarDateTime();
        $startdate->setTimestamp($row ['start_date']->getTimestamp());

        if ($row ['bymonth'] == '') {
            $row ['bymonth'] = $startdate->getMonth();
        }

        $out = [ ];

        $out [] = '<label class="form-label t3js-formengine-label">' . $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.bymonth') . '</label>';
        $out [] = '<div id="bymonth-container" style="margin-bottom: 5px;">';
        $out [] = '<input type="hidden" name="data[' . $table . '][' . $uid . '][bymonth]" id="data[' . $table . '][' . $uid . '][bymonth]" value="' . $row ['bymonth'] . '" />';

        foreach ($this->getMonthsArray() as $value => $label) {
            $name = 'bymonth_' . $value;

            $out [] = '<div class="cal-row" style="display: inline-block; margin-right: 2em">';
            $out [] = '<input type="checkbox" class="bymonth-change-action" id="' . $name . '" name="' . $name . '" value="' . $value . '"/>';
            $out [] = '<label style="padding-left: 2px;" for="' . $name . '">' . $label . '</label>';
            $out [] = '</div>';
        }

        $out [] = '</div>';

        $result ['html'] = implode("\n", $out);
        $result['javaScriptModules'] = [
            JavaScriptModuleInstruction::create('@MarioMatzulla/Cal/RegisterByMonthChangeListener.js'),
            JavaScriptModuleInstruction::create('@MarioMatzulla/Cal/RecurUI/ByMonthUI.js'),
        ];

        return $result;
    }
}
