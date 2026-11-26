..  include:: /Includes.rst.txt

..  _typoscript:

==========
TypoScript
==========

All settings are below `plugin.tx_cal.settings`. The site set "Calendar Base
(cal)" defines the defaults in
:file:`EXT:cal/Configuration/Sets/Cal/setup.typoscript`. Settings of the plugin
form override them (see :ref:`plugin`).

..  note::
    Before cal 4 the settings were below `plugin.tx_cal_controller`. They are
    still read but deprecated.

Date and time formats are ICU patterns, e.g. `dd.MM.yyyy`, `EEEE` (weekday),
`MMMM` (month name) or `HH:mm`. The language of the names comes from the site
language.

..  contents::
    :local:
    :depth: 1

..  _typoscript-site-settings:

Site settings
=============

The site set has these settings (:guilabel:`Site Management > Settings`). They
are used by the TypoScript below.

========================================  ==========  ===============  =====================================
Setting                                   Type        Default          Description
========================================  ==========  ===============  =====================================
`plugin.tx_cal.settings.pidList`          string      (empty)          Storage folders, see below
`plugin.tx_cal.settings.recursive`        int         0                Recursion levels of the storage folders
`…view.weekStartDay`                      string      `monday`         First day of the week (`monday`, `sunday`)
`plugin.tx_cal.settings.singleMaxW`       int         1400             Width of the images of the event view
`plugin.tx_cal.settings.listMaxW`         int         400              Width of the further images
`plugin.tx_cal.settings.view.timeFormat`  string      `HH:mm`          Time format of all views
`…view.privateEvents.busyTitle`          string      (empty)          Text of private events for visitors
`…view.ics.showIcsLinks`                  bool        true             Show the iCalendar download link
`…view.ics.typeNum`                       int         150              Page type of the ..  _typoscript-private-events:

Private events
==============

`view.privateEvents.busyTitle`
    Events marked as private or confidential (field :guilabel:`Visibility`,
    iCalendar `CLASS`, e.g. set in Thunderbird) are shown to visitors with this
    text instead of the title, only with their time: no texts, location, images,
    link and iCalendar download. Empty (default): "Busy", translated ("Belegt").

    The owners of the calendar see the complete event after the login, marked
    with an icon (struck through eye): frontend users and groups of the field
    :guilabel:`Owner` of the calendar and frontend users with the calendar in
    their field :guilabel:`Calendars` (CalDAV). These pages are not cached.

iCalendar download
========================================  ==========  ===============  =====================================

..  _typoscript-general:

General
=======

`pidList`
    Storage folders: comma separated page ids of the folders containing the
    events, locations and organizers. Only records of these folders are shown.
    Empty (default): records of all pages.

`recursive`
    The subpages of the storage folders up to this number of levels are
    included as well. Default `0`: only the folders themselves.

    Different plugins can use different folders with the TypoScript field of
    the plugin, e.g. `pidList = 12,13`.

`view.weekStartDay`
    First day of the week in the month, week, day and year view: `monday`
    (default) or `sunday`. Also a site setting and a field of the plugin.
    The weeks keep their ISO 8601 numbers: with `sunday` a week starts on the
    Sunday before the Monday of the ISO week. The recurrence rules are not
    affected.

`view.allowedViews`
    Comma separated list of the allowed views, the first one is the default
    view (see :ref:`plugin-general`). Values: `year`, `month`, `week`, `day`,
    `list`, `event`, `location`, `organizer`, `ics`.

`view.calendarIds`, `view.categoryIds`
    Preselected calendars and categories (comma separated uids).

`view.dayLinkTarget`, `view.weekLinkTarget`, `view.monthLinkTarget`, `view.yearLinkTarget`
    View that is opened by a link on a day, week number, month or year.
    Default: the view of the same name.

`view.linkOnlyDatesWithEvents`
    Only days with events are linked. Default `true`.

`view.backLink`
    Content object of the back link of the event, location and organizer view.
    Default: a link with `history.back()`.

`dontListenToFlexForm`
    `1` ignores the values of the plugin form, see
    :ref:`plugin-ignore-flexform`.

..  _typoscript-event:

Event view
==========

`view.event.eventViewPid`
    Page with the event view. Empty: the current page.

`view.event.templateFile`
    Own Fluid template of the event view, e.g.
    :file:`EXT:site_package/Resources/Private/Templates/Cal/Event.html`.

`view.event.event.weekdayFormat`, `view.event.event.longDateFormat`, `view.event.event.timeFormat`
    Formats of the date line. Defaults `EEEE`, `dd. MMMM yyyy`, the time
    format of the site settings.

`view.event.event.image.width`, `view.event.event.image.furtherImagesWidth`
    Size of the first image and of the further images (TYPO3 image
    dimensions, e.g. `1400c`).

..  _typoscript-location:

Location and organizer view
===========================

`view.location.locationViewPid`, `view.organizer.organizerViewPid`
    Page with the location / organizer view. Empty: the current page. Links
    to these views are only rendered if the view is allowed in the plugin or
    a page is set.

`view.location.image`, `view.organizer.image`
    `width`
        Maximum width of the first image, default `720m`: the proportions are
        kept. A height like `480c` crops the image.

    `height`
        Height of the first image, empty by default.

    `thumbnailWidth`, `thumbnailHeight`
        Size of the further images, default `120c` × `80c`.

..  _typoscript-day:

Day and week view
=================

`view.day.dayStart`, `view.day.dayEnd`
    Range of the time grid (`HHMMSS`), default `070000` and `230000`.

`view.day.extendGridToEvents`
    `1` extends the time grid to full hours if events start before or end
    after the range. Default `0`: the events are cut at the borders.

`view.day.gridLength`
    Minutes between two lines of the grid, default `30`.

`view.day.hourFormat`, `view.day.hourSuffixFormat`
    Labels of the hours: the hour and a small suffix. Defaults `H` and `mm`.
    For 12-hour clocks e.g. `h` and `a`.

`view.day.displayDate.format`
    Title of the day view, default `EEEE dd.MM.yyyy`.

`view.week.columnWeekdayFormat`, `view.week.columnDateFormat`
    Column headers of the week and day view, defaults `EEE` and `d. MMM`.

`view.week.displayDate.startFormat`, `view.week.displayDate.endFormat`
    Title of the week view, defaults `dd. MMM` and `dd. MMM yyyy`.

`view.week.timeFormatWeek`
    Time of the events.

..  _typoscript-month:

Month view
==========

`view.month.displayDate`
    Title, default `MMMM yyyy`.

`view.month.weekdayFormatLargeMonth`
    Weekday names of the header, default `EEE`.

`view.month.monthOffStyle`, `view.month.monthTodayStyle`, `view.month.eventDayStyle`
    CSS classes of days of other months, today and days with events.

..  _typoscript-year:

Year view
=========

`view.year.dateFormatMonth`
    Title of the months, default `MMMM`.

`view.year.weekdayFormat`
    Weekdays above the months, default `EEEEEE` (two letters).

`view.year.popoverDateFormat`, `view.year.timeFormat`
    Date and time in the box with the events of a day, shown when hovering a
    day. Default `EEEE, d. MMMM yyyy`.

..  _typoscript-list:

List view
=========

`view.list.starttime`, `view.list.endtime`
    Range of the list, PHP relative formats. Defaults `now` and `+1 month`.

`view.list.maxEvents`
    Maximum number of events, default `100`.

`view.list.templateFile`
    Own Fluid template of the list view.

`view.list.heading.dateFormat`
    Format of the heading, default `MMMM yyyy`.

`view.list.event.timeFormat`
    Time of the events.

`view.list.event.card`
    Event cards of the default template:

    `imageWidth`, `imageHeight`
        First image of the event, cropped, default `720c` × `480c`.

    `weekdayFormat`, `dateFormat`
        Weekday and date of multi-day events.

    `descriptionLength`
        The card shows the teaser, or the description shortened to this
        length. Default `200`.

    `showAllImages`
        `1` shows the further images as thumbnails below the "read more"
        link. Also in the plugin form.

    `thumbnailWidth`, `thumbnailHeight`
        Size of the thumbnails, default `120c` × `80c`.

..  _typoscript-lightbox:

Lightbox
========

`view.lightbox.maxWidth`, `view.lightbox.maxHeight`
    Size of the enlarged images in the lightbox of the event and list view,
    default `1600` × `1200`.

..  _typoscript-ics:

iCalendar download
==================

`view.ics.showIcsLinks`
    Show the link "Add to calendar (.ics)" in the event view. The view `ics`
    must be allowed.

`view.ics.typeNum`
    Page type of the download, default `150`. With the route enhancer the
    download has the URL :samp:`/ics/<event>.ics`, see :ref:`speaking-urls`.
