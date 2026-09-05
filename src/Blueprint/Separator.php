<?php

declare(strict_types=1);

namespace Modufolio\Panel\Blueprint;

/**
 * A break between two runs of fields.
 *
 * Declared inline in `form()` as a plain entry between the keys it
 * separates — a break is a thing in the list, not a property of the field
 * that happens to follow it:
 *
 *     return [
 *         'first_name' => ['width' => '1/2'],
 *         'last_name'  => ['width' => '1/2'],
 *         Separator::Line,
 *         'email'      => ['width' => '1/2'],
 *     ];
 *
 * Both take a full grid row, so whatever follows starts on a fresh line.
 * `Line` draws a rule; `Space` is the same gap with nothing drawn in it.
 */
enum Separator: string
{
    case Line  = 'line';
    case Space = 'space';
}
