..  include:: /Includes.rst.txt

.. _speaking-urls:

=============
Speaking URLs
=============

cal ships a route enhancer for the site configuration (it replaces the RealURL configuration of
cal 1.x). Import it in :file:`config/sites/<site>/config.yaml`:

.. code-block:: yaml

   imports:
     - { resource: 'EXT:cal/Configuration/Routes/Cal.yaml' }
     # optional: "/ics/<event>.ics" instead of "?type=150"
     - { resource: 'EXT:cal/Configuration/Routes/CalPageType.yaml' }

Only import :file:`CalPageType.yaml` if the site has no own decorator ``PageTypeSuffix``. Otherwise add
``'.ics': 150`` to the ``map`` of the existing decorator.

Resulting URLs below the page of the plugin:

============  ====================================  ==========================================
View          English                               German (locale ``de_*``)
============  ====================================  ==========================================
Year          ``/2026``                             ``/2026``
Month         ``/2026/11``                          ``/2026/11``
Week          ``/2026/week/47``                     ``/2026/woche/47``
Day           ``/2026/11/21``                       ``/2026/11/21``
Event         ``/event/2026-11-21/cleaning-day``    ``/termin/2026-11-21/friedhofsreinigung``
Location      ``/location/community-hall``          ``/ort/gemeindehaus``
Organizer     ``/organizer/sports-club``            ``/veranstalter/sportverein``
List          ``/list``                             ``/liste``
ICS file      ``/ics/cleaning-day.ics``             ``/ics/friedhofsreinigung.ics``
============  ====================================  ==========================================

The date of an event is part of its URL, so every date of a recurring event has its own URL.

URL segments
============

Events, locations and organizers have the field "URL segment" (``slug``). It is created from the
title / name when the record is saved in the backend, by the import of external calendars and by
CalDAV. Existing records get it from the upgrade wizard "Calendar Base (cal): create the URL segments
of events, locations and organizers". Records without URL segment are linked with the uid as
parameter.

Own words
=========

The words of the paths come from the aspects ``label_week``, ``label_event``, ``label_list``, ``label_ics``,
``label_location`` and ``label_organizer`` (type ``LocaleModifier``). Override them after the import,
e.g. for English only URLs with other words:

.. code-block:: yaml

   imports:
     - { resource: 'EXT:cal/Configuration/Routes/Cal.yaml' }

   routeEnhancers:
     Cal:
       aspects:
         label_week:
           type: LocaleModifier
           default: calendar-week
         label_event:
           type: LocaleModifier
           default: events
         label_location:
           type: LocaleModifier
           default: venues
         label_organizer:
           type: LocaleModifier
           default: hosts

Complete configuration without import
=====================================

The same configuration written directly into the site configuration (English words only):

.. code-block:: yaml

   routeEnhancers:
     Cal:
       type: Extbase
       extension: Cal
       plugin: Controller
       defaultController: 'Content::index'
       routes:
         - { routePath: '/{year}', _controller: 'Content::year' }
         - { routePath: '/{year}/{month}', _controller: 'Content::month' }
         - { routePath: '/{year}/week/{week}', _controller: 'Content::week' }
         - { routePath: '/{year}/{month}/{day}', _controller: 'Content::day' }
         - routePath: '/event/{year}-{month}-{day}/{event}/{event_type}'
           _controller: 'Content::event'
           _arguments: { event: uid, event_type: type }
         - routePath: '/ics/{event}'
           _controller: 'Content::ics'
           _arguments: { event: uid }
         - { routePath: '/list', _controller: 'Content::list' }
         - routePath: '/location/{location}/{location_type}'
           _controller: 'Content::location'
           _arguments: { location: uid, location_type: type }
         - routePath: '/organizer/{organizer}/{organizer_type}'
           _controller: 'Content::organizer'
           _arguments: { organizer: uid, organizer_type: type }
       defaults:
         event_type: tx_cal_event
         location_type: tx_cal_location
         organizer_type: tx_cal_organizer
       requirements:
         year: '\d{4}'
         event_type: '[a-z0-9_]+'
         location_type: '[a-z0-9_]+'
         organizer_type: '[a-z0-9_]+'
       static:
         year: true
         event_type: true
         location_type: true
         organizer_type: true
       aspects:
         week: { type: StaticRangeMapper, start: '1', end: '53' }
         month:
           type: StaticValueMapper
           map: { '01': '1', '02': '2', '03': '3', '04': '4', '05': '5', '06': '6', '07': '7', '08': '8', '09': '9', '10': '10', '11': '11', '12': '12' }
         day:
           type: StaticValueMapper
           map: { '01': '1', '02': '2', '03': '3', '04': '4', '05': '5', '06': '6', '07': '7', '08': '8', '09': '9', '10': '10', '11': '11', '12': '12', '13': '13', '14': '14', '15': '15', '16': '16', '17': '17', '18': '18', '19': '19', '20': '20', '21': '21', '22': '22', '23': '23', '24': '24', '25': '25', '26': '26', '27': '27', '28': '28', '29': '29', '30': '30', '31': '31' }
         event: { type: PersistedAliasMapper, tableName: tx_cal_domain_model_event, routeFieldName: slug }
         location: { type: PersistedAliasMapper, tableName: tx_cal_domain_model_location, routeFieldName: slug }
         organizer: { type: PersistedAliasMapper, tableName: tx_cal_domain_model_organizer, routeFieldName: slug }

     PageTypeSuffix:
       type: PageType
       default: ''
       index: ''
       map:
         '.ics': 150

The type of a record (``event_type`` etc.) is only part of the URL for other record types than the
default ones, e.g. locations of other extensions.
