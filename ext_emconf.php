<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Calendar Base',
    'description' => 'A calendar combining all the functions of the existing calendar extensions plus adding some new features. It is based on the ical standard and ships a CalDAV server.',
    'category' => 'plugin',
    'version' => '4.0.0',
    'state' => 'beta',
    'author' => 'Jan Helke, Mario Matzulla',
    'author_email' => 'cal@typo3.helke.de, mario@matzullas.de',
    'constraints' => [
        'depends' => [
            'php' => '8.2.0-8.5.99',
            'typo3' => '14.0.0-14.3.99',
        ],
        'conflicts' => [
            'caldav' => '',
        ],
        'suggests' => [
            'scheduler' => '14.0.0-14.3.99',
        ],
    ],
];
