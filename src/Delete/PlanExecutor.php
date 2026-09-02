<?php

declare(strict_types=1);

namespace Modufolio\Panel\Delete;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Carries out a {@see DeletionPlan}: clears references, then deletes
 * depth-first.
 *
 * Order matters and is already baked into the plan — a child is queued
 * before the row it points at — so this only has to not reorder it. One
 * transaction, because a half-applied cascade is worse than a refusal. A
 * blocked plan is refused here too, so a caller cannot execute what the
 * collector said not to.
 */
final class PlanExecutor
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function apply(DeletionPlan $plan): void
    {
        if ($plan->isBlocked()) {
            throw new \LogicException(sprintf(
                'Refusing to apply a blocked deletion plan: %s.',
                implode(', ', $plan->protected),
            ));
        }

        $this->entityManager->wrapInTransaction(function () use ($plan): void {
            foreach ($plan->nullifies as $update) {
                $entity = $update['entity'];
                $field  = ucfirst($update['field']);

                if (isset($update['unlink'])) {
                    // A many-to-many peer: drop the link, keep both rows.
                    $entity->{'get' . $field}()->removeElement($update['unlink']);

                    continue;
                }

                if (method_exists($entity, 'set' . $field)) {
                    $entity->{'set' . $field}(null);
                }
            }

            $this->entityManager->flush();

            foreach ($plan->deletes as $entity) {
                $this->entityManager->remove($entity);
            }

            $this->entityManager->flush();
        });
    }
}
