<?php

declare(strict_types=1);

return [
    'ctrl' =>  [
        'default_sortby' => 'start_datetime',
        'hideTable' => true,
    ],
    'columns' =>  [

        'tablename' =>  [
            'exclude' => 1,
            'config' =>  [
                'type' => 'input',
                'size' => '30',
                'max' => '30',
                'default' => '',
            ],
        ],
        'start_datetime' =>  [
            'exclude' => 1,
            'config' =>  [
                'type' => 'input',
                'size' => '14',
                'max' => '14',
                'default' => '0',
                'eval' => 'datetime',
            ],
        ],
        'end_datetime' =>  [
            'exclude' => 1,
            'config' =>  [
                'type' => 'input',
                'size' => '14',
                'max' => '14',
                'default' => '0',
                'eval' => 'datetime',
            ],
        ],
        'event_uid' =>  [

            'exclude' => 1,
            'config' =>  [
                'type' => 'group',
                'allowed' => 'tx_cal_domain_model_event',
                'size' => '1',
                'maxitems' => '1',
                'minitems' => '0',
                'default' => 0,
            ],
        ],
        'event_deviation_uid' =>  [

            'exclude' => 1,
            'config' =>  [
                'type' => 'group',
                'allowed' => 'tx_cal_domain_model_eventdeviation',
                'size' => '1',
                'maxitems' => '1',
                'minitems' => '0',
                'default' => 0,
            ],
        ],
    ],
];
