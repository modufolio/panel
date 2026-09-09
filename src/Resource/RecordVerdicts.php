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
        return $this->verdict($entity)['can'];
    }

    /**
     * The explained refusals for one record: `{delete: "Admins cannot be
     * deleted"}`, empty when every ability is allowed or none is explained.
     *
     * @return array<string, string>
     */
    public function why(object $entity): array
    {
        return $this->verdict($entity)['why'];
    }

    /**
     * Both answers for one record from one round of questions.
     *
     * `can` and `why` are two views of the same verdicts, so asking them
     * apart asked every ability twice. A surface that wants both — the
     * listing, the board, the drawer frame all do — asks once here and reads
     * both keys. {@see Permissions} promises its answers are cheap, but a
     * promise kept is still no reason to spend it twice.
     *
     * @return array{can: array{edit: bool, delete: bool}, why: array<string, string>}
     */
    public function verdict(object $entity): array
    {
        $can = [];
        $why = [];

        foreach (self::ABILITIES as $ability) {
            $can[$ability] = $this->permissions->{$ability}($entity, $this->user);

            if ($can[$ability]) {
                continue;
            }

            $reason = $this->permissions->reason($ability, $entity, $this->user);

            if ($reason !== null) {
                $why[$ability] = $reason;
            }
        }

        /** @var array{edit: bool, delete: bool} $can */
        return ['can' => $can, 'why' => $why];
    }

    /**
     * {@see verdict()} for a page of records, keyed by the presented id:
     * `can` for every record, `why` only for the records with something to
     * explain — absent rather than present and empty, so the prop can be
     * left out when nothing needs a tooltip.
     *
     * @param  array<int, object>               $entities
     * @param  array<int, array<string, mixed>> $rows
     * @return array{can: array<string, array{edit: bool, delete: bool}>, why: array<string, array<string, string>>}
     */
    public function verdictsEach(array $entities, array $rows): array
    {
        $can = [];
        $why = [];

        foreach (self::paired($entities, $rows) as $id => $entity) {
            $verdict  = $this->verdict($entity);
            $can[$id] = $verdict['can'];

            if ($verdict['why'] !== []) {
                $why[$id] = $verdict['why'];
            }
        }

        return ['can' => $can, 'why' => $why];
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
        return $this->verdictsEach($entities, $rows)['can'];
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
        return $this->verdictsEach($entities, $rows)['why'];
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
