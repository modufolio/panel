<?php

declare(strict_types=1);

namespace Modufolio\Panel\Resource;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Applies one drag on a board.
 *
 * The client says where the card was dropped — which column, and between which
 * two cards — and never what position that works out to. The arithmetic is the
 * server's, because two people can drop into the same gap at the same instant
 * and only the server sees both.
 *
 * Neighbours are addressed by the identifier the board's cards already carry,
 * and are re-read here rather than trusted: a stale board sends the neighbours
 * it last saw, and resolving them server-side is what keeps a move against an
 * out-of-date view landing somewhere sensible instead of nowhere.
 */
final readonly class BoardMover
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Move `$entity` into `$column`, between the two named neighbours.
     *
     * Either neighbour may be null, meaning an end of the column. Returns the
     * moved entity, flushed.
     *
     * @throws \InvalidArgumentException when the column is not one the view declares
     */
    public function move(
        PanelResource $resource,
        ResourceView $view,
        object $entity,
        string $column,
        ?string $afterId,
        ?string $beforeId,
    ): object {
        $view->assertUsable($resource::class);

        if (!in_array($column, $view->columnValues(), true)) {
            throw new \InvalidArgumentException(sprintf(
                'Column "%s" is not one this board declares.',
                $column,
            ));
        }

        $this->assign($entity, (string) $view->groupBy(), $column);

        $position = $view->positionField();

        if ($position !== null) {
            $after  = $this->positionOf($resource, $position, $afterId);
            $before = $this->positionOf($resource, $position, $beforeId);

            try {
                $this->assign($entity, $position, BoardPosition::forDrop($after, $before));
            } catch (BoardPositionExhausted) {
                // The gap between those two cards has closed after enough
                // drops into the same spot. Spread the column out and place the
                // card again — the person dragging never sees this, which is
                // the point: an arithmetic limit they cannot observe must not
                // surface as a refused drag.
                $this->rebalance($resource, $view, $column, $position);

                $this->assign($entity, $position, BoardPosition::forDrop(
                    $this->positionOf($resource, $position, $afterId),
                    $this->positionOf($resource, $position, $beforeId),
                ));
            }
        }

        $this->entityManager->flush();

        return $entity;
    }

    /**
     * Spread one column's cards back onto even spacing.
     *
     * Order is preserved and only the spacing changes, so nothing a reader can
     * see moves. Ties are broken by identity, matching how the board itself
     * orders a column that already holds duplicates.
     */
    private function rebalance(
        PanelResource $resource,
        ResourceView $view,
        string $column,
        string $positionField,
    ): void {
        $alias = $resource->queryAlias();

        $cards = $this->entityManager
            ->getRepository($resource->entityClass())
            ->createQueryBuilder($alias)
            ->andWhere("{$alias}.{$view->groupBy()} = :column")
            ->setParameter('column', $column)
            ->orderBy("{$alias}.{$positionField}", 'ASC')
            ->addOrderBy("{$alias}.id", 'ASC')
            ->getQuery()
            ->getResult();

        foreach (BoardPosition::sequence(count($cards)) as $index => $spaced) {
            $this->assign($cards[$index], $positionField, $spaced);
        }

        $this->entityManager->flush();
    }

    /**
     * A neighbour's current position, or null when there is no neighbour.
     *
     * A named neighbour that cannot be found is treated as absent rather than
     * as an error: the board that named it is simply out of date, and refusing
     * the whole drag over a card someone else has since moved would be worse
     * than landing at the end of the column.
     */
    private function positionOf(PanelResource $resource, string $field, ?string $id): ?int
    {
        if ($id === null || $id === '') {
            return null;
        }

        $neighbour = $this->entityManager
            ->getRepository($resource->entityClass())
            ->findOneBy($this->identifierCriteria($resource, $id));

        if ($neighbour === null) {
            return null;
        }

        $value = $this->read($neighbour, $field);

        return $value === null ? null : BoardPosition::normalize($value);
    }

    /**
     * How a card's identifier addresses its record.
     *
     * Board cards carry whatever the presenter calls `id`, which across this
     * panel is the public uuid. An entity without one is addressed by its key.
     *
     * @return array<string, mixed>
     */
    private function identifierCriteria(PanelResource $resource, string $id): array
    {
        $meta = $this->entityManager->getClassMetadata($resource->entityClass());

        return $meta->hasField('uuid') ? ['uuid' => $id] : ['id' => $id];
    }

    private function read(object $entity, string $field): string|int|float|null
    {
        $getter = 'get' . ucfirst($field);

        if (!method_exists($entity, $getter)) {
            throw new \LogicException(sprintf(
                '%s has no %s() to read the board position from.',
                $entity::class,
                $getter,
            ));
        }

        $value = $entity->{$getter}();

        return is_string($value) || is_int($value) || is_float($value) ? $value : null;
    }

    /**
     * Write one field, converting to the enum the mapping declares.
     *
     * The column value arrives as the string the board's declaration names it
     * by. Where the property is enum-backed, passing that string straight to
     * the setter is a TypeError — the mapping knows which enum, so the
     * conversion happens here rather than in every resource that has one.
     */
    private function assign(object $entity, string $field, string|int $value): void
    {
        $setter = 'set' . ucfirst($field);

        if (!method_exists($entity, $setter)) {
            throw new \LogicException(sprintf(
                '%s has no %s(); a board writes the column and position back through setters.',
                $entity::class,
                $setter,
            ));
        }

        $meta     = $this->entityManager->getClassMetadata($entity::class);
        $enumType = $meta->hasField($field)
            ? ($meta->getFieldMapping($field)['enumType'] ?? null)
            : null;

        $entity->{$setter}(
            is_string($enumType) && enum_exists($enumType) && is_string($value)
                ? $enumType::from($value)
                : $value,
        );
    }
}
