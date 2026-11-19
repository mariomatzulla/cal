<?php

declare(strict_types=1);

return [
    'frontend' => [
        'caldav/caldav-server' => [
            'target' => \MarioMatzulla\Cal\Middleware\CalDavMiddleware::class,
            'after' => [
                'typo3/cms-frontend/site-resolution',
            ],
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
        ],
    ],
];
