<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Routing;

use Modufolio\Panel\Routing\Uuid;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The route pattern generated routes match public identifiers with.
 *
 * It has to reject non-uuids, or `/panel/events/create` would match the show
 * route and a resource would lose its create page to its own detail view.
 */
final class UuidTest extends TestCase
{
    /** @return array<string, array{string, bool}> */
    public static function candidates(): array
    {
        return [
            'a real uuid'        => ['33039008-b7cb-42fa-bd4a-44c58b8bcfcb', true],
            'uppercase hex'      => ['33039008-B7CB-42FA-BD4A-44C58B8BCFCB', true],
            'the word create'    => ['create', false],
            'a row number'       => ['42', false],
            'no dashes'          => ['33039008b7cb42fabd4a44c58b8bcfcb', false],
            'one group short'    => ['33039008-b7cb-42fa-bd4a', false],
            'a non-hex letter'   => ['3303900z-b7cb-42fa-bd4a-44c58b8bcfcb', false],
            'export'             => ['export', false],
            'bulk-delete'        => ['bulk-delete', false],
        ];
    }

    #[DataProvider('candidates')]
    public function testThePatternMatchesOnlyUuids(string $candidate, bool $expected): void
    {
        self::assertSame($expected, preg_match('/^' . Uuid::PATTERN . '$/D', $candidate) === 1);
    }
}
