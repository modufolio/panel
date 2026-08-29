<?php

declare(strict_types=1);

namespace Modufolio\Panel\Delete;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Everything that would go, or would stop you, if a record were deleted.
 *
 * The division of labour is the point: gather first, decide second, and never
 * discover halfway through a delete that something was in the way.
 *
 * The walk is over the *reverse* of every to-one relation — those are the rows
 * carrying a foreign key at this object — which Doctrine's metadata can answer
 * directly: every mapped class owning a to-one association pointing here.
 *
 * Two behaviours are worth naming because they are easy to conflate:
 *
 * - PROTECT refuses on sight.
 * - RESTRICT refuses *unless* the referencing rows are themselves being
 *   cascade-deleted by this same operation, which is why that check runs at
 *   the end rather than when the row is found.
 *
 * Many-to-many is deliberately not a candidate for deletion: unlinking is not
 * deleting, so join rows are cleared and counted rather than cascaded into.
 */
final class Collector
{
    /** @var list<object> */
    private array $deletes = [];

    /** @var list<array{entity: object, field: string}> */
    private array $nullifies = [];

    /** @var list<string> */
    private array $protected = [];

    /** @var list<array{objects: list<object>, class: string, field: string}> */
    private array $restricted = [];

    /** @var array<string, int> */
    private array $counts = [];

    /** @var array<string, int> */
    private array $linkCounts = [];

    /** @var array<string, true> guards cycles: class#id already visited */
    private array $seen = [];

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function collect(object $entity): DeletionPlan
    {
        $nested = $this->visit($entity);

        // Deferred to the end because a RESTRICT is only a problem if the
        // referencing row survives, and whether it survives is not known until
        // the whole cascade has been gathered.
        foreach ($this->restricted as $entry) {
            foreach ($entry['objects'] as $object) {
                if (!$this->isBeingDeleted($object)) {
                    $this->protected[] = $this->label($object);
                }
            }
        }

        return new DeletionPlan(
            nested: [$nested],
            counts: $this->counts,
            protected: array_values(array_unique($this->protected)),
            deletes: $this->deletes,
            nullifies: $this->nullifies,
            linkCounts: $this->linkCounts,
        );
    }

    /**
     * @return array{label: string, type: string, children: list<mixed>}
     */
    private function visit(object $entity): array
    {
        $key = $this->identityOf($entity);

        if (isset($this->seen[$key])) {
            return ['label' => $this->label($entity), 'type' => $this->typeLabel($entity), 'children' => []];
        }

        $this->seen[$key] = true;

        $type = $this->typeLabel($entity);
        $this->counts[$type] = ($this->counts[$type] ?? 0) + 1;

        $children = [];

        foreach ($this->referencesTo($entity::class) as [$meta, $field]) {
            $behaviour = $this->behaviourFor($meta, $field);

            if ($behaviour === OnDelete::DO_NOTHING) {
                continue;
            }

            $rows = $this->entityManager
                ->getRepository($meta->getName())
                ->findBy([$field => $entity]);

            if ($rows === []) {
                continue;
            }

            match ($behaviour) {
                OnDelete::PROTECT => array_map(
                    fn (object $row) => $this->protected[] = $this->label($row),
                    $rows,
                ),
                OnDelete::RESTRICT => $this->restricted[] = [
                    'objects' => $rows,
                    'class'   => $meta->getName(),
                    'field'   => $field,
                ],
                OnDelete::SET_NULL => array_map(
                    fn (object $row) => $this->nullifies[] = ['entity' => $row, 'field' => $field],
                    $rows,
                ),
                default => null,
            };

            if ($behaviour !== OnDelete::CASCADE) {
                continue;
            }

            foreach ($rows as $row) {
                $children[] = $this->visit($row);
            }
        }

        // Join rows are cleared, never cascaded into — the other side of a
        // many-to-many is a peer, not a dependent.
        foreach ($this->linksTo($entity::class) as [$meta, $field]) {
            $rows = $this->entityManager
                ->getRepository($meta->getName())
                ->createQueryBuilder('e')
                ->join("e.{$field}", 'link')
                ->where('link = :entity')
                ->setParameter('entity', $entity)
                ->getQuery()
                ->getResult();

            if ($rows === []) {
                continue;
            }

            $label = sprintf('%s links', $this->typeLabelFor($meta->getName()));
            $this->linkCounts[$label] = ($this->linkCounts[$label] ?? 0) + count($rows);

            foreach ($rows as $row) {
                $this->nullifies[] = ['entity' => $row, 'field' => $field, 'unlink' => $entity];
            }
        }

        // Depth-first: a child is deleted before the row it points at, so the
        // order this produces is already safe to execute top to bottom.
        $this->deletes[] = $entity;

        return ['label' => $this->label($entity), 'type' => $type, 'children' => $children];
    }

    /**
     * Every mapped class owning a to-one association that targets `$class`.
     * One-to-one and many-to-one only: a collection is not a reference.
     *
     * @param  class-string $class
     * @return list<array{0: ClassMetadata<object>, 1: string}>
     */
    private function referencesTo(string $class): array
    {
        $found = [];

        foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $meta) {
            foreach ($meta->getAssociationMappings() as $field => $mapping) {
                if (($mapping['targetEntity'] ?? null) !== $class) {
                    continue;
                }

                if (!$meta->isSingleValuedAssociation($field) || !($mapping['isOwningSide'] ?? true)) {
                    continue;
                }

                $found[] = [$meta, $field];
            }
        }

        return $found;
    }

    /**
     * Owning many-to-many associations pointing at `$class`.
     *
     * @param  class-string $class
     * @return list<array{0: ClassMetadata<object>, 1: string}>
     */
    private function linksTo(string $class): array
    {
        $found = [];

        foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $meta) {
            foreach ($meta->getAssociationMappings() as $field => $mapping) {
                if (($mapping['targetEntity'] ?? null) !== $class) {
                    continue;
                }

                if (($mapping['type'] ?? 0) === ClassMetadata::MANY_TO_MANY && ($mapping['isOwningSide'] ?? false)) {
                    $found[] = [$meta, $field];
                }
            }
        }

        return $found;
    }

    /**
     * The declared policy, or one inferred from the schema.
     *
     * Inference exists so an undeclared relation still behaves sensibly, but it
     * never overrides a declaration — and it lands on PROTECT when the schema
     * says nothing, because refusing is the only outcome that cannot quietly
     * lose data.
     *
     * @param ClassMetadata<object> $meta
     */
    private function behaviourFor(ClassMetadata $meta, string $field): string
    {
        $reflection = $meta->getReflectionClass()->hasProperty($field)
            ? $meta->getReflectionClass()->getProperty($field)
            : null;

        foreach ($reflection?->getAttributes(OnDelete::class) ?? [] as $attribute) {
            return $attribute->newInstance()->behaviour;
        }

        $mapping    = $meta->getAssociationMapping($field);
        $joinColumn = ($mapping['joinColumns'] ?? [])[0] ?? [];

        return match (strtoupper((string) ($joinColumn['onDelete'] ?? ''))) {
            'CASCADE'  => OnDelete::CASCADE,
            'SET NULL' => OnDelete::SET_NULL,
            default    => OnDelete::PROTECT,
        };
    }

    private function isBeingDeleted(object $entity): bool
    {
        foreach ($this->deletes as $queued) {
            if ($queued === $entity) {
                return true;
            }
        }

        return false;
    }

    private function identityOf(object $entity): string
    {
        return $entity::class . '#' . spl_object_id($entity);
    }

    /** "Cast member: Vincent Hanna" — what a confirmation can actually read. */
    private function label(object $entity): string
    {
        foreach (['getTitle', 'getName', 'getCharacter', 'getLabel'] as $getter) {
            if (method_exists($entity, $getter)) {
                $value = $entity->{$getter}();

                if (is_string($value) && $value !== '') {
                    return sprintf('%s: %s', $this->typeLabel($entity), $value);
                }
            }
        }

        return $this->typeLabel($entity);
    }

    private function typeLabel(object $entity): string
    {
        return $this->typeLabelFor($entity::class);
    }

    /** @param class-string $class */
    private function typeLabelFor(string $class): string
    {
        $short = substr((string) strrchr('\\' . $class, '\\'), 1);

        return ucfirst(strtolower(trim((string) preg_replace('/(?<!^)[A-Z]/', ' $0', $short))));
    }
}
