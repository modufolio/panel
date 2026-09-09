<?php

declare(strict_types=1);

namespace Modufolio\Panel\Resource;

use Doctrine\ORM\EntityManagerInterface;
use Modufolio\Panel\Table\Filter;
use Modufolio\Panel\Table\RelationOptions;
use Modufolio\Panel\Table\TableSchema;

/**
 * The option lists behind a listing's relation filters, read from the
 * database.
 *
 * The one step of schema resolution that needs a connection, kept apart
 * from the rest so {@see SchemaResolver} stays testable without one: a
 * {@see TableSchema} is a pure value object, and a filter declared over a
 * relation names an entity class, not the rows in it.
 */
final class FilterOptionResolver
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @param array<string, mixed> $values the filter values in force, so a
     *                                     selected option is labelled even when
     *                                     the option list had to be cut short
     */
    public function resolve(TableSchema $schema, array $values = []): TableSchema
    {
        $filters = array_map(function (Filter $filter) use ($values): Filter {
            $relation = $filter->relation();

            if ($relation === null) {
                return $filter;
            }

            // Bounded, the same way form relations are: a dropdown shipped the
            // whole table before this, so a large related table made every
            // listing page carry it. One row beyond the threshold is fetched
            // to tell "exactly full" from "there are more" — the overflow is
            // reported rather than silently trimmed, per the panel's rule that
            // a bound it imposes must be visible.
            /** @var list<array{value: mixed, label: mixed}> $rows */
            $rows = $this->entityManager->createQueryBuilder()
                ->select(sprintf('r.%s AS value, r.%s AS label', $relation->valueField, $relation->labelField))
                ->from($relation->entityClass, 'r')
                ->orderBy(sprintf('r.%s', $relation->labelField), 'ASC')
                ->setMaxResults(RelationOptions::AUTO_SEARCH_THRESHOLD + 1)
                ->getQuery()
                ->getArrayResult();

            $truncated = count($rows) > RelationOptions::AUTO_SEARCH_THRESHOLD;

            if ($truncated) {
                $rows = array_slice($rows, 0, RelationOptions::AUTO_SEARCH_THRESHOLD);

                // The value in force must still read as a name — in the
                // control and in the chip above the table — even when it
                // fell beyond the cut, so it is fetched on its own.
                $rows = [...$rows, ...$this->selectedBeyondTheCut($relation, $values[$filter->key()] ?? null, $rows)];
            }

            return $filter->withResolvedOptions(array_map(
                static fn (array $row): array => [
                    'value' => (string)$row['value'],
                    'label' => (string)$row['label'],
                ],
                $rows
            ), $truncated);
        }, $schema->declaredFilters());

        return $schema->withFilters($filters);
    }

    /**
     * The selected values a truncated option list left out, fetched with
     * their labels so the selection is never shown as a bare id.
     *
     * @param  list<array{value: mixed, label: mixed}> $rows
     * @return list<array{value: mixed, label: mixed}>
     */
    private function selectedBeyondTheCut(RelationOptions $relation, mixed $selected, array $rows): array
    {
        $wanted = array_values(array_filter(
            array_map(static fn (mixed $v): string => is_scalar($v) ? (string) $v : '', is_array($selected) ? $selected : [$selected]),
            static fn (string $v): bool => $v !== '',
        ));
        $have = array_map(static fn (array $row): string => (string) $row['value'], $rows);
        $missing = array_values(array_diff($wanted, $have));

        if ($missing === []) {
            return [];
        }

        /** @var list<array{value: mixed, label: mixed}> $found */
        $found = $this->entityManager->createQueryBuilder()
            ->select(sprintf('r.%s AS value, r.%s AS label', $relation->valueField, $relation->labelField))
            ->from($relation->entityClass, 'r')
            ->where(sprintf('r.%s IN (:selected)', $relation->valueField))
            ->setParameter('selected', $missing)
            ->orderBy(sprintf('r.%s', $relation->labelField), 'ASC')
            ->getQuery()
            ->getArrayResult();

        return $found;
    }
}
