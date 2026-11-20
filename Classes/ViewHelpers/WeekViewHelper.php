<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\ViewHelpers;

use MarioMatzulla\Cal\Model\CalendarDateTime;
use MarioMatzulla\Cal\Model\WeekView;

final class WeekViewHelper extends BaseWeekViewHelper
{
    protected $escapeOutput = false;

    /**
     * @var \MarioMatzulla\Cal\Model\RightsManager
     */
    protected $rightsManager;

    public function initializeArguments(): void
    {

        // registerArgument($name, $type, $description, $required, $defaultValue, $escape)
        $this->registerArgument('events', 'array', 'The event array', true);
        $this->registerArgument('dateObject', '\DateTime', 'A \DateTime object', false, new \DateTime());
        $this->registerArgument('getdate', '\DateTime', 'The selected date. A \DateTime object', false);
        $this->registerArgument('view', 'string', 'The view to render day|week. Default is week', false, 'week');
    }

    public function render()
    {
        $this->settings = (array)$this->templateVariableContainer->get('settings');
        $this->rightsManager = $this->templateVariableContainer->get('rightsManager');

        $weekDate = CalendarDateTime::generateFromDateTime($this->arguments ['dateObject']);
        $weekModel = new WeekView($weekDate->getWeekOfYear(), $weekDate->getYear());

        $today = new CalendarDateTime();
        $weekModel->setCurrent($today);
        $selected = null;

        if (isset($this->arguments['getdate'])) {
            $selected = CalendarDateTime::generateFromDateTime($this->arguments['getdate']);
            $weekModel->setSelected($selected);
        }

        foreach ($this->arguments ['events'] as $event) {
            $weekModel->addEvent($event);
        }
        if ($this->arguments ['view'] == 'week') {
            $this->renderingContext->getVariableProvider()->add('days', $weekModel->getDays());
        }
        if ((bool)($this->settings ['view'] ['day'] ['extendGridToEvents'] ?? false)) {
            $this->extendDayTimesToEvents(array_filter(
                $weekModel->getDays(),
                fn($day): bool => $this->arguments ['view'] !== 'day' || $day->getYmd() === $weekDate->format('Ymd'),
            ));
        }
        $theMatrixAllday = $this->generateTheRenderingMatrix($weekModel, true, false);
        $this->renderingContext->getVariableProvider()->add('theMatrixAllday', $theMatrixAllday);
        if ($this->arguments ['view'] == 'day') {
            $column = array_search($weekDate->format('Ymd'), array_map('strval', array_keys($weekModel->getDays())), true);
            $this->renderingContext->getVariableProvider()->add('alldayEvents', $column === false ? [] : $this->getAlldayEventsOfColumn($theMatrixAllday, (int)$column));
        }
        $eventColumns = [];
        foreach ($weekModel->getDays() as $day) {
            if ($this->arguments ['view'] == 'day') {
                if ($day->getYmd() == $weekDate->format('Ymd')) {
                    $eventColumns[] = $this->getEventsColumn($day);
                    $this->renderingContext->getVariableProvider()->add('day', $day);
                    $this->renderingContext->getVariableProvider()->add('dayIndex', $day->getTime()->getDayOfWeek());
                }
            } else {
                $eventColumns[] = $this->getEventsColumn($day);
            }
        }
        $this->renderingContext->getVariableProvider()->add('eventColumns', $eventColumns);
        $rowspanArray = [];
        for ($i = 0; $i < $this->rowspan;$i++) {
            $rowspanArray[] = $i;
        }
        $this->renderingContext->getVariableProvider()->add('rowspanArray', $rowspanArray);
        $this->renderingContext->getVariableProvider()->add('hourCells', $this->getHourCells());
        $this->renderingContext->getVariableProvider()->add('timeCells', $this->getTimeCells('week'));

        return $this->renderChildren();
    }

}
