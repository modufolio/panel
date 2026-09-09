<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Resource;

/**
 * What this viewer may do with a record, and why not when they may not.
 *
 * The same two {@see Permissions} questions the write endpoints ask, asked
 * with the record in hand — so a button is offered exactly when the request
 * behind it would be accepted. Both surfaces that render those buttons come
 * through here: a listing asks for the whole page at once, a drawer frame for
 * the one record it shows, and neither can drift from the other by asking the
 * abilities in a different order or forgetting one.
 *
 * Reasons are only for refusals the resource chose to explain: an action with
 * a reason renders disabled, with the sentence as its tooltip, instead of
 * vanishing. A refusal with no reason simply removes the button.
 */
final class RecordVerdicts
{
    /** The abilities a record-level button can exist for. */
    private const ABILITIES = ['edit', 'delete'];

    public function __construct(
        private readonly Permissions $permissions,
        private readonly ?object $user = null,
    ) {
    }

    /** @return array{edit: bool, delete: bool} */
    public function can(object $entity): array
    {
        return [
            'edit'   => $this->permissions->edit($entity, $this->user),
            'delete' => $this->permissions->delete($entity, $this->user),
        ];
    }

    /**
     * The explained refusals for one record: `{delete: "Admins cannot be
     * deleted"}`, empty when every ability is allowed or none is explained.
     *
     * @return array<string, string>
     */
    public function why(object $entity): array
    {
        $reasons = [];

        foreach (self::ABILITIES as $ability) {
            if ($this->permissions->{$ability}($entity, $this->user)) {
                continue;
            }

            $reason = $this->permissions->reason($ability, $entity, $this->user);

            if ($reason !== null) {
                $reasons[$ability] = $reason;
            }
        }

        return $reasons;
    }

    /**
     * {@see can()} for a page of records, keyed by the presented id.
     *
     * @param  array<int, object>               $entities
     * @param  array<int, array<string, mixed>> $rows
     * @return array<string, array{edit: bool, delete: bool}>
     */
    public function canEach(array $entities, array $rows): array
    {
        $verdicts = [];

        foreach (self::paired($entities, $rows) as $id => $entity) {
            $verdicts[$id] = $this->can($entity);
        }

        return $verdicts;
    }

    /**
     * {@see why()} for a page of records, keyed by the presented id. Records
     * with nothing to explain are absent rather than present and empty.
     *
     * @param  array<int, object>               $entities
     * @param  array<int, array<string, mixed>> $rows
     * @return array<string, array<string, string>>
     */
    public function whyEach(array $entities, array $rows): array
    {
        $reasons = [];

        foreach (self::paired($entities, $rows) as $id => $entity) {
            $why = $this->why($entity);

            if ($why !== []) {
                $reasons[$id] = $why;
            }
        }

        return $reasons;
    }

    /**
     * Entities by their presented id. Rows and entities pair by position, so
     * a presenter that dropped or reordered rows disqualifies the whole page
     * rather than mislabelling one record's verdicts as another's.
     *
     * @param  array<int, object>               $entities
     * @param  array<int, array<string, mixed>> $rows
     * @return array<string, object>
     */
    private static function paired(array $entities, array $rows): array
    {
        if ($entities === [] || count($entities) !== count($rows)) {
            return [];
        }

        $rows   = array_values($rows);
        $paired = [];

        foreach (array_values($entities) as $index => $entity) {
            $id = (string) ($rows[$index]['id'] ?? '');

            if ($id !== '') {
                $paired[$id] = $entity;
            }
        }

        return $paired;
    }
}
