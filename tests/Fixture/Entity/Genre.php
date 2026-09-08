<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Fixture\Entity;

use Modufolio\Panel\Contracts\HasColorInterface;

/**
 * A choice stored as a string column, declared through Doctrine's `enumType`,
 * that carries its own colour — so a table column over it reads as a badge
 * without the resource restating what the enum already knows.
 */
enum Genre: string implements HasColorInterface
{
    case DRAMA  = 'drama';
    case SCI_FI = 'sci_fi';
    case COMEDY = 'comedy';

    public function getColor(): string
    {
        return match ($this) {
            self::DRAMA  => 'info',
            self::SCI_FI => 'primary',
            self::COMEDY => 'success',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAMA  => 'Drama',
            self::SCI_FI => 'Science fiction',
            self::COMEDY => 'Comedy',
        };
    }
}
