<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Backend\Form\RenderType;

use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;

/**
 * Additional dates of an event (RDATE): a date field per value, for periods with the duration.
 * The JavaScript writes the values into the field "rdate", e.g. "20261110", "20261110T140000Z" or "20261110T140000Z/P1DT2H".
 */
class RDateElement extends AbstractCalFormElementHelper
{
    private const DURATION_FIELDS = [2 => 'Year', 4 => 'Month', 6 => 'Week', 8 => 'Day', 10 => 'Hour', 12 => 'Minute'];

    public function render(): array
    {
        $result = $this->initializeResultArray();
        $table = $this->data['tableName'];
        $row = $this->data['databaseRow'];
        $uid = $row['uid'];
        $rdateType = self::getSelectValue($row, 'rdate_type');
        $values = array_values(array_filter(array_map('trim', explode(',', (string)($row['rdate'] ?? ''))), static fn(string $value): bool => $value !== ''));
        // an empty field for a new value
        $values[] = '';

        $out = [
            '<div class="formengine-field-item t3js-formengine-field-item">',
            '<div class="form-wizards-wrap">',
            '<div class="form-wizards-element" id="rdate-container">',
            '<div class="form-control-wrap">',
        ];
        foreach ($values as $key => $value) {
            $withTime = $rdateType === 'date_time' || $rdateType === 'period';
            $field = $this->nodeFactory->create([
                'tableName' => $table,
                'fieldName' => 'rdate' . $key,
                'databaseRow' => ['uid' => $uid],
                'processedTca' => ['columns' => ['rdate' . $key => ['config' => ['type' => 'input', 'customCssClass' => 'rdate-change-action', 'format' => $withTime ? 'datetime' : 'date']]]],
                'renderType' => 'datetime',
                'parameterArray' => [
                    'itemFormElValue' => self::parseDate($value),
                    'itemFormElName' => 'data[' . $table . '][' . $uid . '][rdate' . $key . ']',
                    'fieldConf' => ['config' => ['type' => 'datetime', 'format' => $withTime ? 'datetime' : 'date']],
                ],
            ])->render();
            $out[] = '<div class="rdate-element">';
            // the JavaScript (RecurUI/RDate.js) finds the date fields by this wrapper
            $out[] = '<div class="cal-rdate-datetime-rendering">' . $field['html'] . '</div>';
            if ($rdateType === 'period') {
                $out[] = $this->renderDuration($key, $value);
            }
            $out[] = '<a href="#" class="remove-rdate">' . $this->getGarbageIcon() . htmlspecialchars($this->translate('tx_cal_event.remove_recurrence')) . '</a>';
            $out[] = '</div>';
        }
        $out[] = '<input type="text" name="' . htmlspecialchars('data[' . $table . '][' . $uid . '][rdate]') . '" id="' . htmlspecialchars('data_' . $table . '_' . $uid . '_rdate') . '" value="' . htmlspecialchars((string)($row['rdate'] ?? '')) . '" />';
        array_push($out, '</div>', '</div>', '</div>', '</div>');
        $result['html'] = implode("\n", $out);
        $result['javaScriptModules'] = [
            JavaScriptModuleInstruction::create('@MarioMatzulla/Cal/RecurUI/RDate.js'),
            JavaScriptModuleInstruction::create('@MarioMatzulla/Cal/RegisterRdateChangeListener.js'),
        ];
        return $result;
    }

    /**
     * Date (and time) of a value, null for an empty or invalid value
     */
    private static function parseDate(string $value): ?\DateTime
    {
        if (!preg_match('/^(\d{4})-?(\d{2})-?(\d{2})(?:T(\d{2}):?(\d{2})(?::?(\d{2}))?)?/i', $value, $match)) {
            return null;
        }
        return new \DateTime(sprintf('%s-%s-%s %02d:%02d:%02d', $match[1], $match[2], $match[3], $match[4] ?? 0, $match[5] ?? 0, $match[6] ?? 0));
    }

    private function renderDuration(int $key, string $value): string
    {
        preg_match('/P(?:(\d+)Y)?(?:(\d+)M)?(?:(\d+)W)?(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?)?/', $value, $match);
        $html = '<label class="t3js-formengine-label">' . htmlspecialchars($this->translate('l_duration')) . '</label><div class="row">';
        $position = 1;
        foreach (self::DURATION_FIELDS as $field) {
            $name = 'rdate' . $field . $key;
            $html .= '<div class="form-group t3js-formengine-validation-marker t3js-formengine-palette-field col-sm-2">'
                . '<label class="t3js-formengine-label">' . htmlspecialchars($this->translate('l_' . strtolower($field))) . '</label>'
                . '<div class="formengine-field-item t3js-formengine-field-item"><div class="form-control-wrap" style="max-width: 192px">'
                . '<input type="text" value="' . (int)($match[$position] ?? 0) . '" class="form-control rdateChanged rdate-change-action" name="' . $name . '" id="' . $name . '"/>'
                . '</div></div></div>';
            $position++;
        }
        return $html . '</div>';
    }
}
