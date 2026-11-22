..  include:: /Includes.rst.txt

..  _caldav:

======
CalDAV
======

cal contains a CalDAV server (formerly the extension `caldav`) based on
`sabre/dav`. CalDAV clients like Apple Calendar, Thunderbird or DAVx⁵ can read
and edit the events.

..  contents::
    :local:
    :depth: 1

..  _caldav-users:

Users and calendars
===================

CalDAV uses frontend users (`fe_users`) for the login.

#.  Create a frontend user, or use an existing one.
#.  Select the calendars of the user in the field
    :guilabel:`The users private calendar` of the frontend user record. Only
    these calendars are available in CalDAV.
#.  Add the account to the CalDAV client with the URL of the next section and
    the username and password of the frontend user.

External and ICS file calendars are read-only, the events of the other
calendars can be created, changed and deleted in the client.

..  important::
    The client sends the password with HTTP Basic authentication. Use CalDAV
    only with HTTPS.

..  _caldav-url:

The URL
=======

The CalDAV server is a PSR-15 middleware, no page or plugin is needed. It
answers below the base URL of the site, followed by `/caldav`:

..  code-block:: text

    <site base>/caldav/                                   server (start of the discovery)
    <site base>/caldav/principals/<username>/             the user (principal)
    <site base>/caldav/calendars/<username>/              all calendars of the user
    <site base>/caldav/calendars/<username>/<calendar>/   a single calendar

`<site base>`
    The base of the site in :guilabel:`Site Management > Sites`, including a
    path if the site has one. The base of a language (e.g. `/en/`) does not
    belong to the URL.

`<username>`
    The username of the frontend user.

`<calendar>`
    The title of the calendar record, URL encoded: spaces become `%20`,
    umlauts are encoded as UTF-8 (`ü` = `%C3%BC`). Use titles without `/`.

Examples for a site with the base `https://www.example.org/` and the frontend
user `anna`:

..  code-block:: text

    https://www.example.org/caldav/
    https://www.example.org/caldav/calendars/anna/
    https://www.example.org/caldav/calendars/anna/Club%20events/

A site in a subdirectory, e.g. the base `https://www.example.org/club/`:

..  code-block:: text

    https://www.example.org/club/caldav/
    https://www.example.org/club/caldav/calendars/anna/Training/

..  tip::
    Open :samp:`<site base>/caldav/` in a browser: the server asks for a login.
    With the TYPO3 context `Development` it shows a page with the calendars of
    the user after the login, which helps to find the exact URL of a calendar.

..  _caldav-select:

Select single calendars
=======================

There are three ways to decide which calendars a user gets:

In TYPO3
    Only the calendars selected in :guilabel:`The users private calendar` of
    the frontend user are available. Create a frontend user for each group of
    calendars, e.g. a user `club-board` with the internal calendars and a user
    `club-public` with the public ones.

In the client, with the server URL
    Clients with discovery (Apple Calendar, DAVx⁵, Thunderbird) find all
    calendars of the user from :samp:`<site base>/caldav/` and list them.
    Uncheck the calendars that should not be synchronized.

In the client, with the URL of a calendar
    Add only one calendar with its full URL, e.g.
    :samp:`https://www.example.org/caldav/calendars/anna/Training/`. Repeat it
    for further calendars.

..  _caldav-clients:

Clients
=======

The menus differ between the versions of the clients, the following steps are
a guideline.

Thunderbird
    :guilabel:`New Calendar` > :guilabel:`On the Network`, enter the username
    and as :guilabel:`Location` the server URL
    (:samp:`https://www.example.org/caldav/`) or the URL of a single calendar.
    Thunderbird lists the found calendars, select the ones to subscribe.

Apple Calendar (macOS)
    :guilabel:`Settings` > :guilabel:`Accounts` > :guilabel:`+` >
    :guilabel:`Other CalDAV Account`. Account type :guilabel:`Manual`,
    username, password and as server address the server URL. All calendars of
    the user appear, single calendars can be hidden.

iPhone and iPad
    :guilabel:`Settings` > :guilabel:`Calendar` > :guilabel:`Accounts` >
    :guilabel:`Add Account` > :guilabel:`Other` >
    :guilabel:`Add CalDAV Account`. Server: the server URL, then username and
    password.

Android (DAVx⁵)
    Add an account with :guilabel:`Login with URL and user name`, base URL:
    the server URL. DAVx⁵ lists the calendars, activate the ones to
    synchronize.

..  _caldav-well-known:

Automatic discovery
===================

Many clients only need the domain: they ask
:samp:`https://www.example.org/.well-known/caldav` first (RFC 6764). cal
redirects :samp:`<site base>/.well-known/caldav` to the server URL
:samp:`<site base>/caldav/`, so entering `https://www.example.org` as server
is enough for a site on the root of the domain.

A site in a subdirectory (e.g. `https://www.example.org/club/`) only gets
:samp:`https://www.example.org/club/.well-known/caldav`. The clients ask the root
of the domain, so enter the full server URL in the client or redirect the root
in the web server, e.g. for Apache:

..  code-block:: apache

    Redirect 301 /.well-known/caldav /club/caldav/

..  _caldav-ics-data:

iCalendar data
==============

CalDAV transfers the iCalendar data of the events. Events created or changed
in the backend get it when they are saved. Events without iCalendar data (e.g.
after the upgrade from cal 1.x) are not visible in CalDAV clients: create the
data with the backend module :guilabel:`Cal ICS Generator`
(see :ref:`backend-modules`).
