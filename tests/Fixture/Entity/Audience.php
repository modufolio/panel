<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Fixture\Entity;

/** An enum that knows its labels but not its colours — a select, never a badge. */
enum Audience: string
{
    case FAMILY = 'family';
    case ADULT  = 'adult';

    public function getLabel(): string
    {
        return match ($this) {
            self::FAMILY => 'Family',
            self::ADULT  => 'Adults only',
        };
    }
}
