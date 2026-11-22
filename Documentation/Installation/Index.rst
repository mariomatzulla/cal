..  include:: /Includes.rst.txt

..  _installation:

============
Installation
============

..  _installation-composer:

Install the extension
=====================

Composer based installations:

..  code-block:: bash

    composer require mariomatzulla/cal

Installations without Composer: install the extension from the TYPO3 Extension
Repository (TER) with the module :guilabel:`Admin Tools > Extensions`.

Afterwards run :guilabel:`Admin Tools > Maintenance > Analyze Database Structure`
to create the tables and fields of cal.

..  _installation-site-set:

Include the configuration
=========================

Add the site sets to the site configuration
(:guilabel:`Site Management > Sites`, or :file:`config/sites/<site>/config.yaml`):

Calendar Base (cal)
    TypoScript settings of the plugin and the page type of the iCalendar
    download. Required.

Calendar Base (cal): default styles
    The default CSS of the calendar views. Optional, leave it out if you
    write your own styles.

..  code-block:: yaml
    :caption: config/sites/<site>/config.yaml

    dependencies:
      - mariomatzulla/cal
      - mariomatzulla/cal-styles

Sites without site sets include the static TypoScript templates
"Calendar Base (cal)" and "Calendar Base (cal): default styles" in their
TypoScript record instead.

The site set has settings for the storage folders, the image sizes, the time
format and the options of the iCalendar download, see :ref:`typoscript`.

..  _installation-records:

Create the records
==================

#.  Create a folder for the calendar records.
#.  Create a calendar record in the folder, then events, and optionally
    locations, organizers and categories. See :ref:`editors`.

..  _installation-plugin:

Add the plugin
==============

#.  Create the content element :guilabel:`Calendar Plugin` on the page that
    shows the calendar.
#.  Select the allowed views on the tab :guilabel:`Plugin`. The first view is
    the default view.
#.  Optionally select the calendars and categories on the tab
    :guilabel:`Filters`. Without a selection the plugin shows the events of
    all calendars.
#.  Optionally limit the plugin to the folder of the records with the site
    setting :guilabel:`Storage folders` (`pidList`), see
    :ref:`typoscript-general`.

See :ref:`plugin` for all options.

..  _installation-urls:

Speaking URLs
=============

Import the route enhancer of cal in the site configuration to get URLs like
:samp:`/2026/11/21` or :samp:`/event/2026-11-21/cleaning-day`. See
:ref:`speaking-urls`.

..  _installation-update:

After an update
===============

Run :guilabel:`Analyze Database Structure` and the upgrade wizards of the
extension (:guilabel:`Admin Tools > Upgrade > Upgrade Wizard` or
:bash:`vendor/bin/typo3 upgrade:run`). Updating from cal 1.x is described in
:ref:`upgrade`.
