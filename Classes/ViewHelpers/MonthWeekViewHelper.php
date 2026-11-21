<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\ViewHelpers;

final class MonthWeekViewHelper extends BaseWeekViewHelper
{
    protected $escapeOutput = false;

    /**
     * @var string
     */
    protected $view;

    /**
     * @var \MarioMatzulla\Cal\Model\MonthView
     */
    protected $monthModel;

    public function initializeArguments(): void
    {

        // registerArgument($name, $type, $description, $required, $defaultValue, $escape)
        $this->registerArgument('month', '\MarioMatzulla\Cal\Model\MonthView', 'The month view object', true);
        $this->registerArgument('type', 'string', 'the size [small|medium|large]', false, 'large');
    }

    public function render()
    {
        $this->settings = (array)$this->templateVariableContainer->get('settings');
        $this->view = (string)($this->templateVariableContainer->get('view') ?? 'month');

        $this->monthModel = $this->arguments ['month'];
        $content = '';

        foreach ($this->monthModel->getWeeks() as $week) {
            $this->renderingContext->getVariableProvider()->add('theMatrix', $this->generateTheRenderingMatrix($week));
            //eventCount gets filled during generateTheMatrix
            $this->renderingContext->getVariableProvider()->add('eventCountOfWeek', $this->eventCount);
            //rowspan gets filled during generateTheMatrix
            $this->renderingContext->getVariableProvider()->add('rowspan', $this->rowspan);
            // it needs to be passed on as array
            $rowspanArray = [];
            for ($i = 0; $i < $this->rowspan;$i++) {
                $rowspanArray[] = $i;
            }
            $this->renderingContext->getVariableProvider()->add('rowspanArray', $rowspanArray);
            $this->renderingContext->getVariableProvider()->add('weekClasses', $this->getWeekClasses($week));
            $this->renderingContext->getVariableProvider()->add('week', $week);
            $count = 0;
            foreach ($week->getDays() as $day) {
                $this->renderingContext->getVariableProvider()->add('dayClasses' . $count, $this->getDayClasses($week, $count));
                $this->renderingContext->getVariableProvider()->add('day' . $count, $day);
                $count++;
            }
            $this->eventCount = 0;
            $content .= $this->renderChildren();
        }
        return $content;
    }

    /**
     * @param $weekdayIndex
     * @return string
     */
    protected function getDayClasses($week, $weekdayIndex)
    {

        $classes = '';
        if ($week->isDayHasEvent() [$weekdayIndex] === 1) {
            $classes .= ' ' . $this->settings ['view'] ['month'] ['eventDayStyle'];
        }

        $localDayIndex = $week->getCurrentDayIndex() + DATE_CALC_BEGIN_WEEKDAY;
        if ($localDayIndex == 7) {
            $localDayIndex = 0;
        }

        if ($localDayIndex === $weekdayIndex) {
            $classes .= ' ' . $this->settings ['view'] ['month'] ['monthTodayStyle'];
        }

        $localDayIndex = $weekdayIndex - DATE_CALC_BEGIN_WEEKDAY;
        if ($localDayIndex === - 1) {
            $localDayIndex = 6;
        }
        $daysKeys = array_keys($week->getdays());
        if ((int)($week->getParentMonth()) != (int)($week->getDays() [$daysKeys [$localDayIndex]]->getMonth())) {
            $classes .= ' ' . $this->settings ['view'] ['month'] ['monthOffStyle'];
        }

        return $classes;
    }

    /**
     * @param \MarioMatzulla\Cal\Model\WeekView $week
     * @return string
     */
    protected function getWeekClasses($week): string
    {

        $classes = '';
        if ($week->isCurrent()) {
            $classes .= ' currentWeek';
        }
        if ($week->isSelected()) {
            $classes .= ' selectedWeek';
        }

        if ($week->isWeekHasEvent()) {
            $classes .= ' withEventWeek';
        } else {
            $classes .= ' emptyWeek';
        }
        return trim($classes);
    }

}
