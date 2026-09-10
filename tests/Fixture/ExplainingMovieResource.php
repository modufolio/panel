<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Fixture;

use Modufolio\Appkit\Security\User\UserInterface;
use Modufolio\Panel\Resource\Permissions;
use Modufolio\Panel\Tests\Fixture\Entity\Movie;

/**
 * The movies, with permissions that refuse two records — one with a reason
 * it can give, one without — for the verdict-and-reason tests.
 */
final class ExplainingMovieResource extends MovieResource
{
    public function permissions(): Permissions
    {
        return new class extends Permissions {
            public function delete(?object $record, ?UserInterface $user): bool
            {
                return !($record instanceof Movie && $record->getTitle() === 'Heat');
            }

            public function edit(?object $record, ?UserInterface $user): bool
            {
                return !($record instanceof Movie && $record->getTitle() === 'Jaws');
            }

            public function reason(string $ability, ?object $record, ?UserInterface $user): ?string
            {
                return $ability === 'delete' ? 'Classics cannot be deleted' : null;
            }
        };
    }
}
