<?php

/**
 * *************************************************************
 * Extension Manager/Repository config file for ext "cal".
 *
 * Auto generated 01-05-2013 10:23
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 * *************************************************************
 */
if(!isset($_EXTKEY)) {
  $_EXTKEY = 'cal';
}
$EM_CONF [$_EXTKEY] = array (
    
    'title' => 'Calendar Base',
    'description' => 'A calendar combining all the functions of the existing calendar extensions plus adding some new features. It is based on the ical standard',
    'category' => 'plugin',
    'shy' => 0,
    'version' => '1.12.0-dev',
    'loadOrder' => '',
    'state' => 'stable',
    'uploadfolder' => 0,
    'createDirs' => 'uploads/tx_cal/pics,uploads/tx_cal/ics,uploads/tx_cal/media',
    'clearCacheOnLoad' => 0,
    'author' => 'Mario Matzulla, Jeff Segars, Franz Koch, Thomas Kowtsch',
    'author_email' => 'mario@matzullas.de, jeff@webempoweredchurch.org, franz.koch@elements-net.de, typo3@thomas-kowtsch.de',
    'author_company' => '',
    'constraints' => array (
        
        'depends' => array (
            
            'typo3' => '11.5.1-',
            'typo3db_legacy' => '1.1.5-'
        )
    )
);

?>