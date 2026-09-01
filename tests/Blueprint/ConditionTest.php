<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Blueprint;

use Modufolio\Panel\Blueprint\Condition;
use PHPUnit\Framework\TestCase;

/**
 * The server-side evaluator mirrors the client's exactly — same tuples,
 * same combinators. These cases double as the parity contract.
 */
final class ConditionTest extends TestCase
{
    public function testTuples(): void
    {
        $values = ['status' => 'published', 'count' => 5, 'tags' => ['a', 'b'], 'cover' => ''];

        $this->assertTrue(Condition::evaluate(['status', 'published'], $values));
        $this->assertFalse(Condition::evaluate(['status', 'draft'], $values));
        $this->assertTrue(Condition::evaluate(['status', '!=', 'draft'], $values));
        $this->assertTrue(Condition::evaluate(['count', '>', 3], $values));
        $this->assertFalse(Condition::evaluate(['count', '>=', 6], $values));
        $this->assertTrue(Condition::evaluate(['status', 'in', ['published', 'held']], $values));
        $this->assertTrue(Condition::evaluate(['tags', 'contains', 'a'], $values));
        $this->assertTrue(Condition::evaluate(['cover', 'empty'], $values));
        $this->assertFalse(Condition::evaluate(['tags', 'empty'], $values));
    }

    public function testCombinatorsComposeAndNest(): void
    {
        $values = ['status' => 'published', 'featured' => true];

        $this->assertTrue(Condition::evaluate(['all' => [['status', 'published'], ['featured', true]]], $values));
        $this->assertFalse(Condition::evaluate(['all' => [['status', 'published'], ['featured', false]]], $values));
        $this->assertTrue(Condition::evaluate(['any' => [['status', 'draft'], ['featured', true]]], $values));
        $this->assertTrue(Condition::evaluate(['not' => ['status', 'draft']], $values));
        $this->assertFalse(Condition::evaluate(['not' => ['any' => [['status', 'published']]]], $values));
    }
}
