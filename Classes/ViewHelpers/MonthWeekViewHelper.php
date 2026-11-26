<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\ViewHelpers;

use MarioMatzulla\Cal\Model\MonthView;
use MarioMatzulla\Cal\Model\WeekView;

/**
 * Renders the children once per week of a month view.
 *
 * Variables: week, weekClasses, theMatrix (events per column and row), rowspan, rowspanArray
 */
final class MonthWeekViewHelper extends BaseWeekViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('month', MonthView::class, 'The month view object', true);
    }

    public function render(): string
    {
        $content = '';
        foreach ($this->arguments['month']->getWeeks() as $week) {
            $variables = $this->renderingContext->getVariableProvider();
            $variables->add('theMatrix', $this->generateTheRenderingMatrix($week));
            // rowspan is set by generateTheRenderingMatrix()
            $variables->add('rowspan', $this->rowspan);
            $variables->add('rowspanArray', range(0, $this->rowspan - 1));
            $variables->add('weekClasses', $this->getWeekClasses($week));
            $variables->add('week', $week);
            $content .= $this->renderChildren();
        }
        return $content;
    }

    private function getWeekClasses(WeekView $week): string
    {
        $classes = [];
        if ($week->isCurrent()) {
            $classes[] = 'currentWeek';
        }
        if ($week->isSelected()) {
            $classes[] = 'selectedWeek';
        }
        $classes[] = $week->isWeekHasEvent() ? 'withEventWeek' : 'emptyWeek';
        return implode(' ', $classes);
    }
}
