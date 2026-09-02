<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Field;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Modufolio\Panel\Field\DateType;
use Modufolio\Panel\Field\FilterableFieldInterface;
use Modufolio\Panel\Field\NumberType;
use Modufolio\Panel\Field\SelectType;
use Modufolio\Panel\Field\TextType;
use Modufolio\Panel\Field\ToggleType;
use PHPUnit\Framework\TestCase;

/**
 * Field types declare their filter operators and build the predicates —
 * asserted at the DQL level, no database needed. Operator keys deliberately
 * match modufolio/json-api's filters (gt/gte/lt/lte/between, after/before),
 * one vocabulary across the ecosystem.
 */
final class FilterOperatorsTest extends TestCase
{
    private function qb(): QueryBuilder
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getExpressionBuilder')->willReturn(new Expr());

        return new QueryBuilder($em);
    }

    /**
     * @param  class-string<FilterableFieldInterface> $type
     * @return array{string, array<string, mixed>} The DQL where part and the bound parameters
     */
    private function whereOf(string $type, string $op, mixed $value): array
    {
        $qb = $this->qb();
        $type::applyFilter($qb, 'p.field', $op, $value, 'f0');

        $params = [];
        foreach ($qb->getParameters() as $parameter) {
            $params[$parameter->getName()] = $parameter->getValue();
        }

        return [(string) $qb->getDQLPart('where'), $params];
    }

    public function testTextContainsEscapesLikeWildcards(): void
    {
        [$where, $params] = $this->whereOf(TextType::class, 'contains', '50%_off');

        // The escape character is named in the predicate: without an ESCAPE
        // clause the engine's default applies, and SQLite has none.
        $this->assertSame("p.field LIKE :f0 ESCAPE '!'", $where);
        $this->assertSame('%50!%!_off%', $params['f0'], 'User input must not smuggle wildcards.');
    }

    public function testTextEmptyCoversNullAndEmptyString(): void
    {
        [$where] = $this->whereOf(TextType::class, 'empty', null);

        $this->assertSame("(p.field IS NULL OR p.field = '')", $where);
    }

    public function testNumberBetweenBindsBothBounds(): void
    {
        [$where, $params] = $this->whereOf(NumberType::class, 'between', [10, 20]);

        $this->assertSame('p.field BETWEEN :f0_from AND :f0_to', $where);
        $this->assertSame(10, $params['f0_from']);
        $this->assertSame(20, $params['f0_to']);
    }

    public function testDateSpeaksTheJsonApiVocabulary(): void
    {
        $this->assertArrayHasKey('after', DateType::filterOperators());
        $this->assertArrayHasKey('before', DateType::filterOperators());

        [$where, $params] = $this->whereOf(DateType::class, 'after', '2026-01-01');
        $this->assertSame('p.field >= :f0', $where);
        $this->assertInstanceOf(\DateTimeImmutable::class, $params['f0']);
        $this->assertSame('2026-01-01 00:00:00', $params['f0']->format('Y-m-d H:i:s'));
    }

    /**
     * A date bound travels as a date, whatever column it meets. Left untyped
     * it would be inferred as a datetime and formatted with a time part,
     * which a DATE column stored as text compares byte-wise — every lower
     * bound then skipped its own day. `before` and `between` spell out the
     * whole day they mean, the way `on` already did.
     */
    public function testDateBoundsAreTypedAsDatesAndCoverWholeDays(): void
    {
        $qb = $this->qb();
        DateType::applyFilter($qb, 'p.field', 'before', '2026-01-31', 'f0');

        $bound = $qb->getParameter('f0') ?? self::fail('The bound was not set.');
        $value = $bound->getValue();

        $this->assertSame('p.field < :f0', (string) $qb->getDQLPart('where'));
        $this->assertSame(Types::DATE_IMMUTABLE, $bound->getType());
        $this->assertInstanceOf(\DateTimeImmutable::class, $value);
        $this->assertSame('2026-02-01', $value->format('Y-m-d'), 'Up to and including the 31st.');

        [$where, $params] = $this->whereOf(DateType::class, 'between', ['2026-01-01', '2026-01-31']);
        $this->assertSame('p.field >= :f0_from AND p.field < :f0_to', $where);
        $this->assertSame('2026-02-01', $params['f0_to']->format('Y-m-d'));
    }

    public function testSelectInNormalisesAScalarToAList(): void
    {
        [$where, $params] = $this->whereOf(SelectType::class, 'in', 'draft');

        $this->assertSame('p.field IN (:f0)', $where);
        $this->assertSame(['draft'], $params['f0']);
    }

    public function testToggleCoercesToBool(): void
    {
        [, $params] = $this->whereOf(ToggleType::class, 'is', '1');

        $this->assertTrue($params['f0']);
    }

    public function testAnUndeclaredOperatorThrowsInsteadOfNoOping(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unknown filter operator "regex"/');

        TextType::applyFilter($this->qb(), 'p.field', 'regex', 'x', 'f0');
    }

    public function testEveryDeclaredOperatorIsApplicable(): void
    {
        $samples = ['between' => [1, 2], 'in' => ['a']];
        // A date type parses its bounds as days, so it gets days.
        $days = ['between' => ['2026-01-01', '2026-01-31']];

        foreach ([TextType::class, NumberType::class, DateType::class, SelectType::class, ToggleType::class] as $type) {
            foreach (array_keys($type::filterOperators()) as $op) {
                $qb    = $this->qb();
                $value = $type === DateType::class ? ($days[$op] ?? '2026-01-01') : ($samples[$op] ?? 'v');
                $type::applyFilter($qb, 'p.field', $op, $value, 'f0');
                $this->assertNotSame('', (string) $qb->getDQLPart('where'), "{$type}::{$op} built no predicate.");
            }
        }
    }
}
