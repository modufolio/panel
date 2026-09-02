<?php

return [
    'includes' => [
        __DIR__ . '/vendor/phpstan/phpstan-phpunit/extension.neon',
    ],
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
            // phpstan-phpunit flags an assertion whose subject the docblocks
            // already narrow as redundant. In the suite these are deliberate:
            // a docblock is a promise, the assertion checks the runtime kept
            // it, and a later shape change fails a test rather than silently
            // weakening one. The extension's value here is narrowing after
            // assertions and the impossible-type checks, which stay on.
            [
                'identifier' => 'method.alreadyNarrowedType',
                'path' => 'tests/*',
                'reportUnmatched' => false,
            ],
            [
                'identifier' => 'staticMethod.alreadyNarrowedType',
                'path' => 'tests/*',
                'reportUnmatched' => false,
            ],
        ],
    ],
];
