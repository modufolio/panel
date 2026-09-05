<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Fixture\Entity;

/** A choice stored as a string column, declared through Doctrine's `enumType`. */
enum Genre: string
{
    case DRAMA  = 'drama';
    case SCI_FI = 'sci_fi';
    case COMEDY = 'comedy';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAMA  => 'Drama',
            self::SCI_FI => 'Science fiction',
            self::COMEDY => 'Comedy',
        };
    }
}
