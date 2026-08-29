<?php

declare(strict_types=1);

namespace Modufolio\Panel\Contracts;

/**
 * A backed enum that knows its own colour token.
 *
 * NOTE: this codebase currently has two colour vocabularies — raw hues
 * ('green', 'blue') consumed by IssuePresenter/ProjectPresenter, and the
 * panel's semantic tokens ('success', 'danger', …) that BadgeColumn accepts.
 * This contract does not unify them; it only says a colour exists.
 */
interface HasColorInterface
{
    public function getColor(): string;
}
