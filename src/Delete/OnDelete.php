<?php

declare(strict_types=1);

namespace Modufolio\Panel\Delete;

use Attribute;

/**
 * What happens to a row when the thing it points at is deleted.
 *
 * The policy belongs on the *referencing* side, because that is the only
 * place that knows what the reference means. A cast entry without its actor is
 * meaningless (PROTECT); a cast entry without its movie cannot exist at all
 * (CASCADE).
 *
 *     #[ORM\ManyToOne(targetEntity: Actor::class)]
 *     #[OnDelete(OnDelete::PROTECT)]
 *     private ?Actor $actor = null;
 *
 * Left undeclared, the policy is inferred from the join column — a schema that
 * says `ON DELETE CASCADE` means it — and otherwise defaults to PROTECT. That
 * default is the point: SQLite here runs with `PRAGMA foreign_keys = 0`, so
 * database-level actions never fire, and a silent no-op leaves dangling
 * references that only surface as a 500 when something reads them back.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class OnDelete
{
    /** Delete the referencing rows too, recursively. */
    public const CASCADE = 'cascade';

    /** Refuse, and name what is in the way. */
    public const PROTECT = 'protect';
    /**
     * Refuse — unless the referencing rows are themselves being deleted by a
     * cascade from the same operation — for the case where blocking would be
     * wrong only because the blocker is going too.
     */
    public const RESTRICT = 'restrict';

    /** Clear the reference and keep the row. */
    public const SET_NULL = 'set_null';

    /** Leave the reference alone — the caller has its own arrangement. */
    public const DO_NOTHING = 'do_nothing';

    public const ALL = [
        self::CASCADE,
        self::PROTECT,
        self::RESTRICT,
        self::SET_NULL,
        self::DO_NOTHING,
    ];

    public function __construct(public readonly string $behaviour)
    {
        if (!in_array($behaviour, self::ALL, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown delete behaviour "%s"; expected one of %s.',
                $behaviour,
                implode(', ', self::ALL),
            ));
        }
    }
}
