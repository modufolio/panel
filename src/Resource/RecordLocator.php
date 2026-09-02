<?php

declare(strict_types=1);

namespace Modufolio\Panel\Resource;

use Doctrine\ORM\EntityManagerInterface;

/**
 * The record a route addresses, if this user is allowed to reach it at all.
 *
 * `scopeQuery()` applies here as it does to the listing: a scope that merely
 * hid rows from a table while leaving them addressable by URL would be
 * decoration. Out of scope reads as not found, which is also the honest
 * answer — the record does not exist as far as this user is concerned.
 */
final class RecordLocator
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function find(PanelResource $resource, ?string $uuid, ?object $user = null): ?object
    {
        if ($uuid === null || $uuid === '') {
            return null;
        }

        $alias = $resource->queryAlias();

        $qb = $this->entityManager
            ->getRepository($resource->entityClass())
            ->createQueryBuilder($alias)
            ->where("{$alias}.uuid = :uuid")
            ->setParameter('uuid', $uuid);

        $resource->scopeQuery($qb, $user);

        $found = $qb->getQuery()->getOneOrNullResult();

        return is_object($found) ? $found : null;
    }
}
