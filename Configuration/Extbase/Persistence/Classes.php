<?php

declare(strict_types=1);

return [
    \MarioMatzulla\Cal\Domain\Model\Category::class => [
        'tableName' => 'sys_category',
        'properties' => [
            'color' => [
                'fieldName' => 'tx_cal_color',
            ],
            'colorPriority' => [
                'fieldName' => 'tx_cal_color_priority',
            ],
        ],
    ],
];
