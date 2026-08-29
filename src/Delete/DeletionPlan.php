<?php

declare(strict_types=1);

namespace Modufolio\Panel\Delete;

/**
 * What deleting one record would actually do.
 *
 * Three things a confirmation needs: a nested list for display, a per-type
 * count for the summary, and — separately — whatever is blocking.
 *
 * Blocked and not blocked are different answers, not degrees of the same one:
 * a plan with `protected` entries is never carried out, so the deletions it
 * also collected are only ever shown as context.
 */
final class DeletionPlan
{
    /**
     * @param list<array{label: string, type: string, children: list<mixed>}> $nested
     * @param array<string, int>                                              $counts by type label
     * @param list<string>                                                    $protected human labels
     * @param list<object>                                                    $deletes in dependency order
     * @param list<array{entity: object, field: string}>                      $nullifies
     * @param array<string, int>                                              $linkCounts join rows cleared
     */
    public function __construct(
        public readonly array $nested = [],
        public readonly array $counts = [],
        public readonly array $protected = [],
        public readonly array $deletes = [],
        public readonly array $nullifies = [],
        public readonly array $linkCounts = [],
    ) {
    }

    public function isBlocked(): bool
    {
        return $this->protected !== [];
    }

    /**
     * The client-facing shape. The plan's own object lists never travel — the
     * browser has no use for entities, and the labels are what a confirmation
     * needs to be honest.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'blocked'    => $this->isBlocked(),
            'protected'  => $this->protected,
            'nested'     => $this->nested,
            'counts'     => $this->counts,
            'linkCounts' => $this->linkCounts,
        ];
    }
}
