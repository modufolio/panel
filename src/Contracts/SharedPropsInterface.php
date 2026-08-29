<?php

declare(strict_types=1);

namespace Modufolio\Panel\Contracts;

/**
 * The props every panel page carries regardless of what it renders — the
 * signed-in user, flash messages, navigation, CSRF tokens.
 *
 * The panel does not know or care what those are. It knows only that the host
 * application has a set of them and that every page it renders must include
 * them, which is the whole of this contract.
 */
interface SharedPropsInterface
{
    /** @return array<string, mixed> */
    public function create(): array;
}
