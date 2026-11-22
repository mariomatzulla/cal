<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\ViewHelpers;

use MarioMatzulla\Cal\Utility\WeekStart;

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
     * @param \MarioMatzulla\Cal\Model\WeekView $week
     * @param int $position position of the day in the week (0 = first day of the week, see WeekStart)
     */
    protected function getDayClasses($week, $position): string
    {
        $classes = '';
        // days with events are stored by weekday (0 = Sunday)
        if (($week->isDayHasEvent() [($position + WeekStart::get()) % 7] ?? 0) === 1) {
            $classes .= ' ' . ($this->settings ['view'] ['month'] ['eventDayStyle'] ?? '');
        }
        if ((int)$week->getCurrentDayIndex() === $position) {
            $classes .= ' ' . ($this->settings ['view'] ['month'] ['monthTodayStyle'] ?? '');
        }
        $days = array_values($week->getDays());
        if (isset($days [$position]) && (int)$week->getParentMonth() !== (int)$days [$position]->getMonth()) {
            $classes .= ' ' . ($this->settings ['view'] ['month'] ['monthOffStyle'] ?? '');
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
