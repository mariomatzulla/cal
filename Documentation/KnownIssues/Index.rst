..  include:: /Includes.rst.txt

..  _known-issues:

============
Known issues
============

*   The marker based templates of cal 1.x cannot be migrated.
*   Events without iCalendar data are not visible in CalDAV clients, create the
    data with the backend module :guilabel:`Cal ICS Generator`.
*   Changes of a series in a CalDAV client with "this and all following dates"
    (`RECURRENCE-ID` with `RANGE=THISANDFUTURE`) are not evaluated, only the
    changed date is taken over. Most clients (e.g. Thunderbird) split the
    series into two events instead, which works.
*   The transparency of events (`TRANSP`, "show as free / busy") is kept for
    CalDAV clients, but not used on the website.
*   Views showing the upcoming events change with the date: the site set
    clears the page cache at midnight (`config.cache_clearAtMidnight = 1`).
    Pages with an own, longer cache lifetime may show the events of the day
    before until their cache expires, see :ref:`caching`.
