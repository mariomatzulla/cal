<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Service;

use MarioMatzulla\Cal\Events\FilterEventsEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;

#[AsEventListener(identifier: 'cal/category-service')]
final class CategoryService extends BaseService
{
    public function __invoke(FilterEventsEvent $event): void
    {
        $this->filterByCategoryMode($event);
    }

    private function filterByCategoryMode(FilterEventsEvent $event): void
    {
        $categoryMode =  isset($event->getSettings()['view']['categoryMode']) ? (int)($event->getSettings()['view']['categoryMode']) : 0;
        $categoryIds = $this->getCategoriesFromSettings($event->getSettings());
        $events = $event->getEvents();
        if ($categoryMode == 0) {
            // Nothing;
        } elseif ($categoryMode == 1) {
            // selected exact match
            $events = $this->filterAllEventsNotExactlyWithTheseCategoryIds($event->getEvents(), $categoryIds);
        } elseif ($categoryMode == 2) {
            //invers zu 1
            $events = $this->filterAllEventsWithTheseCategoryIds($event->getEvents(), $categoryIds);
        } elseif ($categoryMode == 3) {
            //any match
            $events = $this->filterAllEventsNotWithTheseCategoryIds($event->getEvents(), $categoryIds);
        } elseif ($categoryMode == 4) {
            //minimal match?????
            // No idea
        }
        $event->setEvents($events);
    }

    private function filterAllEventsNotWithTheseCategoryIds(array $events, array $categoryIds)
    {
        $result = [];
        foreach ($events as $event) {
            $match = false;
            foreach ($event->getCategories() as $category) {
                if (in_array($category->getUid(), $categoryIds)) {
                    $match = true;
                    break;
                }
                $match = false;

            }
            if ($match === true) {
                $result[] = $event;
            }
        }
        return $result;
    }

    private function filterAllEventsNotExactlyWithTheseCategoryIds(array $events, array $categoryIds)
    {
        $result = [];
        foreach ($events as $event) {
            $match = false;
            if (count($event->getCategories()) == count($categoryIds)) {
                foreach ($event->getCategories() as $category) {
                    if (in_array($category->getUid(), $categoryIds)) {
                        $match = true;
                    } else {
                        $match = false;
                        break;
                    }
                }
                if ($match === true) {
                    $result[] = $event;
                }
            }
        }
        return $result;
    }

    private function filterAllEventsWithTheseCategoryIds(array $events, array $categoryIds)
    {
        $result = [];
        foreach ($events as $event) {
            $match = false;
            foreach ($event->getCategories() as $category) {
                if (in_array($category->getUid(), $categoryIds)) {
                    $match = true;
                    break;
                }
            }
            if ($match === false) {
                $result[] = $event;
            }
        }
        return $result;
    }

    private function getCategoriesFromSettings($settings)
    {
        $categoryIds =  is_array($settings['view']['categoryIds']) ? $settings['view']['categoryIds'] : [];
        return array_filter(array_unique($categoryIds), null, ARRAY_FILTER_USE_BOTH);
    }

}
