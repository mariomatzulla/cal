<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Backend\Form\RenderType;

use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * Helpers of the form elements of the recurrence rule (BYDAY, BYMONTH, BYMONTHDAY, RDATE)
 */
abstract class AbstractCalFormElementHelper extends AbstractFormElement
{
    protected const LLL = 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:';

    public function __construct(
        private readonly IconFactory $iconFactory,
        private readonly SiteFinder $siteFinder,
    ) {}

    protected function getGarbageIcon(): string
    {
        return $this->iconFactory->getIcon('actions-delete', IconSize::SMALL)->render();
    }

    protected function getNewIcon(): string
    {
        return $this->iconFactory->getIcon('actions-add', IconSize::SMALL)->render();
    }

    protected function translate(string $key): string
    {
        return (string)$this->getLanguageService()->sL(self::LLL . $key);
    }

    /**
     * First day of the week of the site of the page (site setting plugin.tx_cal.settings.view.weekStartDay): SU or MO
     */
    protected function getWeekStartDay(int $pageId): string
    {
        try {
            $value = (string)$this->siteFinder->getSiteByPageId($pageId)->getSettings()->get('plugin.tx_cal.settings.view.weekStartDay', 'monday');
        } catch (SiteNotFoundException) {
            $value = 'monday';
        }
        return strtolower(trim($value)) === 'sunday' ? 'SU' : 'MO';
    }

    /**
     * @return array<int, string> ordinal number => label
     */
    protected function getCountsArray(): array
    {
        $counts = [];
        foreach ([1 => 'first', 2 => 'second', 3 => 'third', 4 => 'fourth', 5 => 'fifth', -3 => 'thirdtolast', -2 => 'secondtolast', -1 => 'last'] as $count => $key) {
            $counts[$count] = $this->translate('tx_cal_event.byday_count_' . $key);
        }
        return $counts;
    }

    /**
     * @return array<string, string> weekday (MO, TU, ...) => label, starting with the first day of the week
     */
    protected function getWeekdaysArray(string $startDay): array
    {
        $weekdays = [];
        foreach (['MO' => 'monday', 'TU' => 'tuesday', 'WE' => 'wednesday', 'TH' => 'thursday', 'FR' => 'friday', 'SA' => 'saturday', 'SU' => 'sunday'] as $day => $key) {
            $weekdays[$day] = $this->translate('tx_cal_event.byday_' . $key);
        }
        if ($startDay === 'SU') {
            $weekdays = ['SU' => $weekdays['SU']] + $weekdays;
        }
        return $weekdays;
    }

    /**
     * @return array<int, string> month => label
     */
    protected function getMonthsArray(): array
    {
        $months = [];
        foreach (['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'] as $index => $key) {
            $months[$index + 1] = $this->translate('tx_cal_event.bymonth_' . $key);
        }
        return $months;
    }

    /**
     * Start date of the record: a date object of FormEngine, a timestamp or an ISO date; today for new records
     */
    protected static function getStartDate(array $row): \DateTimeImmutable
    {
        $value = $row['start_date'] ?? null;
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }
        if (is_numeric($value) && (int)$value !== 0) {
            return (new \DateTimeImmutable('@' . (int)$value))->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        }
        if (is_string($value) && $value !== '' && !is_numeric($value)) {
            try {
                return new \DateTimeImmutable($value);
            } catch (\Exception) {
            }
        }
        return new \DateTimeImmutable('today');
    }

    /**
     * Value of a multi value field without the "null" entries of the JavaScript
     */
    protected static function getListValue(array $row, string $field): string
    {
        return implode(',', array_filter(explode(',', (string)($row[$field] ?? '')), static fn(string $value): bool => $value !== 'null' && $value !== ''));
    }

    /**
     * First value of a select field (FormEngine passes arrays)
     */
    protected static function getSelectValue(array $row, string $field): string
    {
        $value = $row[$field] ?? '';
        return (string)(is_array($value) ? ($value[0] ?? '') : $value);
    }

    protected function renderHiddenField(string $field, string $value): string
    {
        $name = 'data[' . $this->data['tableName'] . '][' . $this->data['databaseRow']['uid'] . '][' . $field . ']';
        return '<input type="hidden" name="' . htmlspecialchars($name) . '" id="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($value) . '" />';
    }
}
