<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Backend\Form\RenderType;

use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;

/**
 * Weekdays of the recurrence rule (BYDAY): checkboxes for weekly rules, weekday with ordinal number for monthly and yearly rules
 */
class ByDayElement extends AbstractCalFormElementHelper
{
    private const ALL_WEEKDAYS = 'MO,TU,WE,TH,FR,SA,SU';

    public function render(): array
    {
        $result = $this->initializeResultArray();
        $row = $this->data['databaseRow'];
        // only weekdays with an optional ordinal number, the JavaScript writes e.g. "3null" for an ordinal without weekday
        $byDay = implode(',', array_filter(explode(',', strtoupper(self::getListValue($row, 'byday'))), static fn(string $value): bool => preg_match('/^[-+]?\d{0,2}(MO|TU|WE|TH|FR|SA|SU)$/', $value) === 1));
        $startDay = $this->getWeekStartDay((int)($row['pid'] ?? 0));
        $dayRow = '';
        $addLink = '';
        switch (self::getSelectValue($row, 'freq')) {
            case 'week':
                $dayRow = $this->renderCheckboxes($startDay);
                if ($byDay === '') {
                    $byDay = strtoupper(substr(self::getStartDate($row)->format('D'), 0, 2));
                }
                break;
            case 'month':
                $dayRow = $this->renderByDayRow($startDay, $this->translate('tx_cal_event.recurs_every_month'));
                $addLink = $this->renderAddLink();
                $byDay = $byDay === '' ? self::ALL_WEEKDAYS : $byDay;
                break;
            case 'year':
                $dayRow = $this->renderByDayRow($startDay, $this->translate('tx_cal_event.recurs_selected_months'));
                $addLink = $this->renderAddLink();
                $byDay = $byDay === '' ? self::ALL_WEEKDAYS : $byDay;
                break;
        }
        $result['html'] = implode("\n", [
            '<label class="form-label t3js-formengine-label">' . htmlspecialchars($this->translate('tx_cal_event.byday_short')) . '</label>',
            '<div id="byday-container">',
            $this->renderHiddenField('byday', $byDay),
            $dayRow,
            '</div>',
            $addLink,
        ]);
        $result['javaScriptModules'] = [
            JavaScriptModuleInstruction::create('@MarioMatzulla/Cal/RegisterByDayChangeListener.js'),
            JavaScriptModuleInstruction::create('@MarioMatzulla/Cal/RecurUI/ByDayUI.js'),
        ];
        return $result;
    }

    /**
     * Template of a row "ordinal number, weekday" (copied by the JavaScript for each value)
     */
    private function renderByDayRow(string $startDay, string $endString): string
    {
        $html = '<div class="cal-row">';
        $html .= '<select class="form-control count byday-select-change-action" style="width: 48px; display: inline;"><option value="" />';
        foreach ($this->getCountsArray() as $value => $label) {
            $html .= '<option value="' . $value . '">' . htmlspecialchars($label) . '</option>';
        }
        $html .= '</select>&nbsp;&nbsp;';
        $html .= '<select class="form-control day byday-select-change-action" style="width: 48px; display: inline;"><option value="" />';
        foreach ($this->getWeekdaysArray($startDay) as $value => $label) {
            $html .= '<option value="' . $value . '">' . htmlspecialchars($label) . '</option>';
        }
        $html .= '</select> ' . htmlspecialchars($endString);
        $html .= '<a href="#" class="remove-byday">' . $this->getGarbageIcon() . htmlspecialchars($this->translate('tx_cal_event.remove_recurrence')) . '</a>';
        return $html . '</div>';
    }

    private function renderCheckboxes(string $startDay): string
    {
        $out = [];
        foreach ($this->getWeekdaysArray($startDay) as $value => $label) {
            $out[] = '<div class="cal-row">';
            $out[] = '<input class="byday-change-action" style="padding: 0; margin: 0;" type="checkbox" name="byday_' . $value . '" value="' . $value . '" />';
            $out[] = '<label style="padding-left: 2px;" for="byday_' . $value . '">' . htmlspecialchars($label) . '</label>';
            $out[] = '</div>';
        }
        return implode("\n", $out);
    }

    private function renderAddLink(): string
    {
        return '<div style="padding: 5px 0"><a href="#" class="add-byday">' . $this->getNewIcon() . htmlspecialchars($this->translate('tx_cal_event.add_recurrence')) . '</a></div>';
    }
}
