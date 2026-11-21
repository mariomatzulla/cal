..  include:: /Includes.rst.txt

..  _styling:

======================
Styling and templates
======================

..  _styling-css:

CSS
===

The site set "Calendar Base (cal): default styles" includes the CSS files of
:file:`EXT:cal/Resources/Public/Css/Cal/`:

================  ==========================================================
File              Content
================  ==========================================================
`tokens.css`      CSS custom properties (colors, fonts, spacing, radii)
`base.css`        Header, navigation, view switch, card of the views
`month.css`       Month view
`year.css`        Year view
`timegrid.css`    Time grid of the week and day view
`events.css`      Events in the views
`categories.css`  Colors of the calendar styles (`*_catheader`)
`list.css`        List view
`detail.css`      Event, location and organizer view
================  ==========================================================

..  _styling-tokens:

Custom properties
-----------------

All files use the custom properties of :file:`tokens.css`, which are defined on
`.tx-cal-controller`. Override them in the CSS of the site package to adapt the
design:

..  code-block:: css

    .tx-cal-controller {
      --cal-color-brand: #b45309;
      --cal-color-accent-surface: #fef3c7;
      --cal-color-today-surface: #ecfccb;
      --cal-event-color: #0f766e;
      --cal-event-border-color: #115e59;
      --cal-radius-lg: 0;
    }

Important groups:

*   `--cal-color-*`: surfaces, text, borders, cards, links
*   `--cal-event-color`, `--cal-event-border-color`: default color of events
*   `--cal-font-*`, `--cal-space-*`, `--cal-radius-*`, `--cal-shadow-*`

..  _styling-colors:

Event colors
------------

The color of an event comes from

#.  the color of its category (see :ref:`editors-category`),
#.  otherwise the style of its calendar (field :guilabel:`Header style`),
#.  otherwise `--cal-event-color` of :file:`tokens.css`.

Own calendar styles are added like this:

..  code-block:: css

    .tx-cal-controller :is(.mint_catheader, .mint_catheader_allday, .mint_catheader_text) {
      --cal-event-color: #3eb489;
      --cal-event-border-color: #1d7a5c;
    }

Leave out the site set "default styles" to write the whole CSS yourself.

..  _styling-templates:

Fluid templates
===============

The views are Fluid templates. Copy the files you want to change to your site
package and add the paths:

..  code-block:: typoscript

    plugin.tx_cal.view {
      templateRootPaths.10 = EXT:site_package/Resources/Private/Extensions/Cal/Templates/
      partialRootPaths.10 = EXT:site_package/Resources/Private/Extensions/Cal/Partials/
      layoutRootPaths.10 = EXT:site_package/Resources/Private/Extensions/Cal/Layouts/
    }

=============================  ===============================================
Path                           Content
=============================  ===============================================
`Templates/Content/*.html`     One template per view (`month.html`,
                               `week.html`, `event.html`, …)
`Partials/Calendar/`           Header with title, view switch and navigation
`Partials/Month/`, `Week/`,    Grids of the views
`Day/`, `Year/`
`Partials/Event/`              Events inside of the grids, links to locations
                               and organizers
`Partials/List/`               List and event cards
`Partials/Record/`             Images and details of locations and organizers
=============================  ===============================================

Single templates of the event and list view can also be set with
`view.event.templateFile` and `view.list.templateFile` (see :ref:`typoscript`).
