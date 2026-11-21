<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Controller;

use MarioMatzulla\Cal\Events\FilterEventsEvent;
use MarioMatzulla\Cal\Events\GetEventEvent;
use MarioMatzulla\Cal\Events\GetLocationEvent;
use MarioMatzulla\Cal\Events\GetOrganizerEvent;
use MarioMatzulla\Cal\Events\SearchEventsEvent;
use MarioMatzulla\Cal\Model\CalendarDateTime;
use MarioMatzulla\Cal\Model\RightsManager;
use MarioMatzulla\Cal\Utility\CalculateDateTime;
use MarioMatzulla\Cal\Utility\HelperFunctions;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Fluid\View\FluidViewAdapter;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

class ContentController extends ActionController
{
    /**
     * Record type of the detail views, if the request has no argument "type"
     */
    private const DEFAULT_RECORD_TYPES = [
        'event' => 'tx_cal_event',
        'ics' => 'tx_cal_event',
        'location' => 'tx_cal_location',
        'organizer' => 'tx_cal_organizer',
    ];

    use \MarioMatzulla\Cal\General;

    /**
     * @var array
     */
    protected $configuration;

    /**
     * @var array
     */
    protected $pids =  [];

    public function __construct(ExtensionConfiguration $extensionConfiguration)
    {
        $this->configuration = (array)$extensionConfiguration->get('cal');
    }

    /**
     * Initializes the settings for every action. Runs after the request has been set,
     * so TypoScript and FlexForm can be resolved for the current page.
     */
    protected function initializeAction(): void
    {
        // inject repositories and init data, FlexForm of the rendered plugin
        $contentObject = $this->request->getAttribute('currentContentObject');
        $this->initSettings($contentObject instanceof ContentObjectRenderer ? $contentObject->data : null);
        $this->settings ['view'] ['allowedViewsToLinkTo'] = array_unique(array_merge(HelperFunctions::trimExplode($this->settings ['view'] ['allowedViews'] ?? ''), $this->getAllowedViewsByViewPid()));
        $this->settings ['view'] ['categoryIds'] = array_unique(array_merge(HelperFunctions::trimExplode($this->settings ['view'] ['category'] ?? ''), HelperFunctions::trimExplode($this->settings ['view'] ['categoryIds'] ?? '')));
    }

    /**
     * @return array
     */
    protected function getAllowedViewsByViewPid(): array
    {
        // for now, ownly check basic views.
        $allowedViews = [ ];
        $regularViews = [

            'day',
            'week',
            'month',
            'year',
            'list',
            'event',
            'location',
            'organizer',
        ];
        $feEditingViews = [

            'event',
            'location',
            'organizer',
            'calendar',
            'category',
        ];
        $editingTypes = [

            'create',
            'edit',
            'delete',
        ];

        foreach ($regularViews as $view) {
            if (isset($this->settings ['view'] [$view] [$view . 'ViewPid']) && $this->settings ['view'] [$view] [$view . 'ViewPid'] != '') {
                $allowedViews [] = $view;
            }
        }

        foreach ($feEditingViews as $view) {
            foreach ($editingTypes as $type) {
                if ($this->settings ['view'] [$view] [$type . ucfirst($view) . 'ViewPid'] ?? false) {
                    $allowedViews [] = $type . '_' . $view;
                }
            }
        }

        return $allowedViews;
    }

    /**
     * Index action for this controller.
     * Dispatches to the desired view.
     */
    public function indexAction(): ResponseInterface
    {
        switch ($this->getDefaultView()) {
            case 'list': return $this->listAction();
            case 'event': return $this->eventAction();
            case 'ics': return $this->icsAction();
            case 'year': return $this->yearAction();
            case 'month': return $this->monthAction();
            case 'week': return $this->weekAction();
            case 'day': return $this->dayAction();
        }
        return $this->listAction();
    }

    public function listAction(): ResponseInterface
    {
        $starttime = new \DateTime((string)($this->settings ['view'] ['list'] ['starttime'] ?? '') ?: 'now');
        $endtime = new \DateTime((string)($this->settings ['view'] ['list'] ['endtime'] ?? '') ?: '+1 month');
        $events = $this->prepareListEvents($this->fetchEvents($starttime, $endtime), $starttime, $endtime);

        // Group the events by year of their start
        $years = [];
        foreach ($events as $event) {
            $years[$event->getStart()->format('Y')][] = $event;
        }

        $this->view->assign('starttime', $starttime);
        $this->view->assign('endtime', $endtime);
        $this->view->assign('events', $events);
        $this->view->assign('years', $years);
        $this->view->assign('eventCount', count($events));
        $this->view->assign('eventLink', $this->getEventLinkConfiguration());
        $this->view->assign('viewLinks', $this->getViewLinkConfiguration());
        $this->view->assign('view', 'list');
        $this->addToAllViews();
        $this->useTemplateFile((string)($this->settings ['view'] ['list'] ['templateFile'] ?? ''));
        return $this->htmlResponse();
    }

    /**
     * Allows a different Fluid template per page by TypoScript, e.g.
     * plugin.tx_cal.settings.view.list.templateFile = fileadmin/templates/cal/list/my_list.html
     * plugin.tx_cal.settings.view.event.templateFile = fileadmin/templates/cal/event/my_event.html
     * (EXT: paths are supported as well). The file is a complete template using the layout "Default".
     */
    protected function useTemplateFile(string $templateFile): void
    {
        if (trim($templateFile) === '') {
            return;
        }
        $absoluteFileName = GeneralUtility::getFileAbsFileName(trim($templateFile));
        if ($absoluteFileName === '' || !is_file($absoluteFileName)) {
            return;
        }
        if ($this->view instanceof FluidViewAdapter) {
            $this->view->getRenderingContext()->getTemplatePaths()->setTemplatePathAndFilename($absoluteFileName);
        }
    }

    /**
     * The search returns the base record of a recurring event even if only later occurrences
     * lie in the range. Keep only events overlapping the range, remove duplicates, sort them
     * by start and apply maxEvents.
     *
     * @return list<object>
     */
    protected function prepareListEvents(array $events, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $rangeStart = $start->format('YmdHis');
        $rangeEnd = $end->format('YmdHis');
        $list = [];
        foreach ($events as $event) {
            $eventStart = $event->getStart()->format('YmdHis');
            $eventEnd = $event->getEnd()->format('YmdHis');
            if ($eventStart > $rangeEnd || max($eventEnd, $eventStart) < $rangeStart) {
                continue;
            }
            $list[$event->getModelType() . '_' . $event->getUid() . '_' . $eventStart] = $event;
        }
        uasort($list, static fn($a, $b): int => [$a->getStart()->format('YmdHis'), (string)$a->getTitle()] <=> [$b->getStart()->format('YmdHis'), (string)$b->getTitle()]);
        $list = array_values($list);

        $maxEvents = (int)($this->settings ['view'] ['list'] ['maxEvents'] ?? 0);
        return $maxEvents > 0 ? array_slice($list, 0, $maxEvents) : $list;
    }

    /**
     * Links of the location and organizer of an event: only if the view is one of the allowed views of the
     * plugin or a separate page for the view is configured (view.location.locationViewPid,
     * view.organizer.organizerViewPid).
     *
     * The ICS download is shown if the ICS view is allowed and view.ics.showIcsLinks is set.
     *
     * @return array{location: array{enabled: bool, pageUid: int|null}, organizer: array{enabled: bool, pageUid: int|null}, ics: bool}
     */
    protected function getViewLinkConfiguration(): array
    {
        $allowedViews = (array)($this->settings ['view'] ['allowedViewsToLinkTo'] ?? []);
        $links = [];
        foreach (['location', 'organizer'] as $view) {
            $pageUid = (int)($this->settings ['view'] [$view] [$view . 'ViewPid'] ?? 0);
            $links[$view] = [
                'enabled' => in_array($view, $allowedViews, true),
                'pageUid' => $pageUid > 0 ? $pageUid : null,
            ];
        }
        $links['ics'] = in_array('ics', $allowedViews, true) && (int)($this->settings ['view'] ['ics'] ['showIcsLinks'] ?? 0) === 1;
        return $links;
    }

    /**
     * The title links to the event view only if the event view is one of the allowed views
     * of the plugin or a separate page for the event view is configured.
     *
     * @return array{enabled: bool, pageUid: int|null}
     */
    protected function getEventLinkConfiguration(): array
    {
        $eventViewPid = (int)($this->settings ['view'] ['event'] ['eventViewPid'] ?? 0);
        return [
            'enabled' => in_array('event', (array)($this->settings ['view'] ['allowedViewsToLinkTo'] ?? []), true),
            'pageUid' => $eventViewPid > 0 ? $eventViewPid : null,
        ];
    }

    public function monthAction(): ResponseInterface
    {
        $getdate = new CalendarDateTime();
        $getdate->setTime(0, 0, 0, 0);
        $urlParams = $this->getUrlParameters();
        if ($urlParams['year'] != 'not_submitted') {
            $getdate->setYear((int)($urlParams['year']));
        }
        if ($urlParams['month'] != 'not_submitted') {
            $getdate->setMonth((int)($urlParams['month']));
        }
        $nextMonth = clone $getdate;
        $nextMonth->modify('+1 month');
        $prevMonth = clone $getdate;
        $prevMonth->modify('-1 month');
        $starttime = clone $getdate;
        $endtime = clone $getdate;
        $endtime->modify('last day of this month 23:23:59');
        $starttime->modify('first day of this month');
        $searchStarttime = clone $starttime;
        $searchStarttime->sub(\DateInterval::createFromDateString((string)($this->settings['view']['month']['startPointCorrection'] ?? '0 days')));
        $searchEndtime = clone $endtime;
        $searchEndtime->add(\DateInterval::createFromDateString((string)($this->settings['view']['month']['endPointCorrection'] ?? '0 days')));
        $events = $this->fetchEvents($searchStarttime, $searchEndtime);
        $this->view->assign('starttime', $starttime);
        $this->view->assign('endtime', $endtime);
        $this->view->assign('today', new CalendarDateTime());
        $this->view->assign('getdate', $getdate);
        $this->view->assign('nextMonth', $nextMonth);
        $this->view->assign('prevMonth', $prevMonth);
        $this->view->assign('events', $events);
        $this->view->assign('view', 'month');
        $this->view->assign('calendarViews', $this->getCalendarViewConfiguration('month'));
        $this->view->assign('eventLink', $this->getEventLinkConfiguration());
        $this->view->assign('contentUid', $this->getContentUid());
        $this->addToAllViews();
        return $this->htmlResponse();
    }

    /**
     * Calendar views the plugin may link to (view switch, day numbers, week numbers).
     * The current view is always part of the view switch; "switch" is true if there is another view.
     *
     * @return array{year: bool, month: bool, week: bool, day: bool, list: bool, switch: bool}
     */
    protected function getCalendarViewConfiguration(string $currentView): array
    {
        $allowedViews = (array)($this->settings ['view'] ['allowedViewsToLinkTo'] ?? []);
        $views = [];
        foreach (['year', 'month', 'week', 'day', 'list'] as $view) {
            $views[$view] = $view === $currentView || in_array($view, $allowedViews, true);
        }
        $views['switch'] = count(array_filter($views)) > 1;
        return $views;
    }

    /**
     * uid of the rendered content element, used for unique HTML ids
     */
    protected function getContentUid(): int
    {
        $contentObject = $this->request->getAttribute('currentContentObject');
        return $contentObject instanceof ContentObjectRenderer ? (int)($contentObject->data['uid'] ?? 0) : 0;
    }

    public function yearAction(): ResponseInterface
    {
        $getdate = new CalendarDateTime();
        $getdate->setTime(0, 0, 0, 0);
        $urlParams = $this->getUrlParameters();
        if ($urlParams['year'] != 'not_submitted') {
            $getdate->setYear((int)($urlParams['year']));
        }
        $nextYear = clone $getdate;
        $nextYear->modify('+1 year');
        $prevYear = clone $getdate;
        $prevYear->modify('-1 year');
        $starttime = clone $getdate;
        $endtime = clone $getdate;
        $endtime->modify('last day of this year 23:23:59');
        $starttime->modify('first day of this year');
        $searchStarttime = clone $starttime;
        $searchStarttime->sub(\DateInterval::createFromDateString((string)($this->settings['view']['year']['startPointCorrection'] ?? '0 days')));
        $searchEndtime = clone $endtime;
        $searchEndtime->add(\DateInterval::createFromDateString((string)($this->settings['view']['year']['endPointCorrection'] ?? '0 days')));
        $events = $this->fetchEvents($searchStarttime, $searchEndtime);
        $this->view->assign('starttime', $starttime);
        $this->view->assign('endtime', $endtime);
        $this->view->assign('today', new CalendarDateTime());
        $this->view->assign('getdate', $getdate);
        $this->view->assign('nextYear', $nextYear);
        $this->view->assign('prevYear', $prevYear);
        $this->view->assign('events', $events);
        $this->view->assign('view', 'year');
        $this->addToAllViews();
        return $this->htmlResponse();
    }

    public function weekAction(): ResponseInterface
    {
        $getdate = new CalendarDateTime();
        $getdate->setTime(0, 0, 0, 0);
        $urlParams = $this->getUrlParameters();
        if ($urlParams['year'] != 'not_submitted') {
            $getdate->setYear((int)($urlParams['year']));
        }
        if ($urlParams['week'] != 'not_submitted') {
            $getdate = CalculateDateTime::getDayByWeek($getdate->getYear(), (int)($urlParams['week']), DATE_CALC_BEGIN_WEEKDAY);
        }
        $nextWeek = clone $getdate;
        $nextWeek->modify('+7 days');
        $prevWeek = clone $getdate;
        $prevWeek->modify('-1 day');

        $starttime = CalculateDateTime::getDayByWeek($getdate->getYear(), $getdate->getWeekOfYear(), DATE_CALC_BEGIN_WEEKDAY);
        $endtime = clone $getdate;
        $endtime->modify('+6 days 23:23:59');
        $searchStarttime = clone $starttime;
        $searchStarttime->sub(\DateInterval::createFromDateString((string)($this->settings['view']['week']['startPointCorrection'] ?? '0 days')));
        $searchEndtime = clone $endtime;
        $searchEndtime->add(\DateInterval::createFromDateString((string)($this->settings['view']['week']['endPointCorrection'] ?? '0 days')));
        $events = $this->fetchEvents($searchStarttime, $searchEndtime);
        $this->view->assign('starttime', $starttime);
        $this->view->assign('endtime', $endtime);
        $this->view->assign('today', new CalendarDateTime());
        $this->view->assign('getdate', $getdate);
        $this->view->assign('nextWeek', $nextWeek);
        $this->view->assign('prevWeek', $prevWeek);
        $this->view->assign('events', $events);
        $this->view->assign('view', 'week');
        $this->view->assign('calendarViews', $this->getCalendarViewConfiguration('week'));
        $this->view->assign('eventLink', $this->getEventLinkConfiguration());
        $this->view->assign('contentUid', $this->getContentUid());
        $this->addToAllViews();
        return $this->htmlResponse();
    }

    private function getGetdate()
    {
        $getdate = new CalendarDateTime();
        $getdate->setTime(0, 0, 0, 0);
        $urlParams = $this->getUrlParameters();
        if ($urlParams['year'] != 'not_submitted') {
            $getdate->setYear((int)($urlParams['year']));
        }
        if ($urlParams['month'] != 'not_submitted') {
            $getdate->setMonth((int)($urlParams['month']));
        }
        if ($urlParams['day'] != 'not_submitted') {
            $getdate->setDay((int)($urlParams['day']));
        }
        return $getdate;
    }

    public function dayAction(): ResponseInterface
    {
        $getdate = $this->getGetdate();

        $nextDay = clone $getdate;
        $nextDay->modify('+1 day');
        $prevDay = clone $getdate;
        $prevDay->modify('-1 day');

        $starttime = clone $getdate;
        $endtime = clone $nextDay;
        $endtime->modify('+1 second');
        $searchStarttime = clone $starttime;
        $searchStarttime->sub(\DateInterval::createFromDateString((string)($this->settings['view']['day']['startPointCorrection'] ?? '0 days')));
        $searchEndtime = clone $endtime;
        $searchEndtime->add(\DateInterval::createFromDateString((string)($this->settings['view']['day']['endPointCorrection'] ?? '0 days')));
        $events = $this->fetchEvents($searchStarttime, $searchEndtime);
        $this->view->assign('starttime', $starttime);
        $this->view->assign('endtime', $endtime);
        $this->view->assign('today', new CalendarDateTime());
        $this->view->assign('getdate', $getdate);
        $this->view->assign('nextDay', $nextDay);
        $this->view->assign('prevDay', $prevDay);
        $this->view->assign('events', $events);
        $this->view->assign('view', 'day');
        $this->view->assign('calendarViews', $this->getCalendarViewConfiguration('day'));
        $this->view->assign('eventLink', $this->getEventLinkConfiguration());
        $this->view->assign('contentUid', $this->getContentUid());
        $this->addToAllViews();
        return $this->htmlResponse();
    }

    protected function getDefaultView()
    {
        return HelperFunctions::trimExplode($this->settings['view']['allowedViews'] ?? '')[0] ?? '';
    }

    /**
     * Calendar filter of the plugin (FlexForm "Filter": calendarMode / calendarSelection,
     * TypoScript: view.calendarMode / view.calendar / view.calendarIds):
     * - 0: all calendars (limited to view.calendarIds if set in TypoScript)
     * - 1: only the selected calendars
     * - 2: all calendars except the selected ones
     *
     * @return array{0: array<int>, 1: array<int>} [calendars to show, calendars to exclude]
     */
    protected function getCalendarFilter(): array
    {
        $mode = (int)($this->settings['view']['calendarMode'] ?? 0);
        $selected = array_map('intval', HelperFunctions::trimExplode($this->settings['view']['calendar'] ?? ''));
        $typoScriptIds = array_map('intval', HelperFunctions::trimExplode($this->settings['view']['calendarIds'] ?? ''));
        $selected = array_values(array_filter(array_unique($selected), static fn(int $uid): bool => $uid > 0));
        $typoScriptIds = array_values(array_filter(array_unique($typoScriptIds), static fn(int $uid): bool => $uid > 0));

        return match ($mode) {
            1 => [$selected !== [] ? $selected : $typoScriptIds, []],
            2 => [$typoScriptIds, $selected],
            default => [$typoScriptIds, []],
        };
    }

    protected function fetchEvents(\DateTime $start, \DateTime $end)
    {
        [$calendarIds, $excludedCalendarIds] = $this->getCalendarFilter();
        $events = $this->eventDispatcher->dispatch(new SearchEventsEvent($start, $end, $calendarIds, $excludedCalendarIds))->getEvents();
        if (count($events) > 0) {
            $events = $this->filterEvents($events);
        }
        return $events;
    }

    protected function fetchEvent(string $type, int $uid, CalendarDateTime $getdate)
    {
        [$calendarIds, $excludedCalendarIds] = $this->getCalendarFilter();
        $event = $this->eventDispatcher->dispatch(new GetEventEvent($type, $uid, $getdate, $calendarIds, $excludedCalendarIds))->getEvent();
        if ($event != null) {
            $events = $this->filterEvents([$event]);
            if (count($events) > 0) {
                return $events[0];
            }
        }
        return null;
    }

    /**
     * Event action for this controller.
     * Fetches the event and hands it over to the rendering.
     */
    public function eventAction(): ResponseInterface
    {
        $params = $this->getUrlParameters();
        $getdate = $this->getGetdate();
        $event = $this->fetchEvent((string)$params ['type'], (int)$params ['uid'], $getdate);
        $this->view->assign('event', $event);
        $this->view->assign('view', 'event');
        $this->view->assign('viewLinks', $this->getViewLinkConfiguration());
        $this->addToAllViews();
        $this->useTemplateFile((string)($this->settings ['view'] ['event'] ['templateFile'] ?? ''));
        return $this->htmlResponse();
    }

    /**
     * Location action for this controller.
     * Fetches the location and hands it over to the rendering.
     */
    public function locationAction(): ResponseInterface
    {
        $params = $this->getUrlParameters();
        $location = $this->fetchLocation((string)$params ['type'], (int)$params ['uid']);
        $this->view->assign('location', $location);
        $this->view->assign('view', 'location');
        $this->addToAllViews();
        return $this->htmlResponse();
    }

    /**
     * Organizer action for this controller.
     * Fetches the organizer and hands it over to the rendering.
     */
    public function organizerAction(): ResponseInterface
    {
        $params = $this->getUrlParameters();
        $organizer = $this->fetchOrganizer((string)$params ['type'], (int)$params ['uid']);
        $this->view->assign('organizer', $organizer);
        $this->view->assign('view', 'organizer');
        $this->addToAllViews();
        return $this->htmlResponse();
    }

    /**
     * @param array $events
     */
    protected function filterEvents($events)
    {
        return $this->eventDispatcher->dispatch(new FilterEventsEvent($events, $this->getSettings()))->getEvents();
    }

    protected function fetchLocation(string $type, int $uid)
    {
        return $this->eventDispatcher->dispatch(new GetLocationEvent($type, $uid))->getLocation();
    }

    protected function fetchOrganizer(string $type, int $uid)
    {
        return $this->eventDispatcher->dispatch(new GetOrganizerEvent($type, $uid))->getOrganizer();
    }

    /**
     * Event action for this controller.
     * Fetches the event and hands it over to the rendering.
     */
    public function icsAction(): ResponseInterface
    {
        // Only if the ICS view is one of the allowed views of the plugin
        if (!in_array('ics', (array)($this->settings ['view'] ['allowedViewsToLinkTo'] ?? []), true)) {
            return $this->responseFactory->createResponse(404)
              ->withHeader('Content-Type', 'text/plain; charset=utf-8')
              ->withBody($this->streamFactory->createStream('The ICS view is not allowed'));
        }
        $params = $this->getUrlParameters();
        $event = $this->fetchEvent((string)$params ['type'], (int)$params ['uid'], $this->getGetdate());
        if (!$event) {
            return $this->responseFactory->createResponse(404)
              ->withHeader('Content-Type', 'text/plain; charset=utf-8')
              ->withBody($this->streamFactory->createStream('Could not find the event'));
        }
        $method = 'PUBLISH';
        $version = ExtensionManagementUtility::getExtensionVersion('cal');
        $this->view->assign('event', $event);
        $this->view->assign('view', 'event');
        $this->view->assign('method', $method);
        $this->view->assign('version', $version);
        $this->addToAllViews();
        return $this->responseFactory->createResponse()->withAddedHeader('Expires', '0')->withAddedHeader('Pragma', '')->withAddedHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')->withAddedHeader('Content-Disposition', 'attachment; filename="' . $this->getIcsFileName((string)$event->getTitle()) . '"')->withAddedHeader('Content-Type', 'text/calendar; charset=utf-8')->withBody($this->streamFactory->createStream($this->view->render()));
    }

    /**
     * Builds a safe file name for the ics download (no quotes, slashes or line breaks).
     */
    private function getIcsFileName(string $title): string
    {
        $fileName = trim((string)preg_replace('/[^A-Za-z0-9._-]+/', '_', $title), '_');
        return ($fileName !== '' ? $fileName : 'event') . '.ics';
    }

    private function addToAllViews()
    {
        $this->view->assign('settings', $this->settings);
        $this->view->assign('rightsManager', $this->getRightsManager());
    }

    protected function getRightsManager()
    {
        return new RightsManager($this->settings, $this->configuration);
    }

    /**
     * get and validate relevant url arguments
     *
     * @return array<string, string> the prepared url parameters
     */
    private function getUrlParameters()
    {
        $urlParameters =  [
            'view' => 'list',
            'type' => 'not_submitted',
            'uid' => 'not_submitted',
            'day' => 'not_submitted',
            'week' => 'not_submitted',
            'month' => 'not_submitted',
            'year' => 'not_submitted',
        ];
        if ($this->request->hasArgument('view')) {
            $value = $this->getStringArgument('view');
            if ($value != '') {
                $urlParameters ['view'] = $value;
                $this->view->assign('view', $value);
            }
        }
        // Links (and speaking URLs) omit the record type of the default records
        $urlParameters ['type'] = self::DEFAULT_RECORD_TYPES[$this->request->getControllerActionName()] ?? 'not_submitted';
        if ($this->request->hasArgument('type')) {
            $value = $this->getStringArgument('type');
            if ($value != '') {
                $urlParameters ['type'] = $value;
            }
        }
        if ($this->request->hasArgument('uid')) {
            $urlParameters ['uid'] = $this->getStringArgument('uid');
        }
        if ($this->request->hasArgument('day')) {
            $urlParameters ['day'] = $this->getStringArgument('day');
        }
        if ($this->request->hasArgument('week')) {
            $urlParameters ['week'] = $this->getStringArgument('week');
        }
        if ($this->request->hasArgument('month')) {
            $urlParameters ['month'] = $this->getStringArgument('month');
        }
        if ($this->request->hasArgument('year')) {
            $urlParameters ['year'] = $this->getStringArgument('year');
        }
        return $urlParameters;
    }

    /**
     * Scalar request argument, html escaped. Arrays (manipulated urls) are ignored.
     */
    private function getStringArgument(string $name): string
    {
        $value = $this->request->getArgument($name);
        return is_scalar($value) ? htmlspecialchars((string)$value) : '';
    }
}
