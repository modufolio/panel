<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Table;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Modufolio\Panel\Table\Constraint;
use PHPUnit\Framework\TestCase;

/**
 * A constraint's operators and predicates come from its field type.
 *
 * The class used to carry a second operator table of its own, under different
 * names — so a listing's ad-hoc conditions and the field types disagreed about
 * what "does not contain" is called, and the two switch statements could drift
 * apart silently. These tests pin the seam: the menu is the type's, the
 * predicate is the type's, and everything a request can post is still checked
 * against the declaration before it gets near the query.
 */
final class ConstraintTest extends TestCase
{
    private function qb(): QueryBuilder
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getExpressionBuilder')->willReturn(new Expr());

        return new QueryBuilder($em);
    }

    /**
     * @param array<string, mixed> $condition
     * @return array{string, array<string, mixed>}
     */
    private function applied(Constraint $constraint, array $condition): array
    {
        $qb = $this->qb();
        $constraint->apply($qb, 'p', $condition, 0);

        $params = [];
        foreach ($qb->getParameters() as $parameter) {
            $params[$parameter->getName()] = $parameter->getValue();
        }

        return [(string) $qb->getDQLPart('where'), $params];
    }

    public function testOperatorsComeFromTheFieldType(): void
    {
        $operators = array_column(Constraint::text('title')->toArray()['operators'], 'value');

        $this->assertSame(
            array_keys(\Modufolio\Panel\Field\TextType::filterOperators()),
            $operators,
            'The menu should be the type\'s vocabulary, in the type\'s order.',
        );
    }

    public function testArityTravelsWithEachOperator(): void
    {
        $operators = [];
        foreach (Constraint::number('year')->toArray()['operators'] as $operator) {
            $operators[$operator['value']] = $operator['values'];
        }

        $this->assertSame(1, $operators['gte']);
        $this->assertSame(2, $operators['between'], 'Two bounds, two inputs.');
        $this->assertSame(0, $operators['empty'], 'A nullary operator shows no value input.');
    }

    public function testTextConditionBuildsTheTypesPredicate(): void
    {
        [$where, $params] = $this->applied(
            Constraint::text('title'),
            ['operator' => 'contains', 'value' => '50%_off'],
        );

        $this->assertSame("p.title LIKE :c0_title ESCAPE '!'", $where);
        $this->assertSame('%50!%!_off%', $params['c0_title'], 'Escaping is the type\'s, and it still runs.');
    }

    public function testNumberBetweenPassesBothBoundsAsNumbers(): void
    {
        [$where, $params] = $this->applied(
            Constraint::number('year'),
            ['operator' => 'between', 'value' => '1990', 'value2' => '1999'],
        );

        $this->assertSame('p.year BETWEEN :c0_year_from AND :c0_year_to', $where);
        $this->assertSame(1990.0, $params['c0_year_from'], 'A query string carries text; the column holds numbers.');
        $this->assertSame(1999.0, $params['c0_year_to']);
    }

    public function testBooleanConditionBindsARealBoolean(): void
    {
        [$where, $params] = $this->applied(
            Constraint::boolean('published'),
            ['operator' => 'is', 'value' => '0'],
        );

        $this->assertSame('p.published = :c0_published', $where);
        $this->assertFalse($params['c0_published'], '"0" from a select is false, not a truthy string.');
    }

    /**
     * `on` is a day, and the same declaration is pointed at date and datetime
     * columns alike — so it must be a half-open range, not an equality that
     * only matches midnight.
     */
    public function testDateOnMatchesTheWholeDay(): void
    {
        [$where, $params] = $this->applied(
            Constraint::date('published_at', 'publishedAt'),
            ['operator' => 'on', 'value' => '2026-03-04'],
        );

        $this->assertSame(
            'p.publishedAt >= :c0_published_at_from AND p.publishedAt < :c0_published_at_to',
            $where,
        );
        $this->assertSame('2026-03-04 00:00:00', $params['c0_published_at_from']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-05 00:00:00', $params['c0_published_at_to']->format('Y-m-d H:i:s'));
    }

    public function testAnUndeclaredOperatorNarrowsNothing(): void
    {
        // The type would throw — right for a caller bug, wrong for a query
        // string. The allowlist runs first, so nothing reaches it.
        [$where] = $this->applied(
            Constraint::text('title'),
            ['operator' => 'drop_everything', 'value' => 'x'],
        );

        $this->assertSame('', $where);
    }

    public function testAMissingValueNarrowsNothing(): void
    {
        [$whereOne] = $this->applied(Constraint::text('title'), ['operator' => 'contains', 'value' => '']);
        $this->assertSame('', $whereOne, 'A half-filled condition should not filter.');

        [$whereTwo] = $this->applied(
            Constraint::number('year'),
            ['operator' => 'between', 'value' => '1990'],
        );
        $this->assertSame('', $whereTwo, 'Nor should a range missing its upper bound.');
    }

    public function testANullaryOperatorNeedsNoValue(): void
    {
        [$where] = $this->applied(Constraint::text('title'), ['operator' => 'empty']);

        $this->assertSame("(p.title IS NULL OR p.title = '')", $where);
    }
}
