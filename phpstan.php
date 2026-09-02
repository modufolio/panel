<?php

return [
    'parameters' => [
        'level' => 8,
        'paths' => ['src', 'tests'],
        'tmpDir' => '.phpstan.cache',
        'ignoreErrors' => [
            // Doctrine assigns the generated id by reflection; nothing in PHP writes it.
            [
                'message' => '#Property .+::\$id \(int\|null\) is never assigned int so it can be removed from the property type\.#',
                'path' => 'tests/Fixture/Entity/*',
            ],
        ],
    ],
];
