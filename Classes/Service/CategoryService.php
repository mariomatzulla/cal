<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Service;

use MarioMatzulla\Cal\Events\FilterEventsEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Filters the events by the category selection of the plugin (settings.view.categoryMode / categoryIds)
 */
#[AsEventListener(identifier: 'cal/category-service')]
final class CategoryService
{
    public const MODE_ALL = 0;
    /** Only events with exactly the selected categories */
    public const MODE_EXACT = 1;
    /** Only events without any of the selected categories */
    public const MODE_EXCLUDE = 2;
    /** Only events with at least one of the selected categories */
    public const MODE_ANY = 3;

    public function __invoke(FilterEventsEvent $event): void
    {
        $settings = $event->getSettings();
        $mode = (int)($settings['view']['categoryMode'] ?? self::MODE_ALL);
        if (! in_array($mode, [self::MODE_EXACT, self::MODE_EXCLUDE, self::MODE_ANY], true)) {
            return;
        }
        $categoryIds = array_values(array_unique(array_filter(array_map('intval', (array)($settings['view']['categoryIds'] ?? [])))));
        $event->setEvents(array_values(array_filter($event->getEvents(), static function ($calendarEvent) use ($mode, $categoryIds): bool {
            $uids = [];
            foreach ($calendarEvent->getCategories() ?? [] as $category) {
                $uids[] = (int)$category->getUid();
            }
            $selected = array_intersect($uids, $categoryIds);
            return match ($mode) {
                self::MODE_EXACT => $uids !== [] && count($uids) === count($categoryIds) && count($selected) === count($uids),
                self::MODE_EXCLUDE => $selected === [],
                self::MODE_ANY => $selected !== [],
            };
        })));
    }
}
