..  include:: /Includes.rst.txt

..  _external-calendars:

==================================
External calendars and ICS files
==================================

Calendars of the type :guilabel:`External Calendar` import the events of an
iCalendar feed (:guilabel:`External Calendar URL`), calendars of the type
:guilabel:`Include ICS File` the events of uploaded `.ics` files.

*   The events are imported when the calendar is saved.
*   Imported events cannot be edited. They are replaced by the next import.
*   Imported events get a URL segment and are indexed like other events.

..  _external-calendars-scheduler:

Regular import
==============

Set :guilabel:`Refresh Interval (Minutes)` of the calendar and save it. cal
creates a scheduler task "Calendar Base: Import external calendar" for the
calendar and shows a message with the number of the task. The task is updated
when the interval changes and removed when the interval is emptied or the
calendar is deleted.

The extension `typo3/cms-scheduler` must be installed and the scheduler must
run regularly, e.g. by a cron job:

..  code-block:: bash

    */5 * * * * /path/to/project/vendor/bin/typo3 scheduler:run

The task can also be created manually in :guilabel:`Administration > Scheduler`
with the class "Calendar Base: Import external calendar" and the calendar.
