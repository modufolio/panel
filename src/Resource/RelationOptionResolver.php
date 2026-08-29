<?php

declare(strict_types=1);

namespace Modufolio\Panel\Resource;

use Modufolio\Panel\Table\RelationOptions;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Turns a {@see RelationOptions} declaration into option lists for the client.
 *
 * One place, three callers: the form render (which needs either the whole list
 * or just enough to display the current value), the search endpoint (which
 * needs a bounded, filtered page), and the value resolver the client calls to
 * label a selection it was given as bare identifiers.
 *
 * The rule the whole feature turns on: a relation small enough to scroll is
 * sent in full, exactly as before; anything larger is searched on the server.
 * Nothing in between silently truncates — {@see search()} reports its own cap.
 */
final class RelationOptionResolver
{
    /**
     * LIKE escape character. Not a backslash: that would have to survive PHP,
     * DQL and the driver's own quoting, and each layer treats it differently.
     */
    private const LIKE_ESCAPE = '!';

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * Whether this relation is served by search rather than a preloaded list.
     *
     * A declaration may force either way; left to itself, the row count
     * decides, so a *guessed* relation gets the right behaviour without the
     * resource saying anything.
     */
    public function isSearchable(RelationOptions $relation): bool
    {
        if ($relation->searchable !== null) {
            return $relation->searchable;
        }

        return $this->count($relation) > RelationOptions::AUTO_SEARCH_THRESHOLD;
    }

    /**
     * Every row, ordered by label — the small-relation path, unchanged.
     *
     * @return list<array{value: string, label: string}>
     */
    public function all(RelationOptions $relation): array
    {
        $rows = $this->entityManager
            ->getRepository($relation->entityClass)
            ->findBy([], [$relation->labelField => 'ASC']);

        return array_map(fn (object $row): array => $this->toOption($relation, $row), $rows);
    }

    /**
     * Rows whose label matches `$term`, capped at {@see RelationOptions::SEARCH_LIMIT}.
     *
     * The term is matched with a LIKE on the declared label field only — the
     * client never names a column, and `entityClass` never travels either way.
     *
     * @return array{
     *     data: list<array{value: string, label: string}>,
     *     meta: array{total: int, limit: int, truncated: bool}
     * }
     */
    public function search(RelationOptions $relation, string $term): array
    {
        $limit = RelationOptions::SEARCH_LIMIT;

        $builder = $this->entityManager
            ->getRepository($relation->entityClass)
            ->createQueryBuilder('r')
            ->orderBy('r.' . $relation->labelField, 'ASC');

        if ($term !== '') {
            $builder
                // ESCAPE is not optional here: without it the driver reads the
                // escape character literally and a term containing % matches
                // nothing at all.
                ->where(sprintf(
                    "LOWER(r.%s) LIKE :term ESCAPE '%s'",
                    $relation->labelField,
                    self::LIKE_ESCAPE,
                ))
                // Escaped so a literal % or _ in the term stays literal rather
                // than turning into a wildcard the user did not ask for.
                ->setParameter('term', '%' . $this->escapeLike(mb_strtolower($term)) . '%');
        }

        // One row beyond the cap: its presence is how we know the result was
        // truncated, without paying for a separate COUNT.
        $rows = $builder->setMaxResults($limit + 1)->getQuery()->getResult();

        $truncated = count($rows) > $limit;
        $rows      = array_slice($rows, 0, $limit);

        return [
            'data' => array_map(fn (object $row): array => $this->toOption($relation, $row), $rows),
            'meta' => [
                'total'     => count($rows),
                'limit'     => $limit,
                'truncated' => $truncated,
            ],
        ];
    }

    /**
     * Labels for identifiers the client already holds.
     *
     * A searchable field arrives with its value but no label — the record says
     * `director_id: <uuid>`, and the whole point is that the list was never
     * sent. This resolves exactly the selected ones so the control can render
     * its current state.
     *
     * @param  list<string> $values
     * @return list<array{value: string, label: string}>
     */
    public function byValues(RelationOptions $relation, array $values): array
    {
        $values = array_values(array_filter($values, static fn (string $v): bool => $v !== ''));

        if ($values === []) {
            return [];
        }

        $rows = $this->entityManager
            ->getRepository($relation->entityClass)
            ->findBy([$relation->valueField => $values]);

        return array_map(fn (object $row): array => $this->toOption($relation, $row), $rows);
    }

    /**
     * Whether the target can be created from its label alone.
     *
     * The picker's "Create …" row is only offered when a name is genuinely
     * all a new row needs — every other column nullable or defaulted, no
     * required to-one association, a no-argument constructor. Decided from
     * Doctrine's metadata so the offer cannot drift from what the insert
     * would actually accept, and re-checked server-side on the POST because
     * the client's copy of this answer is a convenience, not a control.
     */
    public function creatableFromLabel(RelationOptions $relation): bool
    {
        $meta = $this->entityManager->getClassMetadata($relation->entityClass);

        if (!$meta->hasField($relation->labelField)) {
            return false;
        }

        $constructor = (new \ReflectionClass($relation->entityClass))->getConstructor();

        if ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 0) {
            return false;
        }

        // Identity and lifecycle columns are filled by the entity itself; the
        // label is what the picker supplies. Everything else must be optional.
        $bookkeeping = ['id', 'uuid', 'createdAt', 'updatedAt', $relation->labelField];

        foreach ($meta->getFieldNames() as $field) {
            if (in_array($field, $bookkeeping, true)) {
                continue;
            }

            $mapping = $meta->getFieldMapping($field);

            if (!($mapping['nullable'] ?? false) && !array_key_exists('default', $mapping['options'] ?? [])) {
                return false;
            }
        }

        foreach ($meta->getAssociationNames() as $association) {
            // Collections start empty; only a required to-one blocks creation.
            if (!$meta->isSingleValuedAssociation($association)) {
                continue;
            }

            $join = $meta->getAssociationMapping($association)['joinColumns'][0] ?? [];

            if (($join['nullable'] ?? true) === false) {
                return false;
            }
        }

        return true;
    }

    /** The row that already carries this exact label, if one does. */
    public function findByLabel(RelationOptions $relation, string $label): ?object
    {
        return $this->entityManager
            ->getRepository($relation->entityClass)
            ->findOneBy([$relation->labelField => $label]);
    }

    /**
     * A new, unflushed instance carrying the label — the caller validates and
     * decides whether it gets persisted.
     */
    public function newFromLabel(RelationOptions $relation, string $label): object
    {
        $entity = new ($relation->entityClass)();
        $entity->{'set' . ucfirst($relation->labelField)}($label);

        return $entity;
    }

    /**
     * One row in the option shape the endpoints speak.
     *
     * @return array{value: string, label: string}
     */
    public function option(RelationOptions $relation, object $row): array
    {
        return $this->toOption($relation, $row);
    }

    private function count(RelationOptions $relation): int
    {
        return (int) $this->entityManager
            ->getRepository($relation->entityClass)
            ->createQueryBuilder('r')
            ->select('COUNT(r.' . $relation->valueField . ')')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return array{value: string, label: string} */
    private function toOption(RelationOptions $relation, object $row): array
    {
        return [
            'value' => (string) $row->{'get' . ucfirst($relation->valueField)}(),
            'label' => (string) $row->{'get' . ucfirst($relation->labelField)}(),
        ];
    }

    private function escapeLike(string $term): string
    {
        $escape = self::LIKE_ESCAPE;

        return str_replace(
            [$escape, '%', '_'],
            [$escape . $escape, $escape . '%', $escape . '_'],
            $term,
        );
    }
}
