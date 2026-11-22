..  include:: /Includes.rst.txt

..  _known-issues:

============
Known issues
============

*   The `INTERVAL` of monthly and weekly rules with several days counts the
    occurrences, not the months or weeks. Example: `FREQ=MONTHLY;INTERVAL=2;BYMONTHDAY=1,15`
    takes every second date instead of the 1st and 15th of every second month.
    Daily rules and yearly rules work as defined in RFC 5545.
*   Locations and organizers of `tt_address` or frontend users can be selected
    in events, but the location and organizer views of cal show the built-in
    records only.
*   The marker based templates of cal 1.x cannot be migrated.
*   Events without iCalendar data are not visible in CalDAV clients, create the
    data with the backend module :guilabel:`Cal ICS Generator`.
