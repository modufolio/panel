<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Fixture;

use Modufolio\Panel\Resource\Permissions;

/** The movie resource, gated on ROLE_USER at the route. */
class UserMovieResource extends MovieResource
{
    public function permissions(): Permissions
    {
        return new Permissions(['ROLE_USER']);
    }
}
