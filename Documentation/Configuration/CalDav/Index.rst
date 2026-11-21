..  include:: /Includes.rst.txt

..  _caldav:

======
CalDAV
======

cal contains a CalDAV server (formerly the extension `caldav`) based on
`sabre/dav`. CalDAV clients like Apple Calendar, Thunderbird or DAVx⁵ can read
and edit the events.

..  _caldav-endpoint:

Endpoint
========

The server is available at :samp:`<site base>/caldav`, e.g.
:samp:`https://www.example.org/caldav`. It is a PSR-15 middleware, no page or
plugin is needed.

..  _caldav-users:

Users and calendars
===================

#.  Create a frontend user.
#.  Assign the calendars to the user in the field
    :guilabel:`The users private calendar` of the frontend user record.
#.  Add the account to the CalDAV client: server URL as above, the username and
    the password of the frontend user.

The user sees the assigned calendars. External and ICS file calendars are
read-only.

..  _caldav-ics-data:

iCalendar data
==============

CalDAV transfers the iCalendar data of the events. Events created or changed
in the backend get it when they are saved. Events without iCalendar data (e.g.
after the upgrade from cal 1.x) are not visible in CalDAV clients: create the
data with the backend module :guilabel:`Cal ICS Generator`
(see :ref:`backend-modules`).
